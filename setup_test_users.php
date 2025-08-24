<?php
/**
 * Setup de usuários de teste
 */
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Setup Usuários</title></head><body>";
echo "<h2>Setup de Usuários de Teste</h2>";

try {
    // Incluir classe de database
    require_once 'api/config/database.php';
    
    echo "<p>✓ Arquivo database.php carregado</p>";
    
    // Testar conexão
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<p>✓ Conexão com banco estabelecida</p>";
    echo "<p>Host: srv1310.hstgr.io</p>";
    echo "<p>Database: u461266905_guincho</p>";
    
    // Verificar se tabela users existe
    $checkTable = "SHOW TABLES LIKE 'users'";
    $stmt = $conn->prepare($checkTable);
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>❌ Tabela 'users' não existe!</strong><br>";
        echo "Execute o script de criação do banco de dados primeiro.";
        echo "</div>";
        exit;
    }
    
    echo "<p>✓ Tabela 'users' existe</p>";
    
    // Verificar usuários existentes
    $queryCount = "SELECT COUNT(*) as count FROM users";
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->execute();
    $userCount = $stmtCount->fetch()['count'];
    
    echo "<p>Usuários existentes: {$userCount}</p>";
    
    // Verificar usuários de teste específicos
    $testUsers = [
        [
            'email' => 'admin@iguincho.com',
            'password' => 'admin123',
            'full_name' => 'Admin Sistema',
            'user_type' => 'admin'
        ],
        [
            'email' => 'cliente@iguincho.com',
            'password' => 'teste123',
            'full_name' => 'Cliente Teste',
            'user_type' => 'client'
        ],
        [
            'email' => 'guincheiro@iguincho.com',
            'password' => 'teste123',
            'full_name' => 'Guincheiro Teste',
            'user_type' => 'driver'
        ]
    ];
    
    foreach ($testUsers as $user) {
        // Verificar se usuário já existe
        $checkUser = "SELECT id FROM users WHERE email = ?";
        $stmtCheck = $conn->prepare($checkUser);
        $stmtCheck->execute([$user['email']]);
        
        if ($stmtCheck->rowCount() > 0) {
            echo "<p>✓ Usuário {$user['email']} já existe</p>";
        } else {
            // Criar usuário
            $insertUser = "INSERT INTO users (email, password, full_name, user_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())";
            $stmtInsert = $conn->prepare($insertUser);
            $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
            
            if ($stmtInsert->execute([$user['email'], $passwordHash, $user['full_name'], $user['user_type']])) {
                echo "<p>✅ Usuário {$user['email']} criado com sucesso</p>";
            } else {
                echo "<p>❌ Erro ao criar {$user['email']}</p>";
            }
        }
    }
    
    // Mostrar todos os usuários
    echo "<h3>Usuários no sistema:</h3>";
    $allUsers = "SELECT id, email, full_name, user_type, status FROM users ORDER BY user_type, full_name";
    $stmtAll = $conn->prepare($allUsers);
    $stmtAll->execute();
    $users = $stmtAll->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Email</th><th>Nome</th><th>Tipo</th><th>Status</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>{$u['id']}</td>";
            echo "<td>{$u['email']}</td>";
            echo "<td>{$u['full_name']}</td>";
            echo "<td>{$u['user_type']}</td>";
            echo "<td>{$u['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<div style='color: green; padding: 15px; border: 2px solid green; margin: 20px 0; text-align: center;'>";
    echo "<strong>🎉 Setup concluído com sucesso!</strong><br>";
    echo "Agora você pode testar o login com os usuários criados.";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>❌ Erro:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
    echo "Arquivo: " . $e->getFile();
    echo "</div>";
}

echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Voltar para Login</a></p>";
echo "</body></html>";
?>