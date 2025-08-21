-- Iguincho Database Schema
-- Tabelas para sistema de cadastro de usuários e parceiros

-- Tabela de usuários (clientes)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('client', 'driver', 'partner') NOT NULL DEFAULT 'client',
    
    -- Dados pessoais
    full_name VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) UNIQUE NOT NULL,
    birth_date DATE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20),
    
    -- Acesso
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Dados do veículo (para clientes)
    license_plate VARCHAR(10),
    vehicle_brand VARCHAR(100),
    vehicle_model VARCHAR(100),
    vehicle_year INT,
    vehicle_color VARCHAR(50),
    
    -- Termos e preferências
    terms_accepted BOOLEAN DEFAULT FALSE,
    marketing_accepted BOOLEAN DEFAULT FALSE,
    
    -- Controle
    status ENUM('active', 'inactive', 'pending_approval', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_cpf (cpf),
    INDEX idx_user_type (user_type),
    INDEX idx_status (status)
);

-- Tabela de parceiros (guincheiros)
CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Dados profissionais
    cnh VARCHAR(20) NOT NULL,
    cnh_category ENUM('B', 'C', 'D', 'E') NOT NULL,
    experience ENUM('0-1', '1-3', '3-5', '5-10', '10+') NOT NULL,
    specialty ENUM('carros', 'motos', 'suv', 'caminhoes', 'todos') NOT NULL,
    work_region VARCHAR(255) NOT NULL,
    availability ENUM('24h', 'comercial', 'noturno', 'fds', 'personalizado') NOT NULL,
    
    -- Dados do guincho
    truck_plate VARCHAR(10) NOT NULL,
    truck_brand VARCHAR(100) NOT NULL,
    truck_model VARCHAR(100) NOT NULL,
    truck_year INT NOT NULL,
    truck_capacity ENUM('leve', 'media', 'pesada', 'extra') NOT NULL,
    
    -- Documentação
    cnh_photo_path VARCHAR(255),
    crlv_photo_path VARCHAR(255),
    vehicle_photo_path VARCHAR(255),
    
    -- Termos específicos
    professional_terms_accepted BOOLEAN DEFAULT FALSE,
    background_check_authorized BOOLEAN DEFAULT FALSE,
    
    -- Status profissional
    approval_status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    approval_date TIMESTAMP NULL,
    approved_by INT NULL,
    
    -- Ratings e estatísticas
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_services INT DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_cnh (cnh),
    INDEX idx_approval_status (approval_status),
    INDEX idx_specialty (specialty),
    INDEX idx_work_region (work_region)
);

-- Tabela de parceiros (estabelecimentos)
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Tipo de negócio
    business_type ENUM('lava-rapido', 'mecanica', 'auto-eletrica') NOT NULL,
    
    -- Dados do estabelecimento
    business_name VARCHAR(255) NOT NULL,
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    address TEXT NOT NULL,
    zip_code VARCHAR(9) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(2) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    hours VARCHAR(255) NOT NULL,
    
    -- Dados do proprietário
    owner_name VARCHAR(255) NOT NULL,
    owner_cpf VARCHAR(14) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    
    -- Documentação
    business_license_path VARCHAR(255),
    business_photo_path VARCHAR(255),
    
    -- Termos específicos
    partner_terms_accepted BOOLEAN DEFAULT FALSE,
    data_sharing_authorized BOOLEAN DEFAULT FALSE,
    
    -- Status profissional
    approval_status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    approval_date TIMESTAMP NULL,
    approved_by INT NULL,
    
    -- Ratings e estatísticas
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_services INT DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_cnpj (cnpj),
    INDEX idx_business_type (business_type),
    INDEX idx_approval_status (approval_status),
    INDEX idx_city_state (city, state)
);

-- Tabela de sessões de usuário
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Tabela de logs de auditoria
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Inserir dados de teste (opcional)
INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted) VALUES
('client', 'João da Silva', '123.456.789-00', '1990-05-15', '(11) 99999-9999', 'joao@exemplo.com', '$2y$10$example_hash', TRUE),
('client', 'Maria Santos', '987.654.321-00', '1985-08-22', '(11) 88888-8888', 'maria@exemplo.com', '$2y$10$example_hash', TRUE);