<?php
/**
 * Get Trip Requests API
 * Returns nearby trip requests for drivers
 */

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

// Get driver's current location (for now, use a default location)
// In a real app, this would come from GPS/location services
$driver_lat = $_GET['lat'] ?? null;
$driver_lng = $_GET['lng'] ?? null;

if (!$driver_lat || !$driver_lng) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Localização do guincheiro é obrigatória (lat, lng)']);
    exit();
}

try {
    // Get search radius from settings
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'driver_search_radius_km'");
    $stmt->execute();
    $radius_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $search_radius = $radius_result ? (float)$radius_result['setting_value'] : 25.0;
    
    // Get nearby trip requests
    $trip_request = new TripRequest($db);
    $stmt = $trip_request->getNearbyRequests($driver_lat, $driver_lng, $search_radius);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter by driver specialty
    $filtered_requests = [];
    foreach ($requests as $request) {
        if ($driver['specialty'] === 'todos' || $driver['specialty'] === $request['service_type']) {
            // Check if driver already has a bid for this request
            $bid_check = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
            $bid_check->execute([$request['id'], $driver['id']]);
            
            $request['has_bid'] = $bid_check->rowCount() > 0;
            $request['distance'] = round($request['distance'], 2);
            $request['time_remaining'] = max(0, strtotime($request['expires_at']) - time());
            
            // Format addresses for display
            $request['origin_short'] = substr($request['origin_address'], 0, 50) . (strlen($request['origin_address']) > 50 ? '...' : '');
            $request['destination_short'] = substr($request['destination_address'], 0, 50) . (strlen($request['destination_address']) > 50 ? '...' : '');
            
            $filtered_requests[] = $request;
        }
    }
    
    // Clean up expired requests
    $trip_request->expireOldRequests();
    
    echo json_encode([
        'success' => true,
        'data' => $filtered_requests,
        'driver_info' => [
            'id' => $driver['id'],
            'specialty' => $driver['specialty'],
            'search_radius_km' => $search_radius,
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