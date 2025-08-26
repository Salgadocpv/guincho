<?php
/**
 * Check client trips for testing
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check what trips belong to client ID 2 (test client)
    $query = "SELECT tr.*, at.id as active_trip_id, at.status as trip_status, at.driver_id,
                     u.full_name as client_name
              FROM trip_requests tr 
              LEFT JOIN active_trips at ON tr.id = at.trip_request_id
              JOIN users u ON tr.client_id = u.id
              WHERE tr.client_id = 2
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also check all active trips
    $all_active_query = "SELECT tr.id, tr.client_id, at.id as active_trip_id, at.status,
                               u.full_name as client_name
                        FROM trip_requests tr 
                        JOIN active_trips at ON tr.id = at.trip_request_id
                        JOIN users u ON tr.client_id = u.id
                        WHERE at.status IN ('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress')";
    
    $all_stmt = $db->prepare($all_active_query);
    $all_stmt->execute();
    $all_active = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Client trips analysis',
        'client_2_trips' => $trips,
        'client_2_trips_count' => count($trips),
        'all_active_trips' => $all_active,
        'test_client_info' => [
            'client_id' => 2,
            'token' => 'test_client_2_1756211315',
            'can_cancel_trips' => array_filter($trips, function($trip) {
                return !empty($trip['active_trip_id']) && 
                       in_array($trip['trip_status'], ['confirmed', 'driver_en_route', 'driver_arrived', 'in_progress']);
            })
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>