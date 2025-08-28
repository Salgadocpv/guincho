<?php
/**
 * Detailed Request Debug - Simulate exactly what driver sees
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';
include_once '../classes/TripRequest.php';
include_once '../classes/ActiveTrip.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $debug_info = [
        'timestamp' => date('Y-m-d H:i:s'),
        'steps' => []
    ];
    
    // Step 1: Check recent requests
    $debug_info['steps']['1_recent_requests'] = [];
    $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone,
                     TIMESTAMPDIFF(MINUTE, tr.created_at, NOW()) as minutes_old,
                     TIMESTAMPDIFF(MINUTE, NOW(), tr.expires_at) as minutes_to_expire
              FROM trip_requests tr
              JOIN users u ON tr.client_id = u.id
              WHERE tr.status = 'pending'
              ORDER BY tr.created_at DESC
              LIMIT 3";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['steps']['1_recent_requests'] = $recent_requests;
    
    // Step 2: Check if any are expired
    $debug_info['steps']['2_expired_check'] = [];
    foreach ($recent_requests as $req) {
        $is_expired = strtotime($req['expires_at']) <= time();
        $debug_info['steps']['2_expired_check'][] = [
            'id' => $req['id'],
            'expires_at' => $req['expires_at'],
            'is_expired' => $is_expired,
            'minutes_to_expire' => $req['minutes_to_expire']
        ];
    }
    
    // Step 3: Check available drivers
    $debug_info['steps']['3_available_drivers'] = [];
    $driver_query = "SELECT d.id, d.user_id, d.specialty, d.approval_status, u.full_name, u.status as user_status
                     FROM drivers d
                     JOIN users u ON d.user_id = u.id
                     ORDER BY d.id DESC
                     LIMIT 5";
    
    $driver_stmt = $db->prepare($driver_query);
    $driver_stmt->execute();
    $drivers = $driver_stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['steps']['3_available_drivers'] = $drivers;
    
    // Step 4: Check for approved drivers who can see guincho requests
    $debug_info['steps']['4_guincho_drivers'] = [];
    foreach ($drivers as $driver) {
        $can_see_guincho = ($driver['specialty'] === 'guincho' || $driver['specialty'] === 'todos') 
                          && $driver['approval_status'] === 'approved'
                          && $driver['user_status'] === 'active';
        
        $debug_info['steps']['4_guincho_drivers'][] = [
            'driver_id' => $driver['id'],
            'name' => $driver['full_name'],
            'specialty' => $driver['specialty'],
            'approval_status' => $driver['approval_status'],
            'user_status' => $driver['user_status'],
            'can_see_guincho' => $can_see_guincho
        ];
    }
    
    // Step 5: Simulate what driver API would return
    $debug_info['steps']['5_driver_api_simulation'] = [];
    
    if (!empty($recent_requests) && !empty($drivers)) {
        // Find an approved driver
        $approved_driver = null;
        foreach ($drivers as $driver) {
            if ($driver['approval_status'] === 'approved' && $driver['user_status'] === 'active') {
                $approved_driver = $driver;
                break;
            }
        }
        
        if ($approved_driver) {
            // Check if driver has active trip
            $active_trip = new ActiveTrip($db);
            $stmt = $active_trip->getDriverActiveTrips($approved_driver['id']);
            $current_active_trip = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $debug_info['steps']['5_driver_api_simulation'] = [
                'driver_has_active_trip' => $current_active_trip ? true : false,
                'active_trip_data' => $current_active_trip,
                'would_see_requests' => !$current_active_trip
            ];
            
            if (!$current_active_trip) {
                // Filter requests for this driver
                $filtered_requests = [];
                foreach ($recent_requests as $request) {
                    if (strtotime($request['expires_at']) > time()) {
                        $can_see = ($approved_driver['specialty'] === $request['service_type'] || 
                                   $approved_driver['specialty'] === 'todos');
                        
                        if ($can_see) {
                            $filtered_requests[] = [
                                'id' => $request['id'],
                                'service_type' => $request['service_type'],
                                'client_name' => $request['client_name'],
                                'client_offer' => $request['client_offer'],
                                'origin_address' => substr($request['origin_address'], 0, 50) . '...',
                                'minutes_old' => $request['minutes_old']
                            ];
                        }
                    }
                }
                
                $debug_info['steps']['5_driver_api_simulation']['filtered_requests'] = $filtered_requests;
                $debug_info['steps']['5_driver_api_simulation']['requests_count'] = count($filtered_requests);
            }
        } else {
            $debug_info['steps']['5_driver_api_simulation'] = [
                'error' => 'No approved drivers found',
                'available_drivers_count' => count($drivers)
            ];
        }
    } else {
        $debug_info['steps']['5_driver_api_simulation'] = [
            'error' => 'No recent requests or no drivers',
            'requests_count' => count($recent_requests),
            'drivers_count' => count($drivers)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'debug' => $debug_info
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => $debug_info ?? []
    ], JSON_PRETTY_PRINT);
}
?>