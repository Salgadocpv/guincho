<?php
/**
 * Test API to set low credits for testing insufficient credits flow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $amount = $_GET['amount'] ?? 0;
    $amount = floatval($amount);
    
    // For testing, just return different responses based on amount
    if ($amount < 25) {
        echo json_encode([
            'success' => true,
            'message' => 'Créditos de teste definidos (insuficientes)',
            'data' => [
                'credits' => [
                    'current_balance' => $amount,
                    'total_earned' => 100.00,
                    'total_spent' => 100.00 - $amount,
                    'last_updated' => date('Y-m-d H:i:s')
                ],
                'settings' => [
                    'cost_per_trip' => 25.00,
                    'pix_min_amount' => 50.00,
                    'pix_max_amount' => 500.00,
                    'pix_fee_percentage' => 3.5
                ],
                'can_accept_trip' => false,
                'recent_history' => [
                    [
                        'id' => 1,
                        'type' => 'trip_charge',
                        'amount' => -25.00,
                        'description' => 'Cobrança por viagem #1234',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                    ]
                ],
                'pending_pix_requests' => []
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Créditos de teste definidos (suficientes)',
            'data' => [
                'credits' => [
                    'current_balance' => $amount,
                    'total_earned' => $amount + 100.00,
                    'total_spent' => 100.00,
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
                        'type' => 'pix_credit',
                        'amount' => $amount,
                        'description' => 'Recarga de teste via PIX',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
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
        'error' => $e->getMessage()
    ]);
}
?>