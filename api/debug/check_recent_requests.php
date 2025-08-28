<?php
/**
 * Check Recent Trip Requests Debug
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get recent trip requests
    $query = "SELECT tr.*, u.full_name as client_name, u.email as client_email,
                     DATE_FORMAT(tr.created_at, '%d/%m/%Y %H:%i:%s') as formatted_created_at,
                     DATE_FORMAT(tr.expires_at, '%d/%m/%Y %H:%i:%s') as formatted_expires_at,
                     TIMESTAMPDIFF(MINUTE, NOW(), tr.expires_at) as minutes_to_expire
              FROM trip_requests tr
              LEFT JOIN users u ON tr.client_id = u.id
              ORDER BY tr.created_at DESC 
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get questionnaire answers for the most recent request
    if (!empty($requests)) {
        $latest_request_id = $requests[0]['id'];
        $answers_query = "SELECT * FROM questionnaire_answers WHERE trip_request_id = ?";
        $answers_stmt = $db->prepare($answers_query);
        $answers_stmt->execute([$latest_request_id]);
        $questionnaire_answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $requests[0]['questionnaire_answers'] = $questionnaire_answers;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'recent_requests' => $requests,
            'current_time' => date('Y-m-d H:i:s'),
            'total_requests' => count($requests)
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>