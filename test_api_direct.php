<?php
/**
 * Teste Direto da API - Simula exatamente o que o JavaScript faz
 */

echo "<h2>🔬 Teste Direto da API de Login</h2>";
echo "<p>Simulando exatamente as mesmas chamadas que o JavaScript faz...</p>";

// Credenciais de teste
$testUsers = [
    ['type' => 'admin', 'email' => 'admin@iguincho.com', 'password' => 'admin123'],
    ['type' => 'client', 'email' => 'cliente@iguincho.com', 'password' => 'teste123'],
    ['type' => 'driver', 'email' => 'guincheiro@iguincho.com', 'password' => 'teste123']
];

foreach ($testUsers as $user) {
    echo "<hr><h3>Testando {$user['type']}: {$user['email']}</h3>";
    
    // Preparar dados JSON (igual ao JavaScript)
    $postData = json_encode([
        'email' => $user['email'],
        'password' => $user['password']
    ]);
    
    echo "<p><strong>Dados enviados:</strong></p>";
    echo "<pre>" . htmlspecialchars($postData) . "</pre>";
    
    // Preparar contexto da requisição
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                       "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData
        ]
    ]);
    
    try {
        // Fazer requisição
        $startTime = microtime(true);
        $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/auth/login.php';
        $response = file_get_contents($apiUrl, false, $context);
        $endTime = microtime(true);
        
        echo "<p><strong>URL da API:</strong> " . htmlspecialchars($apiUrl) . "</p>";
        echo "<p><strong>Tempo de resposta:</strong> " . round(($endTime - $startTime) * 1000) . "ms</p>";
        
        // Analisar headers HTTP
        if (isset($http_response_header)) {
            echo "<p><strong>Headers de resposta:</strong></p>";
            echo "<pre>";
            foreach ($http_response_header as $header) {
                echo htmlspecialchars($header) . "\n";
            }
            echo "</pre>";
        }
        
        echo "<p><strong>Resposta bruta:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        // Tentar decodificar JSON
        $data = json_decode($response, true);
        
        if ($data === null) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
            echo "<strong>❌ Erro:</strong> Resposta não é JSON válido<br>";
            echo "<strong>JSON Error:</strong> " . json_last_error_msg();
            echo "</div>";
        } else {
            echo "<p><strong>JSON decodificado:</strong></p>";
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            
            if (isset($data['success'])) {
                if ($data['success']) {
                    echo "<div style='color: green; padding: 10px; border: 1px solid green;'>";
                    echo "<strong>✅ LOGIN SUCESSO!</strong><br>";
                    if (isset($data['data']['user']['user_type'])) {
                        echo "Tipo de usuário: " . $data['data']['user']['user_type'] . "<br>";
                    }
                    if (isset($data['data']['session_token'])) {
                        echo "Token gerado: " . substr($data['data']['session_token'], 0, 20) . "...<br>";
                    }
                    echo "</div>";
                    
                    // Testar redirecionamento que seria feito
                    $userType = $data['data']['user']['user_type'] ?? 'unknown';
                    $redirectUrls = [
                        'client' => 'services.html',
                        'driver' => 'driver/dashboard.html', 
                        'admin' => 'admin/dashboard.html'
                    ];
                    
                    $redirectUrl = $redirectUrls[$userType] ?? 'services.html';
                    echo "<p><strong>🔄 Redirecionaria para:</strong> " . $redirectUrl . "</p>";
                    
                } else {
                    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
                    echo "<strong>❌ LOGIN FALHOU!</strong><br>";
                    echo "Mensagem: " . ($data['message'] ?? 'Erro desconhecido');
                    echo "</div>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
        echo "<strong>❌ Erro na requisição:</strong><br>";
        echo $e->getMessage();
        echo "</div>";
    }
    
    echo "<br>";
}

// Teste adicional: verificar se API está acessível
echo "<hr><h3>🌐 Teste de Conectividade da API</h3>";

$apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/auth/login.php';

// Teste GET (deveria retornar método não permitido)
echo "<p><strong>Testando GET na API (deve dar erro 405):</strong></p>";
$getContext = stream_context_create(['http' => ['method' => 'GET']]);
try {
    $getResponse = file_get_contents($apiUrl, false, $getContext);
    echo "<pre>" . htmlspecialchars($getResponse) . "</pre>";
} catch (Exception $e) {
    echo "<p>Erro: " . $e->getMessage() . "</p>";
}

// Teste OPTIONS (para CORS)
echo "<p><strong>Testando OPTIONS na API (CORS):</strong></p>";
$optionsContext = stream_context_create(['http' => ['method' => 'OPTIONS']]);
try {
    $optionsResponse = file_get_contents($apiUrl, false, $optionsContext);
    echo "<pre>" . htmlspecialchars($optionsResponse) . "</pre>";
} catch (Exception $e) {
    echo "<p>Erro: " . $e->getMessage() . "</p>";
}

echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Voltar para Login</a></p>";
?>