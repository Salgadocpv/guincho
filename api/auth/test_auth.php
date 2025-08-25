<?php
/**
 * Test authentication endpoint for debugging
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_tokens';
    
    if ($action === 'get_tokens') {
        // Create or get test tokens
        
        // Check if test users exist
        $driver_query = "SELECT id, name FROM users WHERE email = 'test_driver@example.com' AND user_type = 'driver' LIMIT 1";
        $driver_stmt = $db->prepare($driver_query);
        $driver_stmt->execute();
        $driver = $driver_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$driver) {
            // Create test driver
            $create_driver = "INSERT INTO users (name, email, phone, user_type, status, created_at) 
                             VALUES ('Test Driver', 'test_driver@example.com', '11999999999', 'driver', 'active', NOW())";
            $db->prepare($create_driver)->execute();
            $driver_id = $db->lastInsertId();
            $driver = ['id' => $driver_id, 'name' => 'Test Driver'];
        }
        
        $client_query = "SELECT id, name FROM users WHERE email = 'test_client@example.com' AND user_type = 'client' LIMIT 1";
        $client_stmt = $db->prepare($client_query);
        $client_stmt->execute();
        $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            // Create test client
            $create_client = "INSERT INTO users (name, email, phone, user_type, status, created_at) 
                             VALUES ('Test Client', 'test_client@example.com', '11888888888', 'client', 'active', NOW())";
            $db->prepare($create_client)->execute();
            $client_id = $db->lastInsertId();
            $client = ['id' => $client_id, 'name' => 'Test Client'];
        }
        
        // Generate test tokens (simple format for testing)
        $driver_token = 'test_driver_' . $driver['id'] . '_' . time();
        $client_token = 'test_client_' . $client['id'] . '_' . time();
        
        echo json_encode([
            'success' => true,
            'tokens' => [
                'driver' => [
                    'token' => $driver_token,
                    'user_id' => $driver['id'],
                    'user_name' => $driver['name'],
                    'user_type' => 'driver'
                ],
                'client' => [
                    'token' => $client_token,
                    'user_id' => $client['id'],
                    'user_name' => $client['name'],
                    'user_type' => 'client'
                ]
            ],
            'usage' => [
                'set_driver_token' => "localStorage.setItem('auth_token', '{$driver_token}');",
                'set_client_token' => "localStorage.setItem('auth_token', '{$client_token}');"
            ]
        ]);
        
    } elseif ($action === 'validate_token') {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $auth_header);
        
        if (empty($token)) {
            throw new Exception('Token não fornecido');
        }
        
        // Parse test token
        if (preg_match('/^test_(driver|client)_(\d+)_(\d+)$/', $token, $matches)) {
            $user_type = $matches[1];
            $user_id = (int)$matches[2];
            $timestamp = (int)$matches[3];
            
            // Check if token is not too old (24 hours)
            if (time() - $timestamp > 86400) {
                throw new Exception('Token expirado');
            }
            
            // Get user info
            $user_query = "SELECT id, name, email, user_type, status FROM users WHERE id = ? AND user_type = ? AND status = 'active'";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->execute([$user_id, $user_type]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Usuário não encontrado ou inativo');
            }
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'token_info' => [
                    'type' => 'test',
                    'user_type' => $user_type,
                    'created_at' => date('Y-m-d H:i:s', $timestamp),
                    'expires_at' => date('Y-m-d H:i:s', $timestamp + 86400)
                ]
            ]);
            
        } else {
            throw new Exception('Formato de token inválido');
        }
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'token_received' => $token ?? 'none',
            'headers' => getallheaders()
        ]
    ]);
}
?>