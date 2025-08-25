<?php
/**
 * Debug the accept_bid process step by step
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Set São Paulo timezone
date_default_timezone_set('America/Sao_Paulo');

include_once '../config/database.php';
include_once '../middleware/auth.php';

try {
    // Get the bid_id from request
    $data = json_decode(file_get_contents("php://input"));
    $bid_id = $data->bid_id ?? $_GET['bid_id'] ?? null;
    
    $database = new Database();
    $db = $database->getConnection();
    
    $debug_info = [
        'step' => 'Starting debug',
        'current_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'received_data' => $data,
        'bid_id' => $bid_id
    ];
    
    // Step 1: Check authentication
    $debug_info['step'] = 'Checking authentication';
    $auth_result = authenticate();
    $debug_info['auth_result'] = $auth_result;
    
    if (!$auth_result['success']) {
        $debug_info['error'] = 'Authentication failed';
        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit();
    }
    
    $user = $auth_result['user'];
    $debug_info['user'] = [
        'id' => $user['id'],
        'email' => $user['email'] ?? 'no-email',
        'user_type' => $user['user_type']
    ];
    
    // Step 2: Check if user is client
    if ($user['user_type'] !== 'client') {
        $debug_info['error'] = 'User is not a client: ' . $user['user_type'];
        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit();
    }
    
    // Step 3: Validate bid_id
    if (empty($bid_id)) {
        $debug_info['error'] = 'bid_id is empty or missing';
        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit();
    }
    
    // Step 4: Find the bid
    $debug_info['step'] = 'Finding bid';
    
    $bid_query = "
        SELECT 
            tb.*,
            tr.id as trip_id,
            tr.client_id,
            tr.status as trip_status,
            tr.expires_at as trip_expires,
            NOW() as current_server_time
        FROM trip_bids tb
        JOIN trip_requests tr ON tb.trip_request_id = tr.id
        WHERE tb.id = ?
    ";
    
    $bid_stmt = $db->prepare($bid_query);
    $bid_stmt->execute([$bid_id]);
    $bid_data = $bid_stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug_info['bid_query'] = $bid_query;
    $debug_info['bid_query_params'] = [$bid_id];
    $debug_info['bid_found'] = $bid_data ? 'YES' : 'NO';
    
    if (!$bid_data) {
        // Let's check what bids actually exist
        $debug_info['step'] = 'Bid not found, checking all bids';
        
        $all_bids_query = "SELECT id, trip_request_id, driver_id, status FROM trip_bids ORDER BY id DESC LIMIT 5";
        $all_bids_stmt = $db->prepare($all_bids_query);
        $all_bids_stmt->execute();
        $all_bids = $all_bids_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debug_info['all_existing_bids'] = $all_bids;
        $debug_info['error'] = "Bid ID {$bid_id} not found in database";
        
        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit();
    }
    
    $debug_info['bid_data'] = $bid_data;
    
    // Step 5: Check ownership
    if ($bid_data['client_id'] != $user['id']) {
        $debug_info['error'] = "Trip belongs to client {$bid_data['client_id']}, but user is {$user['id']}";
        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit();
    }
    
    // Step 6: Check statuses
    $debug_info['validations'] = [
        'trip_status' => [
            'current' => $bid_data['trip_status'],
            'required' => 'pending',
            'valid' => $bid_data['trip_status'] === 'pending'
        ],
        'bid_status' => [
            'current' => $bid_data['status'],
            'required' => 'pending', 
            'valid' => $bid_data['status'] === 'pending'
        ],
        'bid_expired' => [
            'expires_at' => $bid_data['expires_at'],
            'current_time' => $bid_data['current_server_time'],
            'is_expired' => strtotime($bid_data['expires_at']) <= time()
        ],
        'trip_expired' => [
            'expires_at' => $bid_data['trip_expires'],
            'current_time' => $bid_data['current_server_time'],
            'is_expired' => strtotime($bid_data['trip_expires']) <= time()
        ]
    ];
    
    $debug_info['step'] = 'All validations complete';
    $debug_info['can_proceed'] = (
        $bid_data['trip_status'] === 'pending' &&
        $bid_data['status'] === 'pending' &&
        strtotime($bid_data['expires_at']) > time() &&
        strtotime($bid_data['trip_expires']) > time()
    );
    
    if ($debug_info['can_proceed']) {
        $debug_info['result'] = 'SUCCESS - Bid should be acceptable';
    } else {
        $debug_info['result'] = 'FAILED - See validations for issues';
    }
    
    echo json_encode($debug_info, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>