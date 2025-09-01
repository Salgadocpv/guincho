<?php
/**
 * Test Authenticated Driver Request API
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Simulate the exact authentication flow that drivers use
    $debug_info = [
        'step_1_get_test_driver' => null,
        'step_2_simulate_auth' => null,
        'step_3_check_active_trips' => null,
        'step_4_get_requests' => null
    ];
    
    // Step 1: Get a test driver
    $driver_query = "SELECT d.*, u.full_name, u.email 
                     FROM drivers d
                     JOIN users u ON d.user_id = u.id
                     WHERE d.approval_status = 'approved' 
                       AND u.status = 'active'
                     ORDER BY d.id DESC
                     LIMIT 1";
    
    $stmt = $db->prepare($driver_query);
    $stmt->execute();
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug_info['step_1_get_test_driver'] = $driver;
    
    if (!$driver) {
        throw new Exception("No approved driver found");
    }
    
    // Step 2: Simulate what the auth middleware would do
    $debug_info['step_2_simulate_auth'] = [
        'user_id' => $driver['user_id'],
        'user_type' => 'driver',
        'status' => 'active'
    ];
    
    // Step 3: Check if driver has active trips (like get_requests.php does)
    include_once '../classes/ActiveTrip.php';
    $active_trip = new ActiveTrip($db);
    $stmt = $active_trip->getDriverActiveTrips($driver['id']);
    $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug_info['step_3_check_active_trips'] = [
        'has_active_trip' => $current_active_trip ? true : false,
        'active_trip_data' => $current_active_trip
    ];
    
    // Step 4: Get requests (like get_requests.php does)
    if ($current_active_trip) {
        $debug_info['step_4_get_requests'] = [
            'message' => 'Driver has active trip - would not see new requests',
            'requests' => []
        ];
    } else {
        // Exact query from get_requests.php
        $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone
                  FROM trip_requests tr
                  JOIN users u ON tr.client_id = u.id
                  WHERE tr.status = 'pending' 
                    AND tr.expires_at > NOW()
                  ORDER BY tr.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Apply the same filtering logic as get_requests.php
        $filtered_requests = [];
        foreach ($requests as $request) {
            // Check if driver already has a bid
            $bid_check = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
            $bid_check->execute([$request['id'], $driver['id']]);
            
            $request['has_bid'] = $bid_check->rowCount() > 0;
            $request['time_remaining'] = max(0, strtotime($request['expires_at']) - time());
            
            $filtered_requests[] = $request;
        }
        
        $debug_info['step_4_get_requests'] = [
            'raw_requests_count' => count($requests),
            'filtered_requests_count' => count($filtered_requests),
            'requests' => $filtered_requests
        ];
    }
    
    echo json_encode([
        'success' => true,
        'debug' => $debug_info,
        'current_time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => $debug_info ?? []
    ], JSON_PRETTY_PRINT);
}
?>