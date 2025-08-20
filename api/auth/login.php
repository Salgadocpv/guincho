<?php
/**
 * API Endpoint - Login
 * POST /api/auth/login.php
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
    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception('E-mail e senha são obrigatórios', 400);
    }
    
    // Fazer login
    $user = new User();
    $result = $user->login($data['email'], $data['password']);
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login realizado com sucesso!',
        'data' => [
            'user' => $result['user'],
            'session_token' => $result['session_token'],
            'expires_at' => $result['expires_at']
        ]
    ]);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no login: " . $e->getMessage());
    
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