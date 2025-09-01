<?php
/**
 * Simple cleanup script for trips
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    include_once '../config/database.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Clean tables in safe order
    $queries = [
        'DELETE FROM trip_status_history',
        'DELETE FROM trip_notifications WHERE trip_request_id IS NOT NULL OR active_trip_id IS NOT NULL',
        'DELETE FROM active_trips', 
        'DELETE FROM trip_bids',
        'DELETE FROM trip_requests'
    ];
    
    $results = [];
    foreach ($queries as $query) {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $affected = $stmt->rowCount();
        $results[] = [
            'query' => $query,
            'rows_deleted' => $affected
        ];
    }
    
    // Reset auto increment
    $reset_queries = [
        'ALTER TABLE trip_requests AUTO_INCREMENT = 1',
        'ALTER TABLE trip_bids AUTO_INCREMENT = 1',
        'ALTER TABLE active_trips AUTO_INCREMENT = 1'
    ];
    
    foreach ($reset_queries as $query) {
        $db->prepare($query)->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Sistema limpo com sucesso!',
        'details' => $results,
        'total_deleted' => array_sum(array_column($results, 'rows_deleted'))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>