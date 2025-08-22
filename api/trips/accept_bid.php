<?php
/**
 * Accept Bid API
 * Allows clients to accept a driver's bid
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripBid.php';
include_once '../classes/TripRequest.php';
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

// Only clients can accept bids
if ($user['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas clientes podem aceitar propostas']);
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Validate required fields
if (empty($data->bid_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'bid_id é obrigatório'
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Get bid details
    $trip_bid = new TripBid($db);
    $trip_bid->id = $data->bid_id;
    
    if (!$trip_bid->readOne()) {
        $db->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Proposta não encontrada']);
        exit();
    }
    
    // Verify client owns the trip request
    $trip_request = new TripRequest($db);
    $trip_request->id = $trip_bid->trip_request_id;
    
    if (!$trip_request->readOne()) {
        $db->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Solicitação não encontrada']);
        exit();
    }
    
    if ($trip_request->client_id != $user['id']) {
        $db->rollback();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Esta solicitação não pertence a você']);
        exit();
    }
    
    // Check if trip request is still pending
    if ($trip_request->status !== 'pending') {
        $db->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Esta solicitação não está mais disponível']);
        exit();
    }
    
    // Check if bid is still valid
    if ($trip_bid->status !== 'pending') {
        $db->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Esta proposta não está mais disponível']);
        exit();
    }
    
    // Check if bid has expired
    if (strtotime($trip_bid->expires_at) <= time()) {
        $db->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Esta proposta expirou']);
        exit();
    }
    
    // Accept the bid (this also rejects other bids)
    if (!$trip_bid->accept()) {
        $db->rollback();
        throw new Exception('Erro ao aceitar proposta');
    }
    
    // Create active trip
    $active_trip = new ActiveTrip($db);
    if (!$active_trip->createFromBid($trip_request->id, $trip_bid->id)) {
        $db->rollback();
        throw new Exception('Erro ao criar viagem ativa');
    }
    
    // Get driver info for notifications
    $stmt = $db->prepare("SELECT u.* FROM users u JOIN drivers d ON u.id = d.user_id WHERE d.id = ?");
    $stmt->execute([$trip_bid->driver_id]);
    $driver_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create notifications
    if (class_exists('TripNotification')) {
        $notification = new TripNotification($db);
        
        // Notify accepted driver
        $notification->create(
            $driver_user['id'],
            'bid_accepted',
            'Proposta Aceita!',
            "Sua proposta de R$ " . number_format($trip_bid->bid_amount, 2, ',', '.') . " foi aceita por {$user['full_name']}",
            $trip_request->id,
            $active_trip->id,
            [
                'client_name' => $user['full_name'],
                'client_phone' => $user['phone'],
                'final_price' => $trip_bid->bid_amount,
                'service_type' => $trip_request->service_type
            ]
        );
        
        // Notify client
        $notification->create(
            $user['id'],
            'trip_started',
            'Viagem Confirmada',
            "Sua viagem foi confirmada com {$driver_user['full_name']}",
            $trip_request->id,
            $active_trip->id,
            [
                'driver_name' => $driver_user['full_name'],
                'driver_phone' => $driver_user['phone'],
                'final_price' => $trip_bid->bid_amount
            ]
        );
        
        // Notify rejected drivers
        $stmt = $db->prepare("
            SELECT u.id, u.full_name 
            FROM trip_bids tb 
            JOIN drivers d ON tb.driver_id = d.id 
            JOIN users u ON d.user_id = u.id 
            WHERE tb.trip_request_id = ? AND tb.status = 'rejected' AND tb.id != ?
        ");
        $stmt->execute([$trip_request->id, $trip_bid->id]);
        $rejected_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rejected_drivers as $rejected_driver) {
            $notification->create(
                $rejected_driver['id'],
                'bid_rejected',
                'Proposta não aceita',
                "O cliente escolheu outra proposta para este serviço",
                $trip_request->id,
                null,
                ['reason' => 'client_chose_another']
            );
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposta aceita com sucesso',
        'data' => [
            'active_trip_id' => $active_trip->id,
            'driver_name' => $driver_user['full_name'],
            'driver_phone' => $driver_user['phone'],
            'final_price' => (float)$trip_bid->bid_amount,
            'estimated_arrival_minutes' => (int)$trip_bid->estimated_arrival_minutes
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>