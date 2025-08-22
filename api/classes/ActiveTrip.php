<?php
/**
 * ActiveTrip Class
 * Manages active/confirmed trips
 */

class ActiveTrip {
    private $conn;
    private $table_name = "active_trips";

    public $id;
    public $trip_request_id;
    public $driver_id;
    public $client_id;
    public $final_price;
    public $service_type;
    public $origin_lat;
    public $origin_lng;
    public $origin_address;
    public $destination_lat;
    public $destination_lng;
    public $destination_address;
    public $status;
    public $driver_current_lat;
    public $driver_current_lng;
    public $driver_last_update;
    public $client_rating;
    public $driver_rating;
    public $client_feedback;
    public $driver_feedback;
    public $created_at;
    public $started_at;
    public $completed_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new active trip from accepted bid
     */
    public function createFromBid($trip_request_id, $bid_id) {
        $this->conn->beginTransaction();

        try {
            // Get trip request and bid data
            $query = "SELECT tr.*, tb.driver_id, tb.bid_amount
                      FROM trip_requests tr
                      JOIN trip_bids tb ON tr.id = tb.trip_request_id
                      WHERE tr.id = :trip_request_id AND tb.id = :bid_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":trip_request_id", $trip_request_id);
            $stmt->bindParam(":bid_id", $bid_id);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                throw new Exception("Trip request or bid not found");
            }

