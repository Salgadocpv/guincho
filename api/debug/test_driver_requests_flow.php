<?php
/**
 * Test complete driver requests flow
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database_auto.php';
include_once '../middleware/auth.php';

$debug = [
    'test_name' => 'Driver Requests Flow Debug',
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => []
];

try {
    // Step 1: Check if we have any trip requests in database
    $debug['steps']['1_database_check'] = null;
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM trip_requests");
    $stmt->execute();
    $totalRequests = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM trip_requests WHERE status = 'active'");
    $stmt->execute();
    $activeRequests = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug['steps']['1_database_check'] = [
        'total_requests' => $totalRequests['total'],
        'active_requests' => $activeRequests['active']
    ];
    
    // Step 2: Test authentication for driver context
    $debug['steps']['2_driver_auth'] = null;
    $_SERVER['REQUEST_URI'] = '/guincho/api/trips/get_requests.php';
    $auth_result = authenticate();
    
    $debug['steps']['2_driver_auth'] = [
        'success' => $auth_result['success'],
        'user_id' => $auth_result['user']['id'] ?? null,
        'user_type' => $auth_result['user']['user_type'] ?? null,
        'email' => $auth_result['user']['email'] ?? null
    ];
    
    // Step 3: Check if authenticated user is a driver
    $debug['steps']['3_driver_check'] = null;
    if ($auth_result['success']) {
        $user = $auth_result['user'];
        
        // Check if user is driver or has driver record
        $stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $driverRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug['steps']['3_driver_check'] = [
            'user_type' => $user['user_type'],
            'has_driver_record' => $driverRecord ? true : false,
            'driver_data' => $driverRecord
        ];
    }
    
    // Step 4: Test the actual API call simulation
    $debug['steps']['4_api_simulation'] = null;
    if ($auth_result['success']) {
        $user = $auth_result['user'];
        
        // Simulate the API logic from get_requests.php
        $query = "SELECT tr.*, 
                    u.full_name as client_name,
                    u.phone as client_phone,
                    COUNT(tb.id) as bid_count,
                    AVG(tb.bid_amount) as avg_bid_amount
                  FROM trip_requests tr
                  LEFT JOIN users u ON tr.client_id = u.id
                  LEFT JOIN trip_bids tb ON tr.id = tb.trip_request_id
                  WHERE tr.status = 'active' 
                    AND tr.expires_at > NOW()
                  GROUP BY tr.id
                  ORDER BY tr.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debug['steps']['4_api_simulation'] = [
            'query_executed' => true,
            'requests_found' => count($requests),
            'requests_data' => array_slice($requests, 0, 3) // Show first 3 for debug
        ];
    }
    
    // Step 5: Create a test request if none exist
    $debug['steps']['5_create_test_request'] = null;
    if ($totalRequests['total'] == 0) {
        // Find a test client
        $stmt = $db->prepare("SELECT * FROM users WHERE user_type = 'client' AND email LIKE '%teste%' LIMIT 1");
        $stmt->execute();
        $testClient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($testClient) {
            $stmt = $db->prepare("
                INSERT INTO trip_requests 
                (client_id, service_type, origin_lat, origin_lng, origin_address, 
                 destination_lat, destination_lng, destination_address, 
                 client_offer, distance_km, estimated_duration_minutes, status, expires_at, created_at) 
                VALUES (?, 'guincho', -23.550520, -46.633308, 'Av. Paulista, 1000 - São Paulo, SP', 
                        -23.561440, -46.656166, 'Av. Faria Lima, 2000 - São Paulo, SP', 
                        150.00, 5.2, 25, 'active', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())
            ");
            $stmt->execute([$testClient['id']]);
            
            $debug['steps']['5_create_test_request'] = [
                'created' => true,
                'client_id' => $testClient['id'],
                'client_name' => $testClient['full_name']
            ];
        } else {
            $debug['steps']['5_create_test_request'] = [
                'created' => false,
                'reason' => 'No test client found'
            ];
        }
    } else {
        $debug['steps']['5_create_test_request'] = [
            'skipped' => true,
            'reason' => 'Requests already exist'
        ];
    }
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
    echo json_encode($debug, JSON_PRETTY_PRINT);
}
?>