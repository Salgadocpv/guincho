<?php
/**
 * Simple Profile API - For testing without full auth
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    include_once '../config/database_auto.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // For testing, use a sample user
    $user_type = $_GET['type'] ?? 'client'; // client or driver
    
    if ($user_type === 'client') {
        // Get first client
        $query = "SELECT u.id, u.full_name, u.cpf, u.birth_date, u.phone, u.whatsapp, u.email, u.created_at,
                         cv.license_plate, cv.vehicle_brand, cv.vehicle_model, cv.vehicle_year, cv.vehicle_color
                  FROM users u
                  LEFT JOIN client_vehicles cv ON u.id = cv.client_id
                  WHERE u.user_type = 'client'
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            // Create a test client profile
            $profile = [
                'full_name' => 'João Silva Santos',
                'cpf' => '123.456.789-10',
                'birth_date' => '1990-05-15',
                'phone' => '(11) 98765-4321',
                'whatsapp' => '(11) 98765-4321',
                'email' => 'joao.silva@email.com',
                'license_plate' => 'ABC-1234',
                'vehicle_brand' => 'toyota',
                'vehicle_model' => 'corolla',
                'vehicle_year' => '2020',
                'vehicle_color' => 'branco'
            ];
        }
        
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
                'memberSince' => date('Y-m-d')
            ]
        ];
        
    } else {
        // Get first driver
        $query = "SELECT u.id, u.full_name, u.cpf, u.birth_date, u.phone, u.whatsapp, u.email, u.created_at,
                         d.cnh, d.cnh_category, d.experience, d.specialty, d.work_region, d.availability,
                         d.truck_plate, d.truck_brand, d.truck_model, d.truck_year, d.truck_capacity,
                         d.approval_status, d.approval_date, d.rating
                  FROM users u
                  LEFT JOIN drivers d ON u.id = d.user_id
                  WHERE u.user_type = 'driver'
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            // Create test driver profile
            $profile = [
                'full_name' => 'Carlos Eduardo Silva',
                'cpf' => '987.654.321-00',
                'birth_date' => '1985-03-20',
                'cnh' => '12345678901',
                'cnh_category' => 'B',
                'phone' => '(11) 99887-6543',
                'whatsapp' => '(11) 99887-6543',
                'email' => 'carlos.guincheiro@email.com',
                'experience' => '5-10',
                'specialty' => 'carros',
                'work_region' => 'São Paulo - Zona Sul, Centro',
                'availability' => '24h',
                'truck_plate' => 'GUN-2023',
                'truck_brand' => 'Ford',
                'truck_model' => 'Cargo 816',
                'truck_year' => '2018',
                'truck_capacity' => 'media',
                'approval_status' => 'approved'
            ];
        }
        
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
                'status' => $profile['approval_status'],
                'verifiedAt' => $profile['approval_date'] ?? null,
                'rating' => $profile['rating'] ?? '0.00',
                'memberSince' => date('Y-m-d')
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil carregado com sucesso',
        'data' => $response_data
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>