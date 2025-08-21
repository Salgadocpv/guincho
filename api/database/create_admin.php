<?php
/**
 * Script para criar usuário administrador
 * Execute este script para garantir que o usuário admin existe
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar se admin já existe
    $checkSql = "SELECT COUNT(*) FROM users WHERE email = 'admin@iguincho.com'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() > 0) {
        echo "✅ Usuário admin já existe no banco de dados.\n";
        
        // Atualizar senha se necessário
        $newPasswordHash = password_hash('admin123', PASSWORD_ARGON2I);
        $updateSql = "UPDATE users SET password_hash = :hash WHERE email = 'admin@iguincho.com'";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute(['hash' => $newPasswordHash]);
        
        echo "✅ Senha atualizada para 'admin123'.\n";
    } else {
        // Criar usuário admin
        $passwordHash = password_hash('admin123', PASSWORD_ARGON2I);
        
        $insertSql = "INSERT INTO users 
                     (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
                     VALUES 
                     ('admin', 'Administrador Master', '000.000.000-00', '1990-01-01', '(11) 99999-9999', 'admin@iguincho.com', :hash, TRUE, 'active', TRUE, NOW())";
        
        $insertStmt = $conn->prepare($insertSql);
        $result = $insertStmt->execute(['hash' => $passwordHash]);
        
        if ($result) {
            echo "✅ Usuário administrador criado com sucesso!\n";
        } else {
            echo "❌ Erro ao criar usuário administrador.\n";
        }
    }
    
    // Exibir credenciais
    echo "\n📋 CREDENCIAIS DO ADMINISTRADOR:\n";
    echo "E-mail: admin@iguincho.com\n";
    echo "Senha:  admin123\n";
    echo "Tipo:   admin\n\n";
    
    echo "🔗 Acesse: http://localhost/guincho/\n";
    echo "As credenciais já estão pré-preenchidas na tela de login.\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>