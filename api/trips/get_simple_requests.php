<?php
/**
 * Simple Trip Requests API
 * One simple request with all functionalities for drivers
 */

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

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    // Get driver info
    $stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Dados do guincheiro não encontrados']);
        exit();
    }

    // Check if driver already has an active trip
    $active_trip = new ActiveTrip($db);
    $stmt = $active_trip->getDriverActiveTrips($driver['id']);
    $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current_active_trip) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Você possui uma viagem ativa. Finalize-a antes de ver novas solicitações.',
            'active_trip' => $current_active_trip,
            'driver_available' => false
        ]);
        exit();
    }

    // Get driver's current location (optional - used for distance calculation)
    $driver_lat = $_GET['lat'] ?? -23.5505; // Default São Paulo
    $driver_lng = $_GET['lng'] ?? -46.6333;

    // Simple query - get ALL active trip requests
    $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending' 
                AND tr.expires_at > NOW()
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each request
    $processed_requests = [];
    foreach ($requests as $request) {
        // Calculate distance from driver to trip origin
        $distance = TripRequest::calculateDistance(
            $driver_lat, $driver_lng,
            $request['origin_lat'], $request['origin_lng']
        );
        
        // Check if driver already has a bid for this request
        $bid_check = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
        $bid_check->execute([$request['id'], $driver['id']]);
        
        // Get questionnaire answers
        $answers_query = "SELECT question_id, question_text, option_id, option_text 
                          FROM questionnaire_answers 
                          WHERE trip_request_id = ? 
                          ORDER BY question_id";
        $answers_stmt = $db->prepare($answers_query);
        $answers_stmt->execute([$request['id']]);
        $questionnaire_answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build complete request object
        $processed_requests[] = [
            'id' => $request['id'],
            'service_type' => $request['service_type'],
            'client_name' => $request['client_name'],
            'client_phone' => $request['client_phone'],
            'origin_address' => $request['origin_address'],
            'destination_address' => $request['destination_address'],
            'origin_lat' => $request['origin_lat'],
            'origin_lng' => $request['origin_lng'],
            'destination_lat' => $request['destination_lat'],
            'destination_lng' => $request['destination_lng'],
            'client_offer' => $request['client_offer'],
            'distance_km' => $request['distance_km'],
            'estimated_duration_minutes' => $request['estimated_duration_minutes'],
            'created_at' => $request['created_at'],
            'expires_at' => $request['expires_at'],
            'distance' => round($distance, 2),
            'time_remaining' => max(0, strtotime($request['expires_at']) - time()),
            'has_bid' => $bid_check->rowCount() > 0,
            'questionnaire_answers' => $questionnaire_answers,
            // Additional details for proposal and viewing
            'details' => [
                'can_bid' => !($bid_check->rowCount() > 0),
                'service_icon' => $this->getServiceIcon($request['service_type']),
                'service_name' => $this->getServiceName($request['service_type']),
                'formatted_offer' => 'R$ ' . number_format($request['client_offer'], 2, ',', '.'),
                'origin_short' => substr($request['origin_address'], 0, 50) . (strlen($request['origin_address']) > 50 ? '...' : ''),
                'destination_short' => substr($request['destination_address'], 0, 50) . (strlen($request['destination_address']) > 50 ? '...' : '')
            ]
        ];
    }
    
    // Clean up expired requests
    $trip_request_obj = new TripRequest($db);
    $trip_request_obj->expireOldRequests();
    
    // Return simple, complete response
    echo json_encode([
        'success' => true,
        'data' => $processed_requests,
        'count' => count($processed_requests),
        'driver_available' => true,
        'driver_info' => [
            'id' => $driver['id'],
            'name' => $user['full_name'],
            'specialty' => $driver['specialty'],
            'location' => [
                'lat' => (float)$driver_lat,
                'lng' => (float)$driver_lng
            ]
        ],
        'message' => count($processed_requests) > 0 ? 
            count($processed_requests) . ' solicitações encontradas' : 
            'Nenhuma solicitação disponível no momento'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}

// Helper functions
function getServiceIcon($service) {
    $icons = [
        'guincho' => 'fas fa-truck',
        'bateria' => 'fas fa-car-battery',
        'pneu' => 'fas fa-tire',
        'chaveiro' => 'fas fa-key',
        'mecanico' => 'fas fa-wrench',
        'eletricista' => 'fas fa-bolt'
    ];
    return $icons[$service] ?? 'fas fa-tools';
}

function getServiceName($service) {
    $names = [
        'guincho' => 'Guincho',
        'bateria' => 'Socorro Bateria',
        'pneu' => 'Troca de Pneu',
        'chaveiro' => 'Chaveiro',
        'mecanico' => 'Mecânico',
        'eletricista' => 'Eletricista'
    ];
    return $names[$service] ?? 'Serviço';
}
?>