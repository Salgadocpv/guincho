<?php
header("Content-Type: application/json; charset=UTF-8");
include_once "../config/database_auto.php";

try {
    $database = new DatabaseAuto();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM trip_requests");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM trip_requests WHERE status = \"active\"");
    $stmt->execute();
    $active = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT * FROM trip_requests WHERE status = \"active\" ORDER BY created_at DESC LIMIT 3");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "total_requests" => $total["total"],
        "active_requests" => $active["active"], 
        "sample_requests" => $requests
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()], JSON_PRETTY_PRINT);
}
?>
