<?php
/**
 * Simulate exactly what the frontend does
 */

header("Content-Type: application/json; charset=UTF-8");

// Simulate the exact frontend flow
$testDriverData = [
    'session_token' => 'test_driver_' . time(),
    'user' => [
        'id' => 7,
        'user_type' => 'driver',
        'full_name' => 'João Guincheiro',
        'email' => 'guincheiro@teste.com'
    ],
    'expires_at' => date('c', time() + 24*60*60)
];

// Create the token exactly like the frontend does
$token = base64_encode(json_encode($testDriverData));

// Make request exactly like the frontend
$lat = -23.5505;
$lng = -46.6333;
$url = "https://www.coppermane.com.br/guincho/api/trips/get_requests.php?lat={$lat}&lng={$lng}";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json'
        ]
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Request failed');
    }
    
    $data = json_decode($response, true);
    
    echo json_encode([
        'success' => true,
        'frontend_simulation' => [
            'token_sent' => substr($token, 0, 100) . '...',
            'url_requested' => $url,
            'response_received' => $data,
            'requests_count' => isset($data['data']) ? count($data['data']) : 0
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'frontend_simulation' => [
            'token_sent' => substr($token ?? 'none', 0, 100) . '...',
            'url_requested' => $url
        ]
    ], JSON_PRETTY_PRINT);
}
?>