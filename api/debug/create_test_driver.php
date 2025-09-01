<?php
/**
 * Create Test Driver for Debugging
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    $results = [];
    
    // 1. Create test driver user
    $email = 'guincheiro.teste@exemplo.com';
    $password = password_hash('123456', PASSWORD_DEFAULT);
    
    // Check if user already exists
    $check_user = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check_user->execute([$email]);
    $existing_user = $check_user->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        $user_id = $existing_user['id'];
        $results['user_created'] = false;
        $results['user_id'] = $user_id;
        $results['message'] = 'User already exists';
    } else {
        $user_stmt = $db->prepare("
            INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $user_stmt->execute([
            'driver',
            'Guincheiro Teste',
            '12345678901',
            '1980-01-01',
            '11999887766',
            $email,
            $password,
            1,
            'active',
            1
        ]);
        
        $user_id = $db->lastInsertId();
        $results['user_created'] = true;
        $results['user_id'] = $user_id;
    }
    
    // 2. Create driver profile
    $check_driver = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $check_driver->execute([$user_id]);
    $existing_driver = $check_driver->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_driver) {
        $driver_id = $existing_driver['id'];
        $results['driver_created'] = false;
        $results['driver_id'] = $driver_id;
        
        // Update to ensure it's approved
        $update_stmt = $db->prepare("
            UPDATE drivers SET 
                specialty = 'todos',
                approval_status = 'approved',
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([$driver_id]);
        $results['driver_updated'] = true;
    } else {
        // First, let's check what columns exist in drivers table
        $columns_query = "SHOW COLUMNS FROM drivers";
        $columns_stmt = $db->prepare($columns_query);
        $columns_stmt->execute();
        $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $results['available_columns'] = $columns;
        
        // Insert with only basic required columns
        $driver_stmt = $db->prepare("
            INSERT INTO drivers (user_id, specialty, approval_status, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $driver_stmt->execute([
            $user_id,
            'todos',          // Can handle all service types
            'approved'        // Pre-approved for testing
        ]);
        
        $driver_id = $db->lastInsertId();
        $results['driver_created'] = true;
        $results['driver_id'] = $driver_id;
    }
    
    // 3. Ensure user status is active
    $activate_stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $activate_stmt->execute([$user_id]);
    
    // Commit transaction
    $db->commit();
    
    $results['test_credentials'] = [
        'email' => $email,
        'password' => '123456',
        'login_url' => 'https://www.coppermane.com.br/guincho/index.html'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Guincheiro de teste criado/atualizado com sucesso',
        'data' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>