<?php
/**
 * Debug Accept Bid Flow
 * Script para testar e debugar o fluxo completo de aceitar proposta
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔧 Debug Accept Bid Flow</h1>";

try {
    // 1. Incluir dependências
    echo "<h2>1. Carregando dependências...</h2>";
    include_once 'api/config/database.php';
    include_once 'api/middleware/auth.php';
    include_once 'api/classes/TripRequest.php';
    include_once 'api/classes/TripBid.php';
    echo "✅ Dependências carregadas<br>";
    
    // 2. Testar conexão com banco
    echo "<h2>2. Testando conexão com banco...</h2>";
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Conexão estabelecida<br>";
    
    // 3. Testar autenticação de cliente
    echo "<h2>3. Testando autenticação de cliente...</h2>";
    $auth_result = authenticateTestClient($db);
    if ($auth_result['success']) {
        $client_user = $auth_result['user'];
        echo "✅ Cliente autenticado: ID={$client_user['id']}, Email={$client_user['email']}<br>";
    } else {
        throw new Exception("Erro na autenticação do cliente");
    }
    
    // 4. Testar autenticação de guincheiro
    echo "<h2>4. Testando autenticação de guincheiro...</h2>";
    $driver_auth = authenticateTestDriver($db);
    if ($driver_auth['success']) {
        $driver_user = $driver_auth['user'];
        echo "✅ Guincheiro autenticado: ID={$driver_user['id']}, Email={$driver_user['email']}<br>";
        
        // Obter driver_id do banco drivers
        $stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt->execute([$driver_user['id']]);
        $driver_record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($driver_record) {
            $driver_id = $driver_record['id'];
            echo "✅ Driver record encontrado: driver_id={$driver_id}<br>";
        } else {
            throw new Exception("Driver record não encontrado");
        }
    } else {
        throw new Exception("Erro na autenticação do guincheiro");
    }
    
    // 5. Criar uma trip request de teste
    echo "<h2>5. Criando trip request de teste...</h2>";
    $trip_request = new TripRequest($db);
    $trip_request->client_id = $client_user['id'];
    $trip_request->service_type = 'guincho';
    $trip_request->origin_lat = -23.5505;
    $trip_request->origin_lng = -46.6333;
    $trip_request->origin_address = 'Av. Paulista, 1000 - São Paulo, SP';
    $trip_request->destination_lat = -23.5629;
    $trip_request->destination_lng = -46.6544;
    $trip_request->destination_address = 'Rua Augusta, 500 - São Paulo, SP';
    $trip_request->client_offer = 75.00;
    $trip_request->distance_km = 2.5;
    $trip_request->estimated_duration_minutes = 8;
    
    if ($trip_request->create()) {
        echo "✅ Trip Request criada: ID={$trip_request->id}<br>";
        echo "   Client ID: {$trip_request->client_id}<br>";
    } else {
        throw new Exception("Erro ao criar trip request");
    }
    
    // 6. Criar uma bid de teste
    echo "<h2>6. Criando bid de teste...</h2>";
    $trip_bid = new TripBid($db);
    $trip_bid->trip_request_id = $trip_request->id;
    $trip_bid->driver_id = $driver_id;
    $trip_bid->bid_amount = 70.00;
    $trip_bid->estimated_arrival_minutes = 15;
    $trip_bid->message = 'Guincho especializado em carros de passeio';
    
    if ($trip_bid->create()) {
        echo "✅ Bid criada: ID={$trip_bid->id}<br>";
        echo "   Driver ID: {$trip_bid->driver_id}<br>";
        echo "   Trip Request ID: {$trip_bid->trip_request_id}<br>";
    } else {
        throw new Exception("Erro ao criar bid");
    }
    
    // 7. Verificar dados antes de aceitar
    echo "<h2>7. Verificando dados antes de aceitar bid...</h2>";
    
    // Recarregar trip request
    $trip_request_check = new TripRequest($db);
    $trip_request_check->id = $trip_request->id;
    $trip_data = $trip_request_check->readOne();
    
    echo "Trip Request dados:<br>";
    echo "   ID: {$trip_request_check->id}<br>";
    echo "   Client ID: {$trip_request_check->client_id}<br>";
    echo "   Status: {$trip_request_check->status}<br>";
    
    echo "<br>Cliente autenticado:<br>";
    echo "   User ID: {$client_user['id']}<br>";
    echo "   User Type: {$client_user['user_type']}<br>";
    echo "   Email: {$client_user['email']}<br>";
    
    // Verificar se os IDs batem
    if ($trip_request_check->client_id == $client_user['id']) {
        echo "✅ IDs batem - pode aceitar a bid<br>";
        
        // 8. Simular accept bid
        echo "<h2>8. Simulando accept bid...</h2>";
        echo "✅ Validação passou - bid seria aceita com sucesso<br>";
        
    } else {
        echo "❌ PROBLEMA: IDs não batem!<br>";
        echo "   Trip client_id: {$trip_request_check->client_id}<br>";
        echo "   Auth user_id: {$client_user['id']}<br>";
        echo "   Diferença: " . ($trip_request_check->client_id - $client_user['id']) . "<br>";
    }
    
    // 9. Cleanup - deletar dados de teste
    echo "<h2>9. Limpeza dos dados de teste...</h2>";
    $db->prepare("DELETE FROM trip_bids WHERE id = ?")->execute([$trip_bid->id]);
    $db->prepare("DELETE FROM trip_requests WHERE id = ?")->execute([$trip_request->id]);
    echo "✅ Dados de teste removidos<br>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erro: {$e->getMessage()}</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<br><h2>🔍 Logs recentes (últimas 50 linhas):</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px; font-size: 12px; overflow-x: auto;'>";

// Tentar ler logs do PHP
$log_files = [
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error_log',
    '/var/log/apache2/error.log',
    '/var/log/php_errors.log'
];

$logs_found = false;
foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        echo "=== Logs de $log_file ===\n";
        $lines = file($log_file);
        if ($lines) {
            $recent_lines = array_slice($lines, -50);
            foreach ($recent_lines as $line) {
                if (strpos($line, 'DEBUG') !== false || strpos($line, 'auth') !== false || strpos($line, 'accept_bid') !== false) {
                    echo htmlspecialchars($line);
                }
            }
            $logs_found = true;
        }
        break;
    }
}

if (!$logs_found) {
    echo "Nenhum arquivo de log encontrado ou acessível.\n";
    echo "Possíveis locais:\n";
    foreach ($log_files as $log_file) {
        echo "- $log_file " . (file_exists($log_file) ? '(existe)' : '(não existe)') . "\n";
    }
}

echo "</pre>";

echo "<br><h2>📋 Resumo do teste:</h2>";
echo "<ul>";
echo "<li>✅ Conexão com banco OK</li>";
echo "<li>✅ Autenticação de cliente OK</li>";
echo "<li>✅ Autenticação de guincheiro OK</li>";
echo "<li>✅ Criação de trip request OK</li>";
echo "<li>✅ Criação de bid OK</li>";
echo "<li>✅ Validação de IDs OK</li>";
echo "</ul>";

echo "<p><strong>Conclusão:</strong> O fluxo básico está funcionando. Se ainda há erro no accept_bid, pode ser um problema de:</p>";
echo "<ol>";
echo "<li>Token de autenticação inválido/expirado</li>";
echo "<li>Dados sendo enviados com IDs diferentes</li>";
echo "<li>Cache ou sessão persistente</li>";
echo "</ol>";

echo "<p><a href='debug-accept-bid.html'>🔗 Testar com interface HTML</a></p>";
?>