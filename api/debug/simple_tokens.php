<?php
/**
 * Sistema simples de tokens para teste sem dependência de banco
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $action = $_GET['action'] ?? 'get_tokens';
    
    if ($action === 'get_tokens') {
        // Criar tokens simples sem necessidade de banco
        $timestamp = time();
        
        // IDs fixos para teste
        $driver_id = 1;
        $client_id = 2;
        
        $driver_token = "test_driver_{$driver_id}_{$timestamp}";
        $client_token = "test_client_{$client_id}_{$timestamp}";
        
        echo json_encode([
            'success' => true,
            'message' => 'Tokens de teste gerados (sem banco de dados)',
            'tokens' => [
                'driver' => [
                    'token' => $driver_token,
                    'user_id' => $driver_id,
                    'user_name' => 'Test Driver',
                    'user_type' => 'driver'
                ],
                'client' => [
                    'token' => $client_token,
                    'user_id' => $client_id,
                    'user_name' => 'Test Client',
                    'user_type' => 'client'
                ]
            ],
            'usage' => [
                'driver_command' => "localStorage.setItem('auth_token', '{$driver_token}');",
                'client_command' => "localStorage.setItem('auth_token', '{$client_token}');",
                'note' => 'Execute estes comandos no console do navegador'
            ],
            'debug' => [
                'timestamp' => $timestamp,
                'date' => date('Y-m-d H:i:s', $timestamp),
                'expires_in' => '24 horas'
            ]
        ], JSON_PRETTY_PRINT);
        
    } elseif ($action === 'validate') {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $auth_header);
        
        if (preg_match('/^test_(driver|client)_(\d+)_(\d+)$/', $token, $matches)) {
            $user_type = $matches[1];
            $user_id = (int)$matches[2];
            $timestamp = (int)$matches[3];
            
            $is_valid = (time() - $timestamp) < 86400; // 24 horas
            
            echo json_encode([
                'success' => $is_valid,
                'message' => $is_valid ? 'Token válido' : 'Token expirado',
                'token_info' => [
                    'user_type' => $user_type,
                    'user_id' => $user_id,
                    'created_at' => date('Y-m-d H:i:s', $timestamp),
                    'age_hours' => round((time() - $timestamp) / 3600, 1),
                    'expires_at' => date('Y-m-d H:i:s', $timestamp + 86400)
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Formato de token inválido',
                'received_token' => $token
            ]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>