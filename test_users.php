<?php
require_once 'api/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    echo "<h2>Usuários existentes no sistema:</h2>";
    
    $query = "SELECT id, full_name, email, user_type, status FROM users ORDER BY user_type, full_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo "<p>Nenhum usuário encontrado no banco de dados.</p>";
        echo "<h3>Criando usuários de teste...</h3>";
        
        // Criar usuários de teste
        $testUsers = [
            [
                'full_name' => 'Cliente Teste',
                'email' => 'cliente@iguincho.com',
                'password' => password_hash('teste123', PASSWORD_DEFAULT),
                'user_type' => 'client',
                'status' => 'active'
            ],
            [
                'full_name' => 'Guincheiro Teste',
                'email' => 'guincheiro@iguincho.com',
                'password' => password_hash('teste123', PASSWORD_DEFAULT),
                'user_type' => 'driver',
                'status' => 'active'
            ],
            [
                'full_name' => 'Admin Teste',
                'email' => 'admin@iguincho.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'user_type' => 'admin',
                'status' => 'active'
            ]
        ];
        
        $insertQuery = "INSERT INTO users (full_name, email, password, user_type, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertQuery);
        
        foreach ($testUsers as $user) {
            $insertStmt->execute([
                $user['full_name'],
                $user['email'],
                $user['password'],
                $user['user_type'],
                $user['status']
            ]);
            echo "<p>✓ Usuário criado: {$user['email']} ({$user['user_type']})</p>";
        }
        
        echo "<p><strong>Usuários de teste criados com sucesso!</strong></p>";
        echo "<p><a href='index.html'>← Voltar para o login</a></p>";
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
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
        echo "<p><a href='index.html'>← Voltar para o login</a></p>";
    }

} catch (Exception $e) {
    echo "<p>Erro: " . $e->getMessage() . "</p>";
}
?>