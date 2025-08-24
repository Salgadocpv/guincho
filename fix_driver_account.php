<?php
/**
 * Fix Driver Account - guincheiro@iguincho.com
 * Verifica, cria ou corrige a conta do guincheiro no banco de produção
 */

header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CORREÇÃO CONTA GUINCHEIRO - PRODUÇÃO ===\n\n";

try {
    // Incluir dependências
    include_once 'api/config/database.php';
    
    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();
    echo "✓ Conexão com banco estabelecida (srv1310.hstgr.io)\n\n";
    
    // Verificar se usuário guincheiro@iguincho.com existe
    echo "🔍 Verificando usuário guincheiro@iguincho.com...\n";
    $stmt = $db->prepare("SELECT id, user_type, full_name, email, status, email_verified, created_at FROM users WHERE email = 'guincheiro@iguincho.com'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✓ Usuário encontrado:\n";
        echo "  ID: " . $user['id'] . "\n";
        echo "  Nome: " . $user['full_name'] . "\n";
        echo "  Tipo: " . $user['user_type'] . "\n";
        echo "  Status: " . $user['status'] . "\n";
        echo "  Email verificado: " . ($user['email_verified'] ? 'Sim' : 'Não') . "\n";
        echo "  Criado em: " . $user['created_at'] . "\n\n";
        
        // Verificar senha
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE email = 'guincheiro@iguincho.com'");
        $stmt->execute();
        $password_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify('teste123', $password_data['password_hash'])) {
            echo "✓ Senha 'teste123' é válida\n\n";
        } else {
            echo "❌ Senha 'teste123' NÃO confere - Atualizando...\n";
            $new_hash = password_hash('teste123', PASSWORD_ARGON2I);
            $update_stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'guincheiro@iguincho.com'");
            if ($update_stmt->execute([$new_hash])) {
                echo "✓ Senha atualizada com sucesso\n\n";
            } else {
                echo "❌ Erro ao atualizar senha\n\n";
            }
        }
        
        $user_id = $user['id'];
    } else {
        echo "❌ Usuário NÃO encontrado - Verificando se existe usuário com CPF...\n";
        
        // Verificar se existe usuário com este CPF
        $stmt = $db->prepare("SELECT id, email, full_name, user_type FROM users WHERE cpf = '987.654.321-00'");
        $stmt->execute();
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            echo "⚠️ Usuário encontrado com mesmo CPF:\n";
            echo "  ID: " . $existing_user['id'] . "\n";
            echo "  Email: " . $existing_user['email'] . "\n";
            echo "  Nome: " . $existing_user['full_name'] . "\n";
            echo "  Tipo: " . $existing_user['user_type'] . "\n\n";
            
            if ($existing_user['email'] === 'guincheiro@teste.com') {
                echo "🔄 Atualizando email de guincheiro@teste.com para guincheiro@iguincho.com...\n";
                $stmt = $db->prepare("UPDATE users SET email = 'guincheiro@iguincho.com' WHERE id = ?");
                if ($stmt->execute([$existing_user['id']])) {
                    echo "✓ Email atualizado com sucesso\n\n";
                    $user_id = $existing_user['id'];
                    
                    // Atualizar senha também
                    $password_hash = password_hash('teste123', PASSWORD_ARGON2I);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $user_id]);
                    echo "✓ Senha atualizada\n\n";
                } else {
                    throw new Exception('Erro ao atualizar email');
                }
            } else {
                // Criar com CPF diferente
                $stmt = $db->prepare("
                    INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
                    VALUES ('driver', 'Guincheiro Teste Produção', '987.654.321-99', '1985-05-15', '(11) 88888-8888', 'guincheiro@iguincho.com', ?, TRUE, 'active', TRUE, NOW())
                ");
                $password_hash = password_hash('teste123', PASSWORD_ARGON2I);
                
                if ($stmt->execute([$password_hash])) {
                    $user_id = $db->lastInsertId();
                    echo "✓ Usuário criado com CPF alternativo (ID: $user_id)\n\n";
                } else {
                    throw new Exception('Erro ao criar usuário guincheiro');
                }
            }
        } else {
            // Criar normalmente
            $stmt = $db->prepare("
                INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified, created_at) 
                VALUES ('driver', 'Guincheiro Teste Produção', '987.654.321-00', '1985-05-15', '(11) 88888-8888', 'guincheiro@iguincho.com', ?, TRUE, 'active', TRUE, NOW())
            ");
            $password_hash = password_hash('teste123', PASSWORD_ARGON2I);
            
            if ($stmt->execute([$password_hash])) {
                $user_id = $db->lastInsertId();
                echo "✓ Usuário criado com sucesso (ID: $user_id)\n\n";
            } else {
                throw new Exception('Erro ao criar usuário guincheiro');
            }
        }
    }
    
    // Verificar se tem perfil de driver
    echo "🔍 Verificando perfil de guincheiro...\n";
    $stmt = $db->prepare("SELECT id, approval_status, specialty, work_region FROM drivers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $driver_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($driver_profile) {
        echo "✓ Perfil de guincheiro encontrado:\n";
        echo "  Driver ID: " . $driver_profile['id'] . "\n";
        echo "  Status: " . $driver_profile['approval_status'] . "\n";
        echo "  Especialidade: " . $driver_profile['specialty'] . "\n";
        echo "  Região: " . $driver_profile['work_region'] . "\n\n";
        
        // Garantir que está aprovado
        if ($driver_profile['approval_status'] !== 'approved') {
            echo "⚠️ Status não é 'approved' - Corrigindo...\n";
            $stmt = $db->prepare("UPDATE drivers SET approval_status = 'approved' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo "✓ Status atualizado para 'approved'\n\n";
        }
    } else {
        echo "❌ Perfil de guincheiro NÃO encontrado - Criando...\n";
        
        // Criar perfil de driver
        $stmt = $db->prepare("
            INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, 
                               truck_plate, truck_brand, truck_model, truck_year, truck_capacity, 
                               professional_terms_accepted, background_check_authorized, approval_status, created_at) 
            VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 
                   'TST-1234', 'Ford', 'F-4000', 2020, 'media', 
                   TRUE, TRUE, 'approved', NOW())
        ");
        
        if ($stmt->execute([$user_id])) {
            echo "✓ Perfil de guincheiro criado com sucesso\n\n";
        } else {
            throw new Exception('Erro ao criar perfil de guincheiro');
        }
    }
    
    // Teste de login final
    echo "🧪 Testando login...\n";
    include_once 'api/classes/User.php';
    
    // Fix the path for User.php
    $original_dir = getcwd();
    chdir('api/classes');
    include_once 'User.php';
    chdir($original_dir);
    
    $user = new User();
    $login_result = $user->login('guincheiro@iguincho.com', 'teste123');
    
    if ($login_result['success']) {
        echo "✅ LOGIN FUNCIONANDO!\n";
        echo "  Nome: " . $login_result['user']['full_name'] . "\n";
        echo "  Tipo: " . $login_result['user']['user_type'] . "\n";
        echo "  Token: " . substr($login_result['session_token'], 0, 20) . "...\n\n";
        
        echo "🎉 PROBLEMA RESOLVIDO!\n";
        echo "   ✓ Usuário existe no banco\n";
        echo "   ✓ Senha 'teste123' funciona\n";
        echo "   ✓ Perfil de guincheiro configurado\n";
        echo "   ✓ Status aprovado\n";
        echo "   ✓ Login via API funcionando\n\n";
        
        echo "🔑 CREDENCIAIS VÁLIDAS:\n";
        echo "   Email: guincheiro@iguincho.com\n";
        echo "   Senha: teste123\n";
        
    } else {
        echo "❌ ERRO NO LOGIN: " . $login_result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "💥 ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
}

echo "\n=== FIM DA CORREÇÃO ===\n";
?>