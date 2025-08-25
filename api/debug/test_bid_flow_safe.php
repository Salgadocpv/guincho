<?php
/**
 * Debug script to test the complete bid flow - SAFE VERSION
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Enable error reporting for debugging
ini_set('display_errors', 0); // Don't display errors in output
error_reporting(E_ALL);

try {
    // Test if database file exists first
    $config_path = __DIR__ . '/../config/database.php';
    if (!file_exists($config_path)) {
        throw new Exception("Database config file not found at: $config_path");
    }
    
    include_once $config_path;
    
    if (!class_exists('Database')) {
        throw new Exception("Database class not found after including config");
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $action = $_GET['action'] ?? 'info';
    
    if ($action === 'create_full_test') {
        // First, ensure test users exist
        
        // 1. Create or get test client
        $client_check = $db->prepare("SELECT id FROM users WHERE email = 'test_client@example.com' AND user_type = 'client' LIMIT 1");
        $client_check->execute();
        $client = $client_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            $create_client = $db->prepare("
                INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted) 
                VALUES ('client', 'Test Client', 'test_client@example.com', '(11) 99999-9999', ?, 'active', 1, 1)
            ");
            $client_password = password_hash('test123', PASSWORD_DEFAULT);
            $create_client->execute([$client_password]);
            $client_id = $db->lastInsertId();
        } else {
            $client_id = $client['id'];
        }
        
        // 2. Create or get test driver
        $driver_check = $db->prepare("SELECT id FROM users WHERE email = 'test_driver@example.com' AND user_type = 'driver' LIMIT 1");
        $driver_check->execute();
        $driver = $driver_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$driver) {
            $create_driver = $db->prepare("
                INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted) 
                VALUES ('driver', 'Test Driver', 'test_driver@example.com', '(11) 88888-8888', ?, 'active', 1, 1)
            ");
            $driver_password = password_hash('test123', PASSWORD_DEFAULT);
            $create_driver->execute([$driver_password]);
            $driver_id = $db->lastInsertId();
            
            // Create driver profile
            $create_driver_profile = $db->prepare("
                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, approval_status) 
                VALUES (?, '12345678900', 'C', '3-5', 'guincho', 'São Paulo', '24h', 'approved')
            ");
            $create_driver_profile->execute([$driver_id]);
            
        } else {
            $driver_id = $driver['id'];
        }
        
        // 3. Create a test trip request
        $trip_query = "INSERT INTO trip_requests 
                      (client_id, service_type, origin_lat, origin_lng, origin_address, 
                       destination_lat, destination_lng, destination_address, client_offer, 
                       status, expires_at, created_at)
                      VALUES 
                      (?, 'guincho', -23.5505, -46.6333, 'Teste Debug - Av. Paulista, 1000 - São Paulo/SP',
                       -23.5608, -46.6508, 'Teste Debug - Rua Augusta, 500 - São Paulo/SP', 100.00,
                       'pending', DATE_ADD(NOW(), INTERVAL 60 MINUTE), NOW())";
        
        $trip_stmt = $db->prepare($trip_query);
        $trip_result = $trip_stmt->execute([$client_id]);
        $trip_id = $db->lastInsertId();
        
        if (!$trip_result || !$trip_id) {
            throw new Exception("Failed to create trip request");
        }
        
        // 4. Create a test bid
        $bid_query = "INSERT INTO trip_bids 
                     (trip_request_id, driver_id, bid_amount, estimated_arrival_minutes, message, status, expires_at, created_at)
                     VALUES 
                     (?, ?, 90.00, 20, 'Proposta de teste para debug', 'pending', DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())";
        
        $bid_stmt = $db->prepare($bid_query);
        $bid_result = $bid_stmt->execute([$trip_id, $driver_id]);
        $bid_id = $db->lastInsertId();
        
        if (!$bid_result || !$bid_id) {
            throw new Exception("Failed to create bid");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Cenário de teste criado com sucesso (versão segura)',
            'test_data' => [
                'trip_request_id' => $trip_id,
                'bid_id' => $bid_id,
                'client_id' => $client_id,
                'driver_id' => $driver_id,
                'trip_created' => $trip_result,
                'bid_created' => $bid_result
            ],
            'next_steps' => [
                'driver_can_see' => "/api/trips/get_requests.php?lat=-23.5505&lng=-46.6333",
                'client_can_see' => "/api/trips/get_bids.php?trip_request_id={$trip_id}",
                'test_accept' => "Use bid_id {$bid_id} para testar aceite"
            ]
        ]);
        
    } elseif ($action === 'info') {
        // Show current status
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
            'message' => 'Debug do fluxo de propostas (versão segura)',
            'current_status' => [
                'pending_trips' => $trip_count,
                'pending_bids' => $bid_count
            ],
            'available_tests' => [
                'create_full_test' => 'Cria usuários, solicitação e proposta para teste',
                'info' => 'Mostra status atual do sistema'
            ],
            'usage' => 'Adicione ?action=create_full_test na URL'
        ]);
    }
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in test_bid_flow_safe.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'debug_info' => [
            'config_exists' => file_exists(__DIR__ . '/../config/database.php'),
            'database_class_exists' => class_exists('Database', false),
            'action' => $_GET['action'] ?? 'none'
        ]
    ]);
}
?>