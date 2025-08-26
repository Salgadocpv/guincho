<?php
/**
 * Reset drivers status after trip cleanup
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'reset') {
        // Get current drivers status
        $current_query = "SELECT d.id, d.user_id, u.full_name, u.status as user_status, d.approval_status
                         FROM drivers d 
                         JOIN users u ON d.user_id = u.id 
                         ORDER BY d.id";
        
        $stmt = $db->prepare($current_query);
        $stmt->execute();
        $drivers_before = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reset drivers to available status
        // Set user status to 'active' if approved
        $reset_user_query = "UPDATE users u 
                           JOIN drivers d ON u.id = d.user_id 
                           SET u.status = 'active' 
                           WHERE u.user_type = 'driver' 
                           AND d.approval_status = 'approved'";
        
        $stmt = $db->prepare($reset_user_query);
        $stmt->execute();
        $users_affected = $stmt->rowCount();
        
        // Clear any driver location tracking if exists
        $clear_location_query = "UPDATE drivers SET 
                                current_lat = NULL, 
                                current_lng = NULL, 
                                last_location_update = NULL
                                WHERE approval_status = 'approved'";
        
        $stmt = $db->prepare($clear_location_query);
        $stmt->execute();
        $locations_cleared = $stmt->rowCount();
        
        // Get drivers status after reset
        $stmt = $db->prepare($current_query);
        $stmt->execute();
        $drivers_after = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => "Status dos guincheiros resetado com sucesso!",
            'users_updated' => $users_affected,
            'locations_cleared' => $locations_cleared,
            'drivers_before' => $drivers_before,
            'drivers_after' => $drivers_after,
            'total_drivers' => count($drivers_after)
        ]);
        
    } else {
        // Just show current drivers status
        $query = "SELECT d.id, d.user_id, u.full_name, u.status as user_status, 
                        d.approval_status, d.specialty, d.work_region,
                        d.current_lat, d.current_lng, d.last_location_update
                 FROM drivers d 
                 JOIN users u ON d.user_id = u.id 
                 ORDER BY d.id";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count by status
        $status_counts = [
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'active_users' => 0,
            'with_location' => 0
        ];
        
        foreach ($drivers as $driver) {
            $status_counts[$driver['approval_status']]++;
            if ($driver['user_status'] === 'active') {
                $status_counts['active_users']++;
            }
            if ($driver['current_lat'] && $driver['current_lng']) {
                $status_counts['with_location']++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status atual dos guincheiros',
            'drivers' => $drivers,
            'status_counts' => $status_counts,
            'total_drivers' => count($drivers),
            'note' => 'Use ?action=reset para resetar status dos guincheiros'
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