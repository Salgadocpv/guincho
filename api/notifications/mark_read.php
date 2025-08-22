<?php
/**
 * Mark Notification as Read API
 * Marks one or all notifications as read for authenticated user
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripNotification.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Get posted data
$data = json_decode(file_get_contents("php://input"));

$database = new Database();
$db = $database->getConnection();

try {
    $notification = new TripNotification($db);
    
    if (isset($data->notification_id)) {
        // Mark specific notification as read
        $result = $notification->markAsRead($data->notification_id, $user['id']);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Notificação marcada como lida'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Notificação não encontrada'
            ]);
        }
        
    } elseif (isset($data->mark_all) && $data->mark_all === true) {
        // Mark all notifications as read
        $result = $notification->markAllAsRead($user['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Todas as notificações foram marcadas como lidas'
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetro notification_id ou mark_all é obrigatório'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>