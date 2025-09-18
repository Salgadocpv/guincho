<?php
/**
 * Simple Credits API - For testing without full auth
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    include_once '../config/database_auto.php';
    
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    // For testing, use a sample driver or get first driver
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        // Get first driver
        $stmt = $db->prepare("SELECT id FROM users WHERE user_type = 'driver' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $user ? $user['id'] : 1; // fallback
    }
    
    // Get driver record
    $stmt = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        // Create test driver if none exists
        $driver_id = 1; // fallback ID
    } else {
        $driver_id = $driver['id'];
    }
    
    // Check if credit tables exist, if not return mock data
    $tablesExist = false;
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'driver_credits'");
        $stmt->execute();
        $tablesExist = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $tablesExist = false;
    }
    
    if (!$tablesExist) {
        // Return mock data when credit tables don't exist
        echo json_encode([
            'success' => true,
            'message' => 'Dados de créditos carregados (modo demo)',
            'data' => [
                'credits' => [
                    'current_balance' => 150.00,
                    'total_earned' => 500.00,
                    'total_spent' => 350.00,
                    'last_updated' => date('Y-m-d H:i:s')
                ],
                'settings' => [
                    'cost_per_trip' => 25.00,
                    'pix_min_amount' => 50.00,
                    'pix_max_amount' => 500.00,
                    'pix_fee_percentage' => 3.5
                ],
                'can_accept_trip' => true,
                'recent_history' => [
                    [
                        'id' => 1,
                        'type' => 'trip_charge',
                        'amount' => -25.00,
                        'description' => 'Cobrança por viagem #1234',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                    ],
                    [
                        'id' => 2,
                        'type' => 'pix_credit',
                        'amount' => 100.00,
                        'description' => 'Recarga via PIX',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                    ],
                    [
                        'id' => 3,
                        'type' => 'trip_charge',
                        'amount' => -25.00,
                        'description' => 'Cobrança por viagem #1233',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
                    ]
                ],
                'pending_pix_requests' => []
            ]
        ]);
        exit;
    }
    
    // If credit tables exist, get real data
    try {
        // Get current balance
        $stmt = $db->prepare("SELECT current_balance, total_earned, total_spent, updated_at FROM driver_credits WHERE driver_id = ?");
        $stmt->execute([$driver_id]);
        $credits = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$credits) {
            // Create default credit record
            $stmt = $db->prepare("INSERT INTO driver_credits (driver_id, current_balance, total_earned, total_spent) VALUES (?, 100.00, 100.00, 0.00)");
            $stmt->execute([$driver_id]);
            
            $credits = [
                'current_balance' => 100.00,
                'total_earned' => 100.00,
                'total_spent' => 0.00,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get settings
        $stmt = $db->prepare("SELECT * FROM credit_settings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            $settings = [
                'cost_per_trip' => 25.00,
                'pix_min_amount' => 50.00,
                'pix_max_amount' => 500.00,
                'pix_fee_percentage' => 3.5
            ];
        }
        
        // Check if can accept trip
        $can_accept_trip = floatval($credits['current_balance']) >= floatval($settings['cost_per_trip']);
        
        // Get recent history
        $stmt = $db->prepare("SELECT * FROM credit_transactions WHERE driver_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$driver_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get pending PIX requests
        $stmt = $db->prepare("SELECT * FROM pix_credit_requests WHERE driver_id = ? AND status = 'pending' ORDER BY created_at DESC");
        $stmt->execute([$driver_id]);
        $pixRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Dados de créditos carregados com sucesso',
            'data' => [
                'credits' => [
                    'current_balance' => floatval($credits['current_balance']),
                    'total_earned' => floatval($credits['total_earned']),
                    'total_spent' => floatval($credits['total_spent']),
                    'last_updated' => $credits['updated_at']
                ],
                'settings' => [
                    'cost_per_trip' => floatval($settings['cost_per_trip']),
                    'pix_min_amount' => floatval($settings['pix_min_amount']),
                    'pix_max_amount' => floatval($settings['pix_max_amount']),
                    'pix_fee_percentage' => floatval($settings['pix_fee_percentage'])
                ],
                'can_accept_trip' => $can_accept_trip,
                'recent_history' => $history,
                'pending_pix_requests' => $pixRequests
            ]
        ]);
        
    } catch (Exception $e) {
        // Fallback to mock data if credit queries fail
        echo json_encode([
            'success' => true,
            'message' => 'Dados de créditos carregados (modo fallback)',
            'data' => [
                'credits' => [
                    'current_balance' => 75.00,
                    'total_earned' => 300.00,
                    'total_spent' => 225.00,
                    'last_updated' => date('Y-m-d H:i:s')
                ],
                'settings' => [
                    'cost_per_trip' => 25.00,
                    'pix_min_amount' => 50.00,
                    'pix_max_amount' => 500.00,
                    'pix_fee_percentage' => 3.5
                ],
                'can_accept_trip' => true,
                'recent_history' => [
                    [
                        'id' => 1,
                        'type' => 'trip_charge',
                        'amount' => -25.00,
                        'description' => 'Cobrança por viagem recente',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                    ]
                ],
                'pending_pix_requests' => []
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>