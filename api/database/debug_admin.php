<?php
/**
 * Debug detalhado do usuário admin
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "🔍 DEBUG DETALHADO - Usuário Admin\n";
    echo "================================\n\n";
    
    // 1. Verificar todos os usuários admin
    echo "1. Listando todos os usuários com email admin:\n";
    $sql = "SELECT * FROM users WHERE email LIKE '%admin%'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Email: {$user['email']} | Tipo: '{$user['user_type']}' | Status: {$user['status']}\n";
    }
    
    echo "\n";
    
    // 2. Verificar especificamente admin@iguincho.com
    echo "2. Dados específicos do admin@iguincho.com:\n";
    $sql = "SELECT id, user_type, full_name, email, status, LENGTH(user_type) as type_length, HEX(user_type) as type_hex FROM users WHERE email = 'admin@iguincho.com'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "ID: {$admin['id']}\n";
        echo "Nome: {$admin['full_name']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Tipo: '{$admin['user_type']}'\n";
        echo "Status: {$admin['status']}\n";
        echo "Comprimento do tipo: {$admin['type_length']}\n";
        echo "HEX do tipo: {$admin['type_hex']}\n";
        
        // 3. Tentar forçar atualização
        echo "\n3. Forçando atualização do user_type:\n";
        $updateSql = "UPDATE users SET user_type = 'admin' WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $result = $updateStmt->execute(['id' => $admin['id']]);
        
        if ($result) {
            echo "✅ Atualização executada\n";
            
            // Verificar novamente
            $checkSql = "SELECT user_type FROM users WHERE id = :id";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute(['id' => $admin['id']]);
            $newType = $checkStmt->fetchColumn();
            
            echo "Novo tipo: '{$newType}'\n";
        } else {
            echo "❌ Erro na atualização\n";
        }
        
    } else {
        echo "❌ Admin não encontrado!\n";
    }
    
    // 4. Verificar estrutura da tabela
    echo "\n4. Estrutura da coluna user_type:\n";
    $describeSql = "DESCRIBE users";
    $describeStmt = $conn->prepare($describeSql);
    $describeStmt->execute();
    
    $columns = $describeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'user_type') {
            echo "Campo: {$column['Field']}\n";
            echo "Tipo: {$column['Type']}\n";
            echo "Nulo: {$column['Null']}\n";
            echo "Padrão: {$column['Default']}\n";
            break;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>