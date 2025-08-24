<?php
/**
 * Get Client Requests API
 * Returns all trip requests made by the authenticated client
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
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

// Only clients can get their own requests
if ($user['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas clientes podem acessar suas solicitações']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all trip requests for this client with bid count
    $query = "SELECT 
                tr.*,
                COUNT(tb.id) as bid_count
              FROM trip_requests tr
              LEFT JOIN trip_bids tb ON tr.id = tb.trip_request_id
              WHERE tr.client_id = ?
              GROUP BY tr.id
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user['id']]);
    
    $requests = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format the data
        $request = [
            'id' => (int)$row['id'],
            'service_type' => $row['service_type'],
            'origin_lat' => (float)$row['origin_lat'],
            'origin_lng' => (float)$row['origin_lng'],
            'origin_address' => $row['origin_address'],
            'destination_lat' => (float)$row['destination_lat'],
            'destination_lng' => (float)$row['destination_lng'],
            'destination_address' => $row['destination_address'],
            'client_offer' => (float)$row['client_offer'],
            'status' => $row['status'],
            'distance_km' => (float)$row['distance_km'],
            'estimated_duration_minutes' => (int)$row['estimated_duration_minutes'],
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
            'bid_count' => (int)$row['bid_count']
        ];
        
        $requests[] = $request;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitações carregadas com sucesso',
        'data' => $requests,
        'count' => count($requests)
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_client_requests.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>