            // Create active trip
            $query = "INSERT INTO " . $this->table_name . " 
                    SET trip_request_id = :trip_request_id,
                        driver_id = :driver_id,
                        client_id = :client_id,
                        final_price = :final_price,
                        service_type = :service_type,
                        origin_lat = :origin_lat,
                        origin_lng = :origin_lng,
                        origin_address = :origin_address,
                        destination_lat = :destination_lat,
                        destination_lng = :destination_lng,
                        destination_address = :destination_address,
                        status = 'confirmed'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":trip_request_id", $data['id']);
            $stmt->bindParam(":driver_id", $data['driver_id']);
            $stmt->bindParam(":client_id", $data['client_id']);
            $stmt->bindParam(":final_price", $data['bid_amount']);
            $stmt->bindParam(":service_type", $data['service_type']);
            $stmt->bindParam(":origin_lat", $data['origin_lat']);
            $stmt->bindParam(":origin_lng", $data['origin_lng']);
            $stmt->bindParam(":origin_address", $data['origin_address']);
            $stmt->bindParam(":destination_lat", $data['destination_lat']);
            $stmt->bindParam(":destination_lng", $data['destination_lng']);
            $stmt->bindParam(":destination_address", $data['destination_address']);
            
            $stmt->execute();
            $this->id = $this->conn->lastInsertId();

            // Update trip request status
            $query = "UPDATE trip_requests SET status = 'active' WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $trip_request_id);
            $stmt->execute();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    /**
     * Update trip status
     */
    public function updateStatus($new_status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = CURRENT_TIMESTAMP";

        // Add timestamp for specific status changes
        if ($new_status === 'in_progress') {
            $query .= ", started_at = CURRENT_TIMESTAMP";
        } elseif ($new_status === 'completed') {
            $query .= ", completed_at = CURRENT_TIMESTAMP";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $new_status);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Update driver location
     */
    public function updateDriverLocation($lat, $lng) {
        $query = "UPDATE " . $this->table_name . " 
                  SET driver_current_lat = :lat,
                      driver_current_lng = :lng,
                      driver_last_update = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":lat", $lat);
        $stmt->bindParam(":lng", $lng);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Get active trip by ID
     */
    public function readOne() {
        $query = "SELECT at.*, 
                         uc.full_name as client_name, uc.phone as client_phone, uc.email as client_email,
                         ud.full_name as driver_name, ud.phone as driver_phone, ud.email as driver_email,
                         d.truck_plate, d.truck_brand, d.truck_model, d.rating as driver_rating_avg
                  FROM " . $this->table_name . " at
                  JOIN users uc ON at.client_id = uc.id
                  JOIN drivers d ON at.driver_id = d.id
                  JOIN users ud ON d.user_id = ud.id
                  WHERE at.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->trip_request_id = $row['trip_request_id'];
            $this->driver_id = $row['driver_id'];
            $this->client_id = $row['client_id'];
            $this->final_price = $row['final_price'];
            $this->service_type = $row['service_type'];
            $this->origin_lat = $row['origin_lat'];
            $this->origin_lng = $row['origin_lng'];
            $this->origin_address = $row['origin_address'];
            $this->destination_lat = $row['destination_lat'];
            $this->destination_lng = $row['destination_lng'];
            $this->destination_address = $row['destination_address'];
            $this->status = $row['status'];
            $this->driver_current_lat = $row['driver_current_lat'];
            $this->driver_current_lng = $row['driver_current_lng'];
            $this->driver_last_update = $row['driver_last_update'];
            $this->created_at = $row['created_at'];
            $this->started_at = $row['started_at'];
            $this->completed_at = $row['completed_at'];
            
            return $row;
        }

        return false;
    }

    /**
     * Get client's active trips
     */
    public function getClientActiveTrips($client_id) {
        $query = "SELECT at.*, 
                         ud.full_name as driver_name, ud.phone as driver_phone,
                         d.truck_plate, d.truck_brand, d.truck_model, d.rating as driver_rating_avg
                  FROM " . $this->table_name . " at
                  JOIN drivers d ON at.driver_id = d.id
                  JOIN users ud ON d.user_id = ud.id
                  WHERE at.client_id = :client_id 
                    AND at.status IN ('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress')
                  ORDER BY at.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":client_id", $client_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Get driver's active trips
     */
    public function getDriverActiveTrips($driver_id) {
        $query = "SELECT at.*, 
                         uc.full_name as client_name, uc.phone as client_phone
                  FROM " . $this->table_name . " at
                  JOIN users uc ON at.client_id = uc.id
                  WHERE at.driver_id = :driver_id 
                    AND at.status IN ('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress')
                  ORDER BY at.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":driver_id", $driver_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Add rating and feedback
     */
    public function addRating($user_type, $rating, $feedback = '') {
        if ($user_type === 'client') {
            $query = "UPDATE " . $this->table_name . " 
                      SET client_rating = :rating, client_feedback = :feedback 
                      WHERE id = :id";
        } else {
            $query = "UPDATE " . $this->table_name . " 
                      SET driver_rating = :rating, driver_feedback = :feedback 
                      WHERE id = :id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":rating", $rating);
        $stmt->bindParam(":feedback", $feedback);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Get trip history for client
     */
    public function getClientHistory($client_id, $limit = 10) {
        $query = "SELECT at.*, 
                         ud.full_name as driver_name,
                         d.truck_plate, d.truck_brand, d.truck_model
                  FROM " . $this->table_name . " at
                  JOIN drivers d ON at.driver_id = d.id
                  JOIN users ud ON d.user_id = ud.id
                  WHERE at.client_id = :client_id 
                    AND at.status = 'completed'
                  ORDER BY at.completed_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":client_id", $client_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Get trip history for driver
     */
    public function getDriverHistory($driver_id, $limit = 10) {
        $query = "SELECT at.*, 
                         uc.full_name as client_name
                  FROM " . $this->table_name . " at
                  JOIN users uc ON at.client_id = uc.id
                  WHERE at.driver_id = :driver_id 
                    AND at.status = 'completed'
                  ORDER BY at.completed_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":driver_id", $driver_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Get trip statistics
     */
    public function getStats($user_id, $user_type) {
        if ($user_type === 'client') {
            $where_clause = "client_id = :user_id";
        } else {
            $where_clause = "driver_id = :user_id";
        }

        $query = "SELECT 
                    COUNT(*) as total_trips,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_trips,
                    AVG(final_price) as avg_price,
                    AVG(CASE WHEN status = 'completed' THEN 
                        TIMESTAMPDIFF(MINUTE, created_at, completed_at) ELSE NULL END) as avg_duration_minutes
                  FROM " . $this->table_name . " 
                  WHERE " . $where_clause;

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cancel trip
     */
    public function cancel($reason = '') {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'cancelled', 
                      driver_feedback = CONCAT(COALESCE(driver_feedback, ''), ' [Cancelado: " . $reason . "]'),
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }
}