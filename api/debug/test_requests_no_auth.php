<?php
/**
 * Test Get Requests Without Authentication
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Get all active trip requests without authentication
    $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone,
                     DATE_FORMAT(tr.created_at, '%d/%m/%Y %H:%i:%s') as formatted_created_at,
                     DATE_FORMAT(tr.expires_at, '%d/%m/%Y %H:%i:%s') as formatted_expires_at,
                     TIMESTAMPDIFF(MINUTE, NOW(), tr.expires_at) as minutes_to_expire
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending' 
                AND tr.expires_at > NOW()
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get questionnaire answers for each request
    foreach ($requests as &$request) {
        $answers_query = "SELECT question_id, question_text, option_id, option_text 
                          FROM questionnaire_answers 
                          WHERE trip_request_id = ? 
                          ORDER BY question_id";
        $answers_stmt = $db->prepare($answers_query);
        $answers_stmt->execute([$request['id']]);
        $request['questionnaire_answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $request['time_remaining'] = max(0, strtotime($request['expires_at']) - time());
    }
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'debug' => [
            'total_requests_found' => count($requests),
            'current_time' => date('Y-m-d H:i:s'),
            'query_executed' => true
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>