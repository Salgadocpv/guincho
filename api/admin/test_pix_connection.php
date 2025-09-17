<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database_local.php';
require_once '../middleware/AdminAuth.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Verificar autenticação de admin
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        throw new Exception('Token de autorização necessário');
    }
    
    $token = substr($authHeader, 7);
    $adminAuth = new AdminAuth();
    $admin = $adminAuth->validateToken($token);
    
    if (!$admin) {
        throw new Exception('Acesso negado - Admin apenas');
    }
    
    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados da requisição inválidos');
    }
    
    $provider = trim($input['provider'] ?? '');
    $sandbox = $input['sandbox'] ?? true;
    
    if (empty($provider)) {
        throw new Exception('Provedor PIX é obrigatório');
    }
    
    // Obter configurações do banco
    $database = new DatabaseLocal();
    $pdo = $database->getConnection();
    
    $settings = getPixSettings($pdo);
    
    // Testar conexão baseado no provedor
    $result = testProviderConnection($provider, $settings, $sandbox);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexão testada com sucesso',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getPixSettings($pdo) {
    $query = "SELECT setting_key, setting_value FROM system_settings 
             WHERE category LIKE 'pix_%' OR setting_key LIKE '%pix%'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

function testProviderConnection($provider, $settings, $sandbox) {
    switch ($provider) {
        case 'manual':
            return testManualProvider($settings);
            
        case 'mercadopago':
            return testMercadoPagoProvider($settings, $sandbox);
            
        case 'pagseguro':
            return testPagSeguroProvider($settings, $sandbox);
            
        case 'gerencianet':
            return testGerencianetProvider($settings, $sandbox);
            
        default:
            throw new Exception('Provedor PIX não suportado: ' . $provider);
    }
}

function testManualProvider($settings) {
    // Para modo manual, verificar apenas se as configurações básicas estão preenchidas
    $requiredFields = ['company_pix_key', 'company_name'];
    
    foreach ($requiredFields as $field) {
        if (empty($settings[$field])) {
            throw new Exception("Campo obrigatório não preenchido: {$field}");
        }
    }
    
    return [
        'provider' => 'manual',
        'status' => 'configured',
        'message' => 'Configuração manual válida. PIX funcionará em modo manual.',
        'features' => [
            'qr_code_generation' => false,
            'payment_verification' => false,
            'webhook_support' => false
        ]
    ];
}

function testMercadoPagoProvider($settings, $sandbox) {
    $accessToken = $settings['mp_access_token'] ?? '';
    
    if (empty($accessToken)) {
        throw new Exception('Access Token do MercadoPago não configurado');
    }
    
    // Testar API do MercadoPago
    $baseUrl = $sandbox ? 'https://api.mercadopago.com/sandbox' : 'https://api.mercadopago.com';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/account/settings');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Erro na autenticação com MercadoPago. Verifique o Access Token.');
    }
    
    $data = json_decode($response, true);
    
    return [
        'provider' => 'mercadopago',
        'status' => 'connected',
        'message' => 'Conexão com MercadoPago estabelecida com sucesso',
        'sandbox_mode' => $sandbox,
        'account_info' => [
            'currency' => $data['currency_id'] ?? 'BRL',
            'country' => $data['country_id'] ?? 'BR'
        ],
        'features' => [
            'qr_code_generation' => true,
            'payment_verification' => true,
            'webhook_support' => true
        ]
    ];
}

function testPagSeguroProvider($settings, $sandbox) {
    $email = $settings['ps_email'] ?? '';
    $token = $settings['ps_token'] ?? '';
    
    if (empty($email) || empty($token)) {
        throw new Exception('Email e Token do PagSeguro não configurados');
    }
    
    // Testar API do PagSeguro
    $baseUrl = $sandbox ? 'https://ws.sandbox.pagseguro.uol.com.br' : 'https://ws.riodoce.pagseguro.uol.com.br';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/sessions?email=' . urlencode($email) . '&token=' . urlencode($token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Erro na autenticação com PagSeguro. Verifique email e token.');
    }
    
    return [
        'provider' => 'pagseguro',
        'status' => 'connected',
        'message' => 'Conexão com PagSeguro estabelecida com sucesso',
        'sandbox_mode' => $sandbox,
        'features' => [
            'qr_code_generation' => true,
            'payment_verification' => true,
            'webhook_support' => true
        ]
    ];
}

function testGerencianetProvider($settings, $sandbox) {
    $clientId = $settings['gn_client_id'] ?? '';
    $clientSecret = $settings['gn_client_secret'] ?? '';
    $certificatePath = $settings['gn_certificate_path'] ?? '';
    
    if (empty($clientId) || empty($clientSecret)) {
        throw new Exception('Client ID e Client Secret da Gerencianet não configurados');
    }
    
    if (empty($certificatePath) || !file_exists($certificatePath)) {
        throw new Exception('Certificado da Gerencianet não encontrado: ' . $certificatePath);
    }
    
    // Testar API da Gerencianet
    $baseUrl = $sandbox ? 'https://api-pix-h.gerencianet.com.br' : 'https://api-pix.gerencianet.com.br';
    
    $auth = base64_encode($clientId . ':' . $clientSecret);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['grant_type' => 'client_credentials']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSLCERT, $certificatePath);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Erro na autenticação com Gerencianet. Verifique as credenciais e certificado.');
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['access_token'])) {
        throw new Exception('Erro ao obter token de acesso da Gerencianet');
    }
    
    return [
        'provider' => 'gerencianet',
        'status' => 'connected',
        'message' => 'Conexão com Gerencianet estabelecida com sucesso',
        'sandbox_mode' => $sandbox,
        'token_info' => [
            'expires_in' => $data['expires_in'] ?? 0,
            'token_type' => $data['token_type'] ?? 'Bearer'
        ],
        'features' => [
            'qr_code_generation' => true,
            'payment_verification' => true,
            'webhook_support' => true
        ]
    ];
}
?>