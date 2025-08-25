<?php
/**
 * Create production users for testing
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    include_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // 1. Create production CLIENT user
    $client_email = 'cliente.producao@guincho.com';
    $client_check = $db->prepare("SELECT id, user_type FROM users WHERE email = ? LIMIT 1");
    $client_check->execute([$client_email]);
    $existing_client = $client_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_client) {
        $client_insert = $db->prepare("
            INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted, created_at) 
            VALUES ('client', 'Cliente Produção', ?, '(11) 99999-0001', ?, 'active', 1, 1, NOW())
        ");
        $client_password = password_hash('cliente123', PASSWORD_DEFAULT);
        $client_insert->execute([$client_email, $client_password]);
        $client_id = $db->lastInsertId();
        
        // Create session token for client
        $client_token = 'prod_client_' . $client_id . '_' . time() . '_' . bin2hex(random_bytes(16));
        $session_insert = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
        ");
        $session_insert->execute([$client_id, $client_token]);
        
    } else {
        $client_id = $existing_client['id'];
        // Generate new token for existing client
        $client_token = 'prod_client_' . $client_id . '_' . time() . '_' . bin2hex(random_bytes(16));
        $session_insert = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
        ");
        $session_insert->execute([$client_id, $client_token]);
    }
    
    // 2. Create production DRIVER user  
    $driver_email = 'guincheiro.producao@guincho.com';
    $driver_check = $db->prepare("SELECT id, user_type FROM users WHERE email = ? LIMIT 1");
    $driver_check->execute([$driver_email]);
    $existing_driver = $driver_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_driver) {
        $driver_insert = $db->prepare("
            INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted, created_at) 
            VALUES ('driver', 'Guincheiro Produção', ?, '(11) 88888-0001', ?, 'active', 1, 1, NOW())
        ");
        $driver_password = password_hash('guincheiro123', PASSWORD_DEFAULT);
        $driver_insert->execute([$driver_email, $driver_password]);
        $driver_id = $db->lastInsertId();
        
        // Create driver profile
        $driver_profile = $db->prepare("
            INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, approval_status, created_at) 
            VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 'approved', NOW())
        ");
        $driver_profile->execute([$driver_id]);
        
        // Create session token for driver
        $driver_token = 'prod_driver_' . $driver_id . '_' . time() . '_' . bin2hex(random_bytes(16));
        $session_insert = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
        ");
        $session_insert->execute([$driver_id, $driver_token]);
        
    } else {
        $driver_id = $existing_driver['id'];
        // Generate new token for existing driver
        $driver_token = 'prod_driver_' . $driver_id . '_' . time() . '_' . bin2hex(random_bytes(16));
        $session_insert = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
        ");
        $session_insert->execute([$driver_id, $driver_token]);
    }
    
    // Verify users were created correctly
    $client_verify = $db->prepare("SELECT id, user_type, full_name, email FROM users WHERE id = ?");
    $client_verify->execute([$client_id]);
    $client_data = $client_verify->fetch(PDO::FETCH_ASSOC);
    
    $driver_verify = $db->prepare("SELECT id, user_type, full_name, email FROM users WHERE id = ?");  
    $driver_verify->execute([$driver_id]);
    $driver_data = $driver_verify->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuários de produção criados/atualizados com sucesso',
        'users' => [
            'client' => [
                'id' => $client_id,
                'token' => $client_token,
                'data' => $client_data,
                'login_command' => "localStorage.setItem('auth_token', '{$client_token}');"
            ],
            'driver' => [
                'id' => $driver_id, 
                'token' => $driver_token,
                'data' => $driver_data,
                'login_command' => "localStorage.setItem('auth_token', '{$driver_token}');"
            ]
        ],
        'instructions' => [
            'client' => "Execute no console: localStorage.setItem('auth_token', '{$client_token}');",
            'driver' => "Execute no console: localStorage.setItem('auth_token', '{$driver_token}');"
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar usuários: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>