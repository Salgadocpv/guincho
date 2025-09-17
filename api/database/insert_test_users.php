<?php
// Inserir usuários de teste no banco local

require_once '../config/database_local.php';

try {
    $database = new DatabaseLocal();
    $pdo = $database->getConnection();
    
    echo "<h2>Inserindo usuários de teste...</h2>";
    
    $pdo->beginTransaction();
    
    // Criar admin de teste
    $stmt = $pdo->prepare("INSERT INTO users (user_type, full_name, cpf, birth_date, phone, email, password_hash, terms_accepted, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'admin',
        'Administrador Teste',
        '000.000.000-00',
        '1990-01-01',
        '(11) 99999-9999',
        'admin@iguincho.com',
        password_hash('admin123', PASSWORD_ARGON2I),
        true,
        'active',
        true
    ]);
    $admin_id = $pdo->lastInsertId();
    echo "✅ Admin criado: admin@iguincho.com / admin123<br>";
    
    // Criar cliente de teste
    $stmt->execute([
        'client',
        'Maria Silva Santos',
        '123.456.789-10',
        '1990-05-15',
        '(11) 98765-4321',
        'maria.silva@teste.com',
        password_hash('senha123', PASSWORD_ARGON2I),
        true,
        'active',
        true
    ]);
    $client_id = $pdo->lastInsertId();
    echo "✅ Cliente criado: maria.silva@teste.com / senha123<br>";
    
    // Criar guincheiro de teste
    $stmt->execute([
        'driver',
        'Carlos Eduardo Silva',
        '987.654.321-00',
        '1985-03-20',
        '(11) 99887-6543',
        'carlos.guincheiro@teste.com',
        password_hash('guincheiro123', PASSWORD_ARGON2I),
        true,
        'active',
        true
    ]);
    $driver_user_id = $pdo->lastInsertId();
    echo "✅ Usuário guincheiro criado: carlos.guincheiro@teste.com / guincheiro123<br>";
    
    // Criar perfil do guincheiro
    $stmt = $pdo->prepare("INSERT INTO drivers (user_id, cnh, cnh_category, experience, specialty, work_region, availability, truck_plate, truck_brand, truck_model, truck_year, truck_capacity, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $driver_user_id,
        '12345678901',
        'D',
        '5-10',
        'todos',
        'São Paulo - Centro, Zona Sul',
        '24h',
        'GUN-2023',
        'Ford',
        'Cargo 816',
        2018,
        'media',
        'approved'
    ]);
    echo "✅ Perfil do guincheiro criado<br>";
    
    $pdo->commit();
    
    echo "<br><h3>🎉 Usuários de teste criados com sucesso!</h3>";
    echo "<h4>Credenciais:</h4>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@iguincho.com / admin123</li>";
    echo "<li><strong>Cliente:</strong> maria.silva@teste.com / senha123</li>";
    echo "<li><strong>Guincheiro:</strong> carlos.guincheiro@teste.com / guincheiro123</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "❌ Erro ao inserir usuários: " . $e->getMessage();
    echo "<br>Detalhes: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>