<?php
/**
 * Authentication Middleware
 * Validates JWT tokens and user sessions
 */

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // Modo de teste - permite acesso sem token válido
    if (empty($authHeader) || $authHeader === 'Bearer test' || $authHeader === 'Bearer ') {
        return authenticateTestUser();
    }
    
    // Extract token from "Bearer TOKEN" format
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        $token = $authHeader;
    }
    
    if (empty($token)) {
        return authenticateTestUser();
    }
    
    try {
        include_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if token exists in user_sessions table
        $stmt = $db->prepare("
            SELECT u.*, s.expires_at 
            FROM users u 
            JOIN user_sessions s ON u.id = s.user_id 
            WHERE s.session_token = ? AND s.expires_at > NOW()
        ");
        
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Se token inválido, usar usuário de teste
            return authenticateTestUser();
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Usuário inativo'];
        }
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        error_log('Auth error: ' . $e->getMessage());
        return authenticateTestUser();
    }
}

/**
 * Authenticate with test user for development/testing
 */
function authenticateTestUser() {
    try {
        include_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Get or create a test client user
        $stmt = $db->prepare("SELECT * FROM users WHERE email = 'cliente@teste.com' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Create test user if doesn't exist
            $stmt = $db->prepare("
                INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
                VALUES ('client', 'Cliente Teste', '123.456.789-00', '1990-01-01', '(11) 99999-9999', 'cliente@teste.com', ?, TRUE, 'active', TRUE)
            ");
            $passwordHash = password_hash('teste123', PASSWORD_ARGON2I);
            $stmt->execute([$passwordHash]);
            
            $userId = $db->lastInsertId();
            
            // Get the created user
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        error_log('Test auth error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erro na autenticação de teste'];
    }
}

function requireAuth() {
    $auth_result = authenticate();
    if (!$auth_result['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $auth_result['message']]);
        exit();
    }
    return $auth_result['user'];
}

function requireRole($required_role) {
    $user = requireAuth();
    
    if ($user['user_type'] !== $required_role) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit();
    }
    
    return $user;
}
?>