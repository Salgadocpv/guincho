<?php
/**
 * Force creation of completely new trip with random data
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Use random values to force new entries
        $random = rand(100, 999);
        $client_offer = 70 + $random;
        
        // 1. Create trip request with unique data
        $request_query = "INSERT INTO trip_requests 
                        (client_id, service_type, origin_lat, origin_lng, origin_address, 
                         destination_lat, destination_lng, destination_address, client_offer, 
                         status, distance_km, estimated_duration_minutes, expires_at, created_at) 
                        VALUES 
                        (2, 'guincho', -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP',
                         -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', :client_offer,
                         'pending', 2.5, 15, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())";
        
        $request_stmt = $db->prepare($request_query);
        $request_stmt->bindParam(':client_offer', $client_offer);
        $request_stmt->execute();
        $trip_request_id = $db->lastInsertId();
        
        // 2. Create active trip
        $active_trip_query = "INSERT INTO active_trips 
                            (trip_request_id, driver_id, client_id, final_price, service_type,
                             origin_lat, origin_lng, origin_address, destination_lat, destination_lng, 
                             destination_address, status, created_at) 
                            VALUES 
                            (:trip_request_id, 1, 2, :final_price, 'guincho',
                             -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP',
                             -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', 'confirmed', NOW())";
        
        $active_trip_stmt = $db->prepare($active_trip_query);
        $active_trip_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
        $active_trip_stmt->bindParam(':final_price', $client_offer);
        $active_trip_stmt->execute();
        $active_trip_id = $db->lastInsertId();
        
        // 3. Update trip request status to active
        $update_status = "UPDATE trip_requests SET status = 'active' WHERE id = :trip_request_id";
        $update_stmt = $db->prepare($update_status);
        $update_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        // 4. Immediate verification
        $verify_query = "SELECT tr.*, at.id as active_trip_id, at.status as trip_status, at.driver_id
                       FROM trip_requests tr 
                       JOIN active_trips at ON tr.id = at.trip_request_id 
                       WHERE tr.id = :trip_request_id AND tr.client_id = 2";
        
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
        $verify_stmt->execute();
        $verification = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Also test the cancel API query
        $cancel_test_query = "SELECT tr.*, at.id as active_trip_id, at.driver_id, at.status as trip_status
                            FROM trip_requests tr 
                            LEFT JOIN active_trips at ON tr.id = at.trip_request_id 
                            WHERE tr.id = :trip_request_id 
                            AND tr.client_id = 2";
        
        $cancel_test_stmt = $db->prepare($cancel_test_query);
        $cancel_test_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
        $cancel_test_stmt->execute();
        $cancel_test = $cancel_test_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Force new trip created successfully',
            'data' => [
                'trip_request_id' => $trip_request_id,
                'active_trip_id' => $active_trip_id,
                'client_offer' => $client_offer,
                'random_suffix' => $random
            ],
            'verification' => $verification,
            'cancel_api_test' => $cancel_test,
            'ready_for_cancel' => $cancel_test && !in_array($cancel_test['status'], ['cancelled', 'completed']),
            'timestamps' => [
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours'))
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>