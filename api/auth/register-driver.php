<?php
/**
 * API Endpoint - Cadastro de Guincheiro
 * POST /api/auth/register-driver.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Incluir dependências
    require_once '../classes/User.php';
    require_once '../classes/Driver.php';
    
    // Obter dados JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos', 400);
    }
    
    // Validar campos obrigatórios do usuário
    $required_user_fields = [
        'fullName', 'cpf', 'birthDate', 'phone', 'whatsapp', 'email', 
        'password', 'termsAccepted'
    ];
    
    foreach ($required_user_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Campo obrigatório: {$field}", 400);
        }
    }
    
    // Validar campos obrigatórios do guincheiro
    $required_driver_fields = [
        'cnh', 'cnhCategory', 'experience', 'specialty', 'workRegion', 
        'availability', 'truckPlate', 'truckBrand', 'truckModel', 
        'truckYear', 'truckCapacity', 'professionalTerms', 'backgroundCheck'
    ];
    
    foreach ($required_driver_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Campo obrigatório: {$field}", 400);
        }
    }
    
    // Mapear dados do usuário
    $userData = [
        'full_name' => trim($data['fullName']),
        'cpf' => $data['cpf'],
        'birth_date' => $data['birthDate'],
        'phone' => $data['phone'],
        'whatsapp' => $data['whatsapp'],
        'email' => $data['email'],
        'password' => $data['password'],
        'terms_accepted' => (bool)$data['termsAccepted']
    ];
    
    // Mapear dados do guincheiro
    $driverData = [
        'cnh' => $data['cnh'],
        'cnh_category' => $data['cnhCategory'],
        'experience' => $data['experience'],
        'specialty' => $data['specialty'],
        'work_region' => $data['workRegion'],
        'availability' => $data['availability'],
        'truck_plate' => $data['truckPlate'],
        'truck_brand' => $data['truckBrand'],
        'truck_model' => $data['truckModel'],
        'truck_year' => (int)$data['truckYear'],
        'truck_capacity' => $data['truckCapacity'],
        'professional_terms_accepted' => (bool)$data['professionalTerms'],
        'background_check_authorized' => (bool)$data['backgroundCheck']
    ];
    
    // Criar usuário e guincheiro
    $user = new User();
    $result = $user->registerDriver($userData, $driverData);
    
    // Resposta de sucesso
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro enviado com sucesso!',
        'data' => [
            'user_id' => $result['user_id'],
            'driver_id' => $result['driver_id'],
            'message' => 'Seu cadastro foi enviado para análise. Você receberá um e-mail em até 48 horas com o resultado.',
            'status' => 'pending_approval'
        ]
    ]);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no cadastro de guincheiro: " . $e->getMessage());
    
    // Determinar código de status HTTP
    $status_code = $e->getCode() ?: 500;
    if ($status_code < 100 || $status_code > 599) {
        $status_code = 500;
    }
    
    http_response_code($status_code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $status_code
    ]);
}
?>