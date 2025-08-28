<?php
/**
 * Create Trip Request API
 * Creates a new trip request from client
 */

// Suppress notices and warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripRequest.php';
include_once '../classes/TripNotification.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Only clients can create trip requests
if ($user['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas clientes podem solicitar viagens']);
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Log received data for debugging
error_log('Received trip request data: ' . json_encode($data));

// Validate required fields with more detailed error messages
$missing_fields = [];
if (empty($data->service_type)) $missing_fields[] = 'service_type';
if (empty($data->origin_lat) && $data->origin_lat !== 0) $missing_fields[] = 'origin_lat';
if (empty($data->origin_lng) && $data->origin_lng !== 0) $missing_fields[] = 'origin_lng';
if (empty($data->origin_address)) $missing_fields[] = 'origin_address';
if (empty($data->destination_lat) && $data->destination_lat !== 0) $missing_fields[] = 'destination_lat';
if (empty($data->destination_lng) && $data->destination_lng !== 0) $missing_fields[] = 'destination_lng';
if (empty($data->destination_address)) $missing_fields[] = 'destination_address';
if (empty($data->client_offer) && $data->client_offer !== 0) $missing_fields[] = 'client_offer';

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Campos obrigatórios ausentes: ' . implode(', ', $missing_fields),
        'missing_fields' => $missing_fields,
        'received_data' => $data
    ]);
    exit();
}

// Validate minimum offer
$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'minimum_trip_value'");
$stmt->execute();
$min_value = $stmt->fetch(PDO::FETCH_ASSOC);
$minimum_offer = $min_value ? (float)$min_value['setting_value'] : 25.00;

if ((float)$data->client_offer < $minimum_offer) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => "Oferta mínima é R$ " . number_format($minimum_offer, 2, ',', '.')
    ]);
    exit();
}

try {
    // Create trip request
    $trip_request = new TripRequest($db);
    
    $trip_request->client_id = $user['id'];
    $trip_request->service_type = $data->service_type;
    $trip_request->origin_lat = (float)$data->origin_lat;
    $trip_request->origin_lng = (float)$data->origin_lng;
    $trip_request->origin_address = $data->origin_address;
    $trip_request->destination_lat = (float)$data->destination_lat;
    $trip_request->destination_lng = (float)$data->destination_lng;
    $trip_request->destination_address = $data->destination_address;
    $trip_request->client_offer = (float)$data->client_offer;
    
    // Calculate distance and estimated duration
    $trip_request->distance_km = TripRequest::calculateDistance(
        $trip_request->origin_lat, $trip_request->origin_lng,
        $trip_request->destination_lat, $trip_request->destination_lng
    );
    
    // Estimate duration (assuming 30 km/h average speed in city)
    $trip_request->estimated_duration_minutes = ceil(($trip_request->distance_km / 30) * 60);
    
    // Validate trip request
    $validation_errors = $trip_request->validate();
    if (!empty($validation_errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Dados inválidos',
            'errors' => $validation_errors
        ]);
        exit();
    }
    
    if ($trip_request->create()) {
        // Save questionnaire answers if provided
        if (isset($data->questionnaire_answers) && is_array($data->questionnaire_answers)) {
            try {
                foreach ($data->questionnaire_answers as $answer) {
                    if (isset($answer->question_id, $answer->question_text, $answer->option_id, $answer->option_text)) {
                        $answer_stmt = $db->prepare("
                            INSERT INTO questionnaire_answers 
                            (trip_request_id, question_id, question_text, option_id, option_text) 
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                option_id = VALUES(option_id),
                                option_text = VALUES(option_text),
                                updated_at = CURRENT_TIMESTAMP
                        ");
                        
                        $answer_stmt->execute([
                            $trip_request->id,
                            $answer->question_id,
                            $answer->question_text,
                            $answer->option_id,
                            $answer->option_text
                        ]);
                    }
                }
            } catch (Exception $answer_error) {
                error_log('Error saving questionnaire answers: ' . $answer_error->getMessage());
                // Don't fail the trip creation if answers fail to save
            }
        }
        
        // Get nearby drivers to notify
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'driver_search_radius_km'");
        $stmt->execute();
        $radius_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $search_radius = $radius_result ? (float)$radius_result['setting_value'] : 25.0;
        
        // Find nearby drivers (simplified query for now - will calculate distance later)
        $query = "SELECT d.id as driver_id, d.user_id, u.full_name
                  FROM drivers d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.approval_status = 'approved' 
                    AND u.status = 'active'
                    AND (d.specialty = ? OR d.specialty = 'todos')
                  LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$data->service_type]);
        
        $nearby_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create notifications for nearby drivers
        if (class_exists('TripNotification')) {
            $notification = new TripNotification($db);
            
            foreach ($nearby_drivers as $driver) {
                $notification->create(
                    $driver['user_id'],
                    'new_request',
                    'Nova Solicitação de Serviço',
                    "Nova solicitação de {$data->service_type} - R$ " . number_format($data->client_offer, 2, ',', '.'),
                    $trip_request->id,
                    null,
                    [
                        'service_type' => $data->service_type,
                        'origin_address' => $data->origin_address,
                        'destination_address' => $data->destination_address,
                        'client_offer' => $data->client_offer,
                        'distance_km' => $trip_request->distance_km
                    ]
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitação criada com sucesso',
            'data' => [
                'trip_request_id' => $trip_request->id,
                'distance_km' => $trip_request->distance_km,
                'estimated_duration_minutes' => $trip_request->estimated_duration_minutes,
                'nearby_drivers_count' => count($nearby_drivers)
            ]
        ]);
        
    } else {
        throw new Exception('Erro ao criar solicitação');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>