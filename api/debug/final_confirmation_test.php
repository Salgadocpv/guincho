<?php
/**
 * Final confirmation test - direct API call
 */

header("Content-Type: application/json; charset=UTF-8");

// Test the exact endpoint and method
$testToken = base64_encode(json_encode([
    'user_id' => 7,
    'user_type' => 'driver',
    'session_token' => 'final_test_' . time()
]));

$url = 'https://www.coppermane.com.br/guincho/api/trips/get_requests.php?lat=-23.5505&lng=-46.6333';

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $testToken,
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

$result = [
    'final_test' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'http_code' => $httpCode,
    'curl_error' => $error ?: null,
    'response_received' => !empty($response),
    'response_data' => null,
    'requests_visible' => 0,
    'success' => false
];

if ($response) {
    $data = json_decode($response, true);
    $result['response_data'] = $data;
    $result['requests_visible'] = isset($data['data']) ? count($data['data']) : 0;
    $result['success'] = isset($data['success']) ? $data['success'] : false;
    $result['api_message'] = $data['message'] ?? null;
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>