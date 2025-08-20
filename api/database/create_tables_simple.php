<?php
/**
 * Criação de Tabelas - Iguincho (Versão Simplificada)
 * Script para criar tabelas diretamente via PHP
 */

require_once '../config/database.php';

try {
    echo "<h2>🏗️ Criação de Tabelas - Iguincho</h2>\n";
    echo "<pre>\n";
    
    // Conectar ao banco
    echo "📡 Conectando ao banco de dados...\n";
    $database = new Database();
    $conn = $database->getConnection();
    echo "✅ Conexão estabelecida com sucesso!\n\n";
    
    // Array com todas as queries SQL
    $sql_queries = [
        // 1. Tabela de usuários
        "users" => "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_type ENUM('client', 'driver') NOT NULL DEFAULT 'client',
            full_name VARCHAR(255) NOT NULL,
            cpf VARCHAR(14) UNIQUE NOT NULL,
            birth_date DATE NOT NULL,
            phone VARCHAR(20) NOT NULL,
            whatsapp VARCHAR(20),
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            license_plate VARCHAR(10),
            vehicle_brand VARCHAR(100),
            vehicle_model VARCHAR(100),
            vehicle_year INT,
            vehicle_color VARCHAR(50),
            terms_accepted BOOLEAN DEFAULT FALSE,
            marketing_accepted BOOLEAN DEFAULT FALSE,
            status ENUM('active', 'inactive', 'pending_approval', 'suspended') DEFAULT 'active',
            email_verified BOOLEAN DEFAULT FALSE,
            email_verification_token VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_cpf (cpf),
            INDEX idx_user_type (user_type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 2. Tabela de guincheiros
        "drivers" => "
        CREATE TABLE IF NOT EXISTS drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            cnh VARCHAR(20) NOT NULL,
            cnh_category ENUM('B', 'C', 'D', 'E') NOT NULL,
            experience ENUM('0-1', '1-3', '3-5', '5-10', '10+') NOT NULL,
            specialty ENUM('carros', 'motos', 'suv', 'caminhoes', 'todos') NOT NULL,
            work_region VARCHAR(255) NOT NULL,
            availability ENUM('24h', 'comercial', 'noturno', 'fds', 'personalizado') NOT NULL,
            truck_plate VARCHAR(10) NOT NULL,
            truck_brand VARCHAR(100) NOT NULL,
            truck_model VARCHAR(100) NOT NULL,
            truck_year INT NOT NULL,
            truck_capacity ENUM('leve', 'media', 'pesada', 'extra') NOT NULL,
            cnh_photo_path VARCHAR(255),
            crlv_photo_path VARCHAR(255),
            professional_terms_accepted BOOLEAN DEFAULT FALSE,
            background_check_authorized BOOLEAN DEFAULT FALSE,
            approval_status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
            approval_date TIMESTAMP NULL,
            approved_by INT NULL,
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_services INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_cnh (cnh),
            INDEX idx_approval_status (approval_status),
            INDEX idx_specialty (specialty),
            INDEX idx_work_region (work_region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 3. Tabela de sessões
        "user_sessions" => "
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_session_token (session_token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 4. Tabela de logs
        "audit_logs" => "
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100),
            record_id INT,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $created = 0;
    $errors = 0;
    
    // Executar criação de cada tabela
    foreach ($sql_queries as $table_name => $sql) {
        try {
            echo "🔄 Criando tabela: {$table_name}...\n";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            echo "✅ Tabela '{$table_name}' criada com sucesso!\n\n";
            $created++;
        } catch (Exception $e) {
            $errors++;
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "ℹ️  Tabela '{$table_name}' já existe.\n\n";
                $created++; // Contar como sucesso
            } else {
                echo "❌ Erro ao criar tabela '{$table_name}': " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    // Verificar tabelas criadas
    echo "🔍 Verificando tabelas criadas...\n";
    $tables_query = "SHOW TABLES";
    $stmt = $conn->prepare($tables_query);
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tabelas encontradas no banco:\n";
    foreach ($tables as $table) {
        echo "  ✓ {$table}\n";
    }
    
    // Verificar estrutura das tabelas principais
    $main_tables = ['users', 'drivers', 'user_sessions', 'audit_logs'];
    echo "\n🏗️  Verificando estrutura das tabelas principais...\n";
    
    foreach ($main_tables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ Tabela '{$table}' encontrada!\n";
            
            // Contar registros
            try {
                $count_query = "SELECT COUNT(*) as total FROM {$table}";
                $count_stmt = $conn->prepare($count_query);
                $count_stmt->execute();
                $count = $count_stmt->fetch()['total'];
                echo "   📋 Registros: {$count}\n";
            } catch (Exception $e) {
                echo "   ⚠️  Erro ao contar registros: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ Tabela '{$table}' não encontrada!\n";
        }
    }
    
    // Resumo final
    echo "\n📈 RESUMO DA CRIAÇÃO:\n";
    echo "✅ Tabelas processadas: " . count($sql_queries) . "\n";
    echo "✅ Tabelas criadas/existentes: {$created}\n";
    echo "❌ Erros encontrados: {$errors}\n";
    echo "📊 Total de tabelas no banco: " . count($tables) . "\n";
    
    if ($errors === 0 && $created === count($sql_queries)) {
        echo "\n🎉 TODAS AS TABELAS FORAM CRIADAS COM SUCESSO!\n";
        echo "✅ Banco de dados do Iguincho está pronto para uso!\n";
        echo "🚀 Próximo passo: Inserir usuários de teste\n";
    } else {
        echo "\n⚠️  Algumas tabelas podem não ter sido criadas corretamente.\n";
        echo "🔄 Execute o script novamente se necessário.\n";
    }
    
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<pre>\n";
    echo "❌ ERRO CRÍTICO NA CRIAÇÃO DAS TABELAS:\n";
    echo "📝 Mensagem: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    echo "\n🔧 Verifique:\n";
    echo "  • Configurações do banco em config/database.php\n";
    echo "  • Permissões de acesso ao banco\n";
    echo "  • Se o usuário do banco tem privilégios CREATE TABLE\n";
    echo "</pre>\n";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Criação de Tabelas - Iguincho</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        pre { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
    <p>
        <a href="insert_test_users.php">👥 Inserir Usuários de Teste</a> | 
        <a href="../test/api-test.php">🧪 Testar API</a> | 
        <a href="../../index.html">🏠 Voltar ao App</a>
    </p>
</body>
</html>