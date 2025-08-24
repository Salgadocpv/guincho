<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'api/config/database.php';
include 'api/classes/TripRequest.php';
include 'api/middleware/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== Testing Trip Request Creation ===\n";
    
    // Get test user
    $auth_result = authenticate();
    if (!$auth_result['success']) {
        echo "Auth failed: " . $auth_result['message'] . "\n";
        exit;
    }
    
    $user = $auth_result['user'];
    echo "User authenticated: " . $user['full_name'] . " (ID: " . $user['id'] . ")\n";
    
    // Create trip request
    $trip_request = new TripRequest($db);
    
    $trip_request->client_id = $user['id'];
    $trip_request->service_type = 'guincho';
    $trip_request->origin_lat = -23.5505;
    $trip_request->origin_lng = -46.6333;
    $trip_request->origin_address = 'Test Origin Address';
    $trip_request->destination_lat = -23.5629;
    $trip_request->destination_lng = -46.6544;
    $trip_request->destination_address = 'Test Destination Address';
    $trip_request->client_offer = 75.00;
    $trip_request->distance_km = 5.2;
    $trip_request->estimated_duration_minutes = 15;
    
    echo "Trip request object created and populated\n";
    
    if ($trip_request->create()) {
        echo "SUCCESS: Trip request created with ID: " . $trip_request->id . "\n";
    } else {
        echo "ERROR: Failed to create trip request\n";
        
        // Get last error
        $errorInfo = $db->errorInfo();
        echo "SQL Error: " . implode(' - ', $errorInfo) . "\n";
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>