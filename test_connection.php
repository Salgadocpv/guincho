<?php
/**
 * Teste de Conexão com Banco de Dados
 */

require_once 'api/config/database.php';

echo "<h2>Teste de Conexão com Banco de Dados</h2>";

try {
    $database = new Database();
    $result = $database->testConnection();
    
    if ($result['status'] === 'success') {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "<strong>✓ Conexão Estabelecida com Sucesso!</strong><br>";
        echo "Ambiente: {$result['environment']}<br>";
        echo "Host: {$result['host']}<br>";
        echo "Database: {$result['database']}<br>";
        echo "Server Info: {$result['server_info']}<br>";
        echo "</div>";
        
        // Testar uma query simples
        $conn = $database->getConnection();
        $query = "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $tableCount = $stmt->fetch()['table_count'];
        
        echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
        echo "<strong>Informações do Banco:</strong><br>";
        echo "Número de tabelas: {$tableCount}<br>";
        echo "</div>";
        
        // Testar tabela de usuários
        try {
            $userQuery = "SELECT COUNT(*) as user_count FROM users";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->execute();
            $userCount = $userStmt->fetch()['user_count'];
            
            echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
            echo "<strong>Tabela users:</strong><br>";
            echo "Número de usuários cadastrados: {$userCount}<br>";
            echo "</div>";
            
            // Mostrar alguns usuários
            if ($userCount > 0) {
                $sampleQuery = "SELECT id, full_name, email, user_type, status FROM users LIMIT 5";
                $sampleStmt = $conn->prepare($sampleQuery);
                $sampleStmt->execute();
                $users = $sampleStmt->fetchAll();
                
                echo "<div style='padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
                echo "<strong>Usuários cadastrados:</strong><br>";
                echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
                echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Status</th></tr>";
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['full_name']}</td>";
                    echo "<td>{$user['email']}</td>";
                    echo "<td>{$user['user_type']}</td>";
                    echo "<td>{$user['status']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
            echo "<strong>⚠️ Tabela users não encontrada ou vazia</strong><br>";
            echo "Erro: " . $e->getMessage() . "<br>";
            echo "Pode ser necessário executar o setup do banco de dados.";
            echo "</div>";
        }
        
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>✗ Erro na Conexão!</strong><br>";
        echo "Ambiente: {$result['environment']}<br>";
        echo "Host: {$result['host']}<br>";
        echo "Database: {$result['database']}<br>";
        echo "Erro: {$result['message']}<br>";
        echo "</div>";
        
        echo "<div style='padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
        echo "<strong>Possíveis soluções:</strong><br>";
        echo "• Verificar se o servidor Hostinger está online<br>";
        echo "• Confirmar credenciais do banco de dados<br>";
        echo "• Verificar se o IP está liberado no Hostinger<br>";
        echo "• Checar se o firewall/antivírus está bloqueando<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>✗ Erro Crítico!</strong><br>";
    echo "Erro: " . $e->getMessage() . "<br>";
    echo "</div>";
}

echo "<p><a href='index.html'>← Voltar para o login</a></p>";
?>