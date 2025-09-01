<?php
/**
 * Debug script to test the complete bid flow
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'info';
    
    if ($action === 'create_full_test') {
        // 1. Create a test trip request
        $trip_query = "INSERT INTO trip_requests 
                      (client_id, service_type, origin_lat, origin_lng, origin_address, 
                       destination_lat, destination_lng, destination_address, client_offer, 
                       status, expires_at)
                      VALUES 
                      (1, 'guincho', -23.5505, -46.6333, 'Teste Debug - Av. Paulista, 1000 - São Paulo/SP',
                       -23.5608, -46.6508, 'Teste Debug - Rua Augusta, 500 - São Paulo/SP', 100.00,
                       'pending', DATE_ADD(NOW(), INTERVAL 60 MINUTE))";
        
        $trip_stmt = $db->prepare($trip_query);
        $trip_result = $trip_stmt->execute();
        $trip_id = $db->lastInsertId();
        
        // 2. Create a test bid
        $bid_query = "INSERT INTO trip_bids 
                     (trip_request_id, driver_id, bid_amount, estimated_arrival_minutes, message, status, expires_at)
                     VALUES 
                     (?, 1, 90.00, 20, 'Proposta de teste para debug', 'pending', DATE_ADD(NOW(), INTERVAL 3 MINUTE))";
        
        $bid_stmt = $db->prepare($bid_query);
        $bid_result = $bid_stmt->execute([$trip_id]);
        $bid_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cenário de teste criado com sucesso',
            'test_data' => [
                'trip_request_id' => $trip_id,
                'trip_created' => $trip_result,
                'bid_id' => $bid_id,
                'bid_created' => $bid_result
            ],
            'next_steps' => [
                'driver_can_see' => "GET /api/trips/get_requests.php?lat=-23.5505&lng=-46.6333",
                'client_can_see' => "GET /api/trips/get_bids.php?trip_request_id={$trip_id}",
                'test_accept' => "Use bid_id {$bid_id} para testar aceite"
            ]
        ]);
        
    } elseif ($action === 'test_place_bid') {
        // Test placing a bid
        $trip_id = $_GET['trip_id'] ?? 1;
        
        // Simulate the place_bid request
        $test_data = [
            'trip_request_id' => (int)$trip_id,
            'bid_amount' => 85.50,
            'estimated_arrival_minutes' => 25,
            'message' => 'Teste de proposta via debug'
        ];
        
        $response = file_get_contents('https://www.coppermane.com.br/guincho/api/trips/place_bid.php', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer driver_test_token",
                'content' => json_encode($test_data)
            ]
        ]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Teste de place_bid executado',
            'request_data' => $test_data,
            'api_response' => json_decode($response, true)
        ]);
        
    } elseif ($action === 'test_accept_bid') {
        // Test accepting a bid
        $bid_id = $_GET['bid_id'] ?? 1;
        
        $test_data = [
            'bid_id' => (int)$bid_id
        ];
        
        $response = file_get_contents('https://www.coppermane.com.br/guincho/api/trips/accept_bid.php', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer client_test_token",
                'content' => json_encode($test_data)
            ]
        ]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Teste de accept_bid executado',
            'request_data' => $test_data,
            'api_response' => json_decode($response, true)
        ]);
        
    } else {
        // Show current status and available tests
        $trip_count_query = "SELECT COUNT(*) as count FROM trip_requests WHERE status = 'pending'";
        $trip_count_stmt = $db->prepare($trip_count_query);
        $trip_count_stmt->execute();
        $trip_count = $trip_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $bid_count_query = "SELECT COUNT(*) as count FROM trip_bids WHERE status = 'pending'";
        $bid_count_stmt = $db->prepare($bid_count_query);
        $bid_count_stmt->execute();
        $bid_count = $bid_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Debug do fluxo de propostas',
            'current_status' => [
                'pending_trips' => $trip_count,
                'pending_bids' => $bid_count
            ],
            'available_tests' => [
                'create_full_test' => 'Cria solicitação + proposta completa para teste',
                'test_place_bid' => 'Testa API de fazer proposta (driver)',
                'test_accept_bid' => 'Testa API de aceitar proposta (client)'
            ],
            'usage' => 'Adicione ?action=TESTE_NAME na URL'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>