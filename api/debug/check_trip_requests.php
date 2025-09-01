<?php
/**
 * Debug script to check trip requests in database
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    // Check all trip requests
    $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              ORDER BY tr.created_at DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check only pending requests
    $pending_query = "SELECT tr.*, u.full_name as client_name
                      FROM trip_requests tr
                      JOIN users u ON tr.client_id = u.id
                      WHERE tr.status = 'pending' 
                        AND tr.expires_at > NOW()
                      ORDER BY tr.created_at DESC";
    
    $pending_stmt = $db->prepare($pending_query);
    $pending_stmt->execute();
    $pending_requests = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check system settings
    $settings_query = "SELECT setting_key, setting_value FROM system_settings 
                      WHERE setting_key IN ('trip_request_timeout_minutes', 'driver_search_radius_km')";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check drivers
    $drivers_query = "SELECT d.*, u.full_name, u.user_type FROM drivers d 
                     JOIN users u ON d.user_id = u.id 
                     WHERE u.status = 'active'";
    $drivers_stmt = $db->prepare($drivers_query);
    $drivers_stmt->execute();
    $drivers = $drivers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'create_test') {
        // Create a test trip request
        $test_query = "INSERT INTO trip_requests 
                      (client_id, service_type, origin_lat, origin_lng, origin_address, 
                       destination_lat, destination_lng, destination_address, client_offer, 
                       status, expires_at)
                      VALUES 
                      (1, 'guincho', -23.5505, -46.6333, 'Teste - Av. Paulista, 1000 - São Paulo/SP',
                       -23.5608, -46.6508, 'Teste - Rua Augusta, 500 - São Paulo/SP', 150.00,
                       'pending', DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
        
        $test_stmt = $db->prepare($test_query);
        $result = $test_stmt->execute();
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Solicitação de teste criada com sucesso' : 'Erro ao criar solicitação',
            'test_created' => $result,
            'all_requests_count' => count($all_requests),
            'pending_requests_count' => count($pending_requests)
        ]);
        
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Status do sistema de viagens',
            'all_requests' => $all_requests,
            'all_requests_count' => count($all_requests),
            'pending_requests' => $pending_requests,
            'pending_requests_count' => count($pending_requests),
            'drivers' => $drivers,
            'drivers_count' => count($drivers),
            'system_settings' => $settings,
            'note' => 'Use ?action=create_test para criar uma solicitação de teste'
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