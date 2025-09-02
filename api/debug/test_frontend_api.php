<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Log all headers and parameters
$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'url' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
    'headers' => [],
    'get_params' => $_GET,
    'auth_test' => []
];

// Get all headers
if (function_exists('getallheaders')) {
    $debug['headers'] = getallheaders();
} else {
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header = str_replace('HTTP_', '', $key);
            $header = str_replace('_', '-', $header);
            $debug['headers'][$header] = $value;
        }
    }
}

// Test the actual API call
include_once '../middleware/auth.php';

try {
    $auth_result = authenticate();
    $debug['auth_test']['success'] = $auth_result['success'];
    $debug['auth_test']['user_id'] = $auth_result['user']['id'] ?? null;
    $debug['auth_test']['user_type'] = $auth_result['user']['user_type'] ?? null;
    $debug['auth_test']['email'] = $auth_result['user']['email'] ?? null;
    
    if ($auth_result['success']) {
        include_once '../config/database_auto.php';
        $database = new DatabaseAuto();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM trip_requests WHERE status = 'active' AND expires_at > NOW()");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug['auth_test']['active_requests_count'] = $result['count'];
    }
    
} catch (Exception $e) {
    $debug['auth_test']['error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>