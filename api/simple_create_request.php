<?php
/**
 * Ultra-Simple Create Request API
 * Creates a trip request immediately without complex validations
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
}

include_once 'config/database.php';

// Get data from POST or GET for testing
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If no JSON data, try to get from URL parameters (for testing)
if (empty($data)) {
    $data = $_GET;
}

// Set defaults for testing if not provided
$service_type = $data['service_type'] ?? 'guincho';
$client_offer = $data['client_offer'] ?? 100;
$origin_lat = $data['origin_lat'] ?? -23.5505;
$origin_lng = $data['origin_lng'] ?? -46.6333;
$destination_lat = $data['destination_lat'] ?? -23.5605;
$destination_lng = $data['destination_lng'] ?? -46.6433;
$origin_address = $data['origin_address'] ?? 'São Paulo, SP - Origem';
$destination_address = $data['destination_address'] ?? 'São Paulo, SP - Destino';

$database = new Database();
$db = $database->getConnection();

try {
    // First check if user exists, if not create a test client
    $client_id = 1; // Default test client
    $check_user = $db->prepare("SELECT id FROM users WHERE id = ?");
    $check_user->execute([$client_id]);
    
    if (!$check_user->fetch()) {
        // Create test client user
        $create_user = $db->prepare("INSERT INTO users (id, full_name, email, phone, user_type, status, password) VALUES (?, ?, ?, ?, 'client', 'active', ?)");
        $create_user->execute([
            $client_id,
            'Cliente Teste',
            'cliente@teste.com',
            '(11) 99999-9999',
            password_hash('123456', PASSWORD_DEFAULT)
        ]);
    }
    
    // Create a simple trip request
    $query = "INSERT INTO trip_requests 
              (client_id, service_type, origin_lat, origin_lng, origin_address, 
               destination_lat, destination_lng, destination_address, client_offer, 
               distance_km, estimated_duration_minutes, status, created_at, expires_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE))";
    
    // Use client_id = 1 as default, or from data if provided
    $client_id = $data['client_id'] ?? 1;
    
    // Calculate simple distance (Haversine formula)
    $distance_km = calculateDistance($origin_lat, $origin_lng, $destination_lat, $destination_lng);
    $estimated_minutes = ceil(($distance_km / 30) * 60); // 30 km/h average speed
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $client_id,
        $service_type,
        $origin_lat,
        $origin_lng,
        $origin_address,
        $destination_lat,
        $destination_lng,
        $destination_address,
        $client_offer,
        $distance_km,
        $estimated_minutes
    ]);
    
    if ($result) {
        $trip_request_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitação criada com sucesso!',
            'data' => [
                'trip_request_id' => $trip_request_id,
                'service_type' => $service_type,
                'client_offer' => $client_offer,
                'distance_km' => round($distance_km, 2),
                'estimated_minutes' => $estimated_minutes,
                'origin_address' => $origin_address,
                'destination_address' => $destination_address
            ]
        ]);
    } else {
        throw new Exception('Erro ao inserir no banco de dados');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar solicitação: ' . $e->getMessage()
    ]);
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // Earth radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}
?>