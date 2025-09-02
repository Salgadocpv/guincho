<?php
/**
 * Debug script to check profile-related database tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/database_auto.php';

$database = new DatabaseAuto();
$db = $database->getConnection();

echo "<h2>Database Profile Tables Check</h2>";

// Check if tables exist
$tables_to_check = [
    'users',
    'drivers', 
    'client_vehicles'
];

foreach ($tables_to_check as $table) {
    echo "<h3>Table: $table</h3>";
    
    try {
        // Check if table exists
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->rowCount() > 0) {
            echo "✅ Table exists<br>";
            
            // Show table structure
            $stmt = $db->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' style='margin: 10px 0;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Count records
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "Records: $count<br>";
            
        } else {
            echo "❌ Table does NOT exist<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error checking table: " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
}

// Test authentication
echo "<h3>Authentication Test</h3>";
try {
    include_once '../middleware/auth.php';
    
    // Simulate a test token
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token_123';
    
    $auth_result = authenticate();
    echo "Auth result: " . json_encode($auth_result) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Auth error: " . $e->getMessage() . "<br>";
}

echo "<h3>Database Connection Test</h3>";
try {
    $stmt = $db->prepare("SELECT 1 as test");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Database connection working: " . json_encode($result) . "<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
?>