<?php
/**
 * Simple Driver Request Test
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Just test the basic query that should return requests
    $query = "SELECT COUNT(*) as total_pending 
              FROM trip_requests tr
              WHERE tr.status = 'pending' 
                AND tr.expires_at > NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pending_requests' => $result['total_pending'],
        'current_time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>