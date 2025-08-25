<?php
/**
 * Teste de conexão com banco de dados
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    // Testar include do arquivo de database
    $config_path = __DIR__ . '/../config/database.php';
    if (!file_exists($config_path)) {
        throw new Exception("Arquivo database.php não encontrado em: " . $config_path);
    }
    
    include_once $config_path;
    
    if (!class_exists('Database')) {
        throw new Exception("Classe Database não encontrada após include");
    }
    
    $database = new Database();
    
    // Testar conexão
    $test_result = $database->testConnection();
    
    if ($test_result['status'] === 'success') {
        $conn = $database->getConnection();
        
        // Testar uma query simples
        $stmt = $conn->query("SELECT 1 as test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Conexão com banco funcionando perfeitamente',
            'connection_test' => $test_result,
            'query_test' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro na conexão com banco',
            'connection_test' => $test_result,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'config_path' => $config_path ?? 'undefined',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>