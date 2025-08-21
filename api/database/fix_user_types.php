<?php
/**
 * Script para corrigir os tipos de usuário na tabela
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "🔧 Corrigindo tipos de usuário na tabela...\n\n";
    
    // 1. Alterar a estrutura da tabela para incluir admin e partner
    echo "1. Atualizando ENUM da coluna user_type...\n";
    
    $alterSql = "ALTER TABLE users MODIFY COLUMN user_type ENUM('client', 'driver', 'partner', 'admin') NOT NULL DEFAULT 'client'";
    $result = $conn->exec($alterSql);
    
    echo "✅ Estrutura da tabela atualizada\n\n";
    
    // 2. Verificar a nova estrutura
    echo "2. Nova estrutura da coluna user_type:\n";
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
    
    echo "\n";
    
    // 3. Agora atualizar o usuário admin
    echo "3. Definindo user_type como 'admin' para admin@iguincho.com...\n";
    
    $updateSql = "UPDATE users SET user_type = 'admin' WHERE email = 'admin@iguincho.com'";
    $updateStmt = $conn->prepare($updateSql);
    $result = $updateStmt->execute();
    
    if ($result) {
        echo "✅ Usuário admin atualizado com sucesso\n";
        
        // Verificar se funcionou
        $checkSql = "SELECT user_type FROM users WHERE email = 'admin@iguincho.com'";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute();
        $userType = $checkStmt->fetchColumn();
        
        echo "Tipo de usuário agora: '{$userType}'\n";
    } else {
        echo "❌ Erro ao atualizar usuário admin\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>