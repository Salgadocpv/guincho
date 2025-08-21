<?php
/**
 * Teste simples de login para verificar se funciona com admin
 */

// Ajustar diretório de trabalho
chdir(dirname(__DIR__) . '/auth');
require_once '../classes/User.php';

try {
    echo "🧪 Testando login do administrador...\n\n";
    
    $user = new User();
    $result = $user->login('admin@iguincho.com', 'admin123');
    
    echo "✅ Login realizado com sucesso!\n";
    echo "Dados do usuário:\n";
    echo "ID: " . $result['user']['id'] . "\n";
    echo "Nome: " . $result['user']['full_name'] . "\n";
    echo "Email: " . $result['user']['email'] . "\n";
    echo "Tipo: '" . $result['user']['user_type'] . "'\n";
    echo "Status: " . $result['user']['status'] . "\n";
    
    echo "\nToken de sessão: " . substr($result['session_token'], 0, 20) . "...\n";
    echo "Expira em: " . $result['expires_at'] . "\n";
    
    // Verificar redirecionamento
    $userType = $result['user']['user_type'];
    echo "\n🔄 Redirecionamento baseado no tipo de usuário:\n";
    
    switch($userType) {
        case 'admin':
            echo "➡️ Deve redirecionar para: admin/dashboard.html\n";
            break;
        case 'driver':
            echo "➡️ Deve redirecionar para: driver/dashboard.html\n";
            break;
        case 'partner':
            echo "➡️ Deve redirecionar para: partner/dashboard.html\n";
            break;
        case 'client':
        default:
            echo "➡️ Deve redirecionar para: services.html\n";
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Erro no login: " . $e->getMessage() . "\n";
}
?>