<?php
/**
 * Simple Client Update API - For testing without full auth
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
    
    // For testing, get first client user or create a test one
    $stmt = $db->prepare("SELECT id FROM users WHERE user_type = 'client' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Create a test client if none exists
        $insertUser = $db->prepare("INSERT INTO users (full_name, cpf, birth_date, email, phone, whatsapp, password, user_type, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'client', 'active')");
        $insertUser->execute([
            'João Silva Santos',
            '123.456.789-10',
            '1990-05-15',
            'joao.teste@email.com',
            '(11) 98765-4321',
            '(11) 98765-4321',
            password_hash('teste123', PASSWORD_DEFAULT)
        ]);
        $user_id = $db->lastInsertId();
    } else {
        $user_id = $user['id'];
    }
    
    // Fields that can be updated in users table
    $editable_fields = ['phone', 'whatsapp', 'email'];
    $vehicle_fields = ['licensePlate', 'vehicleBrand', 'vehicleModel', 'vehicleYear', 'vehicleColor'];
    
    // Map frontend field names to database field names
    $field_mapping = [
        'licensePlate' => 'license_plate',
        'vehicleBrand' => 'vehicle_brand',
        'vehicleModel' => 'vehicle_model',
        'vehicleYear' => 'vehicle_year',
        'vehicleColor' => 'vehicle_color'
    ];
    
    // Update user table
    $user_updates = [];
    $user_params = [];
    
    foreach ($editable_fields as $field) {
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
    
    // Update/Insert vehicle data
    $vehicle_updates = [];
    $vehicle_params = [];
    
    foreach ($vehicle_fields as $frontend_field) {
        if (isset($data[$frontend_field]) && $data[$frontend_field] !== '') {
            $db_field = $field_mapping[$frontend_field] ?? $frontend_field;
            $vehicle_updates[] = "$db_field = ?";
            $vehicle_params[] = $data[$frontend_field];
        }
    }
    
    if (!empty($vehicle_updates)) {
        // Check if client has a vehicle record
        $check_stmt = $db->prepare("SELECT id FROM client_vehicles WHERE client_id = ?");
        $check_stmt->execute([$user_id]);
        $vehicle_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vehicle_record) {
            // Update existing vehicle record
            $vehicle_params[] = $vehicle_record['id'];
            $vehicle_query = "UPDATE client_vehicles SET " . implode(', ', $vehicle_updates) . ", updated_at = NOW() WHERE id = ?";
        } else {
            // Create new vehicle record
            $vehicle_updates[] = "client_id = ?";
            $vehicle_updates[] = "created_at = NOW()";
            $vehicle_updates[] = "updated_at = NOW()";
            
            $vehicle_params[] = $user_id;
            
            // Build insert query
            $fields = [];
            $placeholders = [];
            
            foreach ($vehicle_fields as $frontend_field) {
                if (isset($data[$frontend_field]) && $data[$frontend_field] !== '') {
                    $db_field = $field_mapping[$frontend_field] ?? $frontend_field;
                    $fields[] = $db_field;
                    $placeholders[] = "?";
                }
            }
            $fields[] = "client_id";
            $fields[] = "created_at";
            $fields[] = "updated_at";
            $placeholders[] = "?";
            $placeholders[] = "NOW()";
            $placeholders[] = "NOW()";
            
            $vehicle_query = "INSERT INTO client_vehicles (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        }
        
        $stmt = $db->prepare($vehicle_query);
        $stmt->execute($vehicle_params);
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
    
    error_log("Error updating client profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>