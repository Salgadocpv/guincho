<?php
/**
 * API Endpoint - Cadastro de Parceiro
 * POST /api/auth/register-partner.php
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
    require_once '../classes/Partner.php';
    
    // Obter dados JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Dados JSON inválidos', 400);
    }
    
    // Validar campos obrigatórios do usuário
    $required_user_fields = [
        'ownerName', 'ownerCpf', 'whatsapp', 'email', 
        'password', 'termsAccepted'
    ];
    
    foreach ($required_user_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Campo obrigatório: {$field}", 400);
        }
    }
    
    // Validar campos obrigatórios do parceiro
    $required_partner_fields = [
        'businessType', 'businessName', 'cnpj', 'address', 
        'zipCode', 'city', 'state', 'phone', 'hours',
        'partnerTerms', 'dataSharing'
    ];
    
    foreach ($required_partner_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Campo obrigatório: {$field}", 400);
        }
    }
    
    // Validar tipo de negócio
    $validBusinessTypes = ['lava-rapido', 'mecanica', 'auto-eletrica', 'posto-combustivel', 'outros'];
    if (!in_array($data['businessType'], $validBusinessTypes)) {
        throw new Exception('Tipo de negócio inválido', 400);
    }
    
    // Se o tipo é "outros", validar campo customBusinessType
    if ($data['businessType'] === 'outros') {
        if (!isset($data['customBusinessType']) || trim($data['customBusinessType']) === '') {
            throw new Exception('Especifique o tipo de estabelecimento', 400);
        }
        if (strlen(trim($data['customBusinessType'])) < 3) {
            throw new Exception('Tipo de estabelecimento deve ter pelo menos 3 caracteres', 400);
        }
    }
    
    // Mapear dados do usuário
    $userData = [
        'full_name' => trim($data['ownerName']),
        'cpf' => $data['ownerCpf'],
        'birth_date' => null, // Partners não precisam de data de nascimento
        'phone' => $data['phone'],
        'whatsapp' => $data['whatsapp'],
        'email' => $data['email'],
        'password' => $data['password'],
        'terms_accepted' => (bool)$data['termsAccepted']
    ];
    
    // Mapear dados do parceiro
    $partnerData = [
        'business_type' => $data['businessType'],
        'business_name' => $data['businessName'],
        'cnpj' => $data['cnpj'],
        'address' => $data['address'],
        'zip_code' => $data['zipCode'],
        'city' => $data['city'],
        'state' => $data['state'],
        'phone' => $data['phone'],
        'hours' => $data['hours'],
        'owner_name' => trim($data['ownerName']),
        'owner_cpf' => $data['ownerCpf'],
        'whatsapp' => $data['whatsapp'],
        'partner_terms_accepted' => (bool)$data['partnerTerms'],
        'data_sharing_authorized' => (bool)$data['dataSharing']
    ];
    
    // Adicionar tipo de negócio personalizado se for "outros"
    if ($data['businessType'] === 'outros') {
        $partnerData['custom_business_type'] = trim($data['customBusinessType']);
        $partnerData['business_type_display'] = trim($data['customBusinessType']);
    } else {
        $partnerData['business_type_display'] = $data['businessTypeDisplay'] ?? $data['businessType'];
    }
    
    // Validar dados do parceiro
    $partner = new Partner();
    $validationErrors = $partner->validatePartnerData($partnerData);
    
    if (!empty($validationErrors)) {
        throw new Exception(implode(', ', $validationErrors), 400);
    }
    
    // Verificar se CNPJ já existe
    if ($partner->cnpjExists($partnerData['cnpj'])) {
        throw new Exception('CNPJ já cadastrado no sistema', 400);
    }
    
    // Criar usuário e parceiro
    $user = new User();
    $result = $user->registerPartner($userData, $partnerData);
    
    // Resposta de sucesso
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro enviado com sucesso!',
        'data' => [
            'user_id' => $result['user_id'],
            'partner_id' => $result['partner_id'],
            'message' => 'Seu estabelecimento foi enviado para análise. Você receberá um e-mail em até 72 horas com o resultado.',
            'status' => 'pending_approval',
            'business_type' => $partnerData['business_type']
        ]
    ]);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no cadastro de parceiro: " . $e->getMessage());
    
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