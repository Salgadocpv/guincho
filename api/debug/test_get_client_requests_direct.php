<?php
/**
 * Test get_client_requests.php directly
 */

header("Content-Type: application/json; charset=UTF-8");

// Test direct call to get_client_requests.php
$url = "https://www.coppermane.com.br/guincho/api/trips/get_client_requests.php";

$debug = [
    'test_name' => 'Direct Client Requests API Test',
    'url' => $url,
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Without any token (should use context-aware fallback)
$debug['tests']['1_no_token'] = [];
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$debug['tests']['1_no_token'] = [
    'http_code' => $httpCode,
    'response' => $response ? json_decode($response, true) : null,
    'success' => $response && strpos($response, 'success') !== false
];

// Test 2: With client token
$debug['tests']['2_client_token'] = [];
$clientToken = base64_encode(json_encode([
    'user_id' => 6, // Specific client ID
    'user_type' => 'client',
    'session_token' => 'test_client_' . time()
]));

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $clientToken,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$debug['tests']['2_client_token'] = [
    'token_sent' => substr($clientToken, 0, 50) . '...',
    'http_code' => $httpCode,
    'response' => $response ? json_decode($response, true) : null,
    'success' => $response && strpos($response, '"success":true') !== false
];

// Test 3: Check if Maria Silva client exists
$debug['tests']['3_check_maria_client'] = [];
try {
    include_once '../config/database_auto.php';
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, user_type, email, full_name FROM users WHERE email = 'maria.silva@teste.com'");
    $stmt->execute();
    $maria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug['tests']['3_check_maria_client'] = [
        'maria_exists' => $maria ? true : false,
        'maria_data' => $maria,
        'is_client_type' => $maria && $maria['user_type'] === 'client'
    ];
    
    if ($maria) {
        // Check Maria's requests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM trip_requests WHERE client_id = ?");
        $stmt->execute([$maria['id']]);
        $requestCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug['tests']['3_check_maria_client']['requests_count'] = $requestCount['count'];
    }
    
} catch (Exception $e) {
    $debug['tests']['3_check_maria_client']['error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>