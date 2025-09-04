<?php
/**
 * Ultra-Simple Trip Requests API
 * Shows ALL pending requests to drivers - no complex filters
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Ultra-simple query - get ALL active trip requests with LEFT JOIN to avoid missing users
    $query = "SELECT tr.*, 
                     COALESCE(u.full_name, 'Cliente') as client_name, 
                     COALESCE(u.phone, 'N/A') as client_phone
              FROM trip_requests tr
              LEFT JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending' 
                AND (tr.expires_at IS NULL OR tr.expires_at > NOW())
              ORDER BY tr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each request with minimal processing
    $processed_requests = [];
    foreach ($requests as $request) {
        $processed_requests[] = [
            'id' => $request['id'],
            'service_type' => $request['service_type'],
            'client_name' => $request['client_name'],
            'client_phone' => $request['client_phone'],
            'origin_address' => $request['origin_address'],
            'destination_address' => $request['destination_address'],
            'origin_lat' => $request['origin_lat'],
            'origin_lng' => $request['origin_lng'],
            'destination_lat' => $request['destination_lat'],
            'destination_lng' => $request['destination_lng'],
            'client_offer' => $request['client_offer'],
            'distance_km' => $request['distance_km'],
            'estimated_duration_minutes' => $request['estimated_duration_minutes'],
            'created_at' => $request['created_at'],
            'expires_at' => $request['expires_at'],
            'status' => $request['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processed_requests,
        'count' => count($processed_requests),
        'message' => count($processed_requests) . ' solicitações encontradas'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>