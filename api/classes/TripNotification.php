<?php
/**
 * TripNotification Class
 * Manages notifications for the trip system
 */

class TripNotification {
    private $conn;
    private $table_name = "trip_notifications";

    public $id;
    public $user_id;
    public $trip_request_id;
    public $active_trip_id;
    public $type;
    public $title;
    public $message;
    public $extra_data;
    public $is_read;
    public $is_sent;
    public $created_at;
    public $read_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new notification
     */
    public function create($user_id, $type, $title, $message, $trip_request_id = null, $active_trip_id = null, $extra_data = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                SET user_id = :user_id,
                    type = :type,
                    title = :title,
                    message = :message,
                    trip_request_id = :trip_request_id,
                    active_trip_id = :active_trip_id,
                    extra_data = :extra_data";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $title = htmlspecialchars(strip_tags($title));
        $message = htmlspecialchars(strip_tags($message));
        $extra_data_json = $extra_data ? json_encode($extra_data) : null;

        // Bind data
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":trip_request_id", $trip_request_id);
        $stmt->bindParam(":active_trip_id", $active_trip_id);
        $stmt->bindParam(":extra_data", $extra_data_json);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get notifications for a user
     */
    public function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
        $where_clause = "user_id = :user_id";
        if ($unread_only) {
            $where_clause .= " AND is_read = FALSE";
        }

        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE " . $where_clause . "
                  ORDER BY created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $notification_id);
        $stmt->bindParam(":user_id", $user_id);

        return $stmt->execute();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
                  WHERE user_id = :user_id AND is_read = FALSE";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);

        return $stmt->execute();
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as unread_count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND is_read = FALSE";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['unread_count'];
    }

    /**
     * Delete old notifications
     */
    public function deleteOldNotifications($days = 30) {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":days", $days, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Get real-time notifications for SSE (Server-Sent Events)
     */
    public function getRealtimeNotifications($user_id, $last_check = null) {
        $where_clause = "user_id = :user_id AND is_sent = FALSE";
        
        if ($last_check) {
            $where_clause .= " AND created_at > :last_check";
        }

        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE " . $where_clause . "
                  ORDER BY created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        
        if ($last_check) {
            $stmt->bindParam(":last_check", $last_check);
        }

        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark as sent
        if (!empty($notifications)) {
            $ids = array_column($notifications, 'id');
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            $update_query = "UPDATE " . $this->table_name . " 
                            SET is_sent = TRUE 
                            WHERE id IN ($placeholders)";
            
            $stmt = $this->conn->prepare($update_query);
            $stmt->execute($ids);
        }

        return $notifications;
    }

    /**
     * Send push notification (placeholder for future implementation)
     */
    private function sendPushNotification($user_id, $title, $message, $extra_data = null) {
        // TODO: Implement push notification service
        // This could integrate with Firebase Cloud Messaging, OneSignal, etc.
        
        // For now, just log the notification
        error_log("Push notification for user {$user_id}: {$title} - {$message}");
        
        return true;
    }

    /**
     * Get notification by ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->user_id = $row['user_id'];
            $this->trip_request_id = $row['trip_request_id'];
            $this->active_trip_id = $row['active_trip_id'];
            $this->type = $row['type'];
            $this->title = $row['title'];
            $this->message = $row['message'];
            $this->extra_data = $row['extra_data'];
            $this->is_read = $row['is_read'];
            $this->is_sent = $row['is_sent'];
            $this->created_at = $row['created_at'];
            $this->read_at = $row['read_at'];
            
            return $row;
        }

        return false;
    }

    /**
     * Delete notification
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Get notifications by type
     */
    public function getByType($user_id, $type, $limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND type = :type
                  ORDER BY created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    /**
     * Get notification statistics for user
     */
    public function getUserStats($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN is_read = TRUE THEN 1 ELSE 0 END) as read_notifications,
                    SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread_notifications,
                    COUNT(DISTINCT type) as notification_types
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}