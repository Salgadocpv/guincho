<?php
/**
 * Classe Driver - Gerenciamento de guincheiros do Iguincho
 */

class Driver {
    private $conn;
    private $table = 'drivers';
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Criar perfil de guincheiro
     */
    public function create($user_id, $data) {
        try {
            // Validar dados obrigatórios
            $this->validateDriverData($data);
            
            // Query de inserção
            $query = "INSERT INTO " . $this->table . " 
                     SET user_id = :user_id,
                         cnh = :cnh,
                         cnh_category = :cnh_category,
                         experience = :experience,
                         specialty = :specialty,
                         work_region = :work_region,
                         availability = :availability,
                         truck_plate = :truck_plate,
                         truck_brand = :truck_brand,
                         truck_model = :truck_model,
                         truck_year = :truck_year,
                         truck_capacity = :truck_capacity,
                         professional_terms_accepted = :professional_terms_accepted,
                         background_check_authorized = :background_check_authorized,
                         approval_status = 'pending'";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind dos parâmetros
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':cnh', $this->cleanCNH($data['cnh']));
            $stmt->bindParam(':cnh_category', $data['cnh_category']);
            $stmt->bindParam(':experience', $data['experience']);
            $stmt->bindParam(':specialty', $data['specialty']);
            $stmt->bindParam(':work_region', $data['work_region']);
            $stmt->bindParam(':availability', $data['availability']);
            $stmt->bindParam(':truck_plate', strtoupper($data['truck_plate']));
            $stmt->bindParam(':truck_brand', $data['truck_brand']);
            $stmt->bindParam(':truck_model', $data['truck_model']);
            $stmt->bindParam(':truck_year', $data['truck_year']);
            $stmt->bindParam(':truck_capacity', $data['truck_capacity']);
            $stmt->bindParam(':professional_terms_accepted', $data['professional_terms_accepted'], PDO::PARAM_BOOL);
            $stmt->bindParam(':background_check_authorized', $data['background_check_authorized'], PDO::PARAM_BOOL);
            
            if ($stmt->execute()) {
                $driver_id = $this->conn->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Perfil de guincheiro criado com sucesso',
                    'driver_id' => $driver_id
                ];
            } else {
                throw new Exception("Erro ao criar perfil de guincheiro", 500);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao criar guincheiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar guincheiro por user_id
     */
    public function getByUserId($user_id) {
        try {
            $query = "SELECT d.*, u.full_name, u.email, u.phone 
                     FROM " . $this->table . " d
                     INNER JOIN users u ON d.user_id = u.id
                     WHERE d.user_id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch();
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar guincheiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Listar guincheiros por status
     */
    public function getByStatus($status = 'approved') {
        try {
            $query = "SELECT d.*, u.full_name, u.email, u.phone 
                     FROM " . $this->table . " d
                     INNER JOIN users u ON d.user_id = u.id
                     WHERE d.approval_status = :status
                     ORDER BY d.rating DESC, d.total_services DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Erro ao listar guincheiros: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Aprovar guincheiro
     */
    public function approve($driver_id, $approved_by) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET approval_status = 'approved',
                         approval_date = NOW(),
                         approved_by = :approved_by
                     WHERE id = :driver_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':driver_id', $driver_id);
            $stmt->bindParam(':approved_by', $approved_by);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Atualizar status do usuário
                $user_query = "UPDATE users 
                              SET status = 'active' 
                              WHERE id = (SELECT user_id FROM drivers WHERE id = :driver_id)";
                $user_stmt = $this->conn->prepare($user_query);
                $user_stmt->bindParam(':driver_id', $driver_id);
                $user_stmt->execute();
                
                return [
                    'success' => true,
                    'message' => 'Guincheiro aprovado com sucesso'
                ];
            } else {
                throw new Exception("Guincheiro não encontrado", 404);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao aprovar guincheiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Rejeitar guincheiro
     */
    public function reject($driver_id, $approved_by) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET approval_status = 'rejected',
                         approval_date = NOW(),
                         approved_by = :approved_by
                     WHERE id = :driver_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':driver_id', $driver_id);
            $stmt->bindParam(':approved_by', $approved_by);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Atualizar status do usuário
                $user_query = "UPDATE users 
                              SET status = 'inactive' 
                              WHERE id = (SELECT user_id FROM drivers WHERE id = :driver_id)";
                $user_stmt = $this->conn->prepare($user_query);
                $user_stmt->bindParam(':driver_id', $driver_id);
                $user_stmt->execute();
                
                return [
                    'success' => true,
                    'message' => 'Guincheiro rejeitado'
                ];
            } else {
                throw new Exception("Guincheiro não encontrado", 404);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao rejeitar guincheiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Upload de documentos
     */
    public function uploadDocument($driver_id, $document_type, $file) {
        try {
            // Validar tipo de arquivo
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Tipo de arquivo não permitido. Use apenas JPG, JPEG ou PNG.", 400);
            }
            
            // Validar tamanho (máximo 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("Arquivo muito grande. Máximo 5MB.", 400);
            }
            
            // Gerar nome único para o arquivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $driver_id . '_' . $document_type . '_' . time() . '.' . $extension;
            $upload_dir = '../uploads/documents/';
            $upload_path = $upload_dir . $filename;
            
            // Criar diretório se não existir
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Fazer upload do arquivo
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Atualizar banco de dados
                $field = $document_type . '_photo_path';
                $query = "UPDATE " . $this->table . " SET {$field} = :file_path WHERE id = :driver_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':file_path', $filename);
                $stmt->bindParam(':driver_id', $driver_id);
                $stmt->execute();
                
                return [
                    'success' => true,
                    'message' => 'Documento enviado com sucesso',
                    'filename' => $filename
                ];
            } else {
                throw new Exception("Erro ao fazer upload do arquivo", 500);
            }
            
        } catch (Exception $e) {
            error_log("Erro no upload: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validação de dados do guincheiro
     */
    private function validateDriverData($data) {
        $required_fields = ['cnh', 'cnh_category', 'experience', 'specialty', 'work_region', 
                           'availability', 'truck_plate', 'truck_brand', 'truck_model', 
                           'truck_year', 'truck_capacity', 'professional_terms_accepted', 
                           'background_check_authorized'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new Exception("Campo obrigatório não preenchido: {$field}", 400);
            }
        }
        
        // Validações específicas
        if (!in_array($data['cnh_category'], ['B', 'C', 'D', 'E'])) {
            throw new Exception("Categoria de CNH inválida", 400);
        }
        
        if ($data['truck_year'] < 1990 || $data['truck_year'] > 2025) {
            throw new Exception("Ano do veículo inválido", 400);
        }
    }
    
    /**
     * Limpar CNH
     */
    private function cleanCNH($cnh) {
        return preg_replace('/[^0-9]/', '', $cnh);
    }
}
?>