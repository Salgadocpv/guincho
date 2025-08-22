<?php
/**
 * Debug script to check API functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== API DEBUG ===\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check if files exist
$files = [
    'config/database.php',
    'classes/TripRequest.php',
    'classes/TripNotification.php', 
    'middleware/auth.php'
];

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    echo "File: $file - " . (file_exists($fullPath) ? "EXISTS" : "NOT FOUND") . "\n";
}

echo "\n=== Testing Database Connection ===\n";

try {
    include_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "Database connection: SUCCESS\n";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Users table: " . $result['count'] . " records\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Auth ===\n";

try {
    include_once 'middleware/auth.php';
    $auth_result = authenticate();
    echo "Auth result: " . ($auth_result['success'] ? "SUCCESS" : "FAILED") . "\n";
    if (!$auth_result['success']) {
        echo "Auth error: " . $auth_result['message'] . "\n";
    } else {
        echo "User ID: " . $auth_result['user']['id'] . "\n";
        echo "User Type: " . $auth_result['user']['user_type'] . "\n";
    }
} catch (Exception $e) {
    echo "Auth error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Trip Classes ===\n";

try {
    include_once 'classes/TripRequest.php';
    echo "TripRequest class: LOADED\n";
    
    include_once 'classes/TripNotification.php';
    echo "TripNotification class: LOADED\n";
    
} catch (Exception $e) {
    echo "Class loading error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Trip Request Creation ===\n";

try {
    // Simulate creating a trip request
    $tripRequest = new TripRequest($db);
    echo "TripRequest object: CREATED\n";
    
} catch (Exception $e) {
    echo "TripRequest creation error: " . $e->getMessage() . "\n";
}

echo "\n=== End Debug ===\n";
?>