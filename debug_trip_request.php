<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

try {
    // Get posted data
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData);
    
    $response = [
        'success' => true,
        'message' => 'Debug info',
        'debug' => [
            'raw_data' => $rawData,
            'parsed_data' => $data,
            'json_error' => json_last_error_msg(),
            'required_fields' => [
                'service_type' => !empty($data->service_type),
                'origin_lat' => !empty($data->origin_lat),
                'origin_lng' => !empty($data->origin_lng),
                'origin_address' => !empty($data->origin_address),
                'destination_lat' => !empty($data->destination_lat),
                'destination_lng' => !empty($data->destination_lng),
                'destination_address' => !empty($data->destination_address),
                'client_offer' => !empty($data->client_offer)
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Debug error: ' . $e->getMessage()
    ]);
}
?>