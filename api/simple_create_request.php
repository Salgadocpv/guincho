<?php
error_reporting(E_ERROR | E_PARSE);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include_once 'config/database_auto.php';
include_once 'classes/TripRequest.php';
include_once 'middleware/auth.php';

try {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    // Validate required fields
    if (empty($data->service_type) || 
        empty($data->origin_lat) || empty($data->origin_lng) || empty($data->origin_address) ||
        empty($data->destination_lat) || empty($data->destination_lng) || empty($data->destination_address) ||
        empty($data->client_offer)) {
        
        throw new Exception('Missing required fields');
    }
    
    // Auth (use test user)
    $auth_result = authenticate();
    if (!$auth_result['success']) {
        throw new Exception('Auth failed: ' . $auth_result['message']);
    }
    
    $user = $auth_result['user'];
    
    $db = getDBConnectionAuto();
    
    // Create trip request with manual SQL first
    $query = "INSERT INTO trip_requests 
            (client_id, service_type, origin_lat, origin_lng, origin_address, 
             destination_lat, destination_lng, destination_address, client_offer,
             distance_km, estimated_duration_minutes, expires_at)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
    
    $stmt = $db->prepare($query);
    
    // Calculate distance
    $distance = 5.2; // hardcoded for now
    $duration = 15; // hardcoded for now
    
    $success = $stmt->execute([
        $user['id'],
        $data->service_type,
        (float)$data->origin_lat,
        (float)$data->origin_lng,
        $data->origin_address,
        (float)$data->destination_lat,
        (float)$data->destination_lng,
        $data->destination_address,
        (float)$data->client_offer,
        $distance,
        $duration
    ]);
    
    if ($success) {
        $trip_id = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Trip request created successfully',
            'data' => [
                'trip_request_id' => $trip_id,
                'distance_km' => $distance,
                'estimated_duration_minutes' => $duration
            ]
        ]);
    } else {
        throw new Exception('Failed to insert trip request');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>