<?php
/**
 * Authentication Middleware
 * Validates JWT tokens and user sessions
 */

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader)) {
        return ['success' => false, 'message' => 'Token de autorização não fornecido'];
    }
    
    // Extract token from "Bearer TOKEN" format
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        $token = $authHeader;
    }
    
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token inválido'];
    }
    
    try {
        // For now, use simple session validation
        // In production, implement proper JWT validation
        
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
            return ['success' => false, 'message' => 'Token inválido ou expirado'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Usuário inativo'];
        }
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        error_log('Auth error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno de autenticação'];
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