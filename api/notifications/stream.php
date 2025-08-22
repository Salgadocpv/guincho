<?php
/**
 * Server-Sent Events stream for real-time notifications
 * Provides live updates to clients using SSE
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include_once '../config/database.php';
include_once '../classes/TripNotification.php';
include_once '../middleware/auth.php';

// Enable error reporting to file instead of output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Prevent script timeout
set_time_limit(0);
ignore_user_abort(false);

// Check authentication
$auth_token = $_GET['token'] ?? '';
if (empty($auth_token)) {
    sendError('Authentication token required');
    exit();
}

// Set temporary auth token for middleware
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $auth_token;

$auth_result = authenticate();
if (!$auth_result['success']) {
    sendError('Authentication failed: ' . $auth_result['message']);
    exit();
}

$user = $auth_result['user'];

// Initialize database
try {
    $database = new Database();
    $db = $database->getConnection();
    $notification = new TripNotification($db);
} catch (Exception $e) {
    sendError('Database connection failed');
    exit();
}

// Send initial connection message
sendMessage('connected', [
    'user_id' => $user['id'],
    'user_type' => $user['user_type'],
    'timestamp' => time()
]);

$last_check = time();
$heartbeat_counter = 0;

// Main SSE loop
while (true) {
    if (connection_aborted()) {
        break;
    }
    
    try {
        // Get new notifications
        $notifications = $notification->getRealtimeNotifications($user['id'], date('Y-m-d H:i:s', $last_check));
        
        foreach ($notifications as $notif) {
            sendMessage('notification', [
                'id' => $notif['id'],
                'type' => $notif['type'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'trip_request_id' => $notif['trip_request_id'],
                'active_trip_id' => $notif['active_trip_id'],
                'extra_data' => $notif['extra_data'] ? json_decode($notif['extra_data'], true) : null,
                'created_at' => $notif['created_at'],
                'timestamp' => strtotime($notif['created_at'])
            ]);
        }
        
        // Update last check time
        $last_check = time();
        
        // Send heartbeat every 30 seconds
        $heartbeat_counter++;
        if ($heartbeat_counter >= 30) {
            sendMessage('heartbeat', [
                'timestamp' => time(),
                'unread_count' => $notification->getUnreadCount($user['id'])
            ]);
            $heartbeat_counter = 0;
        }
        
        // Check if there are any pending trip requests that need cleanup
        if ($user['user_type'] === 'admin') {
            cleanupExpiredRequests($db);
        }
        
    } catch (Exception $e) {
        error_log('SSE Error: ' . $e->getMessage());
        sendMessage('error', ['message' => 'Internal server error']);
    }
    
    // Flush output and wait 1 second
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
    sleep(1);
    
    // Break if running too long (1 hour max)
    if (time() - $last_check > 3600) {
        sendMessage('timeout', ['message' => 'Connection timeout']);
        break;
    }
}

function sendMessage($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
}

function sendError($message) {
    echo "event: error\n";
    echo "data: " . json_encode(['message' => $message]) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

function cleanupExpiredRequests($db) {
    try {
        // Expire old trip requests
        $stmt = $db->prepare("UPDATE trip_requests SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()");
        $stmt->execute();
        
        // Expire old bids
        $stmt = $db->prepare("UPDATE trip_bids SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()");
        $stmt->execute();
        
    } catch (Exception $e) {
        error_log('Cleanup error: ' . $e->getMessage());
    }
}
?>