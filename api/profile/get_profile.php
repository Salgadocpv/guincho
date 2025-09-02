<?php
/**
 * Get User Profile API
 * Returns user profile data based on user type
 */

error_reporting(E_ERROR | E_PARSE);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    if ($user['user_type'] === 'client') {
        // Get client profile data
        $query = "SELECT u.id, u.full_name, u.cpf, u.birth_date, u.phone, u.whatsapp, u.email, u.created_at,
                         cv.license_plate, cv.vehicle_brand, cv.vehicle_model, cv.vehicle_year, cv.vehicle_color
                  FROM users u
                  LEFT JOIN client_vehicles cv ON u.id = cv.client_id
                  WHERE u.id = ? AND u.user_type = 'client'";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Perfil não encontrado']);
            exit();
        }
        
        // Format response
        $response_data = [
            'user_type' => 'client',
            'profile' => [
                'fullName' => $profile['full_name'],
                'cpf' => $profile['cpf'],
                'birthDate' => $profile['birth_date'],
                'phone' => $profile['phone'],
                'whatsapp' => $profile['whatsapp'],
                'email' => $profile['email'],
                'licensePlate' => $profile['license_plate'],
                'vehicleBrand' => $profile['vehicle_brand'],
                'vehicleModel' => $profile['vehicle_model'],
                'vehicleYear' => $profile['vehicle_year'],
                'vehicleColor' => $profile['vehicle_color'],
                'memberSince' => date('Y-m-d', strtotime($profile['created_at']))
            ]
        ];
        
    } elseif ($user['user_type'] === 'driver') {
        // Get driver profile data
        $query = "SELECT u.id, u.full_name, u.cpf, u.birth_date, u.phone, u.whatsapp, u.email, u.created_at,
                         d.cnh, d.cnh_category, d.experience, d.specialty, d.work_region, d.availability,
                         d.truck_plate, d.truck_brand, d.truck_model, d.truck_year, d.truck_capacity,
                         d.status, d.verified_at, d.rating
                  FROM users u
                  LEFT JOIN drivers d ON u.id = d.user_id
                  WHERE u.id = ? AND u.user_type = 'driver'";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Perfil não encontrado']);
            exit();
        }
        
        // Format response
        $response_data = [
            'user_type' => 'driver',
            'profile' => [
                'fullName' => $profile['full_name'],
                'cpf' => $profile['cpf'],
                'birthDate' => $profile['birth_date'],
                'cnh' => $profile['cnh'],
                'cnhCategory' => $profile['cnh_category'],
                'phone' => $profile['phone'],
                'whatsapp' => $profile['whatsapp'],
                'email' => $profile['email'],
                'experience' => $profile['experience'],
                'specialty' => $profile['specialty'],
                'workRegion' => $profile['work_region'],
                'availability' => $profile['availability'],
                'truckPlate' => $profile['truck_plate'],
                'truckBrand' => $profile['truck_brand'],
                'truckModel' => $profile['truck_model'],
                'truckYear' => $profile['truck_year'],
                'truckCapacity' => $profile['truck_capacity'],
                'status' => $profile['status'],
                'verifiedAt' => $profile['verified_at'],
                'rating' => $profile['rating'],
                'memberSince' => date('Y-m-d', strtotime($profile['created_at']))
            ]
        ];
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil carregado com sucesso',
        'data' => $response_data
    ]);
    
} catch (Exception $e) {
    error_log("Error getting profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>