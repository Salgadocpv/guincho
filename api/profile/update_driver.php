<?php
/**
 * Update Driver Profile API
 * Updates driver profile information (only editable fields)
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

// Only drivers can update driver profile
if ($user['user_type'] !== 'driver') {
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
    
    // Fields that can be updated in users table
    $user_editable_fields = [
        'phone',
        'whatsapp', 
        'email'
    ];
    
    // Fields that can be updated in drivers table
    $driver_editable_fields = [
        'experience',
        'specialty',
        'work_region',
        'availability',
        'truck_brand',
        'truck_model',
        'truck_year',
        'truck_capacity'
    ];
    
    // Update user table (contact info)
    $user_updates = [];
    $user_params = [];
    
    foreach ($user_editable_fields as $field) {
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
    
    // Update drivers table
    $driver_updates = [];
    $driver_params = [];
    
    foreach ($driver_editable_fields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $driver_updates[] = "$field = ?";
            $driver_params[] = $data[$field];
        }
    }
    
    if (!empty($driver_updates)) {
        // Get driver record
        $check_stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $check_stmt->execute([$user['id']]);
        $driver_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($driver_record) {
            // Update existing driver record
            $driver_params[] = $driver_record['id'];
            $driver_query = "UPDATE drivers SET " . implode(', ', $driver_updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($driver_query);
            $stmt->execute($driver_params);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Registro de guincheiro não encontrado']);
            exit();
        }
    }
    
    $db->commit();
    
    // Log the update
    error_log("Driver profile updated - User ID: {$user['id']}, Fields: " . json_encode($data));
    
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
    error_log("Error updating driver profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>