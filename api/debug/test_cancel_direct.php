<?php
/**
 * Direct test of cancel active trip API
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../classes/User.php';

$database = new Database();
$db = $database->getConnection();

try {
    $trip_request_id = $_GET['trip_id'] ?? null;
    
    if (!$trip_request_id) {
        throw new Exception('trip_id parameter required');
    }
    
    // Simulate the cancel logic directly
    $client_id = 2; // Test client
    $token = "test_client_2_1756211315";
    
    // Verify user token
    $user = new User($db);
    $userData = $user->validateSession($token);
    
    if (!$userData) {
        throw new Exception('Invalid token');
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // 1. Verify the trip request belongs to this client and has an active trip
        $verify_query = "SELECT tr.*, at.id as active_trip_id, at.driver_id, at.status as trip_status
                        FROM trip_requests tr 
                        LEFT JOIN active_trips at ON tr.id = at.trip_request_id 
                        WHERE tr.id = :trip_request_id 
                        AND tr.client_id = :client_id";
        
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
        $verify_stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $verify_stmt->execute();
        
        $trip_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$trip_data) {
            throw new Exception('Trip not found or not owned by client');
        }
        
        if (!$trip_data['active_trip_id']) {
            throw new Exception('No active trip found');
        }
        
        $active_trip_id = $trip_data['active_trip_id'];
        $driver_id = $trip_data['driver_id'];
        
        // 2. Update the active trip status to cancelled
        $cancel_trip_query = "UPDATE active_trips 
                             SET status = 'cancelled', 
                                 updated_at = CURRENT_TIMESTAMP,
                                 completed_at = CURRENT_TIMESTAMP
                             WHERE id = :active_trip_id";
        
        $cancel_trip_stmt = $db->prepare($cancel_trip_query);
        $cancel_trip_stmt->bindParam(':active_trip_id', $active_trip_id, PDO::PARAM_INT);
        $cancel_trip_stmt->execute();
        
        // 3. Update the trip request status to cancelled
        $cancel_request_query = "UPDATE trip_requests 
                               SET status = 'cancelled', 
                                   updated_at = CURRENT_TIMESTAMP 
                               WHERE id = :trip_request_id";
        
        $cancel_request_stmt = $db->prepare($cancel_request_query);
        $cancel_request_stmt->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
        $cancel_request_stmt->execute();
        
        // 4. Set driver's user status back to 'active' (available)
        $release_driver_query = "UPDATE users u 
                               JOIN drivers d ON u.id = d.user_id 
                               SET u.status = 'active' 
                               WHERE d.id = :driver_id";
        
        $release_driver_stmt = $db->prepare($release_driver_query);
        $release_driver_stmt->bindParam(':driver_id', $driver_id, PDO::PARAM_INT);
        $release_driver_stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Trip cancelled successfully',
            'trip_data' => $trip_data,
            'updates' => [
                'trip_request_cancelled' => $cancel_request_stmt->rowCount(),
                'active_trip_cancelled' => $cancel_trip_stmt->rowCount(),
                'driver_released' => $release_driver_stmt->rowCount()
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