<?php
/**
 * Check Available Drivers Debug
 */

header("Content-Type: application/json; charset=UTF-8");
include_once 'bootstrap.php';

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Get all drivers
    $query = "SELECT d.*, u.full_name, u.email, u.status as user_status,
                     DATE_FORMAT(u.created_at, '%d/%m/%Y %H:%i:%s') as user_created_at
              FROM drivers d
              LEFT JOIN users u ON d.user_id = u.id
              ORDER BY d.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count drivers by status
    $stats = [
        'total' => count($drivers),
        'approved' => 0,
        'active_users' => 0,
        'guincho_specialists' => 0,
        'all_service_types' => 0
    ];
    
    foreach ($drivers as $driver) {
        if ($driver['approval_status'] === 'approved') {
            $stats['approved']++;
        }
        if ($driver['user_status'] === 'active') {
            $stats['active_users']++;
        }
        if ($driver['specialty'] === 'guincho') {
            $stats['guincho_specialists']++;
        }
        if ($driver['specialty'] === 'todos') {
            $stats['all_service_types']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'drivers' => $drivers,
            'stats' => $stats,
            'current_time' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>