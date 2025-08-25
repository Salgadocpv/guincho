<?php
/**
 * Teste direto de conexão com banco de dados
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

echo "<h1>🔍 Teste de Conexão com Banco</h1>";

try {
    echo "<h2>1. Testando arquivo de configuração...</h2>";
    
    $config_path = __DIR__ . '/api/config/database.php';
    echo "Arquivo: " . $config_path . "<br>";
    echo "Existe: " . (file_exists($config_path) ? "✅ SIM" : "❌ NÃO") . "<br>";
    
    if (file_exists($config_path)) {
        include_once $config_path;
        echo "Incluído: ✅ OK<br>";
        
        if (class_exists('Database')) {
            echo "Classe Database: ✅ OK<br>";
            
            echo "<h2>2. Testando conexão...</h2>";
            $database = new Database();
            
            // Usar reflexão para ver as propriedades
            $reflection = new ReflectionClass($database);
            $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
            
            foreach ($properties as $prop) {
                $prop->setAccessible(true);
                echo $prop->getName() . ": " . $prop->getValue($database) . "<br>";
            }
            
            echo "<h2>3. Testando getConnection()...</h2>";
            $conn = $database->getConnection();
            
            if ($conn) {
                echo "Conexão: ✅ SUCESSO<br>";
                
                echo "<h2>4. Testando query simples...</h2>";
                $stmt = $conn->query("SELECT 1 as test, NOW() as now_time, DATABASE() as db_name");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "Teste: " . $result['test'] . "<br>";
                echo "Hora: " . $result['now_time'] . "<br>";
                echo "Banco: " . $result['db_name'] . "<br>";
                
                echo "<h2>5. Testando tabela users...</h2>";
                $stmt = $conn->query("SHOW TABLES LIKE 'users'");
                $table_exists = $stmt->fetch();
                echo "Tabela users: " . ($table_exists ? "✅ EXISTE" : "❌ NÃO EXISTE") . "<br>";
                
                if ($table_exists) {
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo "Total usuários: " . $count['count'] . "<br>";
                }
                
                echo "<h1>🎉 TUDO FUNCIONANDO!</h1>";
                
            } else {
                echo "Conexão: ❌ FALHOU<br>";
            }
            
        } else {
            echo "Classe Database: ❌ NÃO ENCONTRADA<br>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO:</h2>";
    echo "Mensagem: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "Trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h2>Informações do PHP:</h2>";
echo "Versão PHP: " . PHP_VERSION . "<br>";
echo "PDO disponível: " . (extension_loaded('pdo') ? "✅ SIM" : "❌ NÃO") . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? "✅ SIM" : "❌ NÃO") . "<br>";
echo "Timezone: " . date_default_timezone_get() . "<br>";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "<br>";
?>