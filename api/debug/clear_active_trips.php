<?php
/**
 * Debug script to clear orphaned active trips
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check current active trips
    $query = "SELECT at.*, u.full_name as driver_name 
              FROM active_trips at 
              JOIN drivers d ON at.driver_id = d.id 
              JOIN users u ON d.user_id = u.id 
              WHERE at.status IN ('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress')
              ORDER BY at.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $active_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'clear') {
        // Clear all active trips (set to completed)
        $clear_query = "UPDATE active_trips 
                       SET status = 'completed', completed_at = CURRENT_TIMESTAMP 
                       WHERE status IN ('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress')";
        
        $clear_stmt = $db->prepare($clear_query);
        $result = $clear_stmt->execute();
        $affected_rows = $clear_stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'message' => "Limpeza concluída. $affected_rows viagens marcadas como concluídas.",
            'before_clearing' => $active_trips,
            'affected_rows' => $affected_rows
        ]);
        
    } else {
        // Just view current active trips
        echo json_encode([
            'success' => true,
            'message' => 'Viagens ativas atuais',
            'active_trips' => $active_trips,
            'count' => count($active_trips),
            'note' => 'Use ?action=clear para limpar todas as viagens ativas'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>