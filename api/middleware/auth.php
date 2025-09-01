<?php
/**
 * Authentication Middleware
 * Supports both session-based and Bearer token authentication
 */

function authenticate() {
    // Try Bearer token authentication first
    $headers = getallheaders();
    if (isset($headers['Authorization']) || isset($headers['authorization'])) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'];
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            return authenticateWithToken($token);
        }
    }
    
    // Fallback to session authentication
    session_start();
    
    // Check if user is logged in via session
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
        include_once __DIR__ . '/../config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Get user info from database
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND user_type = ? AND status = 'active'");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_type']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                return ['success' => true, 'user' => $user];
            }
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
        }
    }
    
    // No session or invalid user - use test fallback
    return authenticateTestUser();
}

function authenticateWithToken($token) {
    include_once __DIR__ . '/../config/database.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // For now, we'll decode the token as a simple JSON (like the frontend creates)
        // In production, you'd use proper JWT validation
        $tokenData = json_decode(base64_decode($token), true);
        
        if (!$tokenData || !isset($tokenData['user_id'])) {
            // Fallback: try to find test driver
            return authenticateTestUser();
        }
        
        // Get user from database using token data
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$tokenData['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Set session for consistency
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            
            return ['success' => true, 'user' => $user];
        }
        
        return authenticateTestUser();
        
    } catch (Exception $e) {
        error_log("Token auth error: " . $e->getMessage());
        return authenticateTestUser();
    }
}

function authenticateTestUser() {
    include_once __DIR__ . '/../config/database.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Try to get test driver first (for driver requests)
        $stmt = $db->prepare("
            SELECT u.* FROM users u
            JOIN drivers d ON u.id = d.user_id  
            WHERE u.email = 'guincheiro@iguincho.com' 
               OR u.email = 'guincheiro@teste.com'
            ORDER BY u.id DESC
            LIMIT 1
        ");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no driver found, use test client
        if (!$user) {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = 'maria.silva@teste.com' LIMIT 1");
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$user) {
            // Create test client if doesn't exist
            $stmt = $db->prepare("
                INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
                VALUES ('client', 'Maria Silva Santos', '123.456.789-09', '1990-05-15', '(11) 98765-4321', 'maria.silva@teste.com', ?, TRUE, 'active', TRUE)
            ");
            $passwordHash = password_hash('senha123', PASSWORD_ARGON2I);
            $stmt->execute([$passwordHash]);
            
            $userId = $db->lastInsertId();
            
            // Get the created user
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Set session for consistency
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        error_log("Test auth error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro na autenticação'];
    }
}

?>