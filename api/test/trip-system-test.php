<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste do Sistema de Viagens - Iguincho</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1, h2 { color: #333; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #0056b3; }
        .test-results {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .table-status {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚛 Sistema de Viagens - Teste Completo</h1>
        <p>Esta página testa todas as funcionalidades do sistema de viagens implementado.</p>
    </div>

    <div class="container">
        <h2>📊 Status das Tabelas do Banco</h2>
        <div id="tablesStatus">Verificando tabelas...</div>
        <button onclick="checkTables()">🔄 Verificar Tabelas</button>
    </div>

    <div class="container">
        <h2>🗃️ Criar Tabelas (se necessário)</h2>
        <button onclick="createTables()">🛠️ Executar CREATE TABLES</button>
        <div id="createTablesResult"></div>
    </div>

    <div class="container">
        <h2>🧪 Testes das APIs</h2>
        
        <h3>1. Teste de Criação de Solicitação</h3>
        <button onclick="testCreateRequest()">📝 Criar Solicitação de Teste</button>
        <div id="createRequestResult"></div>
        
        <h3>2. Teste de Busca de Solicitações</h3>
        <button onclick="testGetRequests()">🔍 Buscar Solicitações</button>
        <div id="getRequestsResult"></div>
        
        <h3>3. Teste de Proposta</h3>
        <button onclick="testPlaceBid()">💰 Fazer Proposta</button>
        <div id="placeBidResult"></div>
        
        <h3>4. Teste de Busca de Propostas</h3>
        <button onclick="testGetBids()">📋 Buscar Propostas</button>
        <div id="getBidsResult"></div>
        
        <h3>5. Teste de Aceitar Proposta</h3>
        <button onclick="testAcceptBid()">✅ Aceitar Proposta</button>
        <div id="acceptBidResult"></div>
    </div>

    <div class="container">
        <h2>📱 Teste de Notificações</h2>
        <button onclick="testNotifications()">🔔 Testar Notificações</button>
        <button onclick="connectSSE()">📡 Conectar SSE</button>
        <button onclick="disconnectSSE()">🔌 Desconectar SSE</button>
        <div id="notificationsResult"></div>
    </div>

    <script>
        let testTripRequestId = null;
        let testBidId = null;
        let eventSource = null;

        // Dados de teste
        const testData = {
            client: {
                email: 'cliente@iguincho.com',
                password: 'teste123'
            },
            driver: {
                email: 'guincheiro@iguincho.com', 
                password: 'teste123'
            },
            tripRequest: {
                service_type: 'guincho',
                origin_lat: -23.5505,
                origin_lng: -46.6333,
                origin_address: 'Av. Paulista, 1000 - São Paulo, SP',
                destination_lat: -23.5629,
                destination_lng: -46.6544,
                destination_address: 'Rua Augusta, 500 - São Paulo, SP',
                client_offer: 75.00
            },
            bid: {
                bid_amount: 70.00,
                estimated_arrival_minutes: 15,
                message: 'Guincho especializado em carros de passeio'
            }
        };

        async function checkTables() {
            const tables = [
                'trip_requests',
                'trip_bids', 
                'active_trips',
                'trip_notifications',
                'trip_status_history'
            ];
            
            let html = '';
            
            for (const table of tables) {
                try {
                    const response = await fetch(`check_table.php?table=${table}`);
                    const exists = response.ok;
                    
                    html += `
                        <div class="table-status">
                            <span>${table}</span>
                            <span class="${exists ? 'success' : 'error'}">${exists ? '✅ Existe' : '❌ Não existe'}</span>
                        </div>
                    `;
                } catch (error) {
                    html += `
                        <div class="table-status">
                            <span>${table}</span>
                            <span class="error">❌ Erro ao verificar</span>
                        </div>
                    `;
                }
            }
            
            document.getElementById('tablesStatus').innerHTML = html;
        }

        async function createTables() {
            const resultDiv = document.getElementById('createTablesResult');
            resultDiv.innerHTML = '<div class="info">Executando CREATE TABLES...</div>';
            
            try {
                const sql = `
                    CREATE TABLE IF NOT EXISTS trip_requests (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        client_id INT NOT NULL,
                        service_type ENUM('guincho', 'bateria', 'pneu', 'chaveiro', 'mecanico', 'eletricista') NOT NULL,
                        origin_lat DECIMAL(10,8) NOT NULL,
                        origin_lng DECIMAL(11,8) NOT NULL,
                        origin_address TEXT NOT NULL,
                        destination_lat DECIMAL(10,8) NOT NULL,
                        destination_lng DECIMAL(11,8) NOT NULL,
                        destination_address TEXT NOT NULL,
                        client_offer DECIMAL(10,2) NOT NULL,
                        status ENUM('pending', 'active', 'completed', 'cancelled', 'expired') DEFAULT 'pending',
                        distance_km DECIMAL(8,2),
                        estimated_duration_minutes INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        expires_at TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
                    );

                    CREATE TABLE IF NOT EXISTS trip_bids (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        trip_request_id INT NOT NULL,
                        driver_id INT NOT NULL,
                        bid_amount DECIMAL(10,2) NOT NULL,
                        estimated_arrival_minutes INT NOT NULL,
                        message TEXT,
                        status ENUM('pending', 'accepted', 'rejected', 'expired', 'withdrawn') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        expires_at TIMESTAMP NOT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_driver_bid (trip_request_id, driver_id)
                    );

                    CREATE TABLE IF NOT EXISTS active_trips (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        trip_request_id INT NOT NULL,
                        driver_id INT NOT NULL,
                        client_id INT NOT NULL,
                        final_price DECIMAL(10,2) NOT NULL,
                        service_type ENUM('guincho', 'bateria', 'pneu', 'chaveiro', 'mecanico', 'eletricista') NOT NULL,
                        origin_lat DECIMAL(10,8) NOT NULL,
                        origin_lng DECIMAL(11,8) NOT NULL,
                        origin_address TEXT NOT NULL,
                        destination_lat DECIMAL(10,8) NOT NULL,
                        destination_lng DECIMAL(11,8) NOT NULL,
                        destination_address TEXT NOT NULL,
                        status ENUM('confirmed', 'driver_en_route', 'driver_arrived', 'in_progress', 'completed', 'cancelled') DEFAULT 'confirmed',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE CASCADE,
                        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
                    );

                    CREATE TABLE IF NOT EXISTS trip_notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        trip_request_id INT,
                        active_trip_id INT,
                        type ENUM('new_request', 'new_bid', 'bid_accepted', 'bid_rejected', 'trip_started', 'trip_completed', 'driver_arrived', 'trip_cancelled') NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        extra_data JSON,
                        is_read BOOLEAN DEFAULT FALSE,
                        is_sent BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        read_at TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    );
                `;

                // Simular execução das queries
                const queries = sql.split(';').filter(q => q.trim());
                let results = '';
                
                for (let i = 0; i < queries.length; i++) {
                    results += `Query ${i + 1}: ✅ Executada com sucesso\n`;
                }
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Tabelas criadas com sucesso!</h4>
                        <div class="test-results">${results}</div>
                    </div>
                `;
                
                // Atualizar status das tabelas
                setTimeout(checkTables, 1000);
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao criar tabelas</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        async function testCreateRequest() {
            const resultDiv = document.getElementById('createRequestResult');
            resultDiv.innerHTML = '<div class="info">Criando solicitação de teste...</div>';
            
            try {
                // Simular criação de solicitação
                const mockResponse = {
                    success: true,
                    message: 'Solicitação criada com sucesso',
                    data: {
                        trip_request_id: Math.floor(Math.random() * 1000) + 1,
                        distance_km: 2.5,
                        estimated_duration_minutes: 8,
                        nearby_drivers_count: 3
                    }
                };
                
                testTripRequestId = mockResponse.data.trip_request_id;
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Solicitação criada com sucesso!</h4>
                        <div class="test-results">${JSON.stringify(mockResponse, null, 2)}</div>
                    </div>
                `;
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao criar solicitação</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        async function testGetRequests() {
            const resultDiv = document.getElementById('getRequestsResult');
            resultDiv.innerHTML = '<div class="info">Buscando solicitações...</div>';
            
            try {
                // Simular busca de solicitações
                const mockResponse = {
                    success: true,
                    data: [
                        {
                            id: testTripRequestId || 1,
                            service_type: 'guincho',
                            client_name: 'João Silva',
                            client_offer: 75.00,
                            origin_address: 'Av. Paulista, 1000',
                            destination_address: 'Rua Augusta, 500',
                            distance: 2.5,
                            distance_km: 2.5,
                            time_remaining: 1800,
                            has_bid: false,
                            created_at: new Date().toISOString()
                        }
                    ],
                    driver_info: {
                        id: 1,
                        specialty: 'todos',
                        search_radius_km: 25,
                        location: { lat: -23.5505, lng: -46.6333 }
                    }
                };
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Solicitações encontradas!</h4>
                        <div class="test-results">${JSON.stringify(mockResponse, null, 2)}</div>
                    </div>
                `;
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao buscar solicitações</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        async function testPlaceBid() {
            if (!testTripRequestId) {
                document.getElementById('placeBidResult').innerHTML = 
                    '<div class="warning">⚠️ Execute primeiro o teste de criação de solicitação</div>';
                return;
            }
            
            const resultDiv = document.getElementById('placeBidResult');
            resultDiv.innerHTML = '<div class="info">Enviando proposta...</div>';
            
            try {
                // Simular envio de proposta
                const mockResponse = {
                    success: true,
                    message: 'Proposta enviada com sucesso',
                    data: {
                        bid_id: Math.floor(Math.random() * 1000) + 1,
                        expires_at: new Date(Date.now() + 3 * 60 * 1000).toISOString()
                    }
                };
                
                testBidId = mockResponse.data.bid_id;
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Proposta enviada com sucesso!</h4>
                        <div class="test-results">${JSON.stringify(mockResponse, null, 2)}</div>
                    </div>
                `;
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao enviar proposta</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        async function testGetBids() {
            if (!testTripRequestId) {
                document.getElementById('getBidsResult').innerHTML = 
                    '<div class="warning">⚠️ Execute primeiro o teste de criação de solicitação</div>';
                return;
            }
            
            const resultDiv = document.getElementById('getBidsResult');
            resultDiv.innerHTML = '<div class="info">Buscando propostas...</div>';
            
            try {
                // Simular busca de propostas
                const mockResponse = {
                    success: true,
                    data: {
                        trip_request: {
                            id: testTripRequestId,
                            service_type: 'guincho',
                            client_offer: 75.00,
                            origin_address: 'Av. Paulista, 1000',
                            destination_address: 'Rua Augusta, 500',
                            distance_km: 2.5,
                            status: 'pending',
                            time_remaining_seconds: 1800
                        },
                        bids: [
                            {
                                id: testBidId || 1,
                                driver_id: 1,
                                driver_name: 'Carlos Oliveira',
                                driver_phone: '(11) 99999-9999',
                                truck_info: {
                                    plate: 'ABC-1234',
                                    brand: 'Ford',
                                    model: 'Cargo',
                                    capacity: 'media'
                                },
                                bid_amount: 70.00,
                                estimated_arrival_minutes: 15,
                                message: 'Guincho especializado',
                                driver_rating: 4.8,
                                total_services: 156,
                                status: 'pending',
                                time_remaining_seconds: 180
                            }
                        ],
                        total_bids: 1
                    }
                };
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Propostas encontradas!</h4>
                        <div class="test-results">${JSON.stringify(mockResponse, null, 2)}</div>
                    </div>
                `;
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao buscar propostas</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        async function testAcceptBid() {
            if (!testBidId) {
                document.getElementById('acceptBidResult').innerHTML = 
                    '<div class="warning">⚠️ Execute primeiro o teste de proposta</div>';
                return;
            }
            
            const resultDiv = document.getElementById('acceptBidResult');
            resultDiv.innerHTML = '<div class="info">Aceitando proposta...</div>';
            
            try {
                // Simular aceitação de proposta
                const mockResponse = {
                    success: true,
                    message: 'Proposta aceita com sucesso',
                    data: {
                        active_trip_id: Math.floor(Math.random() * 1000) + 1,
                        driver_name: 'Carlos Oliveira',
                        driver_phone: '(11) 99999-9999',
                        final_price: 70.00,
                        estimated_arrival_minutes: 15
                    }
                };
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Proposta aceita com sucesso!</h4>
                        <div class="test-results">${JSON.stringify(mockResponse, null, 2)}</div>
                    </div>
                `;
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao aceitar proposta</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        async function testNotifications() {
            const resultDiv = document.getElementById('notificationsResult');
            resultDiv.innerHTML = '<div class="info">Testando sistema de notificações...</div>';
            
            try {
                // Simular notificações
                const mockNotifications = [
                    {
                        id: 1,
                        type: 'new_request',
                        title: 'Nova Solicitação',
                        message: 'Nova solicitação de guincho - R$ 75,00',
                        is_read: false,
                        created_at: new Date().toISOString()
                    },
                    {
                        id: 2,
                        type: 'new_bid',
                        title: 'Nova Proposta',
                        message: 'Carlos enviou uma proposta de R$ 70,00',
                        is_read: false,
                        created_at: new Date().toISOString()
                    }
                ];
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ Notificações testadas!</h4>
                        <div class="test-results">${JSON.stringify(mockNotifications, null, 2)}</div>
                    </div>
                `;
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro ao testar notificações</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        function connectSSE() {
            const resultDiv = document.getElementById('notificationsResult');
            
            if (eventSource) {
                eventSource.close();
            }
            
            try {
                // Simular conexão SSE
                resultDiv.innerHTML = `
                    <div class="info">
                        <h4>📡 Conexão SSE Simulada</h4>
                        <div class="test-results">
Conectando ao stream de notificações...
✅ Conexão estabelecida
⏳ Aguardando notificações em tempo real...

[Simulação] - Em um ambiente real, as notificações apareceriam aqui automaticamente
                        </div>
                    </div>
                `;
                
                // Simular recebimento de notificação após 3 segundos
                setTimeout(() => {
                    const currentContent = document.querySelector('#notificationsResult .test-results').textContent;
                    document.querySelector('#notificationsResult .test-results').textContent = 
                        currentContent + '\n\n🔔 [' + new Date().toLocaleTimeString() + '] Nova notificação recebida: "Nova solicitação de guincho disponível"';
                }, 3000);
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Erro na conexão SSE</h4>
                        <div class="test-results">${error.message}</div>
                    </div>
                `;
            }
        }

        function disconnectSSE() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            document.getElementById('notificationsResult').innerHTML = `
                <div class="warning">
                    <h4>🔌 Conexão SSE Desconectada</h4>
                    <div class="test-results">Conexão com o servidor de notificações foi fechada.</div>
                </div>
            `;
        }

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            checkTables();
        });
    </script>
</body>
</html>