<?php
include_once 'api/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== Database Tables ===\n";
    $stmt = $db->query("SHOW TABLES");
    while($row = $stmt->fetch()) {
        echo "Table: " . $row[0] . "\n";
    }
    
    echo "\n=== Checking for trip_requests table ===\n";
    $stmt = $db->query("SHOW TABLES LIKE 'trip_requests'");
    $exists = $stmt->fetch();
    echo "trip_requests table: " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
    
    if (!$exists) {
        echo "\n=== Creating trip tables ===\n";
        $sql = file_get_contents('api/database/create_trip_tables.sql');
        $db->exec($sql);
        echo "Trip tables created successfully\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>