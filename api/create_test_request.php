<?php
/**
 * Create Test Request - Ultra Simple
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Get service type from URL or use default
    $service_type = $_GET['service_type'] ?? 'guincho';
    $client_offer = floatval($_GET['client_offer'] ?? 100);
    
    // Insert directly with minimal validation
    $stmt = $db->prepare("
        INSERT INTO trip_requests 
        (client_id, service_type, origin_lat, origin_lng, origin_address, 
         destination_lat, destination_lng, destination_address, client_offer, 
         distance_km, estimated_duration_minutes, status, created_at, expires_at) 
        VALUES 
        (1, ?, -23.5505, -46.6333, 'Origem de Teste', 
         -23.5605, -46.6433, 'Destino de Teste', ?, 
         5.2, 15, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
    ");
    
    if ($stmt->execute([$service_type, $client_offer])) {
        $trip_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitação de teste criada!',
            'trip_request_id' => $trip_id,
            'service_type' => $service_type,
            'client_offer' => $client_offer
        ]);
    } else {
        throw new Exception('Erro ao executar query');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>