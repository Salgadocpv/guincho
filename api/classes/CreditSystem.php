<?php
class CreditSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Obter saldo de créditos de um guincheiro
    public function getDriverCredits($driver_id) {
        try {
            $query = "SELECT * FROM driver_credits WHERE driver_id = :driver_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':driver_id', $driver_id);
            $stmt->execute();
            
            $credits = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$credits) {
                // Criar registro de créditos se não existir
                $this->createDriverCredits($driver_id);
                $credits = [
                    'driver_id' => $driver_id,
                    'current_balance' => 0.00,
                    'total_added' => 0.00,
                    'total_spent' => 0.00
                ];
            }
            
            return $credits;
        } catch (Exception $e) {
            throw new Exception("Erro ao obter créditos: " . $e->getMessage());
        }
    }
    
    // Criar registro de créditos para guincheiro
    private function createDriverCredits($driver_id) {
        $query = "INSERT INTO driver_credits (driver_id) VALUES (:driver_id)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':driver_id', $driver_id);
        $stmt->execute();
    }
    
    // Adicionar créditos
    public function addCredits($driver_id, $amount, $type = 'add', $description = '', $pix_data = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Obter saldo atual
            $credits = $this->getDriverCredits($driver_id);
            $balance_before = floatval($credits['current_balance']);
            $balance_after = $balance_before + $amount;
            
            // Atualizar saldo
            $query = "UPDATE driver_credits 
                     SET current_balance = :balance_after,
                         total_added = total_added + :amount
                     WHERE driver_id = :driver_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':balance_after', $balance_after);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':driver_id', $driver_id);
            $stmt->execute();
            
            // Registrar transação
            $this->recordTransaction($driver_id, $type, $amount, $balance_before, $balance_after, $description, $pix_data);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'new_balance' => $balance_after,
                'amount_added' => $amount
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao adicionar créditos: " . $e->getMessage());
        }
    }
    
    // Gastar créditos
    public function spendCredits($driver_id, $amount, $trip_id = null, $description = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Obter saldo atual
            $credits = $this->getDriverCredits($driver_id);
            $balance_before = floatval($credits['current_balance']);
            
            // Verificar se tem saldo suficiente
            if ($balance_before < $amount) {
                throw new Exception("Saldo insuficiente. Saldo atual: R$ " . number_format($balance_before, 2, ',', '.'));
            }
            
            $balance_after = $balance_before - $amount;
            
            // Atualizar saldo
            $query = "UPDATE driver_credits 
                     SET current_balance = :balance_after,
                         total_spent = total_spent + :amount
                     WHERE driver_id = :driver_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':balance_after', $balance_after);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':driver_id', $driver_id);
            $stmt->execute();
            
            // Registrar transação
            $this->recordTransaction($driver_id, 'spend', $amount, $balance_before, $balance_after, $description, null, $trip_id);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'new_balance' => $balance_after,
                'amount_spent' => $amount
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erro ao gastar créditos: " . $e->getMessage());
        }
    }
    
    // Registrar transação
    private function recordTransaction($driver_id, $type, $amount, $balance_before, $balance_after, $description, $pix_data = null, $trip_id = null) {
        $query = "INSERT INTO credit_transactions 
                 (driver_id, transaction_type, amount, balance_before, balance_after, description, trip_id, pix_transaction_id, pix_key, pix_amount)
                 VALUES (:driver_id, :type, :amount, :balance_before, :balance_after, :description, :trip_id, :pix_transaction_id, :pix_key, :pix_amount)";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':driver_id', $driver_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':balance_before', $balance_before);
        $stmt->bindParam(':balance_after', $balance_after);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':trip_id', $trip_id);
        
        // Dados do PIX se fornecidos
        $pix_transaction_id = $pix_data['transaction_id'] ?? null;
        $pix_key = $pix_data['pix_key'] ?? null;
        $pix_amount = $pix_data['pix_amount'] ?? null;
        
        $stmt->bindParam(':pix_transaction_id', $pix_transaction_id);
        $stmt->bindParam(':pix_key', $pix_key);
        $stmt->bindParam(':pix_amount', $pix_amount);
        
        $stmt->execute();
    }
    
    // Obter histórico de transações
    public function getTransactionHistory($driver_id, $limit = 50, $offset = 0) {
        $query = "SELECT ct.*, tr.pickup_address, tr.dropoff_address 
                 FROM credit_transactions ct
                 LEFT JOIN trip_requests tr ON ct.trip_id = tr.id
                 WHERE ct.driver_id = :driver_id
                 ORDER BY ct.created_at DESC
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':driver_id', $driver_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Criar solicitação de PIX
    public function createPixRequest($driver_id, $amount_requested, $pix_key, $pix_key_type) {
        try {
            // Obter configurações
            $settings = $this->getCreditSettings();
            
            // Validar valores
            if ($amount_requested < $settings['min_pix_credit_amount']) {
                throw new Exception("Valor mínimo para recarga: R$ " . number_format($settings['min_pix_credit_amount'], 2, ',', '.'));
            }
            
            if ($amount_requested > $settings['max_pix_credit_amount']) {
                throw new Exception("Valor máximo para recarga: R$ " . number_format($settings['max_pix_credit_amount'], 2, ',', '.'));
            }
            
            // Calcular créditos a receber
            $credits_to_receive = $amount_requested * $settings['pix_credit_rate'];
            
            // Data de expiração
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $settings['pix_request_expiry_hours'] . ' hours'));
            
            $query = "INSERT INTO pix_credit_requests 
                     (driver_id, amount_requested, credits_to_receive, pix_key, pix_key_type, expires_at)
                     VALUES (:driver_id, :amount_requested, :credits_to_receive, :pix_key, :pix_key_type, :expires_at)";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':driver_id', $driver_id);
            $stmt->bindParam(':amount_requested', $amount_requested);
            $stmt->bindParam(':credits_to_receive', $credits_to_receive);
            $stmt->bindParam(':pix_key', $pix_key);
            $stmt->bindParam(':pix_key_type', $pix_key_type);
            $stmt->bindParam(':expires_at', $expires_at);
            $stmt->execute();
            
            $request_id = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'request_id' => $request_id,
                'amount_requested' => $amount_requested,
                'credits_to_receive' => $credits_to_receive,
                'pix_key' => $pix_key,
                'expires_at' => $expires_at
            ];
        } catch (Exception $e) {
            throw new Exception("Erro ao criar solicitação PIX: " . $e->getMessage());
        }
    }
    
    // Obter configurações de crédito
    public function getCreditSettings() {
        $settings = [];
        
        $query = "SELECT setting_key, setting_value FROM system_settings 
                 WHERE category = 'credits' OR setting_key IN (
                     'credit_per_trip', 'pix_credit_rate', 'minimum_credit_balance',
                     'max_pix_credit_amount', 'min_pix_credit_amount', 'pix_request_expiry_hours'
                 )";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Valores padrão
        $defaults = [
            'credit_per_trip' => 15.00,
            'pix_credit_rate' => 1.00,
            'minimum_credit_balance' => 5.00,
            'max_pix_credit_amount' => 500.00,
            'min_pix_credit_amount' => 10.00,
            'pix_request_expiry_hours' => 24
        ];
        
        return array_merge($defaults, $settings);
    }
    
    // Verificar se guincheiro pode aceitar viagem
    public function canAcceptTrip($driver_id) {
        $credits = $this->getDriverCredits($driver_id);
        $settings = $this->getCreditSettings();
        
        return floatval($credits['current_balance']) >= floatval($settings['minimum_credit_balance']);
    }
    
    // Obter solicitações PIX do guincheiro
    public function getPixRequests($driver_id, $status = null) {
        $query = "SELECT * FROM pix_credit_requests WHERE driver_id = :driver_id";
        $params = [':driver_id' => $driver_id];
        
        if ($status) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>