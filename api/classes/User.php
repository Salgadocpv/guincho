<?php
/**
 * Classe User - Gerenciamento de usuários do Iguincho
 */

require_once '../config/database.php';
require_once '../config/database_robust.php';

class User {
    private $conn;
    private $table = 'users';
    
    // Propriedades do usuário
    public $id;
    public $user_type;
    public $full_name;
    public $cpf;
    public $birth_date;
    public $phone;
    public $whatsapp;
    public $email;
    public $password_hash;
    public $license_plate;
    public $vehicle_brand;
    public $vehicle_model;
    public $vehicle_year;
    public $vehicle_color;
    public $terms_accepted;
    public $marketing_accepted;
    public $status;
    public $email_verified;
    public $created_at;
    
    public function __construct() {
        try {
            // Tentar conexão robusta primeiro
            $this->conn = getDBConnectionRobust();
        } catch (Exception $e) {
            // Fallback para conexão normal
            error_log("Conexão robusta falhou, tentando conexão padrão: " . $e->getMessage());
            $database = new Database();
            $this->conn = $database->getConnection();
        }
    }
    
    /**
     * Registrar novo usuário cliente
     */
    public function registerClient($data) {
        try {
            // Validar dados obrigatórios
            $this->validateClientData($data);
            
            // Verificar se email já existe
            if ($this->emailExists($data['email'])) {
                throw new Exception("E-mail já cadastrado no sistema", 409);
            }
            
            // Verificar se CPF já existe
            if ($this->cpfExists($data['cpf'])) {
                throw new Exception("CPF já cadastrado no sistema", 409);
            }
            
            // Hash da senha
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Query de inserção
            $query = "INSERT INTO " . $this->table . " 
                     SET user_type = 'client',
                         full_name = :full_name,
                         cpf = :cpf,
                         birth_date = :birth_date,
                         phone = :phone,
                         whatsapp = :whatsapp,
                         email = :email,
                         password_hash = :password_hash,
                         license_plate = :license_plate,
                         vehicle_brand = :vehicle_brand,
                         vehicle_model = :vehicle_model,
                         vehicle_year = :vehicle_year,
                         vehicle_color = :vehicle_color,
                         terms_accepted = :terms_accepted,
                         marketing_accepted = :marketing_accepted,
                         status = 'active'";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind dos parâmetros
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':cpf', $this->cleanCPF($data['cpf']));
            $stmt->bindParam(':birth_date', $data['birth_date']);
            $stmt->bindParam(':phone', $this->cleanPhone($data['phone']));
            $stmt->bindParam(':whatsapp', $this->cleanPhone($data['whatsapp']));
            $stmt->bindParam(':email', strtolower(trim($data['email'])));
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':license_plate', strtoupper($data['license_plate']));
            $stmt->bindParam(':vehicle_brand', $data['vehicle_brand']);
            $stmt->bindParam(':vehicle_model', $data['vehicle_model']);
            $stmt->bindParam(':vehicle_year', $data['vehicle_year']);
            $stmt->bindParam(':vehicle_color', $data['vehicle_color']);
            $stmt->bindParam(':terms_accepted', $data['terms_accepted'], PDO::PARAM_BOOL);
            $stmt->bindParam(':marketing_accepted', $data['marketing_accepted'], PDO::PARAM_BOOL);
            
