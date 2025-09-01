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
include_once '../classes/ActiveTrip.php';
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
$database = new DatabaseAuto();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Dados do guincheiro não encontrados']);
    exit();
}

// Check if this is a request to check for active trips only
$check_active_only = isset($_GET['check_active_only']) && $_GET['check_active_only'] === 'true';

if ($check_active_only) {
    // Check if driver has an active trip
    $active_trip = new ActiveTrip($db);
    $stmt = $active_trip->getDriverActiveTrips($driver['id']);
    $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_active_trip) {
        echo json_encode([
            'success' => true,
            'active_trip' => $current_active_trip,
            'message' => 'Driver has an active trip'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'active_trip' => null,
            'message' => 'Driver is available'
        ]);
    }
    exit();
}

// Check if driver already has an active trip
$active_trip = new ActiveTrip($db);
$stmt = $active_trip->getDriverActiveTrips($driver['id']);
$current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Add driver info to response
$debug_info = [
    'driver_id' => $driver['id'],
    'user_id' => $user['id'],
    'has_active_trip' => $current_active_trip ? true : false
];

if ($current_active_trip) {
    // Driver has an active trip, don't show new requests
    echo json_encode([
        'success' => true,
        'data' => [],
        'message' => 'Você possui uma viagem ativa. Finalize-a antes de ver novas solicitações.',
        'active_trip' => $current_active_trip,
        'debug' => $debug_info
    ]);
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
    
    // Get questionnaire answers for each request (with error handling)
    foreach ($requests as &$request) {
        try {
            $answers_query = "SELECT question_id, question_text, option_id, option_text 
                              FROM questionnaire_answers 
                              WHERE trip_request_id = ? 
                              ORDER BY question_id";
            $answers_stmt = $db->prepare($answers_query);
            $answers_stmt->execute([$request['id']]);
            $request['questionnaire_answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // If questionnaire_answers table doesn't exist, just set empty array
            error_log("Questionnaire answers error: " . $e->getMessage());
            $request['questionnaire_answers'] = [];
        }
    }
    
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
        ],
        'debug' => array_merge($debug_info, [
            'total_requests_found' => count($requests),
            'filtered_requests' => count($filtered_requests),
            'query_executed' => true
        ])
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>