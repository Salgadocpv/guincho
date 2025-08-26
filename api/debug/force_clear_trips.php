<?php
/**
 * Force clear all active trips - Direct DELETE approach
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'force_clear') {
        // Get count before clearing
        $count_query = "SELECT COUNT(*) as count FROM active_trips";
        $stmt = $db->prepare($count_query);
        $stmt->execute();
        $before_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Force delete ALL active trips regardless of status
        $clear_query = "DELETE FROM active_trips";
        $clear_stmt = $db->prepare($clear_query);
        $result = $clear_stmt->execute();
        $affected_rows = $clear_stmt->rowCount();
        
        // Reset auto increment
        $reset_query = "ALTER TABLE active_trips AUTO_INCREMENT = 1";
        $db->prepare($reset_query)->execute();
        
        // Get count after clearing
        $stmt = $db->prepare($count_query);
        $stmt->execute();
        $after_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'message' => "Limpeza forçada concluída. Todas as {$affected_rows} viagens foram removidas.",
            'before_count' => $before_count,
            'after_count' => $after_count,
            'affected_rows' => $affected_rows
        ]);
        
    } else {
        // Show all active trips with details
        $query = "SELECT * FROM active_trips ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Todas as viagens ativas (qualquer status)',
            'active_trips' => $trips,
            'count' => count($trips),
            'note' => 'Use ?action=force_clear para remover TODAS as viagens ativas'
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