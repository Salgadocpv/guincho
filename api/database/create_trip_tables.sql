-- Trip System Database Schema
-- Tabelas para sistema de solicitações e propostas de viagem

-- Tabela de solicitações de viagem
CREATE TABLE IF NOT EXISTS trip_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    service_type ENUM('guincho', 'bateria', 'pneu', 'chaveiro', 'mecanico', 'eletricista') NOT NULL,
    
    -- Localização de origem
    origin_lat DECIMAL(10,8) NOT NULL,
    origin_lng DECIMAL(11,8) NOT NULL,
    origin_address TEXT NOT NULL,
    
    -- Localização de destino
    destination_lat DECIMAL(10,8) NOT NULL,
    destination_lng DECIMAL(11,8) NOT NULL,
    destination_address TEXT NOT NULL,
    
    -- Oferta do cliente
    client_offer DECIMAL(10,2) NOT NULL,
    
    -- Status da solicitação
    status ENUM('pending', 'active', 'completed', 'cancelled', 'expired') DEFAULT 'pending',
    
    -- Metadados
    distance_km DECIMAL(8,2),
    estimated_duration_minutes INT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP, -- Solicitações expiram após X tempo
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_client_id (client_id),
    INDEX idx_service_type (service_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_origin_location (origin_lat, origin_lng),
    INDEX idx_destination_location (destination_lat, destination_lng)
);

-- Tabela de propostas dos guincheiros
CREATE TABLE IF NOT EXISTS trip_bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_request_id INT NOT NULL,
    driver_id INT NOT NULL,
    
    -- Dados da proposta
    bid_amount DECIMAL(10,2) NOT NULL,
    estimated_arrival_minutes INT NOT NULL,
    message TEXT, -- Mensagem opcional do guincheiro
    
    -- Status da proposta
    status ENUM('pending', 'accepted', 'rejected', 'expired', 'withdrawn') DEFAULT 'pending',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL, -- Propostas expiram em 3 minutos
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    
    -- Garantir que um guincheiro só pode fazer uma proposta por solicitação
    UNIQUE KEY unique_driver_bid (trip_request_id, driver_id),
    
    INDEX idx_trip_request_id (trip_request_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);

-- Tabela de viagens ativas/confirmadas
CREATE TABLE IF NOT EXISTS active_trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_request_id INT NOT NULL,
    driver_id INT NOT NULL,
    client_id INT NOT NULL,
    
    -- Dados finais da viagem
    final_price DECIMAL(10,2) NOT NULL,
    service_type ENUM('guincho', 'bateria', 'pneu', 'chaveiro', 'mecanico', 'eletricista') NOT NULL,
    
    -- Localização
    origin_lat DECIMAL(10,8) NOT NULL,
    origin_lng DECIMAL(11,8) NOT NULL,
    origin_address TEXT NOT NULL,
    destination_lat DECIMAL(10,8) NOT NULL,
    destination_lng DECIMAL(11,8) NOT NULL,
    destination_address TEXT NOT NULL,
    
    -- Status da viagem
    status ENUM('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress', 'completed', 'cancelled') DEFAULT 'confirmed',
    
    -- Tracking do guincheiro
    driver_current_lat DECIMAL(10,8),
    driver_current_lng DECIMAL(11,8),
    driver_last_update TIMESTAMP,
    
    -- Avaliações
    client_rating DECIMAL(3,2),
    driver_rating DECIMAL(3,2),
    client_feedback TEXT,
    driver_feedback TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_trip_request_id (trip_request_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_driver_location (driver_current_lat, driver_current_lng)
);

-- Tabela de notificações do sistema
CREATE TABLE IF NOT EXISTS trip_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trip_request_id INT,
    active_trip_id INT,
    
    -- Tipo e conteúdo da notificação
    type ENUM('new_request', 'new_bid', 'bid_accepted', 'bid_rejected', 'trip_started', 'trip_completed', 'driver_arrived', 'trip_cancelled') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    -- Dados extras (JSON)
    extra_data JSON,
    
    -- Status da notificação
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (active_trip_id) REFERENCES active_trips(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_trip_request_id (trip_request_id),
    INDEX idx_active_trip_id (active_trip_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Tabela de histórico de status (auditoria)
CREATE TABLE IF NOT EXISTS trip_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_request_id INT,
    active_trip_id INT,
    
    -- Status change info
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT, -- user_id who made the change
    reason TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (active_trip_id) REFERENCES active_trips(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_trip_request_id (trip_request_id),
    INDEX idx_active_trip_id (active_trip_id),
    INDEX idx_created_at (created_at)
);

-- Atualizar configurações do sistema com novos parâmetros
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
('trip_request_timeout_minutes', '30', 'number', 'Tempo limite para solicitações de viagem (minutos)', 'trip_system', FALSE),
('bid_timeout_minutes', '3', 'number', 'Tempo limite para propostas de guincheiros (minutos)', 'trip_system', FALSE),
('max_bids_per_request', '10', 'number', 'Máximo de propostas por solicitação', 'trip_system', FALSE),
('driver_search_radius_km', '25', 'number', 'Raio de busca por guincheiros (km)', 'trip_system', FALSE),
('allow_counter_offers', 'true', 'boolean', 'Permitir contrapropostas de clientes', 'trip_system', FALSE),
('auto_accept_single_bid', 'false', 'boolean', 'Aceitar automaticamente proposta única', 'trip_system', FALSE),
('notification_push_enabled', 'true', 'boolean', 'Notificações push habilitadas', 'trip_system', FALSE)
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;