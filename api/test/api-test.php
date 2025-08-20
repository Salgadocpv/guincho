<?php
/**
 * Teste da API - Iguincho
 * GET /api/test/api-test.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Incluir configuração do banco
    require_once '../config/database.php';
    
    // Testar conexão com banco
    $database = new Database();
    $connection_test = $database->testConnection();
    
    // Informações do sistema
    $system_info = [
        'api_version' => '1.0.0',
        'app_name' => 'Iguincho',
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
        'endpoints' => [
            'auth' => [
                'POST /api/auth/register-client.php' => 'Cadastro de clientes',
                'POST /api/auth/register-driver.php' => 'Cadastro de guincheiros',
                'POST /api/auth/login.php' => 'Login de usuários'
            ],
            'test' => [
                'GET /api/test/api-test.php' => 'Teste da API'
            ]
        ]
    ];
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'API Iguincho funcionando corretamente!',
        'data' => [
            'system' => $system_info,
            'database' => $connection_test
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no teste da API: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no teste da API: ' . $e->getMessage(),
        'error_code' => 500
    ]);
}
?>