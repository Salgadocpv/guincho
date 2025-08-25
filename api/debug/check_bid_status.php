<?php
/**
 * Debug script to check bid and trip status
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $bid_id = $_GET['bid_id'] ?? null;
    
    if (!$bid_id) {
        // Show all pending bids
        $query = "
            SELECT 
                tb.id as bid_id,
                tb.status as bid_status,
                tb.expires_at as bid_expires,
                tb.created_at as bid_created,
                tr.id as trip_id,
                tr.status as trip_status,
                tr.client_id,
                tr.expires_at as trip_expires,
                tr.created_at as trip_created,
                u.email as client_email,
                d.id as driver_id,
                du.email as driver_email,
                NOW() as current_server_time
            FROM trip_bids tb
            JOIN trip_requests tr ON tb.trip_request_id = tr.id
            JOIN users u ON tr.client_id = u.id
            JOIN users du ON tb.driver_id = du.id
            LEFT JOIN drivers d ON du.id = d.user_id
            ORDER BY tb.created_at DESC
            LIMIT 10
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Status de todas as propostas recentes',
            'bids' => $bids,
            'current_time' => date('Y-m-d H:i:s'),
            'note' => 'Para verificar uma proposta específica, use ?bid_id=X'
        ], JSON_PRETTY_PRINT);
        
    } else {
        // Check specific bid
        $query = "
            SELECT 
                tb.*,
                tr.status as trip_status,
                tr.client_id,
                tr.expires_at as trip_expires,
                u.email as client_email,
                du.email as driver_email,
                NOW() as current_server_time,
                (tb.expires_at <= NOW()) as bid_expired,
                (tr.expires_at <= NOW()) as trip_expired
            FROM trip_bids tb
            JOIN trip_requests tr ON tb.trip_request_id = tr.id
            JOIN users u ON tr.client_id = u.id
            JOIN users du ON tb.driver_id = du.id
            WHERE tb.id = ?
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$bid_id]);
        $bid = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            echo json_encode([
                'success' => false,
                'message' => "Proposta ID $bid_id não encontrada"
            ]);
            exit();
        }
        
        $problems = [];
        
        if ($bid['trip_status'] !== 'pending') {
            $problems[] = "Trip status é '{$bid['trip_status']}' (precisa ser 'pending')";
        }
        
        if ($bid['status'] !== 'pending') {
            $problems[] = "Bid status é '{$bid['status']}' (precisa ser 'pending')";
        }
        
        if ($bid['bid_expired']) {
            $problems[] = "Proposta expirou em {$bid['expires_at']}";
        }
        
        if ($bid['trip_expired']) {
            $problems[] = "Solicitação expirou em {$bid['trip_expires']}";
        }
        
        echo json_encode([
            'success' => true,
            'bid_id' => $bid_id,
            'bid_data' => $bid,
            'can_accept' => empty($problems),
            'problems' => $problems,
            'status_summary' => [
                'trip_status' => $bid['trip_status'],
                'bid_status' => $bid['status'],
                'bid_expired' => $bid['bid_expired'] ? 'SIM' : 'NÃO',
                'trip_expired' => $bid['trip_expired'] ? 'SIM' : 'NÃO'
            ]
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>