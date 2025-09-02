<?php
/**
 * Create Profile Related Tables
 * Creates tables needed for profile functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/database_auto.php';

$database = new DatabaseAuto();
$db = $database->getConnection();

echo "<h2>Creating Profile Tables</h2>";

try {
    $db->beginTransaction();
    
    // Create client_vehicles table
    $clientVehiclesSQL = "CREATE TABLE IF NOT EXISTS client_vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        license_plate VARCHAR(20),
        vehicle_brand VARCHAR(50),
        vehicle_model VARCHAR(50),
        vehicle_year YEAR,
        vehicle_color VARCHAR(30),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_client_id (client_id),
        INDEX idx_license_plate (license_plate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($clientVehiclesSQL);
    echo "✅ Table client_vehicles created/verified<br>";
    
    // Create drivers table
    $driversSQL = "CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        cnh VARCHAR(20),
        cnh_category VARCHAR(10),
        experience VARCHAR(20),
        specialty VARCHAR(50),
        work_region VARCHAR(255),
        availability VARCHAR(50),
        truck_plate VARCHAR(20),
        truck_brand VARCHAR(50),
        truck_model VARCHAR(50),
        truck_year YEAR,
        truck_capacity VARCHAR(30),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rating DECIMAL(3,2) DEFAULT 0.00,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_truck_plate (truck_plate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($driversSQL);
    echo "✅ Table drivers created/verified<br>";
    
    // Add missing columns to users table if needed
    $alterUsersSQL = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS cpf VARCHAR(20) AFTER full_name",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_date DATE AFTER cpf",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) AFTER phone"
    ];
    
    foreach ($alterUsersSQL as $sql) {
        try {
            $db->exec($sql);
            echo "✅ Users table column added/verified<br>";
        } catch (Exception $e) {
            // Column might already exist, that's OK
            echo "ℹ️ Users table already has required columns<br>";
        }
    }
    
    $db->commit();
    
    echo "<h3>Success!</h3>";
    echo "All profile tables have been created/verified successfully.<br>";
    echo "Tables: users, client_vehicles, drivers<br>";
    
    // Test insert some sample data if tables are empty
    createSampleData($db);
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Error creating tables: " . $e->getMessage() . "<br>";
    error_log("Error creating profile tables: " . $e->getMessage());
}

function createSampleData($db) {
    echo "<h3>Creating Sample Data</h3>";
    
    try {
        // Check if we have sample users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type IN ('client', 'driver')");
        $stmt->execute();
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($userCount == 0) {
            // Create sample client
            $clientSQL = "INSERT INTO users (full_name, cpf, birth_date, email, phone, whatsapp, password, user_type, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'client', 'active')";
            $stmt = $db->prepare($clientSQL);
            $stmt->execute([
                'João Silva Santos',
                '123.456.789-10', 
                '1990-05-15',
                'joao.cliente@teste.com',
                '(11) 98765-4321',
                '(11) 98765-4321',
                password_hash('teste123', PASSWORD_DEFAULT)
            ]);
            $clientId = $db->lastInsertId();
            
            // Create sample client vehicle
            $vehicleSQL = "INSERT INTO client_vehicles (client_id, license_plate, vehicle_brand, vehicle_model, vehicle_year, vehicle_color)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($vehicleSQL);
            $stmt->execute([$clientId, 'ABC-1234', 'toyota', 'corolla', 2020, 'branco']);
            
            echo "✅ Sample client created<br>";
            
            // Create sample driver
            $driverSQL = "INSERT INTO users (full_name, cpf, birth_date, email, phone, whatsapp, password, user_type, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'driver', 'active')";
            $stmt = $db->prepare($driverSQL);
            $stmt->execute([
                'Carlos Eduardo Silva',
                '987.654.321-00',
                '1985-03-20', 
                'carlos.guincheiro@teste.com',
                '(11) 99887-6543',
                '(11) 99887-6543',
                password_hash('teste123', PASSWORD_DEFAULT)
            ]);
            $driverId = $db->lastInsertId();
            
            // Create sample driver profile
            $driverProfileSQL = "INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, truck_plate, truck_brand, truck_model, truck_year, truck_capacity, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
            $stmt = $db->prepare($driverProfileSQL);
            $stmt->execute([
                $driverId,
                '12345678901',
                'B',
                '5-10',
                'carros', 
                'São Paulo - Zona Sul, Centro',
                '24h',
                'GUN-2023',
                'Ford',
                'Cargo 816',
                2018,
                'media'
            ]);
            
            echo "✅ Sample driver created<br>";
            
        } else {
            echo "ℹ️ Sample data already exists ($userCount users)<br>";
        }
        
    } catch (Exception $e) {
        echo "⚠️ Could not create sample data: " . $e->getMessage() . "<br>";
    }
}
?>