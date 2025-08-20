<?php
/**
 * API Endpoint - Cadastro de Cliente
 * POST /api/auth/register-client.php
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
    
    // Obter dados JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos', 400);
    }
    
    // Validar campos obrigatórios
    $required_fields = [
        'fullName', 'cpf', 'birthDate', 'phone', 'email', 'password', 
        'licensePlate', 'vehicleBrand', 'vehicleModel', 'vehicleYear', 
        'vehicleColor', 'termsAccepted'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Campo obrigatório: {$field}", 400);
        }
    }
    
    // Mapear dados para formato do banco
    $userData = [
        'full_name' => trim($data['fullName']),
        'cpf' => $data['cpf'],
        'birth_date' => $data['birthDate'],
        'phone' => $data['phone'],
        'whatsapp' => $data['whatsapp'] ?? $data['phone'],
        'email' => $data['email'],
        'password' => $data['password'],
        'license_plate' => $data['licensePlate'],
        'vehicle_brand' => $data['vehicleBrand'],
        'vehicle_model' => $data['vehicleModel'],
        'vehicle_year' => (int)$data['vehicleYear'],
        'vehicle_color' => $data['vehicleColor'],
        'terms_accepted' => (bool)$data['termsAccepted'],
        'marketing_accepted' => (bool)($data['marketingAccepted'] ?? false)
    ];
    
    // Criar usuário
    $user = new User();
    $result = $user->registerClient($userData);
    
    // Resposta de sucesso
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso!',
        'data' => [
            'user_id' => $result['user_id'],
            'message' => 'Bem-vindo ao Iguincho! Seu cadastro foi concluído.'
        ]
    ]);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no cadastro de cliente: " . $e->getMessage());
    
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