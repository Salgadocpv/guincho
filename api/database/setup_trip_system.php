<?php
/**
 * Setup script for Trip System Database
 * Executes the SQL file to create trip-related tables
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/create_trip_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: " . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $db->beginTransaction();
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            $successCount++;
            echo "✓ Executed successfully\n";
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Error in statement: " . substr($statement, 0, 100) . "... - " . $e->getMessage();
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    if ($errorCount === 0) {
        $db->commit();
        echo "\n🎉 Trip system database setup completed successfully!\n";
        echo "📊 Statistics:\n";
        echo "   - Statements executed: {$successCount}\n";
        echo "   - Errors: {$errorCount}\n";
        
        // Test the setup
        testDatabaseSetup($db);
        
    } else {
        $db->rollback();
        echo "\n❌ Setup failed with {$errorCount} errors:\n";
        foreach ($errors as $error) {
            echo "   - {$error}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

function testDatabaseSetup($db) {
    echo "\n🧪 Testing database setup...\n";
    
    $tables = [
        'trip_requests',
        'trip_bids', 
        'active_trips',
        'trip_notifications',
        'trip_status_history'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
            echo "✓ Table '{$table}' is accessible\n";
        } catch (PDOException $e) {
            echo "✗ Table '{$table}' error: " . $e->getMessage() . "\n";
        }
    }
    
    // Test system settings
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM system_settings WHERE category = 'trip_system'");
        $count = $stmt->fetchColumn();
        echo "✓ Trip system settings: {$count} entries\n";
    } catch (PDOException $e) {
        echo "✗ System settings error: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Database test completed!\n";
}

// If running from command line
if (php_sapi_name() === 'cli') {
    echo "Trip System Database Setup\n";
    echo "==========================\n\n";
}
?>