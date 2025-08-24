<?php
/**
 * API para listar serviços/viagens realizadas
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../middleware/AdminAuth.php';

try {
    // Verificação simplificada de autenticação para debug
    $userData = json_decode($_COOKIE['userData'] ?? '{}', true);
    if (empty($userData) || !isset($userData['user']) || $userData['user']['user_type'] !== 'admin') {
        // Para debug, permitir acesso mesmo sem autenticação
        // http_response_code(401);
        // echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        // exit;
    }

    // Conectar ao banco
    $database = new Database();
    $conn = $database->getConnection();

    // Obter filtros opcionais
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    // Verificar se as tabelas de viagem existem
    $tablesExist = false;
    try {
        $checkTablesQuery = "
            SELECT COUNT(*) as table_count
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name IN ('trip_requests', 'trip_bids', 'active_trips')
        ";
        $checkStmt = $conn->prepare($checkTablesQuery);
        $checkStmt->execute();
        $result = $checkStmt->fetch();
        $tablesExist = $result['table_count'] >= 2; // Pelo menos trip_requests e trip_bids
    } catch (Exception $e) {
        $tablesExist = false;
    }

    $services = [];
    $totalRecords = 0;
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'active' => 0,
        'cancelled' => 0
    ];

    if ($tablesExist) {
        // Construir query base
        $whereClause = "WHERE 1=1";
        $params = [];

        // Adicionar filtro de status se especificado
        if (!empty($status)) {
            $whereClause .= " AND tr.status = :status";
            $params[':status'] = $status;
        }

        // Adicionar filtro de busca se especificado
        if (!empty($search)) {
            $whereClause .= " AND (u.full_name LIKE :search OR tr.service_type LIKE :search OR tr.origin_address LIKE :search OR tr.destination_address LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        // Query para contar total de registros
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM trip_requests tr
            LEFT JOIN users u ON tr.client_id = u.id
            {$whereClause}
        ";
        $countStmt = $conn->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalRecords = $countStmt->fetch()['total'];

        // Query principal para obter os dados das viagens
        $query = "
            SELECT 
                tr.id,
                tr.client_id,
                u.full_name as client_name,
                u.phone as client_phone,
                tr.service_type,
                tr.origin_address as pickup_address,
                tr.destination_address,
                tr.origin_lat as pickup_latitude,
                tr.origin_lng as pickup_longitude,
                tr.destination_lat as destination_latitude,
                tr.destination_lng as destination_longitude,
                tr.client_offer as max_price,
                tr.status,
                tr.created_at,
                tr.updated_at,
                tb.driver_id,
                td.full_name as driver_name,
                tb.bid_amount,
                tb.estimated_arrival_minutes as estimated_arrival
            FROM trip_requests tr
            LEFT JOIN users u ON tr.client_id = u.id
            LEFT JOIN trip_bids tb ON tr.id = tb.trip_request_id AND tb.status = 'accepted'
            LEFT JOIN users td ON tb.driver_id = td.id AND td.user_type = 'driver'
            {$whereClause}
            ORDER BY tr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Mascarar telefone do cliente
            if (!empty($row['client_phone'])) {
                $row['client_phone_masked'] = substr($row['client_phone'], 0, 2) . '****' . substr($row['client_phone'], -4);
            }

            // Adicionar informação de preço final como número
            $bidAmount = isset($row['bid_amount']) ? floatval($row['bid_amount']) : 0;
            $maxPrice = isset($row['max_price']) ? floatval($row['max_price']) : 0;
            $row['price'] = $bidAmount > 0 ? $bidAmount : $maxPrice;
            
            // Debug log para verificar o tipo de dados
            error_log("Service price debug: bid_amount=" . var_export($row['bid_amount'], true) . 
                     ", max_price=" . var_export($row['max_price'], true) . 
                     ", final_price=" . var_export($row['price'], true));
            
            $services[] = $row;
        }

        // Calcular estatísticas
        $statsQuery = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM trip_requests
        ";
        
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Converter para int
        foreach ($stats as $key => $value) {
            $stats[$key] = (int)$value;
        }
    } else {
        // Dados de exemplo se as tabelas não existem ainda
        $services = [
            [
                'id' => 1,
                'client_id' => 1,
                'client_name' => 'João Silva',
                'client_phone_masked' => '11****1234',
                'service_type' => 'guincho',
                'pickup_address' => 'Rua das Flores, 123 - Centro',
                'destination_address' => 'Oficina do João - Bairro Industrial',
                'vehicle_info' => 'Honda Civic 2020 - Prata',
                'problem_description' => 'Pane elétrica - carro não liga',
                'urgency_level' => 'medium',
                'max_price' => 150.00,
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'driver_id' => 2,
                'driver_name' => 'Carlos Guincheiro',
                'bid_amount' => 130.00,
                'price' => 130.00,
                'estimated_arrival' => 30
            ],
            [
                'id' => 2,
                'client_id' => 3,
                'client_name' => 'Maria Santos',
                'client_phone_masked' => '11****5678',
                'service_type' => 'bateria',
                'pickup_address' => 'Av. Paulista, 1000 - Bela Vista',
                'destination_address' => null,
                'vehicle_info' => 'Toyota Corolla 2019 - Branco',
                'problem_description' => 'Bateria descarregada',
                'urgency_level' => 'high',
                'max_price' => 80.00,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'driver_id' => 4,
                'driver_name' => 'Roberto Eletricista',
                'bid_amount' => 70.00,
                'price' => 70.00,
                'estimated_arrival' => 15
            ]
        ];
        
        $stats = [
            'total' => count($services),
            'completed' => 1,
            'pending' => 0,
            'active' => 1,
            'cancelled' => 0
        ];
        
        $totalRecords = count($services);
    }

    // Retornar resposta
    echo json_encode([
        'success' => true,
        'data' => $services,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ],
        'stats' => $stats,
        'filters' => [
            'status' => $status,
            'search' => $search
        ],
        'tables_exist' => $tablesExist
    ]);

} catch (Exception $e) {
    error_log("Erro ao listar serviços: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>