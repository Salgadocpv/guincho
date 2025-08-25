<?php
/**
 * Teste detalhado da conexão com banco
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔍 Diagnóstico Detalhado do Banco</h1>";

// Configurações do banco (copiadas do arquivo original)
$host = 'srv1310.hstgr.io';
$db_name = 'u461266905_guincho';
$username = 'u461266905_guincho';
$password = '4580951Ga@';
$charset = 'utf8mb4';

echo "<h2>1. Testando conectividade com o servidor MySQL...</h2>";

try {
    // Teste 1: Conectar apenas ao servidor (sem especificar banco)
    $dsn_server = "mysql:host=$host;charset=$charset";
    echo "DSN do servidor: <code>$dsn_server</code><br>";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo_server = new PDO($dsn_server, $username, $password, $options);
    echo "✅ Conexão com servidor MySQL: <strong>SUCESSO</strong><br>";
    
    // Listar bancos disponíveis
    echo "<h2>2. Bancos disponíveis para este usuário:</h2>";
    $stmt = $pdo_server->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($databases as $db) {
        $highlight = ($db === $db_name) ? " <strong>(ESTE É O NOSSO!)</strong>" : "";
        echo "• $db$highlight<br>";
    }
    
    // Teste 2: Conectar ao banco específico
    echo "<h2>3. Testando conexão com banco específico...</h2>";
    $dsn_full = "mysql:host=$host;dbname=$db_name;charset=$charset";
    echo "DSN completo: <code>$dsn_full</code><br>";
    
    try {
        $pdo_db = new PDO($dsn_full, $username, $password, $options);
        echo "✅ Conexão com banco '$db_name': <strong>SUCESSO</strong><br>";
        
        // Listar tabelas
        echo "<h2>4. Tabelas no banco:</h2>";
        $stmt = $pdo_db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tables)) {
            echo "⚠️ <strong>BANCO VAZIO</strong> - Nenhuma tabela encontrada!<br>";
        } else {
            foreach ($tables as $table) {
                echo "• $table<br>";
            }
        }
        
        echo "<h2>5. Informações do banco:</h2>";
        $info_queries = [
            'SELECT DATABASE() as current_db' => 'Banco atual',
            'SELECT USER() as current_user' => 'Usuário atual', 
            'SELECT VERSION() as mysql_version' => 'Versão MySQL',
            'SELECT NOW() as server_time' => 'Hora do servidor'
        ];
        
        foreach ($info_queries as $query => $description) {
            try {
                $stmt = $pdo_db->query($query);
                $result = $stmt->fetch();
                $value = reset($result);
                echo "• $description: <strong>$value</strong><br>";
            } catch (Exception $e) {
                echo "• $description: ❌ Erro - " . $e->getMessage() . "<br>";
            }
        }
        
        echo "<h1>🎉 BANCO FUNCIONANDO!</h1>";
        echo "<p>O problema deve estar em outra parte do código.</p>";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao conectar no banco '$db_name':<br>";
        echo "<strong>Código:</strong> " . $e->getCode() . "<br>";
        echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
        
        // Códigos de erro comuns
        $error_codes = [
            1049 => "Banco de dados não existe",
            1045 => "Credenciais inválidas", 
            2002 => "Servidor inacessível",
            1044 => "Sem permissão para acessar o banco"
        ];
        
        $code = $e->getCode();
        if (isset($error_codes[$code])) {
            echo "<strong>Significado:</strong> " . $error_codes[$code] . "<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro ao conectar no servidor MySQL:<br>";
    echo "<strong>Código:</strong> " . $e->getCode() . "<br>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br>";
    
    echo "<h2>Possíveis causas:</h2>";
    echo "• Servidor MySQL fora do ar<br>";
    echo "• Credenciais incorretas<br>";
    echo "• Firewall bloqueando conexão<br>";
    echo "• Problema na Hostinger<br>";
}

echo "<hr>";
echo "<h2>Próximos passos:</h2>";
echo "• Se o banco não existir: criar no painel da Hostinger<br>";
echo "• Se as credenciais estiverem erradas: atualizar database.php<br>";
echo "• Se o servidor estiver fora do ar: aguardar ou contatar suporte<br>";
?>