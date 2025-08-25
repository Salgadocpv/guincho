<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Usuários para Login</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .user-card { 
            background: white; 
            margin: 15px 0; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        button { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 5px; 
            cursor: pointer;
            margin: 5px;
            font-size: 16px;
        }
        button:hover { background: #218838; }
        .credentials {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            margin: 10px 0;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <h1>👥 Criar Usuários para Login Real</h1>
    
    <div class="user-card">
        <h2>🎯 Objetivo</h2>
        <p>Criar usuários reais no banco para testar o sistema de login com email/senha através do botão da tela de login.</p>
    </div>

    <div class="user-card">
        <h2>👤 Cliente para Testes</h2>
        <div class="credentials">
            <strong>Email:</strong> cliente@teste.com<br>
            <strong>Senha:</strong> 123456<br>
            <strong>Tipo:</strong> client
        </div>
        <button onclick="createClient()">✅ Criar Cliente</button>
        <div id="client-result"></div>
    </div>

    <div class="user-card">
        <h2>🚛 Guincheiro para Testes</h2>
        <div class="credentials">
            <strong>Email:</strong> guincheiro@teste.com<br>
            <strong>Senha:</strong> 123456<br>
            <strong>Tipo:</strong> driver
        </div>
        <button onclick="createDriver()">✅ Criar Guincheiro</button>
        <div id="driver-result"></div>
    </div>

    <div class="user-card">
        <h2>🔐 Como Testar</h2>
        <ol>
            <li>Crie os usuários clicando nos botões acima</li>
            <li>Acesse a <a href="/guincho/" target="_blank">tela de login</a></li>
            <li>Faça login com as credenciais acima</li>
            <li>O sistema deve funcionar normalmente após o login</li>
        </ol>
    </div>

    <script>
        async function createUser(userData, resultElementId) {
            const resultDiv = document.getElementById(resultElementId);
            resultDiv.innerHTML = '⏳ Criando usuário...';
            
            try {
                <?php
                // Incluir dependências PHP
                include_once 'api/config/database.php';
                
                if ($_POST['action'] === 'create_client') {
                    createClientUser();
                } elseif ($_POST['action'] === 'create_driver') {
                    createDriverUser();
                }
                
                function createClientUser() {
                    try {
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        // Verificar se usuário já existe
                        $check = $db->prepare("SELECT id FROM users WHERE email = 'cliente@teste.com' LIMIT 1");
                        $check->execute();
                        
                        if ($check->fetch()) {
                            echo "document.getElementById('client-result').innerHTML = '<span class=\"error\">❌ Cliente já existe!</span>';";
                            return;
                        }
                        
                        // Criar cliente
                        $insert = $db->prepare("
                            INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted, created_at) 
                            VALUES ('client', 'Cliente Teste', 'cliente@teste.com', '(11) 99999-9999', ?, 'active', 1, 1, NOW())
                        ");
                        
                        $password_hash = password_hash('123456', PASSWORD_DEFAULT);
                        $result = $insert->execute([$password_hash]);
                        
                        if ($result) {
                            echo "document.getElementById('client-result').innerHTML = '<span class=\"success\">✅ Cliente criado com sucesso!</span>';";
                        } else {
                            echo "document.getElementById('client-result').innerHTML = '<span class=\"error\">❌ Erro ao criar cliente</span>';";
                        }
                        
                    } catch (Exception $e) {
                        echo "document.getElementById('client-result').innerHTML = '<span class=\"error\">❌ Erro: " . addslashes($e->getMessage()) . "</span>';";
                    }
                }
                
                function createDriverUser() {
                    try {
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        // Verificar se usuário já existe
                        $check = $db->prepare("SELECT id FROM users WHERE email = 'guincheiro@teste.com' LIMIT 1");
                        $check->execute();
                        
                        if ($check->fetch()) {
                            echo "document.getElementById('driver-result').innerHTML = '<span class=\"error\">❌ Guincheiro já existe!</span>';";
                            return;
                        }
                        
                        // Criar guincheiro
                        $insert = $db->prepare("
                            INSERT INTO users (user_type, full_name, email, phone, password_hash, status, email_verified, terms_accepted, created_at) 
                            VALUES ('driver', 'Guincheiro Teste', 'guincheiro@teste.com', '(11) 88888-8888', ?, 'active', 1, 1, NOW())
                        ");
                        
                        $password_hash = password_hash('123456', PASSWORD_DEFAULT);
                        $result = $insert->execute([$password_hash]);
                        
                        if ($result) {
                            $driver_id = $db->lastInsertId();
                            
                            // Criar perfil do guincheiro
                            $profile = $db->prepare("
                                INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, approval_status, created_at) 
                                VALUES (?, '12345678901', 'C', '3-5', 'guincho', 'São Paulo', '24h', 'approved', NOW())
                            ");
                            $profile->execute([$driver_id]);
                            
                            echo "document.getElementById('driver-result').innerHTML = '<span class=\"success\">✅ Guincheiro criado com sucesso!</span>';";
                        } else {
                            echo "document.getElementById('driver-result').innerHTML = '<span class=\"error\">❌ Erro ao criar guincheiro</span>';";
                        }
                        
                    } catch (Exception $e) {
                        echo "document.getElementById('driver-result').innerHTML = '<span class=\"error\">❌ Erro: " . addslashes($e->getMessage()) . "</span>';";
                    }
                }
                ?>
                
            } catch (error) {
                resultDiv.innerHTML = '<span class="error">❌ Erro: ' + error.message + '</span>';
            }
        }
        
        function createClient() {
            // Fazer POST request para criar cliente
            const form = new FormData();
            form.append('action', 'create_client');
            
            fetch(window.location.href, {
                method: 'POST',
                body: form
            })
            .then(() => location.reload())
            .catch(error => {
                document.getElementById('client-result').innerHTML = '<span class="error">❌ Erro: ' + error.message + '</span>';
            });
        }
        
        function createDriver() {
            // Fazer POST request para criar guincheiro
            const form = new FormData();
            form.append('action', 'create_driver');
            
            fetch(window.location.href, {
                method: 'POST',
                body: form
            })
            .then(() => location.reload())
            .catch(error => {
                document.getElementById('driver-result').innerHTML = '<span class="error">❌ Erro: ' + error.message + '</span>';
            });
        }
    </script>
</body>
</html>