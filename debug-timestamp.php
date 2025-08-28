<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    'server_time' => time(),
    'server_date' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'test_token_parts' => [
        'current_timestamp' => time(),
        'sample_token' => 'test_client_2_' . time()
    ]
]);
?>