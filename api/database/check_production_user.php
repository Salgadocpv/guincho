<?php
/**
 * Check and Create Production Test User
 * Verifica se o usuário cliente@iguincho.com existe no banco de produção
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

try {
    include_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    $response = [
        'success' => true,
        'message' => 'Verificação concluída',
        'results' => []
    ];
    
    // Verificar se o usuário cliente@iguincho.com existe
    $stmt = $db->prepare("SELECT id, user_type, full_name, email, status, email_verified, created_at FROM users WHERE email = 'cliente@iguincho.com' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $response['results'][] = [
            'action' => 'found_user',
            'message' => 'Usuário cliente@iguincho.com encontrado no banco',
            'user_data' => $user
        ];
    } else {
        // Criar o usuário se não existir
        $stmt = $db->prepare("
            INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
            VALUES ('client', 'Cliente Teste Produção', '123.456.789-00', '1990-01-01', '(11) 99999-9999', 'cliente@iguincho.com', ?, TRUE, 'active', TRUE, NOW())
        ");
        $passwordHash = password_hash('teste123', PASSWORD_ARGON2I);
        $success = $stmt->execute([$passwordHash]);
        
        if ($success) {
            $newUserId = $db->lastInsertId();
            
            // Buscar dados do usuário criado
            $stmt = $db->prepare("SELECT id, user_type, full_name, email, status, created_at FROM users WHERE id = ?");
            $stmt->execute([$newUserId]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['results'][] = [
                'action' => 'created_user',
                'message' => 'Usuário cliente@iguincho.com criado com sucesso',
                'user_data' => $newUser
            ];
        } else {
            throw new Exception('Erro ao criar usuário');
        }
    }
    
    // Verificar se o usuário guincheiro também existe
    $stmt = $db->prepare("SELECT id, user_type, full_name, email, status, created_at FROM users WHERE email = 'guincheiro@iguincho.com' LIMIT 1");
    $stmt->execute();
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($driver) {
        $response['results'][] = [
            'action' => 'found_driver',
            'message' => 'Usuário guincheiro@teste.com encontrado',
            'user_data' => $driver
        ];
        
        // Verificar se tem perfil de driver
        $stmt = $db->prepare("SELECT id, approval_status, specialty FROM drivers WHERE user_id = ?");
        $stmt->execute([$driver['id']]);
        $driverProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$driverProfile) {
            // Criar perfil de driver
            $stmt = $db->prepare("
                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, 
                                   truck_plate, truck_brand, truck_model, truck_year, truck_capacity, 
                                   professional_terms_accepted, background_check_authorized, approval_status, created_at) 
                VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 
                       'TST-1234', 'Ford', 'F-4000', 2020, 'media', 
                       TRUE, TRUE, 'approved', NOW())
            ");
            $stmt->execute([$driver['id']]);
            
            $response['results'][] = [
                'action' => 'created_driver_profile',
                'message' => 'Perfil de guincheiro criado para usuário teste'
            ];
        }
    } else {
        // Criar usuário guincheiro
        $stmt = $db->prepare("
            INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
            VALUES ('driver', 'Guincheiro Teste Produção', '987.654.321-00', '1985-05-15', '(11) 88888-8888', 'guincheiro@iguincho.com', ?, TRUE, 'active', TRUE, NOW())
        ");
        $passwordHash = password_hash('teste123', PASSWORD_ARGON2I);
        $success = $stmt->execute([$passwordHash]);
        
        if ($success) {
            $newDriverId = $db->lastInsertId();
            
            // Criar perfil de driver
            $stmt = $db->prepare("
                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, 
                                   truck_plate, truck_brand, truck_model, truck_year, truck_capacity, 
                                   professional_terms_accepted, background_check_authorized, approval_status, created_at) 
                VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 
                       'TST-1234', 'Ford', 'F-4000', 2020, 'media', 
                       TRUE, TRUE, 'approved', NOW())
            ");
            $stmt->execute([$newDriverId]);
            
            $response['results'][] = [
                'action' => 'created_driver',
                'message' => 'Usuário e perfil de guincheiro criados com sucesso'
            ];
        }
    }
    
    // Verificar configurações do sistema
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('minimum_trip_value', 'driver_search_radius_km')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['results'][] = [
        'action' => 'system_settings',
        'message' => 'Configurações do sistema',
        'data' => $settings
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__
    ], JSON_PRETTY_PRINT);
}
?>