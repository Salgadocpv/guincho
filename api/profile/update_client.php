<?php
/**
 * Update Client Profile API
 * Updates client profile information (only editable fields)
 */

error_reporting(E_ERROR | E_PARSE);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Only clients can update client profile
if ($user['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Fields that can be updated by client
    $editable_fields = [
        'phone',
        'whatsapp',
        'email'
    ];
    
    $vehicle_fields = [
        'license_plate',
        'vehicle_brand', 
        'vehicle_model',
        'vehicle_year',
        'vehicle_color'
    ];
    
    // Update user table (contact info)
    $user_updates = [];
    $user_params = [];
    
    foreach ($editable_fields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $user_updates[] = "$field = ?";
            $user_params[] = $data[$field];
        }
    }
    
    if (!empty($user_updates)) {
        $user_params[] = $user['id'];
        $user_query = "UPDATE users SET " . implode(', ', $user_updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($user_query);
        $stmt->execute($user_params);
    }
    
    // Update client_vehicles table
    $vehicle_updates = [];
    $vehicle_params = [];
    
    foreach ($vehicle_fields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $vehicle_updates[] = "$field = ?";
            $vehicle_params[] = $data[$field];
        }
    }
    
    if (!empty($vehicle_updates)) {
        // Check if client has a vehicle record
        $check_stmt = $db->prepare("SELECT id FROM client_vehicles WHERE client_id = ?");
        $check_stmt->execute([$user['id']]);
        $vehicle_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vehicle_record) {
            // Update existing vehicle record
            $vehicle_params[] = $vehicle_record['id'];
            $vehicle_query = "UPDATE client_vehicles SET " . implode(', ', $vehicle_updates) . ", updated_at = NOW() WHERE id = ?";
        } else {
            // Create new vehicle record
            $vehicle_updates[] = "client_id = ?";
            $vehicle_params[] = $user['id'];
            $vehicle_updates[] = "created_at = NOW()";
            $vehicle_updates[] = "updated_at = NOW()";
            
            $placeholders = str_repeat('?,', count($vehicle_updates) - 2) . 'NOW(), NOW()';
            $vehicle_query = "INSERT INTO client_vehicles (" . implode(', ', array_map(function($field) {
                return str_replace(' = ?', '', $field);
            }, $vehicle_updates)) . ") VALUES ($placeholders)";
            
            // Rebuild params for insert
            $insert_params = [];
            foreach ($vehicle_fields as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $insert_params[] = $data[$field];
                }
            }
            $insert_params[] = $user['id'];
            $vehicle_params = $insert_params;
        }
        
        $stmt = $db->prepare($vehicle_query);
        $stmt->execute($vehicle_params);
    }
    
    $db->commit();
    
    // Log the update
    error_log("Client profile updated - User ID: {$user['id']}, Fields: " . json_encode($data));
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil atualizado com sucesso',
        'data' => [
            'user_id' => $user['id'],
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error updating client profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>