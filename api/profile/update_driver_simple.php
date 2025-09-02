<?php
/**
 * Simple Driver Update API - For testing without full auth
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

try {
    include_once '../config/database_auto.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit();
    }
    
    $db->beginTransaction();
    
    // For testing, get first driver user or create a test one
    $stmt = $db->prepare("SELECT id FROM users WHERE user_type = 'driver' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Create a test driver if none exists
        $insertUser = $db->prepare("INSERT INTO users (full_name, cpf, birth_date, email, phone, whatsapp, password, user_type, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'driver', 'active')");
        $insertUser->execute([
            'Carlos Eduardo Silva',
            '987.654.321-00',
            '1985-03-20',
            'carlos.teste@email.com',
            '(11) 99887-6543',
            '(11) 99887-6543',
            password_hash('teste123', PASSWORD_DEFAULT)
        ]);
        $user_id = $db->lastInsertId();
    } else {
        $user_id = $user['id'];
    }
    
    // Fields that can be updated in users table
    $user_editable_fields = ['phone', 'whatsapp', 'email'];
    
    // Fields that can be updated in drivers table
    $driver_editable_fields = ['experience', 'specialty', 'workRegion', 'availability', 'truckBrand', 'truckModel', 'truckYear', 'truckCapacity'];
    
    // Map frontend field names to database field names
    $driver_field_mapping = [
        'workRegion' => 'work_region',
        'truckBrand' => 'truck_brand',
        'truckModel' => 'truck_model',
        'truckYear' => 'truck_year',
        'truckCapacity' => 'truck_capacity'
    ];
    
    // Update user table
    $user_updates = [];
    $user_params = [];
    
    foreach ($user_editable_fields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $user_updates[] = "$field = ?";
            $user_params[] = $data[$field];
        }
    }
    
    if (!empty($user_updates)) {
        $user_params[] = $user_id;
        $user_query = "UPDATE users SET " . implode(', ', $user_updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($user_query);
        $stmt->execute($user_params);
    }
    
    // Update/Insert driver data
    $driver_updates = [];
    $driver_params = [];
    
    foreach ($driver_editable_fields as $frontend_field) {
        if (isset($data[$frontend_field]) && $data[$frontend_field] !== '') {
            $db_field = $driver_field_mapping[$frontend_field] ?? $frontend_field;
            $driver_updates[] = "$db_field = ?";
            $driver_params[] = $data[$frontend_field];
        }
    }
    
    if (!empty($driver_updates)) {
        // Check if driver has a record
        $check_stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $check_stmt->execute([$user_id]);
        $driver_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($driver_record) {
            // Update existing driver record
            $driver_params[] = $driver_record['id'];
            $driver_query = "UPDATE drivers SET " . implode(', ', $driver_updates) . ", updated_at = NOW() WHERE id = ?";
        } else {
            // Create new driver record with default values
            $driver_updates[] = "user_id = ?";
            $driver_updates[] = "cnh = ?";
            $driver_updates[] = "cnh_category = ?";
            $driver_updates[] = "truck_plate = ?";
            $driver_updates[] = "status = 'approved'";
            $driver_updates[] = "created_at = NOW()";
            $driver_updates[] = "updated_at = NOW()";
            
            $driver_params[] = $user_id;
            $driver_params[] = '12345678901'; // Default CNH
            $driver_params[] = 'B'; // Default category
            $driver_params[] = 'GUN-2023'; // Default plate
            
            // Build insert query
            $fields = [];
            $placeholders = [];
            
            foreach ($driver_editable_fields as $frontend_field) {
                if (isset($data[$frontend_field]) && $data[$frontend_field] !== '') {
                    $db_field = $driver_field_mapping[$frontend_field] ?? $frontend_field;
                    $fields[] = $db_field;
                    $placeholders[] = "?";
                }
            }
            $fields[] = "user_id";
            $fields[] = "cnh";
            $fields[] = "cnh_category";
            $fields[] = "truck_plate";
            $fields[] = "status";
            $fields[] = "created_at";
            $fields[] = "updated_at";
            
            $placeholders[] = "?";
            $placeholders[] = "?";
            $placeholders[] = "?";
            $placeholders[] = "?";
            $placeholders[] = "'approved'";
            $placeholders[] = "NOW()";
            $placeholders[] = "NOW()";
            
            $driver_query = "INSERT INTO drivers (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        }
        
        $stmt = $db->prepare($driver_query);
        $stmt->execute($driver_params);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil atualizado com sucesso',
        'data' => [
            'user_id' => $user_id,
            'updated_fields' => array_keys($data),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Error updating driver profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>