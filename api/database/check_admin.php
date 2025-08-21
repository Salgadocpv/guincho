<?php
/**
 * Script para verificar o usuário admin no banco de dados
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar dados do admin
    $sql = "SELECT id, user_type, full_name, email, status, created_at FROM users WHERE email = 'admin@iguincho.com'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✅ Usuário admin encontrado:\n";
        echo "ID: " . $admin['id'] . "\n";
        echo "Nome: " . $admin['full_name'] . "\n";
        echo "Email: " . $admin['email'] . "\n";
        echo "Tipo: '" . $admin['user_type'] . "'\n";
        echo "Status: " . $admin['status'] . "\n";
        echo "Criado em: " . $admin['created_at'] . "\n";
        
        // Verificar se user_type está vazio ou NULL
        if (empty($admin['user_type'])) {
            echo "\n❌ PROBLEMA: user_type está vazio!\n";
            echo "Corrigindo...\n";
            
            $updateSql = "UPDATE users SET user_type = 'admin' WHERE email = 'admin@iguincho.com'";
            $updateStmt = $conn->prepare($updateSql);
            $result = $updateStmt->execute();
            
            if ($result) {
                echo "✅ user_type corrigido para 'admin'\n";
            } else {
                echo "❌ Erro ao corrigir user_type\n";
            }
        } else {
            echo "\n✅ user_type está correto: '" . $admin['user_type'] . "'\n";
        }
    } else {
        echo "❌ Usuário admin não encontrado!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>