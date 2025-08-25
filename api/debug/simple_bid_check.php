<?php
/**
 * Simple bid status check
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>🔍 Debug Simples de Propostas</h1>";

try {
    include_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>1. Propostas no banco:</h2>";
    
    $query = "SELECT id, trip_request_id, driver_id, status, expires_at FROM trip_bids ORDER BY id DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Trip ID</th><th>Driver ID</th><th>Status</th><th>Expires At</th></tr>";
    foreach ($bids as $bid) {
        echo "<tr>";
        echo "<td>{$bid['id']}</td>";
        echo "<td>{$bid['trip_request_id']}</td>";
        echo "<td>{$bid['driver_id']}</td>";
        echo "<td>{$bid['status']}</td>";
        echo "<td>{$bid['expires_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>2. Solicitações de viagem:</h2>";
    
    $query = "SELECT id, client_id, status, expires_at FROM trip_requests ORDER BY id DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Client ID</th><th>Status</th><th>Expires At</th></tr>";
    foreach ($trips as $trip) {
        echo "<tr>";
        echo "<td>{$trip['id']}</td>";
        echo "<td>{$trip['client_id']}</td>";
        echo "<td>{$trip['status']}</td>";
        echo "<td>{$trip['expires_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>3. Hora atual do servidor:</h2>";
    echo "<p><strong>" . date('Y-m-d H:i:s') . "</strong></p>";
    
    // Se tem bid_id específico, testar
    if (isset($_GET['bid_id'])) {
        $bid_id = $_GET['bid_id'];
        echo "<h2>4. Testando proposta ID: $bid_id</h2>";
        
        $query = "
            SELECT 
                tb.id, tb.status as bid_status, tb.expires_at as bid_expires,
                tr.id as trip_id, tr.status as trip_status, tr.expires_at as trip_expires,
                tr.client_id
            FROM trip_bids tb
            JOIN trip_requests tr ON tb.trip_request_id = tr.id
            WHERE tb.id = ?
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$bid_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Campo</th><th>Valor</th><th>Status</th></tr>";
            
            echo "<tr><td>Trip Status</td><td>{$result['trip_status']}</td>";
            echo "<td>" . ($result['trip_status'] == 'pending' ? '✅ OK' : '❌ PROBLEMA') . "</td></tr>";
            
            echo "<tr><td>Bid Status</td><td>{$result['bid_status']}</td>";
            echo "<td>" . ($result['bid_status'] == 'pending' ? '✅ OK' : '❌ PROBLEMA') . "</td></tr>";
            
            $bid_expired = (strtotime($result['bid_expires']) <= time());
            echo "<tr><td>Bid Expires</td><td>{$result['bid_expires']}</td>";
            echo "<td>" . ($bid_expired ? '❌ EXPIRADO' : '✅ OK') . "</td></tr>";
            
            $trip_expired = (strtotime($result['trip_expires']) <= time());
            echo "<tr><td>Trip Expires</td><td>{$result['trip_expires']}</td>";
            echo "<td>" . ($trip_expired ? '❌ EXPIRADO' : '✅ OK') . "</td></tr>";
            
            echo "</table>";
            
            if ($result['trip_status'] == 'pending' && $result['bid_status'] == 'pending' && !$bid_expired && !$trip_expired) {
                echo "<p style='color: green;'><strong>✅ Esta proposta DEVERIA funcionar!</strong></p>";
            } else {
                echo "<p style='color: red;'><strong>❌ Esta proposta tem problemas:</strong></p>";
                echo "<ul>";
                if ($result['trip_status'] != 'pending') echo "<li>Trip status não é 'pending'</li>";
                if ($result['bid_status'] != 'pending') echo "<li>Bid status não é 'pending'</li>";
                if ($bid_expired) echo "<li>Proposta expirada</li>";
                if ($trip_expired) echo "<li>Solicitação expirada</li>";
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>❌ Proposta ID $bid_id não encontrada!</p>";
        }
    }
    
    echo "<hr>";
    echo "<p><strong>Para testar uma proposta específica:</strong> ?bid_id=X</p>";
    echo "<p><strong>Exemplo:</strong> <a href='?bid_id=1'>?bid_id=1</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erro:</h2>";
    echo "<p>{$e->getMessage()}</p>";
}
?>