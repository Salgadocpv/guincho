<?php
/**
 * API Endpoint - Obter Configurações Públicas de Preços
 * GET /api/public/get_pricing.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Incluir dependências
    require_once '../classes/SystemSettings.php';
    
    // Obter configurações públicas de preços
    $settings = new SystemSettings();
    $pricingSettings = $settings->getByCategory('pricing', false); // apenas públicas
    
    // Extrair apenas os valores para facilitar uso no frontend
    $pricing = [];
    foreach ($pricingSettings as $key => $config) {
        $pricing[$key] = $config['value'];
    }
    
    // Garantir que temos pelo menos os valores padrão
    $defaultPricing = [
        'minimum_trip_value' => 25.00,
        'price_per_km' => 3.50,
        'base_service_fee' => 15.00,
        'emergency_multiplier' => 1.5,
        'night_shift_multiplier' => 1.3,
        'weekend_multiplier' => 1.2
    ];
    
    $pricing = array_merge($defaultPricing, $pricing);
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurações de preços obtidas com sucesso',
        'data' => $pricing
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter configurações: ' . $e->getMessage(),
        'data' => [
            'minimum_trip_value' => 25.00,
            'price_per_km' => 3.50,
            'base_service_fee' => 15.00,
            'emergency_multiplier' => 1.5,
            'night_shift_multiplier' => 1.3,
            'weekend_multiplier' => 1.2
        ]
    ]);
}
?>