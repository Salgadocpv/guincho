<?php
/**
 * Debug the exact get_requests.php API logic
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';
include_once '../config/database_auto.php';
include_once '../middleware/auth.php';

// Simulate Authorization header
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode(json_encode([
    'user_id' => 7,
    'user_type' => 'driver',
    'session_token' => 'debug_' . time()
]));

try {
    $debug = [
        'step_1_auth' => null,
        'step_2_driver_check' => null,
        'step_3_active_trip_check' => null,
        'step_4_requests_query' => null,
        'step_5_filtering' => null
    ];
    
    // Step 1: Test authentication
    $auth_result = authenticate();
    $debug['step_1_auth'] = [
        'success' => $auth_result['success'],
        'user_id' => $auth_result['user']['id'] ?? null,
        'user_type' => $auth_result['user']['user_type'] ?? null
    ];
    
    if (!$auth_result['success'] || $auth_result['user']['user_type'] !== 'driver') {
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    $user = $auth_result['user'];
    
    // Step 2: Get driver info
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug['step_2_driver_check'] = [
        'driver_found' => $driver ? true : false,
        'driver_id' => $driver['id'] ?? null,
        'specialty' => $driver['specialty'] ?? null,
        'approval_status' => $driver['approval_status'] ?? null
    ];
    
    if (!$driver) {
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Step 3: Check active trips
    include_once '../classes/ActiveTrip.php';
    $active_trip = new ActiveTrip($db);
    $stmt = $active_trip->getDriverActiveTrips($driver['id']);
    $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug['step_3_active_trip_check'] = [
        'has_active_trip' => $current_active_trip ? true : false,
        'active_trip_id' => $current_active_trip['id'] ?? null
    ];
    
    if ($current_active_trip) {
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Step 4: Execute the exact query from get_requests.php
    $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending' 
                AND tr.expires_at > NOW()
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['step_4_requests_query'] = [
        'query' => $query,
        'total_found' => count($requests),
        'requests_summary' => array_map(function($r) {
            return [
                'id' => $r['id'],
                'service_type' => $r['service_type'],
                'status' => $r['status'],
                'expires_at' => $r['expires_at'],
                'client_name' => $r['client_name']
            ];
        }, $requests)
    ];
    
    // Step 5: Apply filtering (like get_requests.php does)
    $filtered_requests = [];
    foreach ($requests as $request) {
        // Check if driver already has a bid
        $bid_check = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
        $bid_check->execute([$request['id'], $driver['id']]);
        
        $request['has_bid'] = $bid_check->rowCount() > 0;
        $request['time_remaining'] = max(0, strtotime($request['expires_at']) - time());
        
        $filtered_requests[] = $request;
    }
    
    $debug['step_5_filtering'] = [
        'filtered_count' => count($filtered_requests),
        'current_time' => date('Y-m-d H:i:s'),
        'driver_specialty_check' => $driver['specialty']
    ];
    
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'final_requests_for_driver' => $filtered_requests
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug ?? []
    ], JSON_PRETTY_PRINT);
}
?>