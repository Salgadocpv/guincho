<?php
include 'api/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "=== System Settings ===\n";
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%trip%' OR setting_key LIKE '%minimum%'");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['setting_key'] . ": " . $row['setting_value'] . "\n";
    }
    
    echo "\n=== Checking minimum_trip_value ===\n";
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'minimum_trip_value'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "minimum_trip_value: " . $result['setting_value'] . "\n";
    } else {
        echo "minimum_trip_value not found, inserting default...\n";
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES ('minimum_trip_value', '25.00', 'number', 'Valor mínimo para viagens', 'business', FALSE)");
        $stmt->execute();
        echo "Inserted minimum_trip_value = 25.00\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>