            if ($stmt->execute()) {
                $user_id = $this->conn->lastInsertId();
                
                // Log da auditoria
                $this->logAction($user_id, 'user_registered', 'users', $user_id, null, $data);
                
                return [
                    'success' => true,
                    'message' => 'Usuário cadastrado com sucesso',
                    'user_id' => $user_id
                ];
            } else {
                throw new Exception("Erro ao cadastrar usuário", 500);
            }
            
        } catch (Exception $e) {
            error_log("Erro no cadastro de cliente: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Registrar novo guincheiro
     */
    public function registerDriver($userData, $driverData) {
        try {
            $this->conn->beginTransaction();
            
            // Validar dados do usuário
            $this->validateDriverUserData($userData);
            
            // Verificar se email já existe
            if ($this->emailExists($userData['email'])) {
                throw new Exception("E-mail já cadastrado no sistema", 409);
            }
            
            // Verificar se CPF já existe
            if ($this->cpfExists($userData['cpf'])) {
                throw new Exception("CPF já cadastrado no sistema", 409);
            }
            
            // Hash da senha
            $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Inserir usuário
            $query = "INSERT INTO " . $this->table . " 
                     SET user_type = 'driver',
                         full_name = :full_name,
                         cpf = :cpf,
                         birth_date = :birth_date,
                         phone = :phone,
                         whatsapp = :whatsapp,
                         email = :email,
                         password_hash = :password_hash,
                         terms_accepted = :terms_accepted,
                         status = 'pending_approval'";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':full_name', $userData['full_name']);
            $stmt->bindParam(':cpf', $this->cleanCPF($userData['cpf']));
            $stmt->bindParam(':birth_date', $userData['birth_date']);
            $stmt->bindParam(':phone', $this->cleanPhone($userData['phone']));
            $stmt->bindParam(':whatsapp', $this->cleanPhone($userData['whatsapp']));
            $stmt->bindParam(':email', strtolower(trim($userData['email'])));
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':terms_accepted', $userData['terms_accepted'], PDO::PARAM_BOOL);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao cadastrar usuário", 500);
            }
            
            $user_id = $this->conn->lastInsertId();
            
            // Inserir dados do guincheiro
            $driver = new Driver();
            $driver_result = $driver->create($user_id, $driverData);
            
            if (!$driver_result['success']) {
                throw new Exception($driver_result['message'], 500);
            }
            
            $this->conn->commit();
            
            // Log da auditoria
            $this->logAction($user_id, 'driver_registered', 'users', $user_id, null, $userData);
            
            return [
                'success' => true,
                'message' => 'Guincheiro cadastrado com sucesso. Aguarde aprovação.',
                'user_id' => $user_id,
                'driver_id' => $driver_result['driver_id']
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Erro no cadastro de guincheiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Registrar novo parceiro
     */
    public function registerPartner($userData, $partnerData) {
        try {
            $this->conn->beginTransaction();
            
            // Validar se email já existe
            if ($this->emailExists($userData['email'])) {
                throw new Exception("E-mail já cadastrado no sistema", 409);
            }
            
            // Hash da senha
            $password_hash = password_hash($userData['password'], PASSWORD_ARGON2I);
            
            // Inserir usuário
            $query = "INSERT INTO " . $this->table . " 
                     SET user_type = 'partner',
                         full_name = :full_name,
                         cpf = :cpf,
                         phone = :phone,
                         whatsapp = :whatsapp,
                         email = :email,
                         password_hash = :password_hash,
                         terms_accepted = :terms_accepted,
                         status = 'pending_approval'";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':full_name', $userData['full_name']);
            $stmt->bindParam(':cpf', $this->cleanCPF($userData['cpf']));
            $stmt->bindParam(':phone', $this->cleanPhone($userData['phone']));
            $stmt->bindParam(':whatsapp', $this->cleanPhone($userData['whatsapp']));
            $stmt->bindParam(':email', strtolower(trim($userData['email'])));
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':terms_accepted', $userData['terms_accepted'], PDO::PARAM_BOOL);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao cadastrar usuário", 500);
            }
            
            $user_id = $this->conn->lastInsertId();
            
            // Inserir dados do parceiro
            $partnerData['user_id'] = $user_id;
            $partner = new Partner();
            $partner_result = $partner->create($partnerData);
            
            if (!$partner_result || !isset($partner_result['partner_id'])) {
                throw new Exception("Erro ao cadastrar dados do parceiro", 500);
            }
            
            $this->conn->commit();
            
            // Log da auditoria
            $this->logAction($user_id, 'partner_registered', 'users', $user_id, null, $userData);
            
            return [
                'success' => true,
                'message' => 'Parceiro cadastrado com sucesso. Aguarde aprovação.',
                'user_id' => $user_id,
                'partner_id' => $partner_result['partner_id']
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Erro no cadastro de parceiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Login do usuário
     */
    public function login($email, $password) {
        try {
            $query = "SELECT id, user_type, full_name, email, password_hash, status 
                     FROM " . $this->table . " 
                     WHERE email = :email AND status IN ('active', 'pending_approval')";
            
            $stmt = $this->conn->prepare($query);
            $email_clean = strtolower(trim($email));
            $stmt->bindParam(':email', $email_clean);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("E-mail ou senha incorretos", 401);
            }
            
            $user = $stmt->fetch();
            
            if (!password_verify($password, $user['password_hash'])) {
                throw new Exception("E-mail ou senha incorretos", 401);
            }
            
            // Gerar token de sessão
            $session_token = $this->generateSessionToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            $session_query = "INSERT INTO user_sessions 
                             SET user_id = :user_id,
                                 session_token = :session_token,
                                 expires_at = :expires_at,
                                 ip_address = :ip_address,
                                 user_agent = :user_agent";
            
            $session_stmt = $this->conn->prepare($session_query);
            $session_stmt->bindParam(':user_id', $user['id']);
            $session_stmt->bindParam(':session_token', $session_token);
            $session_stmt->bindParam(':expires_at', $expires_at);
            $session_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $session_stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
            $session_stmt->execute();
            
            // Log da auditoria
            $this->logAction($user['id'], 'user_login', 'users', $user['id']);
            
            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => [
                    'id' => $user['id'],
                    'user_type' => $user['user_type'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'status' => $user['status']
                ],
                'session_token' => $session_token,
                'expires_at' => $expires_at
            ];
            
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validação de dados do cliente
     */
    private function validateClientData($data) {
        $required_fields = ['full_name', 'cpf', 'birth_date', 'phone', 'email', 'password', 
                           'license_plate', 'vehicle_brand', 'vehicle_model', 'vehicle_year', 
                           'vehicle_color', 'terms_accepted'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo obrigatório não preenchido: {$field}", 400);
            }
        }
        
        // Validações específicas
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido", 400);
        }
        
        if (!$this->validateCPF($data['cpf'])) {
            throw new Exception("CPF inválido", 400);
        }
        
        if (strlen($data['password']) < 6) {
            throw new Exception("Senha deve ter no mínimo 6 caracteres", 400);
        }
    }
    
    /**
     * Validação de dados do guincheiro
     */
    private function validateDriverUserData($data) {
        $required_fields = ['full_name', 'cpf', 'birth_date', 'phone', 'whatsapp', 'email', 
                           'password', 'terms_accepted'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo obrigatório não preenchido: {$field}", 400);
            }
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido", 400);
        }
        
        if (!$this->validateCPF($data['cpf'])) {
            throw new Exception("CPF inválido", 400);
        }
        
        if (strlen($data['password']) < 8) {
            throw new Exception("Senha deve ter no mínimo 8 caracteres", 400);
        }
    }
    
    /**
     * Verificar se email já existe
     */
    private function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', strtolower(trim($email)));
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Verificar se CPF já existe
     */
    private function cpfExists($cpf) {
        $query = "SELECT id FROM " . $this->table . " WHERE cpf = :cpf";
        $stmt = $this->conn->prepare($query);
        $clean_cpf = $this->cleanCPF($cpf);
        $stmt->bindParam(':cpf', $clean_cpf);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Validar CPF
     */
    private function validateCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Limpar CPF
     */
    private function cleanCPF($cpf) {
        return preg_replace('/[^0-9]/', '', $cpf);
    }
    
    /**
     * Limpar telefone
     */
    private function cleanPhone($phone) {
        return preg_replace('/[^0-9]/', '', $phone);
    }
    
    /**
     * Gerar token de sessão
     */
    private function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Log de auditoria
     */
    private function logAction($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
        try {
            $query = "INSERT INTO audit_logs 
                     SET user_id = :user_id,
                         action = :action,
                         table_name = :table_name,
                         record_id = :record_id,
                         old_values = :old_values,
                         new_values = :new_values,
                         ip_address = :ip_address,
                         user_agent = :user_agent";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id);
            $old_values_json = json_encode($old_values);
            $new_values_json = json_encode($new_values);
            $stmt->bindParam(':old_values', $old_values_json);
            $stmt->bindParam(':new_values', $new_values_json);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro no log de auditoria: " . $e->getMessage());
        }
    }
}
?>