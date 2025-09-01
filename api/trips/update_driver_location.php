<?php
/**
 * Update Driver Location API
 * Allows drivers to update their current location during an active trip
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
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

// Only drivers can update location
if ($user['user_type'] !== 'driver') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas guincheiros podem atualizar localização']);
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Validate required fields
if (empty($data->trip_id) || !isset($data->lat) || !isset($data->lng)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Dados obrigatórios: trip_id, lat, lng'
    ]);
    exit();
}

// Validate coordinates
$lat = (float)$data->lat;
$lng = (float)$data->lng;

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Coordenadas inválidas'
    ]);
    exit();
}

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    // Get driver info
    $stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Dados do guincheiro não encontrados']);
        exit();
    }
    
    // Get and verify active trip
    $active_trip = new ActiveTrip($db);
    $active_trip->id = $data->trip_id;
    
    $trip_data = $active_trip->readOne();
    
    if (!$trip_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Viagem não encontrada']);
        exit();
    }
    
    // Verify this driver belongs to this trip
    if ($trip_data['driver_id'] != $driver['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Esta viagem não pertence a você']);
        exit();
    }
    
    // Only allow location updates for active trips
    $allowed_statuses = ['confirmed', 'driver_en_route', 'driver_arrived', 'in_progress'];
    if (!in_array($trip_data['status'], $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Não é possível atualizar localização neste status de viagem']);
        exit();
    }
    
    // Update driver location
    if ($active_trip->updateDriverLocation($lat, $lng)) {
        
        // Calculate ETA and distance to destination
        $eta_info = null;
        
        // Determine target based on trip status
        $target_lat = ($trip_data['status'] === 'confirmed' || $trip_data['status'] === 'driver_en_route') 
            ? (float)$trip_data['origin_lat'] 
            : (float)$trip_data['destination_lat'];
        $target_lng = ($trip_data['status'] === 'confirmed' || $trip_data['status'] === 'driver_en_route') 
            ? (float)$trip_data['origin_lng'] 
            : (float)$trip_data['destination_lng'];
        
        // Calculate distance using Haversine formula
        $earth_radius = 6371; // km
        $lat_diff = deg2rad($target_lat - $lat);
        $lng_diff = deg2rad($target_lng - $lng);
        $a = sin($lat_diff/2) * sin($lat_diff/2) + 
             cos(deg2rad($lat)) * cos(deg2rad($target_lat)) * 
             sin($lng_diff/2) * sin($lng_diff/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance_km = $earth_radius * $c;
        
        // Calculate ETA (assuming 40km/h average speed)
        $eta_minutes = round(($distance_km / 40) * 60);
        
        $eta_info = [
            'distance_km' => round($distance_km, 2),
            'eta_minutes' => $eta_minutes,
            'target_phase' => ($trip_data['status'] === 'confirmed' || $trip_data['status'] === 'driver_en_route') 
                ? 'going_to_origin' : 'going_to_destination'
        ];
        
        // Auto-update trip status based on proximity
        $proximity_threshold = 0.1; // 100 meters in km
        
        if ($distance_km <= $proximity_threshold) {
            $new_status = null;
            
            if ($trip_data['status'] === 'confirmed' || $trip_data['status'] === 'driver_en_route') {
                // Driver arrived at origin
                $new_status = 'driver_arrived';
            } elseif ($trip_data['status'] === 'in_progress') {
                // Driver arrived at destination (trip completed)
                $new_status = 'completed';
            }
            
            if ($new_status) {
                $active_trip->updateStatus($new_status);
                $eta_info['status_updated'] = $new_status;
                $eta_info['arrived'] = true;
            }
        } else {
            // Update to en_route status if still confirmed
            if ($trip_data['status'] === 'confirmed') {
                $active_trip->updateStatus('driver_en_route');
                $eta_info['status_updated'] = 'driver_en_route';
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Localização atualizada com sucesso',
            'data' => [
                'lat' => $lat,
                'lng' => $lng,
                'eta_info' => $eta_info,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } else {
        throw new Exception('Erro ao atualizar localização no banco de dados');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>