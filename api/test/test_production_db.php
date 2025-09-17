<?php
/**
 * Teste de Conexão com Banco de Produção
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    echo "<h2>🔧 Teste de Conexão - Banco de Produção</h2>";
    echo "<pre>";
    
    // Informações do ambiente
    echo "📍 Ambiente: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n";
    echo "📂 Script: " . __FILE__ . "\n";
    echo "🕒 Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Testar database_auto.php
    echo "🔄 Testando database_auto.php...\n";
    include_once '../config/database_auto.php';
    
    $database = new DatabaseAuto();
    echo "✅ Classe DatabaseAuto carregada\n";
    
    // Informações do ambiente detectado
    $envInfo = $database->getEnvironmentInfo();
    echo "🌍 Ambiente detectado: " . $envInfo['environment'] . "\n";
    echo "🏠 Host: " . $envInfo['host'] . "\n";
    echo "💾 Database: " . $envInfo['database'] . "\n";
    echo "👤 Username: " . $envInfo['username'] . "\n";
    echo "🌐 Server Name: " . $envInfo['server_name'] . "\n";
    echo "🔗 HTTP Host: " . $envInfo['http_host'] . "\n\n";
    
    // Testar conexão
    echo "🔄 Tentando conectar ao banco...\n";
    $db = $database->getConnection();
    echo "✅ Conexão estabelecida com sucesso!\n\n";
    
    // Testar query simples
    echo "🔄 Testando query simples...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Total de usuários no banco: " . $result['total'] . "\n\n";
    
    // Testar usuários drivers
    echo "🔄 Testando usuários drivers...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'driver'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Total de drivers: " . $result['total'] . "\n\n";
    
    // Testar tabela drivers
    echo "🔄 Testando tabela drivers...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM drivers");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Total de registros drivers: " . $result['total'] . "\n\n";
    
    // Listar estrutura das tabelas
    echo "🔄 Verificando estrutura das tabelas...\n";
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Tabelas encontradas:\n";
    foreach ($tables as $table) {
        echo "  ✓ {$table}\n";
    }
    echo "\n";
    
    // Testar dados de um driver específico
    echo "🔄 Testando dados de driver específico...\n";
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, u.user_type, 
               d.cnh, d.approval_status, d.specialty
        FROM users u 
        LEFT JOIN drivers d ON u.id = d.user_id 
        WHERE u.user_type = 'driver' 
        LIMIT 1
    ");
    $stmt->execute();
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($driver) {
        echo "✅ Driver encontrado:\n";
        echo "  👤 Nome: " . $driver['full_name'] . "\n";
        echo "  📧 Email: " . $driver['email'] . "\n";
        echo "  🚗 CNH: " . ($driver['cnh'] ?? 'N/A') . "\n";
        echo "  ✅ Status: " . ($driver['approval_status'] ?? 'N/A') . "\n";
        echo "  🔧 Especialidade: " . ($driver['specialty'] ?? 'N/A') . "\n";
    } else {
        echo "⚠️ Nenhum driver encontrado\n";
    }
    
    echo "\n🎉 TESTE CONCLUÍDO COM SUCESSO!\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>";
    echo "❌ ERRO NO TESTE:\n";
    echo "📝 Mensagem: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . "\n";
    echo "📄 Linha: " . $e->getLine() . "\n";
    echo "\n🔧 Stack Trace:\n";
    echo $e->getTraceAsString();
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teste Conexão Banco - Produção</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        pre { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
    </style>
</head>
<body>
    <p>
        <a href="../profile/get_profile_simple.php?type=driver">🧪 Testar API Profile</a> | 
        <a href="../../index.html">🏠 Voltar ao App</a>
    </p>
</body>
</html>