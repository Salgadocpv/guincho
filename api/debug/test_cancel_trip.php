<?php
/**
 * Test cancel active trip functionality
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'test_cancel') {
        // Test canceling with client token
        $client_token = "test_client_2_1756211315";
        $trip_request_id = $_GET['trip_id'] ?? null;
        
        if (!$trip_request_id) {
            throw new Exception('trip_id parameter required');
        }
        
        // Simulate API call
        $post_data = json_encode(['trip_request_id' => intval($trip_request_id)]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $client_token
                ],
                'content' => $post_data
            ]
        ]);
        
        $api_url = 'https://www.coppermane.com.br/guincho/api/trips/cancel_active_trip.php';
        $result = file_get_contents($api_url, false, $context);
        
        if ($result === false) {
            throw new Exception('Failed to call cancel API');
        }
        
        $response = json_decode($result, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cancel test completed',
            'api_response' => $response,
            'test_details' => [
                'client_token' => $client_token,
                'trip_request_id' => $trip_request_id,
                'api_url' => $api_url
            ]
        ]);
        
    } else {
        // Show available active trips for testing
        $query = "SELECT tr.id, tr.client_id, tr.status as request_status,
                         at.id as active_trip_id, at.status as trip_status, at.driver_id,
                         u.full_name as client_name
                  FROM trip_requests tr 
                  JOIN active_trips at ON tr.id = at.trip_request_id
                  JOIN users u ON tr.client_id = u.id
                  WHERE at.status IN ('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress')
                  ORDER BY tr.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Active trips available for testing',
            'active_trips' => $trips,
            'count' => count($trips),
            'test_instructions' => [
                'To test cancellation, use: ?action=test_cancel&trip_id=X',
                'Available client token: test_client_2_1756211315',
                'Make sure the trip belongs to client_id = 2'
            ]
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