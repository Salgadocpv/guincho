-- Sistema de Créditos para Guincheiros
-- Tabela para controle de créditos dos guincheiros

-- Tabela para configurações de crédito
CREATE TABLE IF NOT EXISTS credit_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_per_trip DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    pix_rate DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    minimum_credit DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabela de créditos dos guincheiros
CREATE TABLE IF NOT EXISTS driver_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    current_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_added DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_spent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_driver (driver_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_balance (current_balance)
);

-- Tabela de transações de crédito
CREATE TABLE IF NOT EXISTS credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    transaction_type ENUM('add', 'spend', 'refund', 'adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    
    -- Dados específicos para cada tipo de transação
    trip_id INT NULL, -- Para gastos em viagens
    pix_transaction_id VARCHAR(255) NULL, -- Para adições via PIX
    pix_key VARCHAR(255) NULL, -- Chave PIX usada
    pix_amount DECIMAL(10,2) NULL, -- Valor pago via PIX
    
    -- Descrição e observações
    description TEXT NULL,
    notes TEXT NULL,
    
    -- Status da transação
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'completed',
    
    -- Auditoria
    processed_by INT NULL, -- Admin que processou (se manual)
    processed_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES trip_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_driver_id (driver_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_trip_id (trip_id),
    INDEX idx_pix_transaction (pix_transaction_id)
);

-- Tabela para solicitações de PIX
CREATE TABLE IF NOT EXISTS pix_credit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    amount_requested DECIMAL(10,2) NOT NULL,
    credits_to_receive DECIMAL(10,2) NOT NULL,
    
    -- Dados do PIX
    pix_key VARCHAR(255) NOT NULL,
    pix_key_type ENUM('cpf', 'cnpj', 'email', 'phone', 'random') NOT NULL,
    
    -- Status da solicitação
    status ENUM('pending', 'payment_confirmed', 'credits_added', 'cancelled', 'expired') DEFAULT 'pending',
    
    -- Comprovante
    payment_proof_path VARCHAR(255) NULL,
    payment_proof_uploaded_at TIMESTAMP NULL,
    
    -- Processamento
    confirmed_by INT NULL,
    confirmed_at TIMESTAMP NULL,
    confirmation_notes TEXT NULL,
    
    -- Expiração
    expires_at TIMESTAMP NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
);

-- Inserir configurações padrão
INSERT INTO credit_settings (credit_per_trip, pix_rate, minimum_credit) VALUES
(15.00, 1.00, 5.00);

-- Inserir configurações do sistema para créditos
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
('credit_per_trip', '15.00', 'number', 'Valor em créditos cobrado por viagem', 'credits', FALSE),
('pix_credit_rate', '1.00', 'number', 'Taxa de conversão PIX para créditos (1 real = X créditos)', 'credits', FALSE),
('minimum_credit_balance', '5.00', 'number', 'Saldo mínimo de créditos para aceitar viagens', 'credits', FALSE),
('max_pix_credit_amount', '500.00', 'number', 'Valor máximo para recarga via PIX', 'credits', FALSE),
('min_pix_credit_amount', '10.00', 'number', 'Valor mínimo para recarga via PIX', 'credits', FALSE),
('pix_request_expiry_hours', '24', 'number', 'Horas para expirar solicitação de PIX', 'credits', FALSE);