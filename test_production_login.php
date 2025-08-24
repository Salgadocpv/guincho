<?php
/**
 * Test Production Login
 * Testa o login com a conta cliente@iguincho.com no ambiente de produção
 */

header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE DE LOGIN EM PRODUÇÃO ===\n\n";

try {
    // Incluir dependências
    include_once 'api/config/database.php';
    
    // Fix the path for User.php that has a relative include
    $original_dir = getcwd();
    chdir('api/classes');
    include_once 'User.php';
    chdir($original_dir);
    
    // Testar conexão com banco
    $database = new Database();
    $db = $database->getConnection();
    echo "✓ Conexão com banco estabelecida\n";
    echo "  Host: srv1310.hstgr.io\n";
    echo "  Database: u461266905_guincho\n\n";
    
    // Verificar se usuário existe
    $stmt = $db->prepare("SELECT id, user_type, full_name, email, status, email_verified, created_at FROM users WHERE email = 'cliente@iguincho.com'");
    $stmt->execute();
    $user_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_check) {
        echo "✓ Usuário encontrado no banco:\n";
        echo "  ID: " . $user_check['id'] . "\n";
        echo "  Tipo: " . $user_check['user_type'] . "\n";
        echo "  Nome: " . $user_check['full_name'] . "\n";
        echo "  Status: " . $user_check['status'] . "\n";
        echo "  Email verificado: " . ($user_check['email_verified'] ? 'Sim' : 'Não') . "\n";
        echo "  Criado em: " . $user_check['created_at'] . "\n\n";
        
        // Verificar hash da senha
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE email = 'cliente@iguincho.com'");
        $stmt->execute();
        $password_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify('teste123', $password_data['password_hash'])) {
            echo "✓ Senha 'teste123' é válida para este usuário\n\n";
        } else {
            echo "✗ PROBLEMA: Senha 'teste123' não confere com o hash armazenado\n";
            echo "  Hash atual: " . substr($password_data['password_hash'], 0, 50) . "...\n\n";
            
            // Tentar atualizar a senha
            echo "Tentando atualizar senha...\n";
            $new_hash = password_hash('teste123', PASSWORD_ARGON2I);
            $update_stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'cliente@iguincho.com'");
            if ($update_stmt->execute([$new_hash])) {
                echo "✓ Senha atualizada com sucesso\n\n";
            } else {
                echo "✗ Erro ao atualizar senha\n\n";
            }
        }
    } else {
        echo "✗ PROBLEMA: Usuário cliente@iguincho.com NÃO encontrado no banco\n";
        echo "Criando usuário...\n";
        
        $stmt = $db->prepare("
            INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
            VALUES ('client', 'Cliente Teste Produção', '123.456.789-00', '1990-01-01', '(11) 99999-9999', 'cliente@iguincho.com', ?, TRUE, 'active', TRUE, NOW())
        ");
        $password_hash = password_hash('teste123', PASSWORD_ARGON2I);
        if ($stmt->execute([$password_hash])) {
            echo "✓ Usuário criado com sucesso\n\n";
        } else {
            echo "✗ Erro ao criar usuário\n\n";
        }
    }
    
    // Teste de login via API
    echo "=== TESTE DE LOGIN VIA API ===\n";
    
    $user = new User();
    $login_result = $user->login('cliente@iguincho.com', 'teste123');
    
    if ($login_result['success']) {
        echo "✓ Login bem-sucedido via API!\n";
        echo "  Token: " . substr($login_result['session_token'], 0, 20) . "...\n";
        echo "  Expira em: " . $login_result['expires_at'] . "\n";
        echo "  Usuário: " . $login_result['user']['full_name'] . "\n";
        echo "  Tipo: " . $login_result['user']['user_type'] . "\n\n";
    } else {
        echo "✗ FALHA no login via API\n";
        echo "  Erro: " . $login_result['message'] . "\n\n";
    }
    
    // Verificar tabelas necessárias
    echo "=== VERIFICAÇÃO DE TABELAS ===\n";
    $tables = ['users', 'user_sessions', 'trip_requests', 'drivers', 'system_settings'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "✓ Tabela $table existe\n";
        } else {
            echo "✗ Tabela $table NÃO existe\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
    echo "  Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>