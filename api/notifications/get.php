<?php
/**
 * Get Notifications API
 * Returns notifications for authenticated user
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/TripNotification.php';
include_once '../middleware/auth.php';

// Check authentication
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $auth_result['message']]);
    exit();
}

$user = $auth_result['user'];

// Get query parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

$database = new DatabaseAuto();
$db = $database->getConnection();

try {
    $notification = new TripNotification($db);
    
    // Get notifications
    $stmt = $notification->getUserNotifications($user['id'], $limit, $unread_only);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format notifications
    $formatted_notifications = array_map(function($notif) {
        return [
            'id' => (int)$notif['id'],
            'type' => $notif['type'],
            'title' => $notif['title'],
            'message' => $notif['message'],
            'trip_request_id' => $notif['trip_request_id'] ? (int)$notif['trip_request_id'] : null,
            'active_trip_id' => $notif['active_trip_id'] ? (int)$notif['active_trip_id'] : null,
            'extra_data' => $notif['extra_data'] ? json_decode($notif['extra_data'], true) : null,
            'is_read' => (bool)$notif['is_read'],
            'created_at' => $notif['created_at'],
            'read_at' => $notif['read_at'],
            'time_ago' => formatTimeAgo($notif['created_at'])
        ];
    }, $notifications);
    
    // Get unread count
    $unread_count = $notification->getUnreadCount($user['id']);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_notifications,
        'unread_count' => $unread_count,
        'total_notifications' => count($formatted_notifications)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}

function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Agora';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' min atrás';
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' h atrás';
    } else {
        $days = floor($time / 86400);
        return $days . ' dia' . ($days > 1 ? 's' : '') . ' atrás';
    }
}
?>