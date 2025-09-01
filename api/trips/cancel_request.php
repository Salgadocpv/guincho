<?php
/**
 * Cancel Trip Request API
 * Cancels a trip request and removes it from driver visibility
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
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

// Only clients can cancel their own requests
if ($user['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas clientes podem cancelar suas solicitações']);
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (empty($data->trip_request_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID da solicitação é obrigatório'
    ]);
    exit();
}

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Verify the request belongs to this client and can be cancelled
    $query = "SELECT id, status, client_id FROM trip_requests 
              WHERE id = ? AND client_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$data->trip_request_id, $user['id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        $db->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Solicitação não encontrada ou não pertence a você'
        ]);
        exit();
    }
    
    // Check if request can be cancelled
    if ($request['status'] === 'completed') {
        $db->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível cancelar uma solicitação já concluída'
        ]);
        exit();
    }
    
    if ($request['status'] === 'cancelled') {
        $db->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Esta solicitação já foi cancelada'
        ]);
        exit();
    }
    
    // Cancel the trip request
    $update_query = "UPDATE trip_requests 
                     SET status = 'cancelled', 
                         updated_at = NOW() 
                     WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_success = $update_stmt->execute([$data->trip_request_id]);
    
    if (!$update_success) {
        $db->rollback();
        throw new Exception('Erro ao cancelar solicitação');
    }
    
    // Cancel all pending bids for this request
    $cancel_bids_query = "UPDATE trip_bids 
                          SET status = 'cancelled' 
                          WHERE trip_request_id = ? AND status = 'pending'";
    $cancel_bids_stmt = $db->prepare($cancel_bids_query);
    $cancel_bids_stmt->execute([$data->trip_request_id]);
    
    // Create notification for drivers who placed bids
    $notify_drivers_query = "SELECT DISTINCT d.user_id, u.full_name 
                             FROM trip_bids tb
                             JOIN drivers d ON tb.driver_id = d.id
                             JOIN users u ON d.user_id = u.id
                             WHERE tb.trip_request_id = ?";
    $notify_stmt = $db->prepare($notify_drivers_query);
    $notify_stmt->execute([$data->trip_request_id]);
    
    // If TripNotification class exists, create notifications
    if (class_exists('TripNotification')) {
        include_once '../classes/TripNotification.php';
        $notification = new TripNotification($db);
        
        while ($driver = $notify_stmt->fetch(PDO::FETCH_ASSOC)) {
            $notification->create(
                $driver['user_id'],
                'request_cancelled',
                'Solicitação Cancelada',
                'A solicitação #' . $data->trip_request_id . ' foi cancelada pelo cliente',
                $data->trip_request_id,
                null,
                ['reason' => 'client_cancelled']
            );
        }
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitação cancelada com sucesso',
        'data' => [
            'trip_request_id' => (int)$data->trip_request_id,
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    error_log('Error in cancel_request.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>