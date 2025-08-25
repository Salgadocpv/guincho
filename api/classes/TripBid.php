<?php
/**
 * TripBid Class
 * Manages driver bids for trip requests
 */

class TripBid {
    private $conn;
    private $table_name = "trip_bids";

    public $id;
    public $trip_request_id;
    public $driver_id;
    public $bid_amount;
    public $estimated_arrival_minutes;
    public $message;
    public $status;
    public $created_at;
    public $expires_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new bid
     */
    public function create() {
        // Check if driver already has a bid for this request
        if ($this->hasExistingBid()) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " 
                SET trip_request_id = :trip_request_id,
                    driver_id = :driver_id,
                    bid_amount = :bid_amount,
                    estimated_arrival_minutes = :estimated_arrival_minutes,
                    message = :message,
                    expires_at = DATE_ADD(NOW(), INTERVAL :timeout_minutes MINUTE)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->message = htmlspecialchars(strip_tags($this->message));

        // Bind data
        $stmt->bindParam(":trip_request_id", $this->trip_request_id);
        $stmt->bindParam(":driver_id", $this->driver_id);
        $stmt->bindParam(":bid_amount", $this->bid_amount);
        $stmt->bindParam(":estimated_arrival_minutes", $this->estimated_arrival_minutes);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":timeout_minutes", $this->getBidTimeoutMinutes());

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get bids for a trip request
     */
    public function getBidsForRequest($trip_request_id) {
        $query = "SELECT tb.*, d.*, u.full_name as driver_name, u.phone as driver_phone,
                         TIMESTAMPDIFF(SECOND, NOW(), tb.expires_at) as seconds_remaining
                  FROM " . $this->table_name . " tb
                  JOIN drivers d ON tb.driver_id = d.id
                  JOIN users u ON d.user_id = u.id
                  WHERE tb.trip_request_id = :trip_request_id 
                    AND tb.status = 'pending'
                    AND tb.expires_at > NOW()
                  ORDER BY tb.bid_amount ASC, tb.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":trip_request_id", $trip_request_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Accept a bid
     */
    public function accept($use_transaction = true) {
        $started_transaction = false;
        
        if ($use_transaction) {
            // Only start transaction if not already in one
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $started_transaction = true;
            }
        }

        try {
            // Update this bid to accepted
            $query = "UPDATE " . $this->table_name . " 
                      SET status = 'accepted', updated_at = CURRENT_TIMESTAMP 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();

            // Reject all other bids for this request
            $query = "UPDATE " . $this->table_name . " 
                      SET status = 'rejected', updated_at = CURRENT_TIMESTAMP 
                      WHERE trip_request_id = :trip_request_id AND id != :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":trip_request_id", $this->trip_request_id);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();

            if ($started_transaction) {
                $this->conn->commit();
            }
            return true;

        } catch (Exception $e) {
            if ($started_transaction) {
                $this->conn->rollback();
            }
            error_log("Error in TripBid->accept(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a bid
     */
    public function reject() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'rejected', updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Get driver's bids
     */
    public function getDriverBids($driver_id) {
        $query = "SELECT tb.*, tr.service_type, tr.origin_address, tr.destination_address, tr.client_offer,
                         u.full_name as client_name,
                         TIMESTAMPDIFF(SECOND, NOW(), tb.expires_at) as seconds_remaining
                  FROM " . $this->table_name . " tb
                  JOIN trip_requests tr ON tb.trip_request_id = tr.id
                  JOIN users u ON tr.client_id = u.id
                  WHERE tb.driver_id = :driver_id 
                    AND tb.status IN ('pending', 'accepted')
                  ORDER BY tb.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":driver_id", $driver_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Get bid by ID
     */
    public function readOne() {
        $query = "SELECT tb.*, tr.*, d.*, u.full_name as driver_name, uc.full_name as client_name
                  FROM " . $this->table_name . " tb
                  JOIN trip_requests tr ON tb.trip_request_id = tr.id
                  JOIN drivers d ON tb.driver_id = d.id
                  JOIN users u ON d.user_id = u.id
                  JOIN users uc ON tr.client_id = uc.id
                  WHERE tb.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->trip_request_id = $row['trip_request_id'];
            $this->driver_id = $row['driver_id'];
            $this->bid_amount = $row['bid_amount'];
            $this->estimated_arrival_minutes = $row['estimated_arrival_minutes'];
            $this->message = $row['message'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->expires_at = $row['expires_at'];
            
            return $row;
        }

        return false;
    }

    /**
     * Check if driver already has a bid for this request
     */
    private function hasExistingBid() {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE trip_request_id = :trip_request_id 
                    AND driver_id = :driver_id 
                    AND status IN ('pending', 'accepted')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":trip_request_id", $this->trip_request_id);
        $stmt->bindParam(":driver_id", $this->driver_id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Expire old bids
     */
    public function expireOldBids() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'expired' 
                  WHERE status = 'pending' AND expires_at <= NOW()";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }

    /**
     * Get bid timeout from system settings
     */
    private function getBidTimeoutMinutes() {
        $query = "SELECT setting_value FROM system_settings 
                  WHERE setting_key = 'bid_timeout_minutes'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['setting_value'] : 3; // Default 3 minutes
    }

    /**
     * Validate bid data
     */
    public function validate() {
        $errors = [];

        if (empty($this->trip_request_id)) {
            $errors[] = "ID da solicitação é obrigatório";
        }

        if (empty($this->driver_id)) {
            $errors[] = "ID do guincheiro é obrigatório";
        }

        if (empty($this->bid_amount) || $this->bid_amount <= 0) {
            $errors[] = "Valor da proposta deve ser maior que zero";
        }

        if (empty($this->estimated_arrival_minutes) || $this->estimated_arrival_minutes <= 0) {
            $errors[] = "Tempo estimado de chegada deve ser maior que zero";
        }

        return $errors;
    }

    /**
     * Get statistics for a driver
     */
    public function getDriverStats($driver_id) {
        $query = "SELECT 
                    COUNT(*) as total_bids,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_bids,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bids,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_bids,
                    AVG(bid_amount) as avg_bid_amount
                  FROM " . $this->table_name . " 
                  WHERE driver_id = :driver_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":driver_id", $driver_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}