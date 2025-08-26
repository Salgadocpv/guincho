<?php
/**
 * Create a test active trip for testing cancellation
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'create') {
        // Start transaction
        $db->beginTransaction();
        
        try {
            // 1. Create a trip request for test client (ID 2)
            $request_query = "INSERT INTO trip_requests 
                            (client_id, service_type, origin_lat, origin_lng, origin_address, 
                             destination_lat, destination_lng, destination_address, client_offer, 
                             status, distance_km, estimated_duration_minutes, expires_at) 
                            VALUES 
                            (2, 'guincho', -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP',
                             -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', 80.00,
                             'active', 2.5, 15, DATE_ADD(NOW(), INTERVAL 2 HOUR))";
            
            $request_stmt = $db->prepare($request_query);
            $request_stmt->execute();
            $trip_request_id = $db->lastInsertId();
            
            // 2. Create active trip for test driver (ID 1)
            $active_trip_query = "INSERT INTO active_trips 
                                (trip_request_id, driver_id, client_id, final_price, service_type,
                                 origin_lat, origin_lng, origin_address, destination_lat, destination_lng, 
                                 destination_address, status, created_at) 
                                VALUES 
                                (:trip_request_id, 1, 2, 80.00, 'guincho',
                                 -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP',
                                 -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', 'confirmed', NOW())";
            
            $active_trip_stmt = $db->prepare($active_trip_query);
            $active_trip_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
            $active_trip_stmt->execute();
            $active_trip_id = $db->lastInsertId();
            
            // Commit transaction
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Test trip created successfully',
                'data' => [
                    'trip_request_id' => $trip_request_id,
                    'active_trip_id' => $active_trip_id,
                    'client_id' => 2,
                    'driver_id' => 1,
                    'status' => 'confirmed',
                    'test_token' => 'test_client_2_1756211315'
                ],
                'next_step' => 'Use the trip_request_id to test cancellation'
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } else {
        // Show current status
        $query = "SELECT COUNT(*) as total_requests FROM trip_requests";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $requests = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $active_query = "SELECT COUNT(*) as total_active FROM active_trips";
        $active_stmt = $db->prepare($active_query);
        $active_stmt->execute();
        $active = $active_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Current system status',
            'current_status' => [
                'total_trip_requests' => $requests['total_requests'],
                'total_active_trips' => $active['total_active']
            ],
            'instructions' => 'Use ?action=create to create a test trip for client ID 2'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>