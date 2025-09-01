<?php
/**
 * Detailed test of driver authentication and request visibility
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';
include_once '../middleware/auth.php';

// Simulate Authorization header
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode(json_encode([
    'user_id' => 2,
    'user_type' => 'driver',
    'session_token' => 'test_' . time()
]));

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $debug = [
        'step_1_headers' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'No auth header',
        'step_2_auth_result' => null,
        'step_3_user_check' => null,
        'step_4_driver_check' => null,
        'step_5_active_trip_check' => null,
        'step_6_requests_available' => null
    ];
    
    // Step 2: Test authentication
    $auth_result = authenticate();
    $debug['step_2_auth_result'] = [
        'success' => $auth_result['success'],
        'user_id' => $auth_result['user']['id'] ?? null,
        'user_type' => $auth_result['user']['user_type'] ?? null,
        'message' => $auth_result['message'] ?? null
    ];
    
    if (!$auth_result['success']) {
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    $user = $auth_result['user'];
    
    // Step 3: Check user details
    $debug['step_3_user_check'] = [
        'user_id' => $user['id'],
        'user_type' => $user['user_type'],
        'email' => $user['email'],
        'status' => $user['status']
    ];
    
    // Step 4: Check if user is a driver
    if ($user['user_type'] !== 'driver') {
        $debug['step_4_driver_check'] = 'User is not a driver: ' . $user['user_type'];
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Get driver info
    $stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug['step_4_driver_check'] = [
        'driver_found' => $driver ? true : false,
        'driver_id' => $driver['id'] ?? null,
        'approval_status' => $driver['approval_status'] ?? null,
        'specialty' => $driver['specialty'] ?? null
    ];
    
    if (!$driver) {
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Step 5: Check for active trips
    include_once '../classes/ActiveTrip.php';
    $active_trip = new ActiveTrip($db);
    $stmt = $active_trip->getDriverActiveTrips($driver['id']);
    $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug['step_5_active_trip_check'] = [
        'has_active_trip' => $current_active_trip ? true : false,
        'active_trip_id' => $current_active_trip['id'] ?? null
    ];
    
    if ($current_active_trip) {
        $debug['step_6_requests_available'] = 'Driver has active trip - would not see new requests';
        echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Step 6: Check available requests
    $query = "SELECT tr.*, u.full_name as client_name
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending' 
                AND tr.expires_at > NOW()
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['step_6_requests_available'] = [
        'total_requests' => count($requests),
        'requests_summary' => array_map(function($r) {
            return [
                'id' => $r['id'],
                'service_type' => $r['service_type'],
                'client_name' => $r['client_name'],
                'status' => $r['status'],
                'created_at' => $r['created_at'],
                'expires_at' => $r['expires_at']
            ];
        }, $requests)
    ];
    
    echo json_encode([
        'success' => true,
        'debug' => $debug
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug ?? []
    ], JSON_PRETTY_PRINT);
}
?>