<?php
/**
 * Create Test Data for Trip System
 * Creates sample users and data for testing
 */

header('Content-Type: application/json; charset=UTF-8');

try {
    include_once '../config/database.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    $results = [];
    
    // Create test client user
    $stmt = $db->prepare("
        INSERT IGNORE INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
        VALUES ('client', 'Cliente Teste', '123.456.789-00', '1990-01-01', '(11) 99999-9999', 'cliente@iguincho.com', ?, TRUE, 'active', TRUE)
    ");
    $passwordHash = password_hash('teste123', PASSWORD_ARGON2I);
    $stmt->execute([$passwordHash]);
    $results[] = "Cliente teste criado";
    
    // Get client ID
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'cliente@iguincho.com'");
    $stmt->execute();
    $clientId = $stmt->fetchColumn();
    
    // Create test driver user
    $stmt = $db->prepare("
        INSERT IGNORE INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
        VALUES ('driver', 'Guincheiro Teste', '987.654.321-00', '1985-05-15', '(11) 88888-8888', 'guincheiro@iguincho.com', ?, TRUE, 'active', TRUE)
    ");
    $stmt->execute([$passwordHash]);
    $results[] = "Guincheiro teste criado";
    
    // Get driver user ID
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'guincheiro@iguincho.com'");
    $stmt->execute();
    $driverUserId = $stmt->fetchColumn();
    
    if ($driverUserId) {
        // Create driver profile
        $stmt = $db->prepare("
            INSERT IGNORE INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, truck_plate, truck_brand, truck_model, truck_year, truck_capacity, approval_status, rating, total_services) 
            VALUES (?, '12345678901', 'D', '5-10', 'todos', 'São Paulo', '24h', 'ABC-1234', 'Ford', 'Cargo', 2020, 'media', 'approved', 4.8, 156)
        ");
        $stmt->execute([$driverUserId]);
        $results[] = "Perfil do guincheiro criado";
        
        // Get driver ID
        $stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt->execute([$driverUserId]);
        $driverId = $stmt->fetchColumn();
    }
    
    // Create admin user
    $stmt = $db->prepare("
        INSERT IGNORE INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
        VALUES ('admin', 'Admin Teste', '000.000.000-00', '1980-01-01', '(11) 77777-7777', 'admin@teste.com', ?, TRUE, 'active', TRUE)
    ");
    $stmt->execute([$passwordHash]);
    $results[] = "Admin teste criado";
    
    // Create test sessions for easy login
    if ($clientId) {
        $clientToken = 'client_test_token_' . time();
        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), '127.0.0.1', 'Test Browser')
            ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$clientId, $clientToken]);
        $results[] = "Sessão do cliente criada: " . $clientToken;
    }
    
    if (isset($driverUserId)) {
        $driverToken = 'driver_test_token_' . time();
        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), '127.0.0.1', 'Test Browser')
            ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$driverUserId, $driverToken]);
        $results[] = "Sessão do guincheiro criada: " . $driverToken;
    }
    
    // Create sample trip request
    if ($clientId) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO trip_requests (id, client_id, service_type, origin_lat, origin_lng, origin_address, destination_lat, destination_lng, destination_address, client_offer, distance_km, estimated_duration_minutes, expires_at) 
            VALUES (1, ?, 'guincho', -23.5505, -46.6333, 'Av. Paulista, 1000 - São Paulo, SP', -23.5629, -46.6544, 'Rua Augusta, 500 - São Paulo, SP', 75.00, 2.5, 8, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ");
        $stmt->execute([$clientId]);
        $results[] = "Solicitação de teste criada";
        
        // Create sample bid
        if (isset($driverId)) {
            $stmt = $db->prepare("
                INSERT IGNORE INTO trip_bids (trip_request_id, driver_id, bid_amount, estimated_arrival_minutes, message, expires_at) 
                VALUES (1, ?, 70.00, 15, 'Guincho especializado em carros de passeio', DATE_ADD(NOW(), INTERVAL 3 MINUTE))
            ");
            $stmt->execute([$driverId]);
            $results[] = "Proposta de teste criada";
        }
    }
    
    // Update system settings
    $settings = [
        ['trip_request_timeout_minutes', '30'],
        ['bid_timeout_minutes', '3'], 
        ['driver_search_radius_km', '25'],
        ['minimum_trip_value', '25.00'],
        ['max_bids_per_request', '10']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category) 
            VALUES (?, ?, 'number', 'Configuração do sistema de viagens', 'trip_system')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute($setting);
    }
    $results[] = "Configurações do sistema atualizadas";
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados de teste criados com sucesso',
        'results' => $results,
        'test_credentials' => [
            'client' => ['email' => 'cliente@teste.com', 'password' => 'teste123'],
            'driver' => ['email' => 'guincheiro@teste.com', 'password' => 'teste123'],
            'admin' => ['email' => 'admin@teste.com', 'password' => 'teste123']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar dados de teste: ' . $e->getMessage()
    ]);
}
?>