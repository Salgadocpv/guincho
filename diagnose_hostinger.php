<?php
/**
 * Diagnóstico Específico de Conectividade Hostinger
 */
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Diagnóstico Hostinger</title></head><body>";
echo "<h1>🔍 Diagnóstico de Conectividade Hostinger</h1>";

// Configurações do banco (igual ao database.php)
$host = 'srv1310.hstgr.io';
$dbname = 'u461266905_guincho';
$username = 'u461266905_guincho';
$password = '4580951Ga@';

echo "<div style='background: #e7f3ff; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff;'>";
echo "<h3>📋 Configurações de Conexão:</h3>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Database:</strong> $dbname</p>";
echo "<p><strong>Username:</strong> $username</p>";
echo "<p><strong>Password:</strong> " . str_repeat('*', strlen($password)) . "</p>";
echo "</div>";

// Teste 1: Resolução DNS
echo "<h2>🌐 Teste 1: Resolução DNS</h2>";
try {
    $ip = gethostbyname($host);
    if ($ip === $host) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
        echo "❌ DNS não resolveu o hostname<br>";
        echo "Hostname: $host não foi resolvido para IP";
        echo "</div>";
    } else {
        echo "<div style='color: green; padding: 10px; border: 1px solid green;'>";
        echo "✅ DNS OK<br>";
        echo "Hostname: $host → IP: $ip";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "❌ Erro na resolução DNS: " . $e->getMessage();
    echo "</div>";
}

// Teste 2: Conectividade TCP (porta 3306)
echo "<h2>🔌 Teste 2: Conectividade TCP</h2>";
$port = 3306;
$timeout = 10;

$connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
if ($connection) {
    echo "<div style='color: green; padding: 10px; border: 1px solid green;'>";
    echo "✅ Porta 3306 acessível<br>";
    echo "Conexão TCP estabelecida com $host:$port";
    fclose($connection);
    echo "</div>";
} else {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "❌ Porta 3306 inacessível<br>";
    echo "Erro: [$errno] $errstr<br>";
    echo "Possíveis causas:<br>";
    echo "• Firewall bloqueando conexões externas<br>";
    echo "• Hostinger bloqueou IP local<br>";
    echo "• Servidor MySQL offline";
    echo "</div>";
}

// Teste 3: Extensões PHP necessárias
echo "<h2>🐘 Teste 3: Extensões PHP</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✅ $ext: Disponível</p>";
    } else {
        echo "<p style='color: red;'>❌ $ext: NÃO DISPONÍVEL</p>";
    }
}

// Teste 4: Tentativa de conexão PDO
echo "<h2>🔐 Teste 4: Conexão PDO</h2>";
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    echo "<p><strong>DSN:</strong> $dsn</p>";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_TIMEOUT => 10
    ];
    
    echo "<p>🔄 Tentando conectar...</p>";
    $startTime = microtime(true);
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000);
    
    echo "<div style='color: green; padding: 15px; border: 2px solid green;'>";
    echo "🎉 <strong>CONEXÃO ESTABELECIDA!</strong><br>";
    echo "Tempo: {$connectionTime}ms<br>";
    echo "Status: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "<br>";
    echo "Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "<br>";
    echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "</div>";
    
    // Teste adicional: query simples
    echo "<h3>🔍 Teste de Query</h3>";
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✅ Query funcionando: " . $result['test'] . "</p>";
        
        // Verificar tabela users
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Tabela 'users' existe</p>";
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $count = $stmt->fetch()['count'];
            echo "<p style='color: blue;'>📊 Usuários cadastrados: $count</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Tabela 'users' não encontrada</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro na query: " . $e->getMessage() . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 15px; border: 2px solid red;'>";
    echo "❌ <strong>ERRO NA CONEXÃO PDO</strong><br>";
    echo "Código: " . $e->getCode() . "<br>";
    echo "Mensagem: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "</div>";
    
    // Diagnóstico baseado no código de erro
    $errorCode = $e->getCode();
    echo "<h3>🩺 Diagnóstico do Erro:</h3>";
    
    switch ($errorCode) {
        case 2002:
            echo "<p>🔍 <strong>Erro 2002</strong> - Servidor não encontrado ou inacessível<br>";
            echo "• Verifique se o hostname está correto<br>";
            echo "• Confirme se não há firewall bloqueando<br>";
            echo "• Teste de outro servidor/IP</p>";
            break;
            
        case 1045:
            echo "<p>🔍 <strong>Erro 1045</strong> - Credenciais inválidas<br>";
            echo "• Usuário ou senha incorretos<br>";
            echo "• Verifique no painel da Hostinger</p>";
            break;
            
        case 1049:
            echo "<p>🔍 <strong>Erro 1049</strong> - Database não existe<br>";
            echo "• Nome do database incorreto<br>";
            echo "• Database foi removido</p>";
            break;
            
        case 2003:
            echo "<p>🔍 <strong>Erro 2003</strong> - Conexão recusada<br>";
            echo "• MySQL server não está rodando<br>";
            echo "• Porta bloqueada por firewall</p>";
            break;
            
        default:
            echo "<p>🔍 <strong>Erro $errorCode</strong> - Consulte documentação MySQL</p>";
    }
}

// Informações do ambiente
echo "<h2>💻 Informações do Ambiente</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>OS:</strong> " . PHP_OS . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>IP Local:</strong> " . $_SERVER['SERVER_ADDR'] . "</p>";
echo "<p><strong>User Agent:</strong> " . $_SERVER['HTTP_USER_AGENT'] . "</p>";

// Teste de conectividade externa
echo "<h2>🌍 Teste de Conectividade Externa</h2>";
echo "<p>Testando acesso a sites externos...</p>";

$testSites = ['google.com', 'hostinger.com'];
foreach ($testSites as $site) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'HEAD'
        ]
    ]);
    
    $result = @get_headers("http://$site", 0, $context);
    if ($result) {
        echo "<p style='color: green;'>✅ $site acessível</p>";
    } else {
        echo "<p style='color: red;'>❌ $site inacessível</p>";
    }
}

echo "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
echo "<h3>💡 Próximos Passos:</h3>";
echo "<ol>";
echo "<li>Se DNS falhou: problema de rede local</li>";
echo "<li>Se TCP falhou: firewall ou Hostinger bloqueou IP</li>";
echo "<li>Se PDO falhou: credenciais ou configuração</li>";
echo "<li>Se tudo passou: problema estava temporário</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Tentar Login Novamente</a></p>";
echo "</body></html>";
?>