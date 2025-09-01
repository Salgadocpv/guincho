<?php
/**
 * Create test data with São Paulo timezone
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Definir timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

try {
    include_once '../config/database.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Limpar dados antigos
    $db->exec("DELETE FROM trip_bids");
    $db->exec("DELETE FROM trip_requests");
    $db->exec("ALTER TABLE trip_requests AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE trip_bids AUTO_INCREMENT = 1");
    
    // Usar o cliente que está logado (ID 1 - cliente@iguincho.com)
    $client_id = 1; // Cliente que está fazendo login no sistema
    
    $driver_check = $db->prepare("SELECT id FROM users WHERE email = 'guincheiro@teste.com' LIMIT 1");
    $driver_check->execute();
    $driver = $driver_check->fetch();
    
    if (!$driver) {
        $driver_insert = $db->prepare("
            INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted, created_at) 
            VALUES ('driver', 'Guincheiro Teste', 'guincheiro@teste.com', '(11) 88888-8888', ?, 'active', 1, 1, NOW())
        ");
        $driver_password = password_hash('123456', PASSWORD_DEFAULT);
        $driver_insert->execute([$driver_password]);
        $driver_id = $db->lastInsertId();
        
        // Criar perfil do driver
        $driver_profile = $db->prepare("
            INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, approval_status, created_at) 
            VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 'approved', NOW())
        ");
        $driver_profile->execute([$driver_id]);
        $driver_profile_id = $db->lastInsertId();
    } else {
        $driver_user_id = $driver['id'];
        // Get the driver profile ID
        $driver_profile_query = $db->prepare("SELECT id FROM drivers WHERE user_id = ? LIMIT 1");
        $driver_profile_query->execute([$driver_user_id]);
        $driver_profile = $driver_profile_query->fetch();
        
        if (!$driver_profile) {
            // Create driver profile if doesn't exist
            $create_profile = $db->prepare("
                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, approval_status, created_at) 
                VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 'approved', NOW())
            ");
            $create_profile->execute([$driver_user_id]);
            $driver_profile_id = $db->lastInsertId();
        } else {
            $driver_profile_id = $driver_profile['id'];
        }
    }
    
    // Criar solicitação de viagem (expira em 2 horas - horário SP)
    $trip_expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
    
    $trip_insert = $db->prepare("
        INSERT INTO trip_requests 
        (client_id, service_type, origin_lat, origin_lng, origin_address, 
         destination_lat, destination_lng, destination_address, client_offer, 
         status, expires_at, created_at)
        VALUES 
        (?, 'guincho', -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo/SP',
         -23.5608, -46.6508, 'Rua Augusta, 500 - São Paulo/SP', 120.00,
         'pending', ?, NOW())
    ");
    
    $trip_insert->execute([$client_id, $trip_expires]);
    $trip_id = $db->lastInsertId();
    
    // Criar proposta (expira em 1 hora - horário SP)
    $bid_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $bid_insert = $db->prepare("
        INSERT INTO trip_bids 
        (trip_request_id, driver_id, bid_amount, estimated_arrival_minutes, message, status, expires_at, created_at)
        VALUES 
        (?, ?, 100.00, 25, 'Proposta teste - horário São Paulo', 'pending', ?, NOW())
    ");
    
    $bid_insert->execute([$trip_id, $driver_profile_id, $bid_expires]);
    $bid_id = $db->lastInsertId();
    
    // Verificar horários
    $verify_query = "
        SELECT 
            tr.id as trip_id, tr.created_at as trip_created, tr.expires_at as trip_expires,
            tb.id as bid_id, tb.created_at as bid_created, tb.expires_at as bid_expires,
            NOW() as current_server_time
        FROM trip_requests tr
        JOIN trip_bids tb ON tr.id = tb.trip_request_id
        WHERE tr.id = ? AND tb.id = ?
    ";
    
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$trip_id, $bid_id]);
    $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados de teste criados com timezone São Paulo',
        'timezone' => date_default_timezone_get(),
        'current_php_time' => date('Y-m-d H:i:s'),
        'test_data' => [
            'client_id' => $client_id,
            'driver_user_id' => isset($driver_id) ? $driver_id : $driver_user_id,
            'driver_profile_id' => $driver_profile_id,
            'trip_id' => $trip_id,
            'bid_id' => $bid_id
        ],
        'timestamps' => $result,
        'instructions' => [
            'login_client' => 'Use: cliente@teste.com / 123456',
            'login_driver' => 'Use: guincheiro@teste.com / 123456',
            'test_accept' => "Use bid_id {$bid_id} para testar aceite"
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>