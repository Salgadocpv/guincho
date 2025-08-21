<?php
/**
 * Middleware de Autenticação Administrativa
 */

require_once '../classes/User.php';

class AdminAuth {
    
    /**
     * Verificar se o usuário é administrador
     */
    public static function requireAdmin() {
        try {
            $userData = self::getCurrentUser();
            
            if (!$userData || $userData['user_type'] !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Acesso negado. Apenas administradores podem acessar esta área.',
                    'error_code' => 403
                ]);
                exit;
            }
            
            return $userData;
            
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Acesso não autorizado: ' . $e->getMessage(),
                'error_code' => 401
            ]);
            exit;
        }
    }
    
    /**
     * Verificar se o usuário tem permissão específica
     */
    public static function requirePermission($permission) {
        $userData = self::requireAdmin();
        
        // Por enquanto, todos os admins têm todas as permissões
        // Futuramente, implementar sistema de permissões granulares
        return $userData;
    }
    
    /**
     * Obter dados do usuário atual
     */
    private static function getCurrentUser() {
        // Verificar token de sessão no header Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            return self::getUserByToken($token);
        }
        
        // Verificar sessão PHP (fallback)
        session_start();
        if (isset($_SESSION['user_id'])) {
            return self::getUserById($_SESSION['user_id']);
        }
        
        throw new Exception('Token de acesso não encontrado');
    }
    
    /**
     * Obter usuário por token de sessão
     */
    private static function getUserByToken($token) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $sql = "SELECT u.id, u.user_type, u.full_name, u.email, u.status 
                   FROM users u
                   INNER JOIN user_sessions s ON u.id = s.user_id
                   WHERE s.session_token = :token 
                   AND s.expires_at > NOW()
                   AND u.status = 'active'";
            
            $stmt = $db->prepare($sql);
            $stmt->execute(['token' => $token]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Token inválido ou expirado');
            }
            
            return $user;
            
        } catch (Exception $e) {
            throw new Exception('Erro na autenticação: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter usuário por ID
     */
    private static function getUserById($userId) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $sql = "SELECT id, user_type, full_name, email, status 
                   FROM users 
                   WHERE id = :user_id AND status = 'active'";
            
            $stmt = $db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Usuário não encontrado');
            }
            
            return $user;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao obter usuário: ' . $e->getMessage());
        }
    }
    
    /**
     * Log de ações administrativas
     */
    public static function logAdminAction($action, $details = null, $userId = null) {
        try {
            if (!$userId) {
                $currentUser = self::getCurrentUser();
                $userId = $currentUser['id'];
            }
            
            $db = Database::getInstance()->getConnection();
            
            $sql = "INSERT INTO audit_logs 
                   (user_id, action, table_name, new_values, ip_address, user_agent) 
                   VALUES (:user_id, :action, 'system_settings', :details, :ip, :user_agent)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'details' => json_encode($details),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar log administrativo: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar se o sistema está em modo de manutenção
     */
    public static function checkMaintenanceMode() {
        try {
            $settings = new SystemSettings();
            
            if ($settings->isMaintenanceMode()) {
                http_response_code(503);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sistema em manutenção. Tente novamente mais tarde.',
                    'error_code' => 503
                ]);
                exit;
            }
            
        } catch (Exception $e) {
            // Em caso de erro, permitir acesso normal
            error_log("Erro ao verificar modo de manutenção: " . $e->getMessage());
        }
    }
}
?>