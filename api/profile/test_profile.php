<?php
/**
 * Test Profile API - Simple version without authentication
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    include_once '../config/database_auto.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection working',
        'database_info' => $database->getEnvironmentInfo(),
        'tables_check' => checkTables($db)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

function checkTables($db) {
    $tables = ['users', 'drivers', 'client_vehicles'];
    $results = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table LIMIT 1");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $results[$table] = "✅ Exists ($count records)";
        } catch (Exception $e) {
            $results[$table] = "❌ Error: " . $e->getMessage();
        }
    }
    
    return $results;
}
?>