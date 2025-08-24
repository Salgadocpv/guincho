<?php
/**
 * Setup do Banco Local - Execute apenas uma vez
 */
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Setup Banco Local</title>";
echo "<style>
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔧 Setup do Banco de Dados Local</h1>";

try {
    require_once 'api/config/database_auto.php';
    
    $database = new DatabaseAuto();
    $envInfo = $database->getEnvironmentInfo();
    
    echo "<div class='info'>";
    echo "<h3>📋 Informações do Ambiente:</h3>";
    echo "<p><strong>Ambiente:</strong> {$envInfo['environment']}</p>";
    echo "<p><strong>Host:</strong> {$envInfo['host']}</p>";
    echo "<p><strong>Database:</strong> {$envInfo['database']}</p>";
    echo "<p><strong>Username:</strong> {$envInfo['username']}</p>";
    echo "<p><strong>Server Name:</strong> {$envInfo['server_name']}</p>";
    echo "<p><strong>HTTP Host:</strong> {$envInfo['http_host']}</p>";
    echo "</div>";
    
    if ($envInfo['environment'] === 'local') {
        echo "<h2>🏠 Configurando Ambiente Local</h2>";
        
        try {
            $result = $database->setupLocalDatabase();
            
            echo "<div class='success'>";
            echo "<h3>🎉 {$result['message']}</h3>";
            echo "<p>Database criado: <strong>{$result['database']}</strong></p>";
            echo "</div>";
            
            // Verificar usuários criados
            $conn = $database->getConnection();
            $stmt = $conn->query("SELECT email, full_name, user_type FROM users");
            $users = $stmt->fetchAll();
            
            if (count($users) > 0) {
                echo "<div class='info'>";
                echo "<h3>👥 Usuários de Teste Criados:</h3>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                echo "<tr style='background: #f0f0f0;'><th>Email</th><th>Nome</th><th>Tipo</th><th>Senha</th></tr>";
                
                $passwords = [
                    'admin@iguincho.com' => 'admin123',
                    'cliente@iguincho.com' => 'teste123',
                    'guincheiro@iguincho.com' => 'teste123'
                ];
                
                foreach ($users as $user) {
                    $password = $passwords[$user['email']] ?? 'N/A';
                    echo "<tr>";
                    echo "<td>{$user['email']}</td>";
                    echo "<td>{$user['full_name']}</td>";
                    echo "<td>{$user['user_type']}</td>";
                    echo "<td><strong>{$password}</strong></td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            }
            
            echo "<div class='success'>";
            echo "<h3>✅ Setup Concluído!</h3>";
            echo "<p>Agora você pode testar o login localmente sem consumir recursos da Hostinger.</p>";
            echo "<p>O sistema detectará automaticamente o ambiente e usará:</p>";
            echo "<ul>";
            echo "<li><strong>Local:</strong> localhost/guincho_local (XAMPP)</li>";
            echo "<li><strong>Produção:</strong> Hostinger (quando fizer push)</li>";
            echo "</ul>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<h3>❌ Erro no Setup:</h3>";
            echo "<p>{$e->getMessage()}</p>";
            
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                echo "<div class='warning'>";
                echo "<h4>🔧 Como resolver:</h4>";
                echo "<ol>";
                echo "<li>Abra o XAMPP Control Panel</li>";
                echo "<li>Inicie os serviços <strong>Apache</strong> e <strong>MySQL</strong></li>";
                echo "<li>Clique em <strong>Admin</strong> do MySQL para abrir phpMyAdmin</li>";
                echo "<li>Certifique-se que o usuário 'root' não tem senha</li>";
                echo "<li>Execute este script novamente</li>";
                echo "</ol>";
                echo "</div>";
            }
            echo "</div>";
        }
        
    } else {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Ambiente de Produção Detectado</h3>";
        echo "<p>Este script só pode ser executado em ambiente local.</p>";
        echo "<p>Em produção, o sistema usará automaticamente a Hostinger.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Erro Crítico:</h3>";
    echo "<p>{$e->getMessage()}</p>";
    echo "</div>";
}

echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 Ir para Login</a></p>";
echo "</body></html>";
?>