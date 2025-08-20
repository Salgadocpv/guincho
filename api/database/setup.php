<?php
/**
 * Setup do Banco de Dados - Iguincho
 * Script para criar as tabelas do sistema
 */

require_once '../config/database.php';

try {
    echo "<h2>🚀 Setup do Banco de Dados - Iguincho</h2>\n";
    echo "<pre>\n";
    
    // Conectar ao banco
    echo "📡 Conectando ao banco de dados...\n";
    $database = new Database();
    $conn = $database->getConnection();
    echo "✅ Conexão estabelecida com sucesso!\n\n";
    
    // Ler arquivo SQL
    echo "📄 Lendo script de criação das tabelas...\n";
    $sql_file = __DIR__ . '/create_tables.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Arquivo SQL não encontrado: {$sql_file}");
    }
    
    $sql_content = file_get_contents($sql_file);
    echo "✅ Script SQL carregado!\n\n";
    
    // Dividir em comandos individuais
    $sql_commands = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($cmd) { return !empty($cmd) && !preg_match('/^--/', $cmd); }
    );
    
    echo "🔧 Executando comandos SQL...\n";
    echo "Total de comandos: " . count($sql_commands) . "\n\n";
    
    $executed = 0;
    $errors = 0;
    
    foreach ($sql_commands as $i => $command) {
        try {
            if (trim($command)) {
                // Mostrar resumo do comando
                $first_line = strtok($command, "\n");
                $command_type = strtoupper(strtok($first_line, " "));
                
                echo "🔄 Executando: {$command_type}";
                if (preg_match('/CREATE TABLE.*?(\w+)/i', $first_line, $matches)) {
                    echo " (tabela: {$matches[1]})";
                } elseif (preg_match('/INSERT INTO.*?(\w+)/i', $first_line, $matches)) {
                    echo " (dados em: {$matches[1]})";
                }
                echo "\n";
                
                $stmt = $conn->prepare($command);
                $stmt->execute();
                $executed++;
                echo "✅ Sucesso!\n\n";
            }
        } catch (Exception $e) {
            $errors++;
            echo "❌ Erro: " . $e->getMessage() . "\n\n";
            
            // Se for erro de tabela já existir, continuar
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "⚠️  Erro crítico encontrado. Parando execução.\n";
                break;
            } else {
                echo "ℹ️  Tabela já existe, continuando...\n\n";
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
            $desc_query = "DESCRIBE {$table}";
            $stmt = $conn->prepare($desc_query);
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "📋 Tabela '{$table}' - {$stmt->rowCount()} colunas:\n";
            foreach ($columns as $column) {
                echo "  • {$column['Field']} ({$column['Type']})";
                if ($column['Key'] === 'PRI') echo " [PRIMARY KEY]";
                if ($column['Key'] === 'MUL') echo " [INDEX]";
                echo "\n";
            }
            echo "\n";
        } else {
            echo "❌ Tabela '{$table}' não encontrada!\n\n";
        }
    }
    
    // Resumo final
    echo "📈 RESUMO DO SETUP:\n";
    echo "✅ Comandos executados: {$executed}\n";
    echo "❌ Erros encontrados: {$errors}\n";
    echo "📊 Tabelas no banco: " . count($tables) . "\n";
    
    if ($errors === 0) {
        echo "\n🎉 SETUP CONCLUÍDO COM SUCESSO!\n";
        echo "✅ Banco de dados do Iguincho está pronto para uso!\n";
    } else {
        echo "\n⚠️  Setup concluído com alguns erros.\n";
        echo "ℹ️  Verifique os erros acima e execute novamente se necessário.\n";
    }
    
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<pre>\n";
    echo "❌ ERRO CRÍTICO NO SETUP:\n";
    echo "📝 Mensagem: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    echo "\n🔧 Verifique:\n";
    echo "  • Configurações do banco em config/database.php\n";
    echo "  • Permissões de acesso ao banco\n";
    echo "  • Se o arquivo create_tables.sql existe\n";
    echo "</pre>\n";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup - Iguincho Database</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        pre { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
    </style>
</head>
<body>
    <p><a href="../test/api-test.php">🧪 Testar API</a> | <a href="../../index.html">🏠 Voltar ao App</a></p>
</body>
</html>