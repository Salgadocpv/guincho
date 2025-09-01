<?php
/**
 * Test with the exact token format that available-requests.html creates
 */

header("Content-Type: application/json; charset=UTF-8");

// Replicate the exact token creation from available-requests.html line 715-729
$testDriverData = [
    'session_token' => 'test_driver_' . time(),
    'user' => [
        'id' => 7,
        'user_type' => 'driver',
        'full_name' => 'João Guincheiro',
        'email' => 'guincheiro@teste.com'
    ],
    'expires_at' => (new DateTime('+24 hours'))->format('c')
];

// This is exactly what localStorage.setItem('auth_token', ...) stores
$authToken = base64_encode(json_encode($testDriverData));

// Test the request exactly as the frontend does
$lat = -23.5505;
$lng = -46.6333;
$url = "/guincho/api/trips/get_requests.php?lat={$lat}&lng={$lng}";
$fullUrl = "https://www.coppermane.com.br{$url}";

$debug = [
    'token_format_test' => true,
    'token_data_stored' => $testDriverData,
    'token_encoded' => $authToken,
    'token_length' => strlen($authToken),
    'url_to_call' => $fullUrl,
    'timestamp' => date('Y-m-d H:i:s')
];

// Make request using fetch-like curl
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $fullUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $authToken,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

$debug['request_result'] = [
    'http_code' => $httpCode,
    'curl_error' => $curlError ?: null,
    'response_length' => strlen($response),
    'response_received' => !empty($response)
];

if ($response) {
    $responseData = json_decode($response, true);
    $debug['api_response'] = [
        'success' => $responseData['success'] ?? false,
        'data_count' => isset($responseData['data']) ? count($responseData['data']) : 0,
        'message' => $responseData['message'] ?? null,
        'debug_info' => $responseData['debug'] ?? null
    ];
    
    if (isset($responseData['data']) && count($responseData['data']) > 0) {
        $debug['first_request_sample'] = [
            'id' => $responseData['data'][0]['id'],
            'service_type' => $responseData['data'][0]['service_type'],
            'client_name' => $responseData['data'][0]['client_name'],
            'origin_address' => substr($responseData['data'][0]['origin_address'], 0, 50),
            'client_offer' => $responseData['data'][0]['client_offer']
        ];
    }
}

// Final assessment
if (isset($debug['api_response']['data_count']) && $debug['api_response']['data_count'] > 0) {
    $debug['conclusion'] = "SUCCESS: API returned {$debug['api_response']['data_count']} requests. Frontend should display them.";
} else {
    $debug['conclusion'] = "ISSUE: API returned no requests for this driver token.";
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>