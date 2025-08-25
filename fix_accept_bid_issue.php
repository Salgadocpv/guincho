<?php
/**
 * Fix Accept Bid Issue
 * Script para corrigir o problema de "Esta solicitação não pertence a você"
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔧 Fix Accept Bid Issue</h1>";

try {
    // Incluir dependências
    include_once 'api/config/database.php';
    
    echo "<h2>1. Conectando ao banco...</h2>";
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Conectado<br>";
    
    echo "<h2>2. Verificando usuários de teste...</h2>";
    
    // Verificar se existem usuários de teste
    $stmt = $db->prepare("SELECT id, email, user_type FROM users WHERE email IN ('cliente@iguincho.com', 'guincheiro@iguincho.com', 'admin@iguincho.com')");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Usuários encontrados:<br>";
    foreach ($users as $user) {
        echo "- ID: {$user['id']}, Email: {$user['email']}, Type: {$user['user_type']}<br>";
    }
    
    // Verificar trip_requests órfãos
    echo "<h2>3. Verificando trip_requests órfãos...</h2>";
    $stmt = $db->prepare("
        SELECT tr.id, tr.client_id, u.email 
        FROM trip_requests tr 
        LEFT JOIN users u ON tr.client_id = u.id 
        WHERE u.id IS NULL
        LIMIT 10
    ");
    $stmt->execute();
    $orphan_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orphan_requests) > 0) {
        echo "❌ Encontrados " . count($orphan_requests) . " trip_requests órfãos:<br>";
        foreach ($orphan_requests as $req) {
            echo "- Request ID: {$req['id']}, Client ID inexistente: {$req['client_id']}<br>";
        }
        
        // Limpar trip_requests órfãos
        echo "<h3>Limpando trip_requests órfãos...</h3>";
        $stmt = $db->prepare("DELETE FROM trip_requests WHERE client_id NOT IN (SELECT id FROM users)");
        $deleted = $stmt->execute();
        echo $deleted ? "✅ Trip requests órfãos removidos<br>" : "❌ Erro ao remover<br>";
    } else {
        echo "✅ Nenhum trip_request órfão encontrado<br>";
    }
    
    // Verificar trip_bids órfãos
    echo "<h2>4. Verificando trip_bids órfãos...</h2>";
    $stmt = $db->prepare("
        SELECT tb.id, tb.driver_id, d.id as driver_exists
        FROM trip_bids tb 
        LEFT JOIN drivers d ON tb.driver_id = d.id 
        WHERE d.id IS NULL
        LIMIT 10
    ");
    $stmt->execute();
    $orphan_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orphan_bids) > 0) {
        echo "❌ Encontrados " . count($orphan_bids) . " trip_bids órfãos:<br>";
        foreach ($orphan_bids as $bid) {
            echo "- Bid ID: {$bid['id']}, Driver ID inexistente: {$bid['driver_id']}<br>";
        }
        
        // Limpar trip_bids órfãos
        echo "<h3>Limpando trip_bids órfãos...</h3>";
        $stmt = $db->prepare("DELETE FROM trip_bids WHERE driver_id NOT IN (SELECT id FROM drivers)");
        $deleted = $stmt->execute();
        echo $deleted ? "✅ Trip bids órfãos removidos<br>" : "❌ Erro ao remover<br>";
    } else {
        echo "✅ Nenhum trip_bid órfão encontrado<br>";
    }
    
    // Verificar se driver record existe para guincheiro de teste
    echo "<h2>5. Verificando driver record para guincheiro de teste...</h2>";
    $stmt = $db->prepare("
        SELECT u.id as user_id, u.email, d.id as driver_id 
        FROM users u 
        LEFT JOIN drivers d ON u.id = d.user_id 
        WHERE u.email = 'guincheiro@iguincho.com'
    ");
    $stmt->execute();
    $driver_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($driver_check) {
        echo "User ID: {$driver_check['user_id']}, Driver ID: " . ($driver_check['driver_id'] ?? 'NULL') . "<br>";
        
        if (!$driver_check['driver_id']) {
            echo "❌ Driver record não existe, criando...<br>";
            $stmt = $db->prepare("
                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, 
                                   truck_plate, truck_brand, truck_model, truck_year, truck_capacity, 
                                   professional_terms_accepted, background_check_authorized, approval_status) 
                VALUES (?, '12345678900', 'C', '3-5', 'guincho', 'São Paulo', '24h', 
                       'ABC-1234', 'Ford', 'F-4000', 2018, 'media', 
                       TRUE, TRUE, 'approved')
            ");
            $created = $stmt->execute([$driver_check['user_id']]);
            echo $created ? "✅ Driver record criado<br>" : "❌ Erro ao criar driver record<br>";
        } else {
            echo "✅ Driver record existe<br>";
        }
    }
    
    // Testar fluxo de autenticação
    echo "<h2>6. Testando middleware de autenticação...</h2>";
    
    // Simular headers para diferentes tipos de usuário
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_client_token';
    
    include_once 'api/middleware/auth.php';
    
    // Teste cliente
    echo "<h3>Teste Cliente:</h3>";
    $auth_result = authenticateTestClient($db);
    if ($auth_result['success']) {
        echo "✅ Cliente: ID={$auth_result['user']['id']}, Email={$auth_result['user']['email']}<br>";
    } else {
        echo "❌ Erro na autenticação do cliente<br>";
    }
    
    // Teste guincheiro
    echo "<h3>Teste Guincheiro:</h3>";
    $driver_auth = authenticateTestDriver($db);
    if ($driver_auth['success']) {
        echo "✅ Guincheiro: ID={$driver_auth['user']['id']}, Email={$driver_auth['user']['email']}<br>";
    } else {
        echo "❌ Erro na autenticação do guincheiro<br>";
    }
    
    echo "<h2>7. Resultado do diagnóstico:</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "<h3>✅ Diagnóstico completo!</h3>";
    echo "<p><strong>Possíveis causas do erro 'Esta solicitação não pertence a você':</strong></p>";
    echo "<ul>";
    echo "<li>🔍 <strong>Dados órfãos:</strong> Trip requests com client_id de usuários inexistentes (corrigido se encontrado)</li>";
    echo "<li>🔍 <strong>Driver records:</strong> Guincheiros sem registro na tabela drivers (corrigido se necessário)</li>";
    echo "<li>🔍 <strong>Autenticação inconsistente:</strong> Sistema de teste pode estar criando usuários diferentes</li>";
    echo "</ul>";
    echo "<p><strong>Recomendações:</strong></p>";
    echo "<ul>";
    echo "<li>1. Use sempre o mesmo token/sessão durante todo o fluxo</li>";
    echo "<li>2. Verifique se o localStorage não está sendo limpo entre requests</li>";
    echo "<li>3. Use o arquivo <a href='test-accept-flow-complete.html'>test-accept-flow-complete.html</a> para testar</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erro: {$e->getMessage()}</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>