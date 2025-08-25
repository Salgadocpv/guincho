<?php
/**
 * Simple test to check basic functionality + DB
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔍 Teste de Banco via simple_test.php</h1>";

try {
    // Teste básico
    echo "<h2>1. PHP funcionando:</h2>";
    echo "✅ Timestamp: " . date('Y-m-d H:i:s') . "<br>";
    echo "✅ Server: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "<br>";
    
    // Teste de banco
    echo "<h2>2. Testando banco:</h2>";
    
    $config_path = __DIR__ . '/../config/database.php';
    echo "Config existe: " . (file_exists($config_path) ? "✅ SIM" : "❌ NÃO") . "<br>";
    
    if (file_exists($config_path)) {
        include_once $config_path;
        echo "Config carregado: ✅ OK<br>";
        
        // Teste direto de conexão (usando localhost em produção)
        $host = 'localhost';
        $db_name = 'u461266905_guincho';
        $username = 'u461266905_guincho';
        $password = '4580951Ga@';
        
        echo "<h2>3. Credenciais:</h2>";
        echo "Host: $host<br>";
        echo "Database: $db_name<br>";
        echo "User: $username<br>";
        echo "Password: " . str_repeat('*', strlen($password)) . "<br>";
        
        echo "<h2>4. Testando conexão direta:</h2>";
        
        try {
            $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            echo "✅ <strong>CONEXÃO FUNCIONANDO!</strong><br>";
            
            // Teste de query
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = '$db_name'");
            $result = $stmt->fetch();
            echo "Total de tabelas: " . $result['total'] . "<br>";
            
            // Listar algumas tabelas
            $stmt = $pdo->query("SHOW TABLES LIMIT 5");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "Algumas tabelas: " . implode(', ', $tables) . "<br>";
            
        } catch (PDOException $e) {
            echo "❌ <strong>ERRO DE CONEXÃO:</strong><br>";
            echo "Código: " . $e->getCode() . "<br>";
            echo "Mensagem: " . $e->getMessage() . "<br>";
            
            // Tentar sem especificar o banco
            try {
                echo "<h2>5. Testando só o servidor:</h2>";
                $dsn_server = "mysql:host=$host;charset=utf8mb4";
                $pdo_server = new PDO($dsn_server, $username, $password);
                echo "✅ Servidor MySQL acessível<br>";
                
                $stmt = $pdo_server->query("SHOW DATABASES");
                $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "Bancos disponíveis: " . implode(', ', $dbs) . "<br>";
                
            } catch (PDOException $e2) {
                echo "❌ Servidor inacessível: " . $e2->getMessage() . "<br>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><a href='/guincho/'>← Voltar para o app</a></p>";
?>