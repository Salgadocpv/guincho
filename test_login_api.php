<?php
/**
 * Teste da API de Login
 */

echo "<h2>Teste da API de Login</h2>";

// Simular request POST para login
$_SERVER['REQUEST_METHOD'] = 'POST';

// Dados de teste
$testCredentials = [
    [
        'email' => 'admin@iguincho.com',
        'password' => 'admin123',
        'type' => 'Admin'
    ],
    [
        'email' => 'cliente@iguincho.com', 
        'password' => 'teste123',
        'type' => 'Cliente'
    ],
    [
        'email' => 'guincheiro@iguincho.com',
        'password' => 'teste123', 
        'type' => 'Guincheiro'
    ]
];

foreach ($testCredentials as $cred) {
    echo "<h3>Testando {$cred['type']}: {$cred['email']}</h3>";
    
    try {
        // Capturar output da API
        ob_start();
        
        // Simular dados POST
        file_put_contents('php://input', json_encode([
            'email' => $cred['email'],
            'password' => $cred['password']
        ]));
        
        // Incluir API de login
        include 'api/auth/login.php';
        
        $output = ob_get_clean();
        
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Resposta da API:</strong><br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        echo "</div>";
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>Erro:</strong> " . $e->getMessage();
        echo "</div>";
    }
    
    echo "<hr>";
}

echo "<p><a href='index.html'>← Voltar para o login</a></p>";
?>