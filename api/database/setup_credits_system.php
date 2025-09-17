<?php
// Setup do Sistema de Créditos
// Este arquivo cria as tabelas necessárias para o sistema de créditos

require_once '../config/database_local.php';

try {
    $database = new DatabaseLocal();
    $pdo = $database->getConnection();
    
    // Ler e executar o arquivo SQL
    $sql = file_get_contents(__DIR__ . '/create_credits_system.sql');
    
    // Executar cada comando SQL separadamente
    $commands = explode(';', $sql);
    
    $pdo->beginTransaction();
    
    foreach ($commands as $command) {
        $command = trim($command);
        if (!empty($command) && !preg_match('/^--/', $command)) {
            try {
                $pdo->exec($command);
                echo "✓ Comando executado com sucesso\n";
            } catch (PDOException $e) {
                // Ignorar erros de tabela já existente
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
                echo "⚠ Comando ignorado (já existe): " . substr($command, 0, 50) . "...\n";
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n✅ Sistema de créditos configurado com sucesso!\n";
    echo "📋 Tabelas criadas:\n";
    echo "   - credit_settings (configurações de crédito)\n";
    echo "   - driver_credits (saldo de créditos dos guincheiros)\n";
    echo "   - credit_transactions (histórico de transações)\n";
    echo "   - pix_credit_requests (solicitações de recarga via PIX)\n";
    echo "⚙️ Configurações padrão inseridas no system_settings\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "\n❌ Erro ao configurar sistema de créditos:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>