<?php
/**
 * API Endpoint - Gerenciamento de Configurações do Sistema
 * GET/POST /api/admin/settings.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    require_once '../classes/SystemSettings.php';
    require_once '../classes/User.php';
    
    // Verificar autenticação e permissão de admin
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $userData = null;
    
    // Em produção, implementar verificação de token JWT
    // Por enquanto, verificar se o usuário está logado via session ou header
    if (!$authHeader && !isset($_SESSION['user_id'])) {
        throw new Exception('Acesso não autorizado', 401);
    }
    
    // Simulação de verificação de admin (implementar verificação real)
    $isAdmin = true; // Substituir por verificação real do token/session
    
    if (!$isAdmin) {
        throw new Exception('Acesso negado. Apenas administradores podem acessar esta área.', 403);
    }
    
    $systemSettings = new SystemSettings();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obter configurações
        $settings = $systemSettings->getAll(true); // Include private settings for admin
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $settings
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Atualizar configurações
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['settings'])) {
            throw new Exception('Dados inválidos', 400);
        }
        
        $settings = $data['settings'];
        $updatedBy = $data['updated_by'] ?? null;
        
        // Validar configurações antes de salvar
        $validationErrors = validateSettings($settings);
        if (!empty($validationErrors)) {
            throw new Exception('Dados inválidos: ' . implode(', ', $validationErrors), 400);
        }
        
        // Atualizar configurações
        $result = $systemSettings->setMultiple($settings, $updatedBy);
        
        if ($result) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Configurações atualizadas com sucesso',
                'updated_count' => count($settings)
            ]);
        } else {
            throw new Exception('Erro ao atualizar configurações', 500);
        }
        
    } else {
        throw new Exception('Método não permitido', 405);
    }
    
} catch (Exception $e) {
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

/**
 * Validar configurações antes de salvar
 */
function validateSettings($settings) {
    $errors = [];
    
    foreach ($settings as $key => $value) {
        switch ($key) {
            case 'service_commission':
                if (!is_numeric($value) || $value < 0 || $value > 50) {
                    $errors[] = 'Comissão deve estar entre 0% e 50%';
                }
                break;
                
            case 'max_driver_radius':
                if (!is_numeric($value) || $value < 1 || $value > 200) {
                    $errors[] = 'Raio máximo deve estar entre 1km e 200km';
                }
                break;
                
            case 'min_service_price':
            case 'max_service_price':
                if (!is_numeric($value) || $value < 0) {
                    $errors[] = 'Preços devem ser valores positivos';
                }
                break;
                
            case 'support_email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'E-mail de suporte inválido';
                }
                break;
                
            case 'app_name':
                if (strlen(trim($value)) < 2) {
                    $errors[] = 'Nome da aplicação muito curto';
                }
                break;
        }
    }
    
    // Validar se preço mínimo é menor que máximo
    if (isset($settings['min_service_price']) && isset($settings['max_service_price'])) {
        if ($settings['min_service_price'] >= $settings['max_service_price']) {
            $errors[] = 'Preço mínimo deve ser menor que o preço máximo';
        }
    }
    
    return $errors;
}
?>