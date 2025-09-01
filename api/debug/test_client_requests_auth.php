<?php
/**
 * Test client requests authentication issue
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';
include_once '../config/database_auto.php';
include_once '../middleware/auth.php';

$debug = [
    'test_name' => 'Client Requests Authentication Debug',
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => []
];

try {
    // Step 1: Test without any token (should use fallback)
    $debug['steps']['1_no_token_auth'] = null;
    $auth_result = authenticate();
    $debug['steps']['1_no_token_auth'] = [
        'success' => $auth_result['success'],
        'user_id' => $auth_result['user']['id'] ?? null,
        'user_type' => $auth_result['user']['user_type'] ?? null,
        'email' => $auth_result['user']['email'] ?? null
    ];
    
    // Step 2: Test with client token
    $debug['steps']['2_client_token_test'] = null;
    $clientToken = base64_encode(json_encode([
        'user_id' => 6, // Assuming client ID is 6 (Maria Silva)
        'user_type' => 'client',
        'session_token' => 'test_client_' . time()
    ]));
    
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $clientToken;
    $client_auth = authenticate();
    $debug['steps']['2_client_token_test'] = [
        'token_sent' => substr($clientToken, 0, 50) . '...',
        'success' => $client_auth['success'],
        'user_id' => $client_auth['user']['id'] ?? null,
        'user_type' => $client_auth['user']['user_type'] ?? null,
        'email' => $client_auth['user']['email'] ?? null
    ];
    
    // Step 3: Check if test client exists in database
    $debug['steps']['3_test_client_check'] = null;
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, user_type, email, full_name, status FROM users WHERE user_type = 'client' ORDER BY id DESC LIMIT 3");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['steps']['3_test_client_check'] = [
        'total_clients_found' => count($clients),
        'clients' => $clients
    ];
    
    // Step 4: Test the actual client requests API logic
    $debug['steps']['4_api_logic_test'] = null;
    if ($client_auth['success']) {
        $user = $client_auth['user'];
        
        if ($user['user_type'] !== 'client') {
            $debug['steps']['4_api_logic_test'] = [
                'would_fail' => true,
                'error_message' => 'Apenas clientes podem acessar suas solicitações',
                'actual_user_type' => $user['user_type']
            ];
        } else {
            // Test the query
            $query = "SELECT COUNT(*) as request_count FROM trip_requests WHERE client_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $debug['steps']['4_api_logic_test'] = [
                'would_succeed' => true,
                'client_id' => $user['id'],
                'requests_count' => $result['request_count']
            ];
        }
    }
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
    echo json_encode($debug, JSON_PRETTY_PRINT);
}
?>