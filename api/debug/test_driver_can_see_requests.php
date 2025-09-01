<?php
/**
 * Test if driver can see requests with Bearer token
 */

header("Content-Type: application/json; charset=UTF-8");

// Simulate what the frontend does
$testToken = base64_encode(json_encode([
    'user_id' => 2, // Assuming driver user ID is 2
    'user_type' => 'driver',
    'session_token' => 'test_' . time()
]));

// Make request with Bearer token
$url = 'https://www.coppermane.com.br/guincho/api/trips/get_requests.php?lat=-23.5505&lng=-46.6333';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Authorization: Bearer ' . $testToken,
            'Content-Type: application/json'
        ]
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);
    
    echo json_encode([
        'success' => true,
        'test_token_sent' => substr($testToken, 0, 50) . '...',
        'api_response' => $data,
        'requests_visible' => isset($data['data']) ? count($data['data']) : 0
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>