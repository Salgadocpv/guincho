<?php
/**
 * Configuração de Banco de Dados - Guincho App
 * Arquivo de conexão com MySQL
 */

class Database {
    // Usar localhost em produção para evitar limite de conexões
    private $host = 'localhost';
    private $db_name = 'u461266905_guincho';
    private $username = 'u461266905_guincho';
    private $password = '4580951Ga@';
    private $charset = 'utf8mb4';
    public $conn;

    /**
     * Conectar ao banco de dados
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Erro na conexão com o banco de dados", 500);
        }

        return $this->conn;
    }

    /**
     * Testar conexão
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            return [
                'status' => 'success',
                'message' => 'Conexão estabelecida com sucesso',
                'environment' => 'Servidor (Hostinger)',
                'host' => $this->host,
                'database' => $this->db_name,
                'server_info' => $conn->getAttribute(PDO::ATTR_SERVER_INFO)
            ];
        } catch(Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro na conexão: ' . $e->getMessage(),
                'environment' => 'Servidor (Hostinger)',
                'host' => $this->host,
                'database' => $this->db_name
            ];
        }
    }

    /**
     * Fechar conexão
     */
    public function closeConnection() {
        $this->conn = null;
    }
}

/**
 * Função helper para obter conexão
 */
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}
?>