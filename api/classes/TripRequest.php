<?php
/**
 * TripRequest Class
 * Manages trip requests from clients
 */

class TripRequest {
    private $conn;
    private $table_name = "trip_requests";

    public $id;
    public $client_id;
    public $service_type;
    public $origin_lat;
    public $origin_lng;
    public $origin_address;
    public $destination_lat;
    public $destination_lng;
    public $destination_address;
    public $client_offer;
    public $status;
    public $distance_km;
    public $estimated_duration_minutes;
    public $created_at;
    public $expires_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new trip request
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET client_id = :client_id,
                    service_type = :service_type,
                    origin_lat = :origin_lat,
                    origin_lng = :origin_lng,
                    origin_address = :origin_address,
                    destination_lat = :destination_lat,
                    destination_lng = :destination_lng,
                    destination_address = :destination_address,
                    client_offer = :client_offer,
                    distance_km = :distance_km,
                    estimated_duration_minutes = :estimated_duration_minutes,
                    expires_at = DATE_ADD(NOW(), INTERVAL :timeout_minutes MINUTE)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->service_type = htmlspecialchars(strip_tags($this->service_type));
        $this->origin_address = htmlspecialchars(strip_tags($this->origin_address));
        $this->destination_address = htmlspecialchars(strip_tags($this->destination_address));

        // Bind data
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":service_type", $this->service_type);
        $stmt->bindParam(":origin_lat", $this->origin_lat);
        $stmt->bindParam(":origin_lng", $this->origin_lng);
        $stmt->bindParam(":origin_address", $this->origin_address);
        $stmt->bindParam(":destination_lat", $this->destination_lat);
        $stmt->bindParam(":destination_lng", $this->destination_lng);
        $stmt->bindParam(":destination_address", $this->destination_address);
        $stmt->bindParam(":client_offer", $this->client_offer);
        $stmt->bindParam(":distance_km", $this->distance_km);
        $stmt->bindParam(":estimated_duration_minutes", $this->estimated_duration_minutes);
        $stmt->bindParam(":timeout_minutes", $this->getRequestTimeoutMinutes());

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get trip requests for nearby drivers
     */
    public function getNearbyRequests($driver_lat, $driver_lng, $radius_km = 25) {
        $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone,
                         (6371 * acos(cos(radians(:driver_lat)) * cos(radians(tr.origin_lat)) 
                         * cos(radians(tr.origin_lng) - radians(:driver_lng)) 
                         + sin(radians(:driver_lat)) * sin(radians(tr.origin_lat)))) AS distance
                  FROM " . $this->table_name . " tr
                  JOIN users u ON tr.client_id = u.id
                  WHERE tr.status = 'pending' 
                    AND tr.expires_at > NOW()
                    AND (6371 * acos(cos(radians(:driver_lat)) * cos(radians(tr.origin_lat)) 
                         * cos(radians(tr.origin_lng) - radians(:driver_lng)) 
                         + sin(radians(:driver_lat)) * sin(radians(tr.origin_lat)))) <= :radius_km
                  ORDER BY distance ASC, tr.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":driver_lat", $driver_lat);
        $stmt->bindParam(":driver_lng", $driver_lng);
        $stmt->bindParam(":radius_km", $radius_km);

        $stmt->execute();
        return $stmt;
    }

    /**
     * Get client's active requests
     */
    public function getClientRequests($client_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE client_id = :client_id 
                    AND status IN ('pending', 'active')
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":client_id", $client_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Update trip request status
     */
    public function updateStatus($new_status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $new_status);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Get trip request by ID
     */
    public function readOne() {
        $query = "SELECT tr.*, u.full_name as client_name, u.phone as client_phone, u.email as client_email
                  FROM " . $this->table_name . " tr
                  JOIN users u ON tr.client_id = u.id
                  WHERE tr.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->client_id = $row['client_id'];
            $this->service_type = $row['service_type'];
            $this->origin_lat = $row['origin_lat'];
            $this->origin_lng = $row['origin_lng'];
            $this->origin_address = $row['origin_address'];
            $this->destination_lat = $row['destination_lat'];
            $this->destination_lng = $row['destination_lng'];
            $this->destination_address = $row['destination_address'];
            $this->client_offer = $row['client_offer'];
            $this->status = $row['status'];
            $this->distance_km = $row['distance_km'];
            $this->estimated_duration_minutes = $row['estimated_duration_minutes'];
            $this->created_at = $row['created_at'];
            $this->expires_at = $row['expires_at'];
            
            return $row;
        }

        return false;
    }

    /**
     * Expire old trip requests
     */
    public function expireOldRequests() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'expired' 
                  WHERE status = 'pending' AND expires_at <= NOW()";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }

    /**
     * Get request timeout from system settings
     */
    private function getRequestTimeoutMinutes() {
        $query = "SELECT setting_value FROM system_settings 
                  WHERE setting_key = 'trip_request_timeout_minutes'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['setting_value'] : 30; // Default 30 minutes
    }

    /**
     * Calculate distance between two points (Haversine formula)
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Validate trip request data
     */
    public function validate() {
        $errors = [];

        if (empty($this->client_id)) {
            $errors[] = "Cliente é obrigatório";
        }

        if (empty($this->service_type)) {
            $errors[] = "Tipo de serviço é obrigatório";
        }

        if (empty($this->origin_lat) || empty($this->origin_lng)) {
            $errors[] = "Localização de origem é obrigatória";
        }

        if (empty($this->destination_lat) || empty($this->destination_lng)) {
            $errors[] = "Localização de destino é obrigatória";
        }

        if (empty($this->client_offer) || $this->client_offer <= 0) {
            $errors[] = "Oferta do cliente deve ser maior que zero";
        }

        return $errors;
    }
}