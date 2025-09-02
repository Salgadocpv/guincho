<?php
header("Content-Type: application/json; charset=UTF-8");

// Simulate what the frontend should be doing
$testToken = base64_encode(json_encode([
    'user_id' => 1, // Assuming driver ID 1 exists
    'user_type' => 'driver',
    'session_token' => 'test_driver_' . time()
]));

$url = "https://www.coppermane.com.br/guincho/api/trips/get_requests.php?lat=-23.5505&lng=-46.6333";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $testToken,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo json_encode([
    'test_info' => [
        'url' => $url,
        'token_sent' => substr($testToken, 0, 50) . '...',
        'http_code' => $httpCode
    ],
    'api_response' => $response ? json_decode($response, true) : null,
    'raw_response' => $response
], JSON_PRETTY_PRINT);
?>