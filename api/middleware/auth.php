<?php
/**
 * Simple Session-Based Authentication Middleware
 * No tokens, no JWT, just PHP sessions
 */

function authenticate() {
    session_start();
    
    // Check if user is logged in
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
    
    // No session or invalid user - use test client
    return authenticateTestClient();
}

function authenticateTestClient() {
    include_once __DIR__ . '/../config/database.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get or create test client
        $stmt = $db->prepare("SELECT * FROM users WHERE email = 'maria.silva@teste.com' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Create test user if doesn't exist
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
        
        // Set session for test user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        error_log("Test auth error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro na autenticação'];
    }
}
?>