<?php
/**
 * Script para criar a tabela system_settings
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "🗄️ Criando tabela system_settings...\n\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        category VARCHAR(50) DEFAULT 'general',
        is_public BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT NULL,
        
        INDEX idx_category (category),
        INDEX idx_is_public (is_public),
        INDEX idx_setting_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $result = $conn->exec($sql);
    
    echo "✅ Tabela system_settings criada com sucesso!\n\n";
    
    // Inserir algumas configurações padrão
    echo "📝 Inserindo configurações padrão...\n";
    
    $defaultSettings = [
        ['maintenance_mode', 'false', 'boolean', 'Modo de manutenção do sistema', 'system', 0],
        ['app_name', 'Iguincho', 'string', 'Nome da aplicação', 'general', 1],
        ['app_version', '1.0.0', 'string', 'Versão da aplicação', 'general', 1],
        ['support_email', 'suporte@iguincho.com', 'string', 'E-mail de suporte', 'contact', 1],
        ['support_phone', '(11) 99999-9999', 'string', 'Telefone de suporte', 'contact', 1],
        ['max_distance_km', '50', 'number', 'Distância máxima de atendimento (km)', 'business', 1]
    ];
    
    $insertSql = "INSERT IGNORE INTO system_settings 
                  (setting_key, setting_value, setting_type, description, category, is_public) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insertSql);
    
    foreach ($defaultSettings as $setting) {
        $result = $stmt->execute($setting);
        if ($result) {
            echo "✅ Configuração criada: {$setting[0]}\n";
        }
    }
    
    echo "\n🎉 Tabela system_settings configurada com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>