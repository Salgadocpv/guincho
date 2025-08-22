<?php
/**
 * Get Bids API
 * Returns bids for a trip request (for clients to view proposals)
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripBid.php';
include_once '../classes/TripRequest.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Get trip request ID from URL
$trip_request_id = $_GET['trip_request_id'] ?? null;

if (!$trip_request_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'trip_request_id é obrigatório']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verify trip request exists and user has permission to view it
    $trip_request = new TripRequest($db);
    $trip_request->id = $trip_request_id;
    
    if (!$trip_request->readOne()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Solicitação não encontrada']);
        exit();
    }
    
    // Check if user has permission to view this request
    if ($user['user_type'] === 'client' && $trip_request->client_id != $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para ver esta solicitação']);
        exit();
    }
    
    // For drivers, check if they have a bid on this request
    if ($user['user_type'] === 'driver') {
        $stmt = $db->prepare("SELECT d.id FROM drivers d WHERE d.user_id = ?");
        $stmt->execute([$user['id']]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$driver) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Dados do guincheiro não encontrados']);
            exit();
        }
        
        // Drivers can only see their own bid unless they are admin
        $stmt = $db->prepare("SELECT id FROM trip_bids WHERE trip_request_id = ? AND driver_id = ?");
        $stmt->execute([$trip_request_id, $driver['id']]);
        
        if ($stmt->rowCount() === 0 && $user['user_type'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sem permissão para ver propostas desta solicitação']);
            exit();
        }
    }
    
    // Get bids for the trip request
    $trip_bid = new TripBid($db);
    $stmt = $trip_bid->getBidsForRequest($trip_request_id);
    $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up expired bids
    $trip_bid->expireOldBids();
    
    // Calculate time remaining for each bid and format data
    $formatted_bids = [];
    foreach ($bids as $bid) {
        $time_remaining = max(0, (int)$bid['seconds_remaining']);
        
        $formatted_bid = [
            'id' => $bid['id'],
            'driver_id' => $bid['driver_id'],
            'driver_name' => $bid['driver_name'],
            'driver_phone' => $bid['driver_phone'],
            'truck_info' => [
                'plate' => $bid['truck_plate'],
                'brand' => $bid['truck_brand'],
                'model' => $bid['truck_model'],
                'capacity' => $bid['truck_capacity']
            ],
            'bid_amount' => (float)$bid['bid_amount'],
            'estimated_arrival_minutes' => (int)$bid['estimated_arrival_minutes'],
            'message' => $bid['message'],
            'driver_rating' => (float)$bid['rating'],
            'total_services' => (int)$bid['total_services'],
            'status' => $bid['status'],
            'time_remaining_seconds' => $time_remaining,
            'created_at' => $bid['created_at'],
            'expires_at' => $bid['expires_at']
        ];
        
        // If driver is viewing, only show their bid
        if ($user['user_type'] === 'driver' && isset($driver)) {
            if ($bid['driver_id'] == $driver['id']) {
                $formatted_bids[] = $formatted_bid;
            }
        } else {
            $formatted_bids[] = $formatted_bid;
        }
    }
    
    // Sort bids by amount (lowest first) for clients
    if ($user['user_type'] === 'client') {
        usort($formatted_bids, function($a, $b) {
            return $a['bid_amount'] <=> $b['bid_amount'];
        });
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'trip_request' => [
                'id' => $trip_request->id,
                'service_type' => $trip_request->service_type,
                'client_offer' => (float)$trip_request->client_offer,
                'origin_address' => $trip_request->origin_address,
                'destination_address' => $trip_request->destination_address,
                'distance_km' => (float)$trip_request->distance_km,
                'status' => $trip_request->status,
                'expires_at' => $trip_request->expires_at,
                'time_remaining_seconds' => max(0, strtotime($trip_request->expires_at) - time())
            ],
            'bids' => $formatted_bids,
            'total_bids' => count($formatted_bids)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>