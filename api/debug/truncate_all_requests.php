<?php
/**
 * Truncate All Trip Requests and Related Data
 * WARNING: This will delete ALL trip request data!
 */

header("Content-Type: application/json; charset=UTF-8");
include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction for safety
    $db->beginTransaction();
    
    $results = [];
    
    // 1. Delete questionnaire answers first (if table exists)
    try {
        $stmt = $db->prepare("DELETE FROM questionnaire_answers");
        $stmt->execute();
        $results['questionnaire_answers_deleted'] = $stmt->rowCount();
    } catch (Exception $e) {
        $results['questionnaire_answers_note'] = 'Table does not exist or error: ' . $e->getMessage();
    }
    
    // 2. Delete trip notifications
    try {
        $stmt = $db->prepare("DELETE FROM trip_notifications WHERE active_trip_id IS NOT NULL OR trip_request_id IS NOT NULL");
        $stmt->execute();
        $results['trip_notifications_deleted'] = $stmt->rowCount();
    } catch (Exception $e) {
        $results['trip_notifications_error'] = $e->getMessage();
    }
    
    // 3. Delete trip status history
    try {
        $stmt = $db->prepare("DELETE FROM trip_status_history");
        $stmt->execute();
        $results['trip_status_history_deleted'] = $stmt->rowCount();
    } catch (Exception $e) {
        $results['trip_status_history_error'] = $e->getMessage();
    }
    
    // 4. Delete active trips
    try {
        $stmt = $db->prepare("DELETE FROM active_trips");
        $stmt->execute();
        $results['active_trips_deleted'] = $stmt->rowCount();
    } catch (Exception $e) {
        $results['active_trips_error'] = $e->getMessage();
    }
    
    // 5. Delete trip bids
    try {
        $stmt = $db->prepare("DELETE FROM trip_bids");
        $stmt->execute();
        $results['trip_bids_deleted'] = $stmt->rowCount();
    } catch (Exception $e) {
        $results['trip_bids_error'] = $e->getMessage();
    }
    
    // 6. Finally delete trip requests
    $stmt = $db->prepare("DELETE FROM trip_requests");
    $stmt->execute();
    $results['trip_requests_deleted'] = $stmt->rowCount();
    
    // Reset AUTO_INCREMENT counters
    try {
        $db->exec("ALTER TABLE trip_requests AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE active_trips AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE trip_bids AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE trip_status_history AUTO_INCREMENT = 1");
        try {
            $db->exec("ALTER TABLE questionnaire_answers AUTO_INCREMENT = 1");
        } catch (Exception $e) {
            // Table may not exist
        }
        $results['auto_increment_reset'] = true;
    } catch (Exception $e) {
        $results['auto_increment_error'] = $e->getMessage();
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Todas as solicitações e dados relacionados foram removidos',
        'data' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao limpar dados: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>