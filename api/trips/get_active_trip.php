<?php
/**
 * Get Active Trip API
 * Returns details of an active trip by ID
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
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

// Get trip ID from URL parameter
$trip_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$trip_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'ID da viagem é obrigatório'
    ]);
    exit();
}

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    $active_trip = new ActiveTrip($db);
    $active_trip->id = $trip_id;
    
    $trip_data = $active_trip->readOne();
    
    if (!$trip_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Viagem não encontrada']);
        exit();
    }
    
    // Verify user has access to this trip (either client or driver)
    $has_access = false;
    
    if ($user['user_type'] === 'client' && $trip_data['client_id'] == $user['id']) {
        $has_access = true;
    }
    
    if ($user['user_type'] === 'driver') {
        // Get driver ID from user
        $stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($driver && $trip_data['driver_id'] == $driver['id']) {
            $has_access = true;
        }
    }
    
    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado a esta viagem']);
        exit();
    }
    
    // Calculate ETA if driver location is available
    $eta_minutes = null;
    if ($trip_data['driver_current_lat'] && $trip_data['driver_current_lng'] && 
        $trip_data['status'] !== 'completed' && $trip_data['status'] !== 'cancelled') {
        
        // Simple ETA calculation based on distance (assuming 40km/h average speed)
        $driver_lat = (float)$trip_data['driver_current_lat'];
        $driver_lng = (float)$trip_data['driver_current_lng'];
        
        // Determine target location based on trip status
        $target_lat = ($trip_data['status'] === 'confirmed' || $trip_data['status'] === 'driver_en_route') 
            ? (float)$trip_data['origin_lat'] 
            : (float)$trip_data['destination_lat'];
        $target_lng = ($trip_data['status'] === 'confirmed' || $trip_data['status'] === 'driver_en_route') 
            ? (float)$trip_data['origin_lng'] 
            : (float)$trip_data['destination_lng'];
        
        // Haversine distance calculation
        $earth_radius = 6371; // km
        $lat_diff = deg2rad($target_lat - $driver_lat);
        $lng_diff = deg2rad($target_lng - $driver_lng);
        $a = sin($lat_diff/2) * sin($lat_diff/2) + 
             cos(deg2rad($driver_lat)) * cos(deg2rad($target_lat)) * 
             sin($lng_diff/2) * sin($lng_diff/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance_km = $earth_radius * $c;
        
        // ETA calculation (40km/h average speed in city)
        $eta_minutes = round(($distance_km / 40) * 60);
    }
    
    // Format the response
    $response_data = [
        'id' => (int)$trip_data['id'],
        'trip_request_id' => (int)$trip_data['trip_request_id'],
        'driver_id' => (int)$trip_data['driver_id'],
        'client_id' => (int)$trip_data['client_id'],
        'service_type' => $trip_data['service_type'],
        'final_price' => (float)$trip_data['final_price'],
        'status' => $trip_data['status'],
        
        // Locations
        'origin_lat' => (float)$trip_data['origin_lat'],
        'origin_lng' => (float)$trip_data['origin_lng'],
        'origin_address' => $trip_data['origin_address'],
        'destination_lat' => (float)$trip_data['destination_lat'],
        'destination_lng' => (float)$trip_data['destination_lng'],
        'destination_address' => $trip_data['destination_address'],
        
        // Driver tracking
        'driver_current_lat' => $trip_data['driver_current_lat'] ? (float)$trip_data['driver_current_lat'] : null,
        'driver_current_lng' => $trip_data['driver_current_lng'] ? (float)$trip_data['driver_current_lng'] : null,
        'driver_last_update' => $trip_data['driver_last_update'],
        'eta_minutes' => $eta_minutes,
        
        // People info
        'client_name' => $trip_data['client_name'],
        'client_phone' => $trip_data['client_phone'],
        'client_email' => $trip_data['client_email'],
        'driver_name' => $trip_data['driver_name'],
        'driver_phone' => $trip_data['driver_phone'],
        'driver_email' => $trip_data['driver_email'],
        'truck_plate' => $trip_data['truck_plate'],
        'truck_brand' => $trip_data['truck_brand'],
        'truck_model' => $trip_data['truck_model'],
        
        // Timestamps
        'created_at' => $trip_data['created_at'],
        'started_at' => $trip_data['started_at'],
        'completed_at' => $trip_data['completed_at']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados da viagem carregados com sucesso',
        'data' => $response_data
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>