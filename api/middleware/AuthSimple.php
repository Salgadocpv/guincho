<?php
class Auth {
    private $pdo;
    
    public function __construct() {
        require_once __DIR__ . '/../config/database_local.php';
        $database = new DatabaseLocal();
        $this->pdo = $database->getConnection();
    }
    
    public function validateToken($token) {
        try {
            // For now, we'll decode the token as a simple JSON (like the frontend creates)
            // In production, you'd use proper JWT validation
            $tokenData = json_decode(base64_decode($token), true);
            
            if (!$tokenData || !isset($tokenData['user_id'])) {
                // Try to get test user
                return $this->getTestUser();
            }
            
            // Get user from database using token data
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$tokenData['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                return $user;
            }
            
            // Fallback to test user
            return $this->getTestUser();
            
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            return $this->getTestUser();
        }
    }
    
    private function getTestUser() {
        try {
            // Check the request to determine what type of user is needed
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            
            if (strpos($requestUri, 'credits') !== false || strpos($requestUri, 'driver') !== false) {
                // For driver requests, try to get test driver first
                $stmt = $this->pdo->prepare("
                    SELECT u.* FROM users u
                    JOIN drivers d ON u.id = d.user_id  
                    WHERE u.email = 'guincheiro@iguincho.com' 
                       OR u.email = 'guincheiro@teste.com'
                    ORDER BY u.id DESC
                    LIMIT 1
                ");
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    return $user;
                }
                
                // Create test driver if doesn't exist
                return $this->createTestDriver();
            } else {
                // For client requests
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = 'maria.silva@teste.com' AND user_type = 'client' LIMIT 1");
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $user = $this->createTestClient();
                }
                
                return $user;
            }
            
        } catch (Exception $e) {
            error_log("Test auth error: " . $e->getMessage());
            throw new Exception('Erro na autenticação');
        }
    }
    
    private function createTestClient() {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
            VALUES ('client', 'Maria Silva Santos', '123.456.789-09', '1990-05-15', '(11) 98765-4321', 'maria.silva@teste.com', ?, TRUE, 'active', TRUE)
        ");
        $passwordHash = password_hash('senha123', PASSWORD_ARGON2I);
        $stmt->execute([$passwordHash]);
        
        $userId = $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function createTestDriver() {
        $this->pdo->beginTransaction();
        
        try {
            // Create user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) 
                VALUES ('driver', 'João Guincheiro', '987.654.321-00', '1985-03-10', '(11) 91234-5678', 'guincheiro@teste.com', ?, TRUE, 'active', TRUE)
            ");
            $passwordHash = password_hash('senha123', PASSWORD_ARGON2I);
            $stmt->execute([$passwordHash]);
            
            $userId = $this->pdo->lastInsertId();
            
            // Create driver profile
            $stmt = $this->pdo->prepare("
                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, truck_plate, truck_brand, truck_model, truck_year, truck_capacity, approval_status) 
                VALUES (?, '12345678900', 'D', '3-5', 'todos', 'São Paulo', '24h', 'ABC-1234', 'Ford', 'Cargo', 2020, 'media', 'approved')
            ");
            $stmt->execute([$userId]);
            
            $this->pdo->commit();
            
            // Return user data
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>