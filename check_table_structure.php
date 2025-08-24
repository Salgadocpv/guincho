<?php
include 'api/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "=== trip_requests table structure ===\n";
    $stmt = $db->query('DESCRIBE trip_requests');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . " - Null:" . $row['Null'] . " - Default:" . $row['Default'] . "\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>