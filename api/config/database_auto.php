<?php
/**
 * Configuração Automática de Banco por Ambiente
 * - Local: usa localhost/XAMPP
 * - Produção: usa Hostinger
 */

class DatabaseAuto {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    public $conn;
    private $environment;

    public function __construct() {
        $this->detectEnvironment();
        $this->setDatabaseConfig();
    }

    /**
     * Detecta automaticamente o ambiente
     */
    private function detectEnvironment() {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        
        // Detectores de ambiente local
        $localIndicators = [
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            'xampp',
            'wamp',
            'mamp'
        ];
        
        $isLocal = false;
        foreach ($localIndicators as $indicator) {
            if (strpos($serverName, $indicator) !== false || 
                strpos($httpHost, $indicator) !== false) {
                $isLocal = true;
                break;
            }
        }
        
        // Verificar se está rodando em servidor web local
        if (!$isLocal) {
            $isLocal = (
                strpos($serverName, '.local') !== false ||
                strpos($httpHost, '.test') !== false ||
                in_array($serverName, ['localhost', '127.0.0.1', '::1']) ||
                in_array($httpHost, ['localhost', '127.0.0.1', '::1'])
            );
        }
        
        $this->environment = $isLocal ? 'local' : 'production';
        
        // Log do ambiente detectado
        error_log("Ambiente detectado: {$this->environment} (HOST: {$httpHost})");
    }

    /**
     * Configura banco baseado no ambiente
     */
    private function setDatabaseConfig() {
        if ($this->environment === 'local') {
            // Configuração XAMPP Local
            $this->host = 'localhost';
            $this->db_name = 'guincho_local';
            $this->username = 'root';
            $this->password = '';
            
            error_log("Usando configuração LOCAL: localhost/guincho_local");
            
        } else {
            // Configuração Hostinger Produção
            $this->host = 'srv1310.hstgr.io';
            $this->db_name = 'u461266905_guincho';
            $this->username = 'u461266905_guincho';
            $this->password = '4580951Ga@';
            
            error_log("Usando configuração PRODUÇÃO: Hostinger");
        }
    }

    /**
     * Conectar ao banco
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => $this->environment === 'local' ? 5 : 30
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            error_log("Conexão estabelecida no ambiente: {$this->environment}");
            
        } catch(PDOException $exception) {
            $errorMsg = "Erro de conexão ({$this->environment}): " . $exception->getMessage();
            error_log($errorMsg);
            
            // Se é local e falhou, pode ser que banco não existe
            if ($this->environment === 'local') {
                throw new Exception("Banco local não encontrado. Execute setup_local_database.php primeiro.", 500);
            } else {
                throw new Exception("Erro na conexão com servidor: " . $exception->getMessage(), 500);
            }
        }

        return $this->conn;
    }

    /**
     * Criar banco local se não existir
     */
    public function setupLocalDatabase() {
        if ($this->environment !== 'local') {
            throw new Exception("Setup só pode ser executado em ambiente local");
        }
        
        try {
            // Conectar sem especificar database
            $dsn = "mysql:host=" . $this->host . ";charset=" . $this->charset;
            $tempConn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Criar database se não existir
            $tempConn->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Conectar ao database criado
            $this->conn = $this->getConnection();
            
            // Criar tabela users se não existir
            $this->createUsersTable();
            $this->createTestUsers();
            
            return [
                'status' => 'success',
                'message' => 'Banco local configurado com sucesso',
                'database' => $this->db_name
            ];
            
        } catch (Exception $e) {
            throw new Exception("Erro no setup local: " . $e->getMessage());
        }
    }
    
    /**
     * Criar tabela users
     */
    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            user_type ENUM('client', 'driver', 'admin', 'partner') NOT NULL,
            status ENUM('active', 'inactive', 'pending_approval', 'suspended') DEFAULT 'active',
            phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_user_type (user_type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->exec($sql);
        error_log("Tabela users criada/verificada no ambiente local");
    }
    
    /**
     * Criar usuários de teste
     */
    private function createTestUsers() {
        $testUsers = [
            [
                'email' => 'admin@iguincho.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'full_name' => 'Admin Sistema',
                'user_type' => 'admin'
            ],
            [
                'email' => 'cliente@iguincho.com',
                'password' => password_hash('teste123', PASSWORD_DEFAULT),
                'full_name' => 'Cliente Teste',
                'user_type' => 'client'
            ],
            [
                'email' => 'guincheiro@iguincho.com',
                'password' => password_hash('teste123', PASSWORD_DEFAULT),
                'full_name' => 'Guincheiro Teste',
                'user_type' => 'driver'
            ]
        ];
        
        $insertQuery = "INSERT IGNORE INTO users (email, password, full_name, user_type, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $this->conn->prepare($insertQuery);
        
        foreach ($testUsers as $user) {
            $stmt->execute([
                $user['email'],
                $user['password'],
                $user['full_name'],
                $user['user_type']
            ]);
        }
        
        error_log("Usuários de teste criados/verificados no ambiente local");
    }

    /**
     * Obter informações do ambiente
     */
    public function getEnvironmentInfo() {
        return [
            'environment' => $this->environment,
            'host' => $this->host,
            'database' => $this->db_name,
            'username' => $this->username,
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'N/A'
        ];
    }
}

/**
 * Função helper para obter conexão automática
 */
function getDBConnectionAuto() {
    static $database = null;
    
    if ($database === null) {
        $database = new DatabaseAuto();
    }
    
    return $database->getConnection();
}
?>