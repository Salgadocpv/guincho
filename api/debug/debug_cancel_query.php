<?php
/**
 * Debug the cancel query to understand why it's not finding trips
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $trip_request_id = $_GET['trip_id'] ?? null;
    $client_id = 2; // Test client
    
    if (!$trip_request_id) {
        throw new Exception('trip_id parameter required');
    }
    
    // 1. Check if trip_request exists
    $check_request = "SELECT * FROM trip_requests WHERE id = :trip_request_id";
    $stmt1 = $db->prepare($check_request);
    $stmt1->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
    $stmt1->execute();
    $trip_request = $stmt1->fetch(PDO::FETCH_ASSOC);
    
    // 2. Check if active_trip exists
    $check_active = "SELECT * FROM active_trips WHERE trip_request_id = :trip_request_id";
    $stmt2 = $db->prepare($check_active);
    $stmt2->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
    $stmt2->execute();
    $active_trip = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    // 3. Test the exact query from cancel API
    $cancel_query = "SELECT tr.*, at.id as active_trip_id, at.driver_id, at.status as trip_status
                    FROM trip_requests tr 
                    LEFT JOIN active_trips at ON tr.id = at.trip_request_id 
                    WHERE tr.id = :trip_request_id 
                    AND tr.client_id = :client_id";
    
    $stmt3 = $db->prepare($cancel_query);
    $stmt3->bindParam(':trip_request_id', $trip_request_id, PDO::PARAM_INT);
    $stmt3->bindParam(':client_id', $client_id, PDO::PARAM_INT);
    $stmt3->execute();
    $cancel_result = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    // 4. Check all trips for client 2
    $all_client_trips = "SELECT tr.id, tr.client_id, tr.status, at.id as active_id, at.status as active_status
                        FROM trip_requests tr 
                        LEFT JOIN active_trips at ON tr.id = at.trip_request_id 
                        WHERE tr.client_id = 2";
    $stmt4 = $db->prepare($all_client_trips);
    $stmt4->execute();
    $all_trips = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'debug_info' => [
            'searched_trip_id' => $trip_request_id,
            'searched_client_id' => $client_id,
            'trip_request_exists' => $trip_request ? true : false,
            'trip_request_data' => $trip_request,
            'active_trip_exists' => $active_trip ? true : false,
            'active_trip_data' => $active_trip,
            'cancel_query_result' => $cancel_result,
            'cancel_query_found' => $cancel_result ? true : false,
            'all_client_2_trips' => $all_trips,
            'all_trips_count' => count($all_trips)
        ],
        'analysis' => [
            'problem' => $cancel_result ? 'No problem found' : 'Query returned no results',
            'client_id_match' => $trip_request && $trip_request['client_id'] == $client_id ? 'YES' : 'NO',
            'active_trip_linked' => $active_trip ? 'YES' : 'NO',
            'recommendation' => !$cancel_result ? 'Check if trip_request.client_id matches expected client_id' : 'Query should work'
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