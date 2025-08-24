<?php
/**
 * Teste Detalhado da API de Login
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste Detalhado de Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔍 Teste Detalhado da API de Login</h1>

    <div class="test-section">
        <h3>1. Teste Manual via cURL (simulado)</h3>
        <button onclick="testLoginAPI('admin@iguincho.com', 'admin123', 'admin')">Testar Admin</button>
        <button onclick="testLoginAPI('cliente@iguincho.com', 'teste123', 'client')">Testar Cliente</button>
        <button onclick="testLoginAPI('guincheiro@iguincho.com', 'teste123', 'driver')">Testar Guincheiro</button>
        <button onclick="testLoginAPI('inexistente@teste.com', 'senha123', 'error')">Testar Usuário Inexistente</button>
        <div id="apiResults"></div>
    </div>

    <div class="test-section">
        <h3>2. Teste de Headers e CORS</h3>
        <button onclick="testCORS()">Testar CORS</button>
        <div id="corsResults"></div>
    </div>

    <div class="test-section">
        <h3>3. Teste Frontend JavaScript</h3>
        <button onclick="testFrontendLogin('admin')">Testar Quick Login Admin</button>
        <button onclick="testFrontendLogin('client')">Testar Quick Login Cliente</button>
        <button onclick="testFrontendLogin('driver')">Testar Quick Login Guincheiro</button>
        <div id="frontendResults"></div>
    </div>

    <div class="test-section">
        <h3>4. Verificações de Sistema</h3>
        <button onclick="checkSystemInfo()">Verificar Informações do Sistema</button>
        <div id="systemResults"></div>
    </div>

    <p><a href="index.html" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Voltar para Login</a></p>

    <script>
        // Teste de API via fetch
        async function testLoginAPI(email, password, expectedType) {
            const resultsDiv = document.getElementById('apiResults');
            
            try {
                console.log(`🧪 Testing login API: ${email}`);
                
                const startTime = Date.now();
                const response = await fetch('api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                const endTime = Date.now();
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    data = { error: 'Invalid JSON', raw: responseText };
                }
                
                const testResult = `
                    <div class="test-section ${response.ok ? 'success' : 'error'}">
                        <h4>Teste: ${email}</h4>
                        <p><strong>Status HTTP:</strong> ${response.status} ${response.statusText}</p>
                        <p><strong>Tempo de resposta:</strong> ${endTime - startTime}ms</p>
                        <p><strong>Headers:</strong></p>
                        <pre>${Array.from(response.headers.entries()).map(([k,v]) => `${k}: ${v}`).join('\n')}</pre>
                        <p><strong>Resposta:</strong></p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        <p><strong>Status:</strong> ${data.success ? '✅ Sucesso' : '❌ Falhou'}</p>
                        ${data.success ? `<p><strong>Tipo de usuário:</strong> ${data.data?.user?.user_type || 'N/A'}</p>` : ''}
                    </div>
                `;
                
                resultsDiv.innerHTML += testResult;
                
            } catch (error) {
                const errorResult = `
                    <div class="test-section error">
                        <h4>Erro no teste: ${email}</h4>
                        <p><strong>Erro:</strong> ${error.message}</p>
                        <p><strong>Stack:</strong></p>
                        <pre>${error.stack}</pre>
                    </div>
                `;
                resultsDiv.innerHTML += errorResult;
            }
        }

        // Teste de CORS
        async function testCORS() {
            const resultsDiv = document.getElementById('corsResults');
            
            try {
                const response = await fetch('api/auth/login.php', {
                    method: 'OPTIONS'
                });
                
                const corsHeaders = {};
                response.headers.forEach((value, key) => {
                    if (key.toLowerCase().includes('access-control')) {
                        corsHeaders[key] = value;
                    }
                });
                
                resultsDiv.innerHTML = `
                    <div class="test-section info">
                        <h4>Headers CORS encontrados:</h4>
                        <pre>${JSON.stringify(corsHeaders, null, 2)}</pre>
                        <p><strong>Status:</strong> ${Object.keys(corsHeaders).length > 0 ? '✅ CORS configurado' : '❌ CORS não encontrado'}</p>
                    </div>
                `;
                
            } catch (error) {
                resultsDiv.innerHTML = `
                    <div class="test-section error">
                        <h4>Erro no teste CORS:</h4>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }

        // Teste do frontend (simula quickLogin)
        function testFrontendLogin(userType) {
            const resultsDiv = document.getElementById('frontendResults');
            
            const credentials = {
                client: { email: 'cliente@iguincho.com', password: 'teste123' },
                driver: { email: 'guincheiro@iguincho.com', password: 'teste123' },
                admin: { email: 'admin@iguincho.com', password: 'admin123' }
            };

            if (!credentials[userType]) {
                resultsDiv.innerHTML += `
                    <div class="test-section error">
                        <h4>Erro:</h4>
                        <p>Tipo de usuário inválido: ${userType}</p>
                    </div>
                `;
                return;
            }

            // Simular o processo do quickLogin
            console.log(`🎭 Simulating quickLogin for: ${userType}`);
            
            resultsDiv.innerHTML += `
                <div class="test-section info">
                    <h4>Simulando quickLogin(${userType})</h4>
                    <p><strong>Email:</strong> ${credentials[userType].email}</p>
                    <p><strong>Password:</strong> ${'*'.repeat(credentials[userType].password.length)}</p>
                    <p>Executando testLoginAPI...</p>
                </div>
            `;
            
            // Executar o teste da API
            testLoginAPI(credentials[userType].email, credentials[userType].password, userType);
        }

        // Informações do sistema
        function checkSystemInfo() {
            const resultsDiv = document.getElementById('systemResults');
            
            const systemInfo = {
                userAgent: navigator.userAgent,
                url: window.location.href,
                protocol: window.location.protocol,
                host: window.location.host,
                cookies: document.cookie,
                localStorage: Object.keys(localStorage).length,
                sessionStorage: Object.keys(sessionStorage).length,
                javascriptEnabled: true,
                fetchSupported: typeof fetch !== 'undefined',
                promiseSupported: typeof Promise !== 'undefined',
                consoleMessages: 'Check browser console for detailed logs'
            };
            
            resultsDiv.innerHTML = `
                <div class="test-section info">
                    <h4>Informações do Sistema:</h4>
                    <pre>${JSON.stringify(systemInfo, null, 2)}</pre>
                </div>
            `;
        }

        // Executar alguns testes automaticamente ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Página de testes carregada');
            checkSystemInfo();
        });
    </script>
</body>
</html>