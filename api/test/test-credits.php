<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Teste do Sistema de Créditos</h2>";

try {
    // Test database connection
    echo "<h3>1. Testando conexão com banco de dados</h3>";
    require_once '../config/database_local.php';
    $database = new DatabaseLocal();
    $pdo = $database->getConnection();
    echo "✅ Conexão com banco OK<br><br>";
    
    // Test if tables exist
    echo "<h3>2. Verificando tabelas do sistema de créditos</h3>";
    $tables = ['credit_settings', 'driver_credits', 'credit_transactions', 'pix_credit_requests'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "✅ Tabela {$table} existe<br>";
        } else {
            echo "❌ Tabela {$table} NÃO existe<br>";
        }
    }
    echo "<br>";
    
    // Test system_settings
    echo "<h3>3. Verificando configurações PIX no system_settings</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_settings WHERE category LIKE 'pix_%' OR setting_key LIKE '%pix%'");
    $stmt->execute();
    $pixSettingsCount = $stmt->fetch()['count'];
    echo "📋 Encontradas {$pixSettingsCount} configurações PIX<br><br>";
    
    // Test Auth
    echo "<h3>4. Testando autenticação</h3>";
    require_once '../middleware/AuthSimple.php';
    $auth = new Auth();
    
    // Try to get a test user
    $user = $auth->validateToken('test');
    if ($user) {
        echo "✅ Autenticação OK - Usuário: {$user['full_name']} (Tipo: {$user['user_type']})<br>";
        
        // Test if user is driver
        if ($user['user_type'] === 'driver') {
            $stmt = $pdo->prepare("SELECT id FROM drivers WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $driver = $stmt->fetch();
            
            if ($driver) {
                echo "✅ Driver ID encontrado: {$driver['id']}<br>";
            } else {
                echo "❌ Driver ID não encontrado<br>";
            }
        }
    } else {
        echo "❌ Falha na autenticação<br>";
    }
    echo "<br>";
    
    // Test CreditSystem
    echo "<h3>5. Testando sistema de créditos</h3>";
    require_once '../classes/CreditSystem.php';
    $creditSystem = new CreditSystem($pdo);
    
    if ($user && $user['user_type'] === 'driver') {
        $stmt = $pdo->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $driver = $stmt->fetch();
        
        if ($driver) {
            $driver_id = $driver['id'];
            
            try {
                $credits = $creditSystem->getDriverCredits($driver_id);
                echo "✅ Créditos obtidos - Saldo: R$ {$credits['current_balance']}<br>";
                
                $settings = $creditSystem->getCreditSettings();
                echo "✅ Configurações obtidas - Crédito por viagem: R$ {$settings['credit_per_trip']}<br>";
                
                $canAccept = $creditSystem->canAcceptTrip($driver_id);
                echo "✅ Pode aceitar viagem: " . ($canAccept ? 'Sim' : 'Não') . "<br>";
                
            } catch (Exception $e) {
                echo "❌ Erro no sistema de créditos: " . $e->getMessage() . "<br>";
            }
        }
    }
    echo "<br>";
    
    echo "<h3>6. Resultado</h3>";
    echo "✅ Teste concluído! Sistema parece estar funcionando.";
    
} catch (Exception $e) {
    echo "❌ ERRO FATAL: " . $e->getMessage();
    echo "<br>Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>