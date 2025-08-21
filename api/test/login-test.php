<?php
/**
 * Teste de Login - Iguincho
 * Teste direto da API de login
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste de Login - Iguincho</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔑 Teste de Login - Iguincho</h2>
        
        <form onsubmit="testLogin(event)">
            <div class="form-group">
                <label for="email">Email:</label>
                <select id="email">
                    <option value="cliente@iguincho.com">cliente@iguincho.com (Cliente)</option>
                    <option value="guincheiro@iguincho.com">guincheiro@iguincho.com (Guincheiro)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" value="123456">
            </div>
            
            <button type="submit">🚀 Testar Login</button>
        </form>
        
        <div id="result"></div>
    </div>

    <script>
        async function testLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const resultDiv = document.getElementById('result');
            
            resultDiv.innerHTML = '<div class="result">⏳ Testando login...</div>';
            
            try {
                const response = await fetch('/guincho/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="result success">
                            <h3>✅ Login realizado com sucesso!</h3>
                            <p><strong>Usuário:</strong> ${data.data.user.full_name}</p>
                            <p><strong>Tipo:</strong> ${data.data.user.user_type}</p>
                            <p><strong>Email:</strong> ${data.data.user.email}</p>
                            <p><strong>Token:</strong> ${data.data.session_token.substring(0, 20)}...</p>
                            <h4>📋 Resposta completa:</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h3>❌ Erro no login</h3>
                            <p><strong>Mensagem:</strong> ${data.message}</p>
                            <p><strong>Código:</strong> ${data.error_code || 'N/A'}</p>
                            <h4>📋 Resposta completa:</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
                        <h3>❌ Erro de conexão</h3>
                        <p><strong>Erro:</strong> ${error.message}</p>
                        <p>Verifique se o XAMPP está rodando e se o banco local foi configurado.</p>
                    </div>
                `;
            }
        }
        
        // Teste automático ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página carregada. Você pode testar o login agora.');
        });
    </script>
</body>
</html>