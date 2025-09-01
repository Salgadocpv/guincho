<?php
/**
 * Test exact frontend flow - simulate available-requests.html
 */

header("Content-Type: application/json; charset=UTF-8");

// Create the exact token that the frontend creates
$testDriverData = [
    'session_token' => 'test_driver_' . time(),
    'user' => [
        'id' => 7,
        'user_type' => 'driver',
        'full_name' => 'João Guincheiro Teste',
        'email' => 'guincheiro@teste.com'
    ],
    'expires_at' => date('c', time() + 24*60*60)
];

$token = base64_encode(json_encode($testDriverData));

// Make the exact same request as frontend
$lat = -23.5505;
$lng = -46.6333;
$url = "https://www.coppermane.com.br/guincho/api/trips/get_requests.php?lat={$lat}&lng={$lng}";

$debug_info = [
    'test_type' => 'frontend_exact_simulation',
    'token_data' => $testDriverData,
    'url_called' => $url,
    'timestamp' => date('Y-m-d H:i:s')
];

// Use curl to make the exact same request
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (compatible; DriverApp/1.0)'
    ],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    $debug_info['curl_error'] = $error;
    $debug_info['success'] = false;
} else {
    $debug_info['http_code'] = $httpCode;
    $debug_info['response_received'] = !empty($response);
    
    if ($response) {
        $responseData = json_decode($response, true);
        $debug_info['response_data'] = $responseData;
        $debug_info['requests_count'] = isset($responseData['data']) ? count($responseData['data']) : 0;
        $debug_info['success'] = isset($responseData['success']) ? $responseData['success'] : false;
        
        // Extract key info about the requests
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            $debug_info['requests_summary'] = array_map(function($request) {
                return [
                    'id' => $request['id'],
                    'service_type' => $request['service_type'],
                    'origin_short' => $request['origin_short'] ?? substr($request['origin_address'] ?? '', 0, 30),
                    'client_offer' => $request['client_offer'],
                    'has_bid' => $request['has_bid'] ?? false,
                    'time_remaining' => $request['time_remaining'] ?? 0
                ];
            }, $responseData['data']);
        }
    } else {
        $debug_info['error'] = 'Empty response from API';
        $debug_info['success'] = false;
    }
}

echo json_encode([
    'frontend_simulation' => $debug_info,
    'conclusion' => $debug_info['requests_count'] > 0 ? 
        'API is working - frontend should see ' . $debug_info['requests_count'] . ' requests' :
        'API returned no requests for this driver'
], JSON_PRETTY_PRINT);
?>