<?php
/**
 * Configuração Robusta de Banco de Dados
 * Sistema com fallback e retry para conexões instáveis
 */

class DatabaseRobust {
    // Configurações primárias (Hostinger) - usando localhost em produção
    private $configs = [
        [
            'host' => 'localhost',
            'db_name' => 'u461266905_guincho',
            'username' => 'u461266905_guincho',
            'password' => '4580951Ga@',
            'port' => 3306,
            'name' => 'Hostinger Localhost'
        ]
        // Pode adicionar servidores de backup aqui
    ];
    
    private $charset = 'utf8mb4';
    public $conn;
    private $maxRetries = 3;
    private $retryDelay = 2; // segundos

    /**
     * Conectar ao banco com retry automático
     */
    public function getConnection() {
        $lastException = null;
        
        foreach ($this->configs as $config) {
            for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
                try {
                    error_log("Tentando conexão {$config['name']} - Tentativa $attempt/{$this->maxRetries}");
                    
                    // Definir timezone para São Paulo
                    date_default_timezone_set('America/Sao_Paulo');
                    
                    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db_name']};charset={$this->charset}";
                    
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}, time_zone = '-03:00'",
                        PDO::ATTR_TIMEOUT => 30, // 30 segundos timeout
                        PDO::MYSQL_ATTR_COMPRESS => true, // Compressão
                        PDO::ATTR_PERSISTENT => false // Não usar conexões persistentes
                    ];

                    $startTime = microtime(true);
                    $this->conn = new PDO($dsn, $config['username'], $config['password'], $options);
                    $endTime = microtime(true);
                    
                    $connectionTime = round(($endTime - $startTime) * 1000);
                    error_log("Conexão estabelecida com {$config['name']} em {$connectionTime}ms");
                    
                    // Teste rápido da conexão
                    $this->conn->query("SELECT 1");
                    
                    return $this->conn;
                    
                } catch (PDOException $e) {
                    $lastException = $e;
                    $errorMsg = "Falha na conexão {$config['name']} (tentativa $attempt): " . $e->getMessage();
                    error_log($errorMsg);
                    
                    // Se não é a última tentativa, aguarda antes de tentar novamente
                    if ($attempt < $this->maxRetries) {
                        sleep($this->retryDelay);
                        $this->retryDelay *= 2; // Backoff exponencial
                    }
                }
            }
            
            // Reset delay para próximo servidor
            $this->retryDelay = 2;
        }
        
        // Se chegou aqui, todas as tentativas falharam
        $finalError = "Todas as tentativas de conexão falharam. Último erro: " . 
                     ($lastException ? $lastException->getMessage() : 'Desconhecido');
        
        error_log("ERRO CRÍTICO: " . $finalError);
        throw new Exception($finalError, 500);
    }

    /**
     * Testar conectividade antes de tentar conectar
     */
    public function testConnectivity() {
        $results = [];
        
        foreach ($this->configs as $config) {
            $result = [
                'name' => $config['name'],
                'host' => $config['host'],
                'port' => $config['port'],
                'dns_ok' => false,
                'tcp_ok' => false,
                'mysql_ok' => false,
                'response_time' => null,
                'error' => null
            ];
            
            try {
                // Teste DNS
                $ip = gethostbyname($config['host']);
                $result['dns_ok'] = ($ip !== $config['host']);
                $result['resolved_ip'] = $ip;
                
                if ($result['dns_ok']) {
                    // Teste TCP
                    $startTime = microtime(true);
                    $socket = @fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
                    
                    if ($socket) {
                        $result['tcp_ok'] = true;
                        fclose($socket);
                        
                        $endTime = microtime(true);
                        $result['response_time'] = round(($endTime - $startTime) * 1000);
                        
                        // Teste MySQL básico
                        try {
                            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db_name']};charset={$this->charset}";
                            $testConn = new PDO($dsn, $config['username'], $config['password'], [
                                PDO::ATTR_TIMEOUT => 10,
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                            ]);
                            
                            $testConn->query("SELECT 1");
                            $result['mysql_ok'] = true;
                            
                        } catch (Exception $e) {
                            $result['error'] = "MySQL: " . $e->getMessage();
                        }
                        
                    } else {
                        $result['error'] = "TCP: [$errno] $errstr";
                    }
                } else {
                    $result['error'] = "DNS: Não foi possível resolver hostname";
                }
                
            } catch (Exception $e) {
                $result['error'] = "Geral: " . $e->getMessage();
            }
            
            $results[] = $result;
        }
        
        return $results;
    }

    /**
     * Obter status detalhado da conexão
     */
    public function getConnectionStatus() {
        if (!$this->conn) {
            return ['status' => 'disconnected'];
        }
        
        try {
            $status = [
                'status' => 'connected',
                'server_info' => $this->conn->getAttribute(PDO::ATTR_SERVER_INFO),
                'server_version' => $this->conn->getAttribute(PDO::ATTR_SERVER_VERSION),
                'connection_status' => $this->conn->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                'autocommit' => $this->conn->getAttribute(PDO::ATTR_AUTOCOMMIT),
                'client_version' => $this->conn->getAttribute(PDO::ATTR_CLIENT_VERSION)
            ];
            
            // Teste de latência
            $startTime = microtime(true);
            $this->conn->query("SELECT 1");
            $endTime = microtime(true);
            $status['latency_ms'] = round(($endTime - $startTime) * 1000);
            
            return $status;
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
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
 * Função helper para obter conexão robusta
 */
function getDBConnectionRobust() {
    static $database = null;
    
    if ($database === null) {
        $database = new DatabaseRobust();
    }
    
    return $database->getConnection();
}

/**
 * Função para testar conectividade
 */
function testDatabaseConnectivity() {
    $database = new DatabaseRobust();
    return $database->testConnectivity();
}
?>