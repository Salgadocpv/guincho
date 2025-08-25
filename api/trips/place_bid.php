<?php
/**
 * Place Bid API
 * Allows drivers to place bids on trip requests
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripBid.php';
include_once '../classes/TripRequest.php';
include_once '../classes/TripNotification.php';
include_once '../classes/ActiveTrip.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Only drivers can place bids
if ($user['user_type'] !== 'driver') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas guincheiros podem fazer propostas']);
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Validate required fields
if (empty($data->trip_request_id) || 
    empty($data->bid_amount) || 
    empty($data->estimated_arrival_minutes)) {
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Dados obrigatórios: trip_request_id, bid_amount, estimated_arrival_minutes'
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get driver info
    $stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Dados do guincheiro não encontrados']);
        exit();
    }
    
    // Check if driver already has an active trip
    $active_trip = new ActiveTrip($db);
    $stmt = $active_trip->getDriverActiveTrips($driver['id']);
    $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_active_trip) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Você já possui uma viagem ativa em andamento. Finalize-a antes de fazer uma nova proposta.',
            'active_trip_id' => $current_active_trip['id']
        ]);
        exit();
    }
    
    // Verify trip request exists and is still active
    $trip_request = new TripRequest($db);
    $trip_request->id = $data->trip_request_id;
    
    if (!$trip_request->readOne()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Solicitação de viagem não encontrada']);
        exit();
    }
    
    if ($trip_request->status !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Esta solicitação não está mais disponível']);
        exit();
    }
    
    // Check if request has expired
    if (strtotime($trip_request->expires_at) <= time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Esta solicitação expirou']);
        exit();
    }
    
    // Check maximum bids per request
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_bids_per_request'");
    $stmt->execute();
    $max_bids_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_bids = $max_bids_result ? (int)$max_bids_result['setting_value'] : 10;
    
    $stmt = $db->prepare("SELECT COUNT(*) as bid_count FROM trip_bids WHERE trip_request_id = ? AND status = 'pending'");
    $stmt->execute([$data->trip_request_id]);
    $current_bids = $stmt->fetch(PDO::FETCH_ASSOC)['bid_count'];
    
    if ($current_bids >= $max_bids) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Número máximo de propostas atingido para esta solicitação']);
        exit();
    }
    
    // Create bid
    $trip_bid = new TripBid($db);
    
    $trip_bid->trip_request_id = $data->trip_request_id;
    $trip_bid->driver_id = $driver['id'];
    $trip_bid->bid_amount = $data->bid_amount;
    $trip_bid->estimated_arrival_minutes = $data->estimated_arrival_minutes;
    $trip_bid->message = $data->message ?? '';
    
    // Validate bid
    $validation_errors = $trip_bid->validate();
    if (!empty($validation_errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Dados inválidos',
            'errors' => $validation_errors
        ]);
        exit();
    }
    
    // Validate minimum bid amount
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'minimum_trip_value'");
    $stmt->execute();
    $min_value = $stmt->fetch(PDO::FETCH_ASSOC);
    $minimum_bid = $min_value ? (float)$min_value['setting_value'] : 25.00;
    
    if ((float)$data->bid_amount < $minimum_bid) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Valor mínimo da proposta é R$ " . number_format($minimum_bid, 2, ',', '.')
        ]);
        exit();
    }
    
    if ($trip_bid->create()) {
        // Notify client about new bid
        if (class_exists('TripNotification')) {
            $notification = new TripNotification($db);
            $notification->create(
                $trip_request->client_id,
                'new_bid',
                'Nova Proposta Recebida',
                "{$user['full_name']} enviou uma proposta de R$ " . number_format($data->bid_amount, 2, ',', '.'),
                $data->trip_request_id,
                null,
                [
                    'bid_id' => $trip_bid->id,
                    'driver_name' => $user['full_name'],
                    'bid_amount' => $data->bid_amount,
                    'estimated_arrival_minutes' => $data->estimated_arrival_minutes,
                    'message' => $data->message ?? ''
                ]
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Proposta enviada com sucesso',
            'data' => [
                'bid_id' => $trip_bid->id,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+3 minutes'))
            ]
        ]);
        
    } else {
        // Check if driver already has a bid for this request
        $stmt = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
        $stmt->execute([$data->trip_request_id, $driver['id']]);
        
        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Você já fez uma proposta para esta solicitação']);
        } else {
            throw new Exception('Erro ao criar proposta');
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>