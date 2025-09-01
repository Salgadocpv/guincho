<?php
/**
 * Test exact driver flow - replicate available-requests.html behavior
 */

header("Content-Type: application/json; charset=UTF-8");

// Simulate localStorage data exactly like the page creates
$testDriverData = [
    'session_token' => 'test_driver_' . time(),
    'user' => [
        'id' => 7,  // Use existing driver ID
        'user_type' => 'driver',
        'full_name' => 'João Guincheiro Teste',
        'email' => 'guincheiro@teste.com'
    ],
    'expires_at' => date('c', time() + 24*60*60)
];

// Simulate token creation exactly like frontend
$authToken = base64_encode(json_encode($testDriverData));

// Test the exact API call that available-requests.html makes
$lat = -23.5505; // São Paulo coordinates
$lng = -46.6333;
$apiUrl = "/guincho/api/trips/get_requests.php?lat={$lat}&lng={$lng}";
$fullUrl = "https://www.coppermane.com.br{$apiUrl}";

$debug_info = [
    'step_1_token_creation' => [
        'token_data' => $testDriverData,
        'token_length' => strlen($authToken),
        'token_preview' => substr($authToken, 0, 50) . '...'
    ],
    'step_2_api_request' => [
        'url' => $fullUrl,
        'method' => 'GET',
        'headers' => [
            'Authorization' => 'Bearer ' . substr($authToken, 0, 20) . '...'
        ]
    ],
    'step_3_response' => null
];

// Make the actual request
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            "Authorization: Bearer {$authToken}",
            'Content-Type: application/json'
        ]
    ]
]);

try {
    $response = file_get_contents($fullUrl, false, $context);
    
    if ($response === false) {
        $debug_info['step_3_response'] = [
            'success' => false,
            'error' => 'Request failed - no response',
            'http_response_header' => $http_response_header ?? []
        ];
    } else {
        $responseData = json_decode($response, true);
        
        $debug_info['step_3_response'] = [
            'success' => true,
            'raw_response_length' => strlen($response),
            'parsed_response' => $responseData,
            'requests_found' => isset($responseData['data']) ? count($responseData['data']) : 0,
            'debug_from_api' => $responseData['debug'] ?? null
        ];
    }
    
} catch (Exception $e) {
    $debug_info['step_3_response'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode([
    'test_name' => 'Exact Driver Flow Simulation',
    'timestamp' => date('Y-m-d H:i:s'),
    'debug' => $debug_info
], JSON_PRETTY_PRINT);
?>