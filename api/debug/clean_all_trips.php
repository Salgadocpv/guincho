<?php
/**
 * Debug script to clean all trips from the system
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'view';
    
    // Get current counts before cleaning
    $counts_before = [];
    
    $tables = [
        'trip_requests' => 'SELECT COUNT(*) as count FROM trip_requests',
        'trip_bids' => 'SELECT COUNT(*) as count FROM trip_bids',
        'active_trips' => 'SELECT COUNT(*) as count FROM active_trips',
        'trip_notifications' => 'SELECT COUNT(*) as count FROM trip_notifications',
        'trip_status_history' => 'SELECT COUNT(*) as count FROM trip_status_history'
    ];
    
    foreach ($tables as $table => $query) {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $counts_before[$table] = $result['count'];
    }
    
    if ($action === 'clean_all') {
        $db->beginTransaction();
        
        try {
            // Clean all trip-related tables in order (respecting foreign keys)
            $cleanup_queries = [
                'DELETE FROM trip_status_history',
                'DELETE FROM trip_notifications WHERE trip_request_id IS NOT NULL OR active_trip_id IS NOT NULL',
                'DELETE FROM active_trips',
                'DELETE FROM trip_bids', 
                'DELETE FROM trip_requests'
            ];
            
            $results = [];
            foreach ($cleanup_queries as $query) {
                $stmt = $db->prepare($query);
                $stmt->execute();
                $results[] = [
                    'query' => $query,
                    'affected_rows' => $stmt->rowCount()
                ];
            }
            
            // Reset AUTO_INCREMENT counters
            $reset_queries = [
                'ALTER TABLE trip_requests AUTO_INCREMENT = 1',
                'ALTER TABLE trip_bids AUTO_INCREMENT = 1',
                'ALTER TABLE active_trips AUTO_INCREMENT = 1',
                'ALTER TABLE trip_notifications AUTO_INCREMENT = 1',
                'ALTER TABLE trip_status_history AUTO_INCREMENT = 1'
            ];
            
            foreach ($reset_queries as $query) {
                $stmt = $db->prepare($query);
                $stmt->execute();
            }
            
            $db->commit();
            
            // Get counts after cleaning
            $counts_after = [];
            foreach ($tables as $table => $query) {
                $stmt = $db->prepare($query);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $counts_after[$table] = $result['count'];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Todas as viagens foram removidas do sistema com sucesso!',
                'counts_before' => $counts_before,
                'counts_after' => $counts_after,
                'cleanup_details' => $results,
                'total_removed' => array_sum($counts_before)
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } elseif ($action === 'clean_requests_only') {
        // Clean only trip requests (and cascade will clean related data)
        $cleanup_query = "DELETE FROM trip_requests";
        $stmt = $db->prepare($cleanup_query);
        $stmt->execute();
        $affected = $stmt->rowCount();
        
        // Reset counter
        $reset_query = "ALTER TABLE trip_requests AUTO_INCREMENT = 1";
        $db->prepare($reset_query)->execute();
        
        echo json_encode([
            'success' => true,
            'message' => "Removidas {$affected} solicitações de viagem (e dados relacionados por cascade)",
            'requests_removed' => $affected,
            'counts_before' => $counts_before
        ]);
        
    } else {
        // Just show current status
        // Get some sample data
        $sample_requests = [];
        $sample_query = "SELECT id, service_type, status, created_at, client_offer FROM trip_requests ORDER BY created_at DESC LIMIT 5";
        $stmt = $db->prepare($sample_query);
        $stmt->execute();
        $sample_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Status atual do sistema de viagens',
            'counts' => $counts_before,
            'total_records' => array_sum($counts_before),
            'sample_requests' => $sample_requests,
            'actions' => [
                'clean_all' => 'Remove TODAS as viagens e dados relacionados',
                'clean_requests_only' => 'Remove apenas solicitações (cascade remove o resto)'
            ],
            'note' => 'Use ?action=clean_all ou ?action=clean_requests_only para limpar'
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