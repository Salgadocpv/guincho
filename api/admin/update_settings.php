<?php
/**
 * API Endpoint - Atualizar Configurações do Sistema
 * POST /api/admin/update_settings.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Obter dados JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['settings'])) {
        throw new Exception('Dados de configurações inválidos', 400);
    }
    
    // Validar tipos de dados
    $validatedSettings = [];
    foreach ($data['settings'] as $key => $value) {
        // Validações específicas para campos críticos
        switch ($key) {
            case 'minimum_trip_value':
            case 'price_per_km':
            case 'base_service_fee':
                if (!is_numeric($value) || $value < 0) {
                    throw new Exception("Valor inválido para {$key}. Deve ser um número positivo.", 400);
                }
                $validatedSettings[$key] = (float)$value;
                break;
                
            case 'emergency_multiplier':
            case 'night_shift_multiplier':
            case 'weekend_multiplier':
                if (!is_numeric($value) || $value < 1) {
                    throw new Exception("Multiplicador {$key} deve ser maior ou igual a 1.", 400);
                }
                $validatedSettings[$key] = (float)$value;
                break;
                
            case 'max_distance_km':
                if (!is_numeric($value) || $value < 1 || $value > 200) {
                    throw new Exception("Distância máxima deve estar entre 1 e 200 km.", 400);
                }
                $validatedSettings[$key] = (int)$value;
                break;
                
            case 'maintenance_mode':
                $validatedSettings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
                
            default:
                // Para outros campos, manter o valor como string
                $validatedSettings[$key] = (string)$value;
                break;
        }
    }
    
    // Atualizar configurações
    $settings = new SystemSettings();
    $result = $settings->setMultiple($validatedSettings, $adminData['id']);
    
    if ($result) {
        // Log da ação administrativa
        AdminAuth::logAdminAction('update_settings', [
            'updated_settings' => array_keys($validatedSettings),
            'changes' => $validatedSettings
        ]);
        
        // Resposta de sucesso
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Configurações atualizadas com sucesso!',
            'data' => [
                'updated_count' => count($validatedSettings),
                'updated_keys' => array_keys($validatedSettings)
            ]
        ]);
    } else {
        throw new Exception('Erro ao salvar configurações no banco de dados', 500);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar configurações: ' . $e->getMessage()
    ]);
}
?>