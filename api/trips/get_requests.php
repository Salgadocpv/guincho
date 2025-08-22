<?php
/**
 * Get Trip Requests API
 * Returns nearby trip requests for drivers
 */

// Suppress notices and warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripRequest.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Only drivers can view trip requests
if ($user['user_type'] !== 'driver') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas guincheiros podem ver solicitações']);
    exit();
}

// Get driver info
$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Dados do guincheiro não encontrados']);
    exit();
}

// Get driver's current location (optional - used for distance calculation)
$driver_lat = $_GET['lat'] ?? -23.5505; // Default São Paulo
$driver_lng = $_GET['lng'] ?? -46.6333;

try {
    // Get all active trip requests (we'll filter by driver's work region later)
    $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending' 
                AND tr.expires_at > NOW()
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate distance from driver to each trip origin
    foreach ($requests as &$request) {
        $distance = TripRequest::calculateDistance(
            $driver_lat, $driver_lng,
            $request['origin_lat'], $request['origin_lng']
        );
        $request['distance'] = round($distance, 2);
    }
    
    // Show ALL requests to driver (no filters for testing)
    $filtered_requests = [];
    foreach ($requests as $request) {
        // Check if driver already has a bid for this request
        $bid_check = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
        $bid_check->execute([$request['id'], $driver['id']]);
        
        $request['has_bid'] = $bid_check->rowCount() > 0;
        $request['time_remaining'] = max(0, strtotime($request['expires_at']) - time());
        
        // Format addresses for display
        $request['origin_short'] = substr($request['origin_address'], 0, 50) . (strlen($request['origin_address']) > 50 ? '...' : '');
        $request['destination_short'] = substr($request['destination_address'], 0, 50) . (strlen($request['destination_address']) > 50 ? '...' : '');
        
        $filtered_requests[] = $request;
    }
    
    // Clean up expired requests
    $trip_request_obj = new TripRequest($db);
    $trip_request_obj->expireOldRequests();
    
    echo json_encode([
        'success' => true,
        'data' => $filtered_requests,
        'driver_info' => [
            'id' => $driver['id'],
            'specialty' => $driver['specialty'],
            'location' => [
                'lat' => (float)$driver_lat,
                'lng' => (float)$driver_lng
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>