<?php
/**
 * Create and immediately verify test trip
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'create_and_verify';
    
    if ($action === 'create_and_verify') {
        // Start transaction
        $db->beginTransaction();
        
        try {
            // 1. First, clean up any existing trips for client 2
            $cleanup_active = "DELETE FROM active_trips WHERE client_id = 2";
            $db->prepare($cleanup_active)->execute();
            
            $cleanup_requests = "DELETE FROM trip_requests WHERE client_id = 2";
            $db->prepare($cleanup_requests)->execute();
            
            // Also cleanup notifications for clean slate
            $cleanup_notifications = "DELETE FROM trip_notifications WHERE user_id = 2";
            $db->prepare($cleanup_notifications)->execute();
            
            // 2. Create a fresh trip request for test client (ID 2)
            $request_query = "INSERT INTO trip_requests 
                            (client_id, service_type, origin_lat, origin_lng, origin_address, 
                             destination_lat, destination_lng, destination_address, client_offer, 
                             status, distance_km, estimated_duration_minutes, expires_at) 
                            VALUES 
                            (2, 'guincho', -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP',
                             -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', 85.00,
                             'pending', 2.5, 15, DATE_ADD(NOW(), INTERVAL 2 HOUR))";
            
            $request_stmt = $db->prepare($request_query);
            $request_stmt->execute();
            $trip_request_id = $db->lastInsertId();
            
            // 3. Create active trip for test driver (ID 1)
            $active_trip_query = "INSERT INTO active_trips 
                                (trip_request_id, driver_id, client_id, final_price, service_type,
                                 origin_lat, origin_lng, origin_address, destination_lat, destination_lng, 
                                 destination_address, status, created_at) 
                                VALUES 
                                (:trip_request_id, 1, 2, 85.00, 'guincho',
                                 -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP',
                                 -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', 'confirmed', NOW())";
            
            $active_trip_stmt = $db->prepare($active_trip_query);
            $active_trip_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
            $active_trip_stmt->execute();
            $active_trip_id = $db->lastInsertId();
            
            // Update trip request status to active now that we have an active trip
            $update_status = "UPDATE trip_requests SET status = 'active' WHERE id = :trip_request_id";
            $update_stmt = $db->prepare($update_status);
            $update_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            // 4. Immediately verify the trip was created
            $verify_query = "SELECT tr.*, at.id as active_trip_id, at.status as trip_status 
                           FROM trip_requests tr 
                           JOIN active_trips at ON tr.id = at.trip_request_id 
                           WHERE tr.client_id = 2 AND tr.id = :trip_request_id";
            
            $verify_stmt = $db->prepare($verify_query);
            $verify_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
            $verify_stmt->execute();
            $verification = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Trip created and verified successfully',
                'created_data' => [
                    'trip_request_id' => $trip_request_id,
                    'active_trip_id' => $active_trip_id,
                    'client_id' => 2,
                    'driver_id' => 1,
                    'status' => 'confirmed'
                ],
                'verification' => $verification,
                'verified' => $verification ? true : false,
                'test_instructions' => [
                    'Use this trip_request_id to test cancellation: ' . $trip_request_id,
                    'Client token: test_client_2_1756211315',
                    'Trip should be visible in my-requests for client 2'
                ]
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } else {
        // Just show current trips for client 2
        $query = "SELECT tr.*, at.id as active_trip_id, at.status as trip_status 
                 FROM trip_requests tr 
                 LEFT JOIN active_trips at ON tr.id = at.trip_request_id 
                 WHERE tr.client_id = 2 
                 ORDER BY tr.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Current trips for client 2',
            'trips' => $trips,
            'count' => count($trips)
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