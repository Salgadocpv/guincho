<?php
include 'api/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Test the exact query with manual parameter counting
    $query = "INSERT INTO trip_requests 
            (client_id, service_type, origin_lat, origin_lng, origin_address, 
             destination_lat, destination_lng, destination_address, client_offer,
             distance_km, estimated_duration_minutes, expires_at)
            VALUES 
            (:client_id, :service_type, :origin_lat, :origin_lng, :origin_address,
             :destination_lat, :destination_lng, :destination_address, :client_offer,
             :distance_km, :estimated_duration_minutes, DATE_ADD(NOW(), INTERVAL :timeout_minutes MINUTE))";
    
    echo "=== Query Analysis ===\n";
    echo "Query: " . $query . "\n\n";
    
    // Count placeholders
    $placeholders = [];
    preg_match_all('/:(\w+)/', $query, $matches);
    $placeholders = $matches[1];
    
    echo "Found placeholders (" . count($placeholders) . "):\n";
    foreach ($placeholders as $i => $placeholder) {
        echo ($i+1) . ". :" . $placeholder . "\n";
    }
    
    echo "\n=== Testing query preparation ===\n";
    $stmt = $db->prepare($query);
    echo "Query prepared successfully\n";
    
    echo "\n=== Testing parameter binding ===\n";
    
    // Test values
    $test_values = [
        ':client_id' => 3,
        ':service_type' => 'guincho',
        ':origin_lat' => -23.5505,
        ':origin_lng' => -46.6333,
        ':origin_address' => 'Test Origin',
        ':destination_lat' => -23.5629,
        ':destination_lng' => -46.6544,
        ':destination_address' => 'Test Destination',
        ':client_offer' => 75.00,
        ':distance_km' => 5.2,
        ':estimated_duration_minutes' => 15,
        ':timeout_minutes' => 30
    ];
    
    foreach ($test_values as $param => $value) {
        echo "Binding $param = $value\n";
        $stmt->bindValue($param, $value);
    }
    
    echo "\n=== Testing execution ===\n";
    if ($stmt->execute()) {
        echo "Query executed successfully!\n";
        echo "Last insert ID: " . $db->lastInsertId() . "\n";
    } else {
        echo "Query execution failed\n";
        $errorInfo = $stmt->errorInfo();
        echo "Error: " . implode(' - ', $errorInfo) . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>