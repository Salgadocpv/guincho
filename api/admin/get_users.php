<?php
/**
 * API para listar usuários por tipo (cliente, guincheiro, parceiro)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../middleware/AdminAuth.php';

try {
    // Verificar autenticação de admin
    $admin_auth = new AdminAuth();
    $auth_result = $admin_auth->checkAuth();
    
    if (!$auth_result['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $auth_result['message']]);
        exit;
    }

    // Conectar ao banco
    $database = new Database();
    $conn = $database->getConnection();

    // Obter tipo de usuário da query string
    $userType = $_GET['type'] ?? 'client';
    
    // Validar tipo de usuário
    $validTypes = ['client', 'driver', 'partner', 'admin'];
    if (!in_array($userType, $validTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
        exit;
    }

    // Obter filtros opcionais
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    // Construir query base
    $whereClause = "WHERE user_type = :user_type";
    $params = [':user_type' => $userType];

    // Adicionar filtro de status se especificado
    if (!empty($status)) {
        $whereClause .= " AND status = :status";
        $params[':status'] = $status;
    }

    // Adicionar filtro de busca se especificado
    if (!empty($search)) {
        $whereClause .= " AND (full_name LIKE :search OR email LIKE :search OR phone LIKE :search OR cpf LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Query para contar total de registros
    $countQuery = "SELECT COUNT(*) as total FROM users {$whereClause}";
    $countStmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];

    // Query principal para obter os dados
    $query = "
        SELECT 
            id,
            user_type,
            full_name,
            cpf,
            birth_date,
            phone,
            whatsapp,
            email,
            license_plate,
            vehicle_brand,
            vehicle_model,
            vehicle_year,
            vehicle_color,
            status,
            email_verified,
            terms_accepted,
            marketing_accepted,
            created_at,
            updated_at
        FROM users 
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Mascarar dados sensíveis
        if (!empty($row['cpf'])) {
            $row['cpf_masked'] = substr($row['cpf'], 0, 3) . '.***.***-' . substr($row['cpf'], -2);
        }
        if (!empty($row['phone'])) {
            $row['phone_masked'] = substr($row['phone'], 0, 2) . '****' . substr($row['phone'], -4);
        }
        
        $users[] = $row;
    }

    // Calcular estatísticas básicas para este tipo de usuário
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) as pending_approval,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM users 
        WHERE user_type = :user_type
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bindParam(':user_type', $userType);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Retornar resposta
    echo json_encode([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ],
        'stats' => [
            'total' => (int)$stats['total'],
            'active' => (int)$stats['active'],
            'pending_approval' => (int)$stats['pending_approval'],
            'inactive' => (int)$stats['inactive']
        ],
        'filters' => [
            'type' => $userType,
            'status' => $status,
            'search' => $search
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro ao listar usuários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor',
        'debug' => $e->getMessage()
    ]);
}
?>