<?php
/**
 * Classe Partner - Gerenciamento de Parceiros
 */

require_once 'Database.php';

class Partner {
    private $db;
    private $table = 'partners';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Criar novo parceiro
     */
    public function create($userData) {
        try {
            $sql = "INSERT INTO {$this->table} (
                user_id, business_type, business_name, cnpj, address, 
                zip_code, city, state, phone, hours, owner_name, 
                owner_cpf, whatsapp, partner_terms_accepted, 
                data_sharing_authorized, approval_status, created_at
            ) VALUES (
                :user_id, :business_type, :business_name, :cnpj, :address,
                :zip_code, :city, :state, :phone, :hours, :owner_name,
                :owner_cpf, :whatsapp, :partner_terms_accepted,
                :data_sharing_authorized, 'pending', NOW()
            )";
            
            $stmt = $this->db->prepare($sql);
            
            $result = $stmt->execute([
                'user_id' => $userData['user_id'],
                'business_type' => $userData['business_type'],
                'business_name' => $userData['business_name'],
                'cnpj' => $userData['cnpj'],
                'address' => $userData['address'],
                'zip_code' => $userData['zip_code'],
                'city' => $userData['city'],
                'state' => $userData['state'],
                'phone' => $userData['phone'],
                'hours' => $userData['hours'],
                'owner_name' => $userData['owner_name'],
                'owner_cpf' => $userData['owner_cpf'],
                'whatsapp' => $userData['whatsapp'],
                'partner_terms_accepted' => $userData['partner_terms_accepted'] ? 1 : 0,
                'data_sharing_authorized' => $userData['data_sharing_authorized'] ? 1 : 0
            ]);
            
            if (!$result) {
                throw new Exception('Erro ao inserir parceiro: ' . implode(', ', $stmt->errorInfo()));
            }
            
            return [
                'partner_id' => $this->db->lastInsertId(),
                'status' => 'success'
            ];
            
        } catch (Exception $e) {
            throw new Exception('Erro ao criar parceiro: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar parceiro por ID do usuário
     */
    public function getByUserId($userId) {
        try {
            $sql = "SELECT p.*, u.email, u.full_name as user_full_name 
                   FROM {$this->table} p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE p.user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao buscar parceiro: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar parceiro por ID
     */
    public function getById($partnerId) {
        try {
            $sql = "SELECT p.*, u.email, u.full_name as user_full_name 
                   FROM {$this->table} p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE p.id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $partnerId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao buscar parceiro: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar parceiros por tipo de negócio
     */
    public function getByBusinessType($businessType, $city = null, $state = null) {
        try {
            $sql = "SELECT p.*, u.email 
                   FROM {$this->table} p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE p.business_type = :business_type 
                   AND p.approval_status = 'approved'";
            
            $params = ['business_type' => $businessType];
            
            if ($city) {
                $sql .= " AND p.city = :city";
                $params['city'] = $city;
            }
            
            if ($state) {
                $sql .= " AND p.state = :state";
                $params['state'] = $state;
            }
            
            $sql .= " ORDER BY p.rating DESC, p.business_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao buscar parceiros: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar status de aprovação
     */
    public function updateApprovalStatus($partnerId, $status, $approvedBy = null) {
        try {
            $sql = "UPDATE {$this->table} SET 
                   approval_status = :status, 
                   approval_date = NOW(),
                   approved_by = :approved_by,
                   updated_at = NOW()
                   WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'status' => $status,
                'approved_by' => $approvedBy,
                'id' => $partnerId
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao atualizar status: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar caminhos dos documentos
     */
    public function updateDocuments($partnerId, $businessLicensePath = null, $businessPhotoPath = null) {
        try {
            $sql = "UPDATE {$this->table} SET updated_at = NOW()";
            $params = ['id' => $partnerId];
            
            if ($businessLicensePath) {
                $sql .= ", business_license_path = :business_license_path";
                $params['business_license_path'] = $businessLicensePath;
            }
            
            if ($businessPhotoPath) {
                $sql .= ", business_photo_path = :business_photo_path";
                $params['business_photo_path'] = $businessPhotoPath;
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao atualizar documentos: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar rating do parceiro
     */
    public function updateRating($partnerId, $newRating) {
        try {
            $sql = "UPDATE {$this->table} SET 
                   rating = :rating,
                   total_services = total_services + 1,
                   updated_at = NOW()
                   WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'rating' => $newRating,
                'id' => $partnerId
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao atualizar rating: ' . $e->getMessage());
        }
    }
    
    /**
     * Listar parceiros pendentes de aprovação
     */
    public function getPendingApproval() {
        try {
            $sql = "SELECT p.*, u.email, u.full_name as user_full_name, u.created_at as user_created
                   FROM {$this->table} p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE p.approval_status = 'pending'
                   ORDER BY p.created_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao buscar parceiros pendentes: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar se CNPJ já existe
     */
    public function cnpjExists($cnpj, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE cnpj = :cnpj";
            $params = ['cnpj' => $cnpj];
            
            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
                $params['exclude_id'] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao verificar CNPJ: ' . $e->getMessage());
        }
    }
    
    /**
     * Validar dados do parceiro
     */
    public function validatePartnerData($data) {
        $errors = [];
        
        // Validar campos obrigatórios
        $requiredFields = [
            'business_type' => 'Tipo de negócio',
            'business_name' => 'Nome do estabelecimento',
            'cnpj' => 'CNPJ',
            'address' => 'Endereço',
            'zip_code' => 'CEP',
            'city' => 'Cidade',
            'state' => 'Estado',
            'phone' => 'Telefone',
            'hours' => 'Horário de funcionamento',
            'owner_name' => 'Nome do proprietário',
            'owner_cpf' => 'CPF do proprietário',
            'whatsapp' => 'WhatsApp'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = "{$label} é obrigatório";
            }
        }
        
        // Validar CNPJ
        if (!empty($data['cnpj']) && !$this->validateCNPJ($data['cnpj'])) {
            $errors[] = 'CNPJ inválido';
        }
        
        // Validar CPF do proprietário
        if (!empty($data['owner_cpf']) && !$this->validateCPF($data['owner_cpf'])) {
            $errors[] = 'CPF do proprietário inválido';
        }
        
        // Validar tipo de negócio
        $validTypes = ['lava-rapido', 'mecanica', 'auto-eletrica'];
        if (!empty($data['business_type']) && !in_array($data['business_type'], $validTypes)) {
            $errors[] = 'Tipo de negócio inválido';
        }
        
        return $errors;
    }
    
    /**
     * Validar CNPJ
     */
    private function validateCNPJ($cnpj) {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $resto = $soma % 11;
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }
        
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $resto = $soma % 11;
        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }
    
    /**
     * Validar CPF
     */
    private function validateCPF($cpf) {
        $cpf = preg_replace('/\D/', '', $cpf);
        
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
}
?>