<?php
/**
 * Debug API - Show all requests regardless of status/expiration
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Get ALL trip requests for debugging
    $query = "SELECT tr.*, 
                     u.full_name as user_name, 
                     u.phone as user_phone
              FROM trip_requests tr
              LEFT JOIN users u ON tr.client_id = u.id
              ORDER BY tr.created_at DESC 
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get only pending non-expired
    $pending_query = "SELECT tr.*, 
                             COALESCE(u.full_name, 'Cliente') as client_name, 
                             COALESCE(u.phone, 'N/A') as client_phone
                      FROM trip_requests tr
                      LEFT JOIN users u ON tr.client_id = u.id
                      WHERE tr.status = 'pending' 
                        AND (tr.expires_at IS NULL OR tr.expires_at > NOW())
                      ORDER BY tr.created_at DESC";
    
    $pending_stmt = $db->prepare($pending_query);
    $pending_stmt->execute();
    $pending_requests = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current time for reference
    $time_query = "SELECT NOW() as current_time";
    $time_stmt = $db->prepare($time_query);
    $time_stmt->execute();
    $current_time = $time_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'current_server_time' => $current_time['current_time'],
        'all_requests' => $all_requests,
        'all_count' => count($all_requests),
        'pending_requests' => $pending_requests,
        'pending_count' => count($pending_requests),
        'debug_info' => [
            'last_request_id' => count($all_requests) > 0 ? $all_requests[0]['id'] : 'none',
            'last_request_status' => count($all_requests) > 0 ? $all_requests[0]['status'] : 'none',
            'last_request_expires' => count($all_requests) > 0 ? $all_requests[0]['expires_at'] : 'none'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>