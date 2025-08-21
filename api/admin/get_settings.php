<?php
/**
 * API Endpoint - Obter Configurações do Sistema
 * GET /api/admin/get_settings.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Verificar se é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Incluir dependências
    require_once '../middleware/AdminAuth.php';
    require_once '../classes/SystemSettings.php';
    
    // Verificar autenticação de admin
    $adminData = AdminAuth::requireAdmin();
    
    // Obter configurações
    $settings = new SystemSettings();
    $allSettings = $settings->getAll(true); // incluir configurações privadas
    
    // Organizar por categoria
    $categorizedSettings = [];
    foreach ($allSettings as $key => $config) {
        $category = $config['category'];
        if (!isset($categorizedSettings[$category])) {
            $categorizedSettings[$category] = [];
        }
        $categorizedSettings[$category][$key] = $config;
    }
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurações obtidas com sucesso',
        'data' => [
            'settings' => $allSettings,
            'categorized' => $categorizedSettings
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter configurações: ' . $e->getMessage()
    ]);
}
?>