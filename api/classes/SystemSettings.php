<?php
/**
 * Classe SystemSettings - Gerenciamento de Parâmetros do Sistema
 */

require_once dirname(__DIR__) . '/config/database.php';

class SystemSettings {
    private $db;
    private $table = 'system_settings';
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Obter todas as configurações
     */
    public function getAll($includePrivate = false) {
        try {
            $sql = "SELECT setting_key, setting_value, setting_type, description, category, is_public 
                   FROM {$this->table}";
            
            if (!$includePrivate) {
                $sql .= " WHERE is_public = TRUE";
            }
            
            $sql .= " ORDER BY category ASC, setting_key ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['setting_key'];
                $value = $this->convertValue($row['setting_value'], $row['setting_type']);
                
                $settings[$key] = [
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'category' => $row['category'],
                    'is_public' => (bool)$row['is_public']
                ];
            }
            
            return $settings;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao obter configurações: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter configuração específica
     */
    public function get($key, $default = null) {
        try {
            $sql = "SELECT setting_value, setting_type FROM {$this->table} WHERE setting_key = :key";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['key' => $key]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                return $default;
            }
            
            return $this->convertValue($row['setting_value'], $row['setting_type']);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao obter configuração: ' . $e->getMessage());
        }
    }
    
    /**
     * Definir configuração
     */
    public function set($key, $value, $updatedBy = null) {
        try {
            // Get current setting to determine type
            $currentSql = "SELECT setting_type FROM {$this->table} WHERE setting_key = :key";
            $currentStmt = $this->db->prepare($currentSql);
            $currentStmt->execute(['key' => $key]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                throw new Exception("Configuração '{$key}' não encontrada");
            }
            
            $settingType = $current['setting_type'];
            $formattedValue = $this->formatValue($value, $settingType);
            
            $sql = "UPDATE {$this->table} 
                   SET setting_value = :value, 
                       updated_at = NOW(),
                       updated_by = :updated_by
                   WHERE setting_key = :key";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'value' => $formattedValue,
                'updated_by' => $updatedBy,
                'key' => $key
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao definir configuração: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar múltiplas configurações
     */
    public function setMultiple($settings, $updatedBy = null) {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $this->set($key, $value, $updatedBy);
            }
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception('Erro ao atualizar configurações: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter configurações por categoria
     */
    public function getByCategory($category, $includePrivate = false) {
        try {
            $sql = "SELECT setting_key, setting_value, setting_type, description, is_public 
                   FROM {$this->table} 
                   WHERE category = :category";
            
            if (!$includePrivate) {
                $sql .= " AND is_public = TRUE";
            }
            
            $sql .= " ORDER BY setting_key ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['category' => $category]);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['setting_key'];
                $value = $this->convertValue($row['setting_value'], $row['setting_type']);
                
                $settings[$key] = [
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'is_public' => (bool)$row['is_public']
                ];
            }
            
            return $settings;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao obter configurações da categoria: ' . $e->getMessage());
        }
    }
    
    /**
     * Converter valor baseado no tipo
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'json':
                return json_decode($value, true) ?: [];
            case 'string':
            default:
                return (string)$value;
        }
    }
    
    /**
     * Formatar valor para armazenamento
     */
    private function formatValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'number':
                return (string)$value;
            case 'json':
                return json_encode($value);
            case 'string':
            default:
                return (string)$value;
        }
    }
    
    /**
     * Criar nova configuração
     */
    public function create($key, $value, $type = 'string', $description = '', $category = 'general', $isPublic = false) {
        try {
            $sql = "INSERT INTO {$this->table} 
                   (setting_key, setting_value, setting_type, description, category, is_public) 
                   VALUES (:key, :value, :type, :description, :category, :is_public)";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'key' => $key,
                'value' => $this->formatValue($value, $type),
                'type' => $type,
                'description' => $description,
                'category' => $category,
                'is_public' => $isPublic ? 1 : 0
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erro ao criar configuração: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar se está em modo de manutenção
     */
    public function isMaintenanceMode() {
        return $this->get('maintenance_mode', false);
    }
    
    /**
     * Verificar se configuração existe
     */
    public function exists($key) {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE setting_key = :key";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['key' => $key]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao verificar configuração: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter configurações públicas para frontend
     */
    public function getPublicSettings() {
        try {
            $settings = $this->getAll(false);
            $publicSettings = [];
            
            foreach ($settings as $key => $config) {
                if ($config['is_public']) {
                    $publicSettings[$key] = $config['value'];
                }
            }
            
            return $publicSettings;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao obter configurações públicas: ' . $e->getMessage());
        }
    }
}
?>