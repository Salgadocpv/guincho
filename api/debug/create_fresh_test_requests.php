<?php
/**
 * Create Fresh Test Requests for Drivers to See
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';
include_once '../config/database_auto.php';

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // Get test client
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'maria.silva@teste.com' LIMIT 1");
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        throw new Exception("Test client not found");
    }
    
    $clientId = $client['id'];
    $now = new DateTime();
    $expiresAt = $now->modify('+30 minutes')->format('Y-m-d H:i:s');
    
    // Clear old expired requests first
    $db->prepare("DELETE FROM trip_requests WHERE status = 'expired' OR expires_at < NOW()")->execute();
    
    // Create 3 fresh test requests
    $testRequests = [
        [
            'service_type' => 'guincho',
            'origin_lat' => -22.9068,
            'origin_lng' => -43.1729,
            'origin_address' => 'Rua das Flores, 123 - Rio de Janeiro, RJ',
            'destination_lat' => -22.9035,
            'destination_lng' => -43.1711,
            'destination_address' => 'Avenida Atlântica, 456 - Copacabana, RJ',
            'client_offer' => 85.00,
            'distance_km' => 3.2
        ],
        [
            'service_type' => 'guincho',
            'origin_lat' => -23.5505,
            'origin_lng' => -46.6333,
            'origin_address' => 'Praça da Sé, São Paulo - SP',
            'destination_lat' => -23.5475,
            'destination_lng' => -46.6361,
            'destination_address' => 'Shopping Light - Centro, São Paulo - SP',
            'client_offer' => 45.00,
            'distance_km' => 1.8
        ],
        [
            'service_type' => 'bateria',
            'origin_lat' => -23.5629,
            'origin_lng' => -46.6544,
            'origin_address' => 'Avenida Paulista, 1000 - Bela Vista, São Paulo - SP',
            'destination_lat' => -23.5629,
            'destination_lng' => -46.6544,
            'destination_address' => 'Avenida Paulista, 1000 - Bela Vista, São Paulo - SP',
            'client_offer' => 65.00,
            'distance_km' => 0
        ]
    ];
    
    $createdRequests = [];
    
    foreach ($testRequests as $request) {
        $stmt = $db->prepare("
            INSERT INTO trip_requests 
            (client_id, service_type, origin_lat, origin_lng, origin_address, 
             destination_lat, destination_lng, destination_address, client_offer, 
             status, distance_km, estimated_duration_minutes, expires_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
        ");
        
        $estimatedDuration = max(15, ceil(($request['distance_km'] / 30) * 60));
        
        $stmt->execute([
            $clientId,
            $request['service_type'],
            $request['origin_lat'],
            $request['origin_lng'],
            $request['origin_address'],
            $request['destination_lat'],
            $request['destination_lng'],
            $request['destination_address'],
            $request['client_offer'],
            $request['distance_km'],
            $estimatedDuration,
            $expiresAt
        ]);
        
        $requestId = $db->lastInsertId();
        $createdRequests[] = [
            'id' => $requestId,
            'service_type' => $request['service_type'],
            'origin_address' => $request['origin_address'],
            'client_offer' => $request['client_offer'],
            'expires_at' => $expiresAt
        ];
    }
    
    // Verify requests were created
    $stmt = $db->prepare("
        SELECT COUNT(*) as pending_count 
        FROM trip_requests 
        WHERE status = 'pending' AND expires_at > NOW()
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Fresh test requests created successfully',
        'created_requests' => $createdRequests,
        'total_pending_now' => $result['pending_count'],
        'expires_at' => $expiresAt,
        'current_time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>