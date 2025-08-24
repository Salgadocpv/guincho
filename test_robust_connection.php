<?php
/**
 * Teste da Conexão Robusta com Hostinger
 */
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Teste Conexão Robusta</title>";
echo "<style>
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🛡️ Teste de Conexão Robusta</h1>";

try {
    require_once 'api/config/database_robust.php';
    
    echo "<div class='info'><strong>✅ Arquivo database_robust.php carregado</strong></div>";
    
    // Teste 1: Conectividade básica
    echo "<h2>🔍 Teste 1: Análise de Conectividade</h2>";
    $database = new DatabaseRobust();
    $connectivity = $database->testConnectivity();
    
    foreach ($connectivity as $result) {
        echo "<h3>📡 {$result['name']}</h3>";
        echo "<p><strong>Host:</strong> {$result['host']}:{$result['port']}</p>";
        
        if ($result['dns_ok']) {
            echo "<div class='success'>✅ DNS OK - IP: {$result['resolved_ip']}</div>";
        } else {
            echo "<div class='error'>❌ DNS Falhou</div>";
        }
        
        if ($result['tcp_ok']) {
            echo "<div class='success'>✅ TCP OK - Tempo: {$result['response_time']}ms</div>";
        } else {
            echo "<div class='error'>❌ TCP Falhou</div>";
        }
        
        if ($result['mysql_ok']) {
            echo "<div class='success'>✅ MySQL OK</div>";
        } else {
            echo "<div class='error'>❌ MySQL Falhou</div>";
        }
        
        if ($result['error']) {
            echo "<div class='warning'>⚠️ Erro: {$result['error']}</div>";
        }
    }
    
    // Teste 2: Conexão real
    echo "<h2>🔌 Teste 2: Estabelecer Conexão</h2>";
    try {
        $conn = $database->getConnection();
        echo "<div class='success'><strong>🎉 CONEXÃO ESTABELECIDA COM SUCESSO!</strong></div>";
        
        // Status da conexão
        $status = $database->getConnectionStatus();
        echo "<h3>📊 Status da Conexão:</h3>";
        echo "<pre>" . json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        // Teste de query
        echo "<h3>🔍 Teste de Query:</h3>";
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            
            echo "<div class='success'>✅ Query executada - Usuários encontrados: $count</div>";
            
            // Mostrar alguns usuários
            if ($count > 0) {
                $stmt = $conn->prepare("SELECT id, email, full_name, user_type FROM users LIMIT 3");
                $stmt->execute();
                $users = $stmt->fetchAll();
                
                echo "<h4>👥 Usuários de Exemplo:</h4>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>ID</th><th>Email</th><th>Nome</th><th>Tipo</th></tr>";
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['email']}</td>";
                    echo "<td>{$user['full_name']}</td>";
                    echo "<td>{$user['user_type']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
        } catch (Exception $e) {
            echo "<div class='warning'>⚠️ Erro na query: " . $e->getMessage() . "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ <strong>Falha na Conexão:</strong><br>";
        echo "Erro: " . $e->getMessage() . "</div>";
    }
    
    // Teste 3: API de Login
    echo "<h2>🔐 Teste 3: API de Login com Conexão Robusta</h2>";
    
    $testUsers = [
        ['email' => 'admin@iguincho.com', 'password' => 'admin123', 'type' => 'Admin'],
        ['email' => 'cliente@iguincho.com', 'password' => 'teste123', 'type' => 'Cliente']
    ];
    
    foreach ($testUsers as $user) {
        echo "<h4>🧪 Testando {$user['type']}: {$user['email']}</h4>";
        
        // Simular request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        try {
            // Capturar output
            ob_start();
            
            // Simular input JSON
            $jsonInput = json_encode([
                'email' => $user['email'],
                'password' => $user['password']
            ]);
            
            // Preparar ambiente para API
            $oldInput = file_get_contents('php://input');
            file_put_contents('php://temp/maxmemory:1048576', $jsonInput);
            
            // Incluir API (isso pode não funcionar perfeitamente devido ao php://input)
            // include 'api/auth/login.php';
            
            $output = ob_get_clean();
            
            if ($output) {
                echo "<div class='info'>📤 Resposta da API:</div>";
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
            } else {
                echo "<div class='warning'>⚠️ Não foi possível simular API (limitação do php://input)</div>";
            }
            
        } catch (Exception $e) {
            ob_end_clean();
            echo "<div class='error'>❌ Erro no teste: " . $e->getMessage() . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ ERRO CRÍTICO:</strong><br>";
    echo "Mensagem: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine();
    echo "</div>";
}

echo "<div class='info'>";
echo "<h3>💡 Resumo:</h3>";
echo "<ul>";
echo "<li>Se todos os testes passaram: problema resolvido!</li>";
echo "<li>Se DNS falhou: problema de rede/internet</li>";
echo "<li>Se TCP falhou: firewall ou Hostinger bloqueou</li>";
echo "<li>Se MySQL falhou: credenciais ou servidor offline</li>";
echo "</ul>";
echo "</div>";

echo "<p><a href='index.html' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 Tentar Login Agora!</a></p>";

echo "</body></html>";
?>