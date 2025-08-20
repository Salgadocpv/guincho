<?php
/**
 * Inserir Usuários de Teste - Iguincho
 * Script para criar usuários mocados para teste
 */

require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Driver.php';

try {
    echo "<h2>👥 Criação de Usuários de Teste - Iguincho</h2>\n";
    echo "<pre>\n";
    
    echo "🔧 Iniciando criação de usuários de teste...\n\n";
    
    // Dados do cliente de teste
    $clientData = [
        'full_name' => 'João Silva Santos',
        'cpf' => '111.444.777-35', // CPF válido
        'birth_date' => '1990-05-15',
        'phone' => '(11) 99999-1234',
        'whatsapp' => '(11) 99999-1234',
        'email' => 'cliente@iguincho.com',
        'password' => '123456',
        'license_plate' => 'ABC-1234',
        'vehicle_brand' => 'toyota',
        'vehicle_model' => 'corolla',
        'vehicle_year' => 2020,
        'vehicle_color' => 'branco',
        'terms_accepted' => true,
        'marketing_accepted' => true
    ];
    
    // Dados do guincheiro de teste
    $driverUserData = [
        'full_name' => 'Carlos Eduardo Moreira',
        'cpf' => '222.555.888-46',
        'birth_date' => '1985-08-22',
        'phone' => '(11) 88888-5678',
        'whatsapp' => '(11) 88888-5678',
        'email' => 'guincheiro@iguincho.com',
        'password' => '123456',
        'terms_accepted' => true
    ];
    
    $driverProfessionalData = [
        'cnh' => '12345678901',
        'cnh_category' => 'D',
        'experience' => '5-10',
        'specialty' => 'todos',
        'work_region' => 'São Paulo - Grande SP',
        'availability' => '24h',
        'truck_plate' => 'GUI-2024',
        'truck_brand' => 'Ford',
        'truck_model' => 'Cargo 815',
        'truck_year' => 2018,
        'truck_capacity' => 'media',
        'professional_terms_accepted' => true,
        'background_check_authorized' => true
    ];
    
    // Conectar ao banco
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. CRIAR CLIENTE DE TESTE
    echo "👤 CRIANDO CLIENTE DE TESTE:\n";
    echo "📧 Email: {$clientData['email']}\n";
    echo "🔒 Senha: {$clientData['password']}\n";
    echo "📱 Nome: {$clientData['full_name']}\n\n";
    
    try {
        // Verificar se já existe
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':email', $clientData['email']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            echo "ℹ️  Cliente já existe, removendo para recriar...\n";
            $delete_query = "DELETE FROM users WHERE email = :email";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bindParam(':email', $clientData['email']);
            $delete_stmt->execute();
        }
        
        $user = new User();
        $client_result = $user->registerClient($clientData);
        echo "✅ Cliente criado com sucesso! ID: {$client_result['user_id']}\n\n";
        
    } catch (Exception $e) {
        echo "❌ Erro ao criar cliente: " . $e->getMessage() . "\n\n";
    }
    
    // 2. CRIAR GUINCHEIRO DE TESTE
    echo "🚛 CRIANDO GUINCHEIRO DE TESTE:\n";
    echo "📧 Email: {$driverUserData['email']}\n";
    echo "🔒 Senha: {$driverUserData['password']}\n";
    echo "📱 Nome: {$driverUserData['full_name']}\n";
    echo "🚚 Especialidade: Todos os veículos\n";
    echo "⏰ Disponibilidade: 24 horas\n\n";
    
    try {
        // Verificar se já existe
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':email', $driverUserData['email']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            echo "ℹ️  Guincheiro já existe, removendo para recriar...\n";
            
            // Remover dados relacionados primeiro
            $user_data = $check_stmt->fetch();
            $user_id = $user_data['id'];
            
            $delete_driver = "DELETE FROM drivers WHERE user_id = :user_id";
            $delete_driver_stmt = $conn->prepare($delete_driver);
            $delete_driver_stmt->bindParam(':user_id', $user_id);
            $delete_driver_stmt->execute();
            
            $delete_user = "DELETE FROM users WHERE id = :user_id";
            $delete_user_stmt = $conn->prepare($delete_user);
            $delete_user_stmt->bindParam(':user_id', $user_id);
            $delete_user_stmt->execute();
        }
        
        $user = new User();
        $driver_result = $user->registerDriver($driverUserData, $driverProfessionalData);
        echo "✅ Guincheiro criado com sucesso! User ID: {$driver_result['user_id']}, Driver ID: {$driver_result['driver_id']}\n";
        
        // Aprovar automaticamente o guincheiro para teste
        echo "🔓 Aprovando guincheiro para teste...\n";
        $driver = new Driver();
        $approval_result = $driver->approve($driver_result['driver_id'], $driver_result['user_id']);
        echo "✅ Guincheiro aprovado automaticamente!\n\n";
        
    } catch (Exception $e) {
        echo "❌ Erro ao criar guincheiro: " . $e->getMessage() . "\n\n";
    }
    
    // 3. VERIFICAR USUÁRIOS CRIADOS
    echo "🔍 VERIFICANDO USUÁRIOS CRIADOS:\n";
    
    $users_query = "SELECT id, user_type, full_name, email, status, created_at FROM users ORDER BY created_at DESC LIMIT 10";
    $users_stmt = $conn->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "👤 ID: {$user['id']} | Tipo: {$user['user_type']} | Nome: {$user['full_name']} | Email: {$user['email']} | Status: {$user['status']}\n";
    }
    
    echo "\n🎯 DADOS DE ACESSO PARA TESTE:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "👤 CLIENTE DE TESTE:\n";
    echo "   📧 Email: cliente@iguincho.com\n";
    echo "   🔒 Senha: 123456\n";
    echo "   👤 Nome: João Silva Santos\n";
    echo "   🚗 Veículo: Toyota Corolla 2020 Branco (ABC-1234)\n\n";
    
    echo "🚛 GUINCHEIRO DE TESTE:\n";
    echo "   📧 Email: guincheiro@iguincho.com\n";
    echo "   🔒 Senha: 123456\n";
    echo "   👤 Nome: Carlos Eduardo Moreira\n";
    echo "   🚚 Guincho: Ford Cargo 815 2018 (GUI-2024)\n";
    echo "   ⚡ Status: APROVADO e ATIVO\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "🧪 PARA TESTAR:\n";
    echo "1. Acesse: http://localhost/guincho/index.html\n";
    echo "2. Use os dados de login acima\n";
    echo "3. Teste as funcionalidades do sistema\n\n";
    
    echo "✅ USUÁRIOS DE TESTE CRIADOS COM SUCESSO!\n";
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<pre>\n";
    echo "❌ ERRO CRÍTICO:\n";
    echo "📝 Mensagem: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    echo "</pre>\n";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Usuários de Teste - Iguincho</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        pre { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
    </style>
</head>
<body>
    <p><a href="../test/api-test.php">🧪 Testar API</a> | <a href="setup.php">🔧 Setup DB</a> | <a href="../../index.html">🏠 Login</a></p>
</body>
</html>