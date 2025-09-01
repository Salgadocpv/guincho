<?php
/**
 * Check database schema to understand foreign key relationships
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔍 Database Schema Check</h1>";

try {
    include_once '../config/database.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    echo "<h2>1. trip_bids table structure:</h2>";
    $describe = $db->query("DESCRIBE trip_bids");
    $columns = $describe->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>2. Foreign key constraints for trip_bids:</h2>";
    $fk_query = "
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'u461266905_guincho' 
        AND TABLE_NAME = 'trip_bids' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    
    $fk_result = $db->query($fk_query);
    $constraints = $fk_result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Constraint</th><th>Column</th><th>References Table</th><th>References Column</th></tr>";
    foreach ($constraints as $fk) {
        echo "<tr>";
        echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
        echo "<td>{$fk['COLUMN_NAME']}</td>";
        echo "<td>{$fk['REFERENCED_TABLE_NAME']}</td>";
        echo "<td>{$fk['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>3. drivers table structure:</h2>";
    $drivers_describe = $db->query("DESCRIBE drivers");
    $drivers_columns = $drivers_describe->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($drivers_columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>4. Current data in tables:</h2>";
    
    echo "<h3>users (drivers only):</h3>";
    $users = $db->query("SELECT id, email, user_type FROM users WHERE user_type = 'driver' LIMIT 5")->fetchAll();
    echo "<pre>" . print_r($users, true) . "</pre>";
    
    echo "<h3>drivers:</h3>";
    $drivers = $db->query("SELECT id, user_id FROM drivers LIMIT 5")->fetchAll();
    echo "<pre>" . print_r($drivers, true) . "</pre>";
    
    echo "<h2>5. Analysis:</h2>";
    echo "<p>Based on the schema, <code>trip_bids.driver_id</code> should reference:</p>";
    foreach ($constraints as $fk) {
        if ($fk['COLUMN_NAME'] === 'driver_id') {
            echo "<p><strong>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</strong></p>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erro:</h2>";
    echo "<p>{$e->getMessage()}</p>";
}
?>