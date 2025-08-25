<?php
/**
 * Update Trip Status API
 * Allows updating the status of an active trip
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/ActiveTrip.php';
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

// Validate required fields
if (empty($data->trip_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Dados obrigatórios: trip_id, status'
    ]);
    exit();
}

// Valid status transitions
$valid_statuses = [
    'confirmed',
    'driver_en_route', 
    'driver_arrived',
    'in_progress',
    'completed',
    'cancelled'
];

if (!in_array($data->status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Status inválido. Valores válidos: ' . implode(', ', $valid_statuses)
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get and verify active trip
    $active_trip = new ActiveTrip($db);
    $active_trip->id = $data->trip_id;
    
    $trip_data = $active_trip->readOne();
    
    if (!$trip_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Viagem não encontrada']);
        exit();
    }
    
    // Verify user has permission to update this trip
    $has_permission = false;
    
    // Client can update their own trips
    if ($user['user_type'] === 'client' && $trip_data['client_id'] == $user['id']) {
        $has_permission = true;
        // Clients can only cancel trips
        if ($data->status !== 'cancelled') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Clientes só podem cancelar viagens']);
            exit();
        }
    }
    
    // Driver can update their own trips
    if ($user['user_type'] === 'driver') {
        $stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($driver && $trip_data['driver_id'] == $driver['id']) {
            $has_permission = true;
        }
    }
    
    // Admin can update any trip
    if ($user['user_type'] === 'admin') {
        $has_permission = true;
    }
    
    if (!$has_permission) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para atualizar esta viagem']);
        exit();
    }
    
    // Validate status transitions
    $current_status = $trip_data['status'];
    $new_status = $data->status;
    
    // Define allowed transitions
    $allowed_transitions = [
        'confirmed' => ['driver_en_route', 'cancelled'],
        'driver_en_route' => ['driver_arrived', 'cancelled'],
        'driver_arrived' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [], // Final state
        'cancelled' => []  // Final state
    ];
    
    if (!isset($allowed_transitions[$current_status]) || 
        !in_array($new_status, $allowed_transitions[$current_status])) {
        
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Transição de status inválida: '{$current_status}' para '{$new_status}'"
        ]);
        exit();
    }
    
    // Update the status
    if ($active_trip->updateStatus($new_status)) {
        
        // Send notifications based on new status
        if (class_exists('TripNotification')) {
            $notification = new TripNotification($db);
            
            switch ($new_status) {
                case 'driver_en_route':
                    // Notify client that driver is on the way
                    $notification->create(
                        $trip_data['client_id'],
                        'driver_en_route',
                        'Guincheiro a caminho',
                        "Seu guincheiro {$trip_data['driver_name']} está a caminho do local",
                        $trip_data['trip_request_id'],
                        $trip_data['id'],
                        [
                            'driver_name' => $trip_data['driver_name'],
                            'driver_phone' => $trip_data['driver_phone']
                        ]
                    );
                    break;
                    
                case 'driver_arrived':
                    // Notify client that driver has arrived
                    $notification->create(
                        $trip_data['client_id'],
                        'driver_arrived',
                        'Guincheiro chegou!',
                        "Seu guincheiro {$trip_data['driver_name']} chegou no local de origem",
                        $trip_data['trip_request_id'],
                        $trip_data['id'],
                        [
                            'driver_name' => $trip_data['driver_name'],
                            'driver_phone' => $trip_data['driver_phone'],
                            'arrival_time' => date('H:i')
                        ]
                    );
                    break;
                    
                case 'in_progress':
                    // Notify client that service has started
                    $notification->create(
                        $trip_data['client_id'],
                        'service_started',
                        'Serviço iniciado',
                        "O serviço foi iniciado por {$trip_data['driver_name']}",
                        $trip_data['trip_request_id'],
                        $trip_data['id'],
                        [
                            'driver_name' => $trip_data['driver_name'],
                            'service_type' => $trip_data['service_type'],
                            'start_time' => date('H:i')
                        ]
                    );
                    break;
                    
                case 'completed':
                    // Notify both client and driver that trip is completed
                    $notification->create(
                        $trip_data['client_id'],
                        'trip_completed',
                        'Viagem concluída',
                        "Sua viagem foi concluída com sucesso. Avalie o serviço prestado por {$trip_data['driver_name']}",
                        $trip_data['trip_request_id'],
                        $trip_data['id'],
                        [
                            'driver_name' => $trip_data['driver_name'],
                            'final_price' => $trip_data['final_price'],
                            'completion_time' => date('H:i')
                        ]
                    );
                    
                    $notification->create(
                        $trip_data['driver_user_id'] ?? $trip_data['driver_id'],
                        'trip_completed',
                        'Viagem concluída',
                        "Viagem concluída com sucesso! Você pode avaliar o cliente {$trip_data['client_name']}",
                        $trip_data['trip_request_id'],
                        $trip_data['id'],
                        [
                            'client_name' => $trip_data['client_name'],
                            'final_price' => $trip_data['final_price'],
                            'completion_time' => date('H:i')
                        ]
                    );
                    break;
                    
                case 'cancelled':
                    $canceller_name = ($user['user_type'] === 'client') 
                        ? $trip_data['client_name'] 
                        : $trip_data['driver_name'];
                    
                    $reason = isset($data->reason) ? $data->reason : 'Motivo não informado';
                    
                    // Notify the other party about cancellation
                    if ($user['user_type'] === 'client') {
                        // Client cancelled, notify driver
                        $notification->create(
                            $trip_data['driver_user_id'] ?? $trip_data['driver_id'],
                            'trip_cancelled',
                            'Viagem cancelada',
                            "A viagem foi cancelada pelo cliente. Motivo: {$reason}",
                            $trip_data['trip_request_id'],
                            $trip_data['id'],
                            [
                                'cancelled_by' => 'client',
                                'canceller_name' => $canceller_name,
                                'reason' => $reason
                            ]
                        );
                    } else {
                        // Driver cancelled, notify client
                        $notification->create(
                            $trip_data['client_id'],
                            'trip_cancelled',
                            'Viagem cancelada',
                            "A viagem foi cancelada pelo guincheiro. Motivo: {$reason}",
                            $trip_data['trip_request_id'],
                            $trip_data['id'],
                            [
                                'cancelled_by' => 'driver',
                                'canceller_name' => $canceller_name,
                                'reason' => $reason
                            ]
                        );
                    }
                    break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status da viagem atualizado com sucesso',
            'data' => [
                'trip_id' => (int)$data->trip_id,
                'old_status' => $current_status,
                'new_status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $user['full_name']
            ]
        ]);
        
    } else {
        throw new Exception('Erro ao atualizar status no banco de dados');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>