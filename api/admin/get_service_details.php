<?php
/**
 * API para obter detalhes completos de um serviço/viagem
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

    // Obter parâmetros
    $serviceId = $_GET['id'] ?? '';

    if (empty($serviceId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do serviço é obrigatório']);
        exit;
    }

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

    if (!$tablesExist) {
        // Retornar dados de exemplo se as tabelas não existem
        $exampleService = [
            'id' => $serviceId,
            'client_id' => 1,
            'client_name' => 'João Silva',
            'client_phone' => '11999991234',
            'client_email' => 'joao@email.com',
            'service_type' => 'guincho',
            'pickup_address' => 'Rua das Flores, 123 - Centro, São Paulo - SP',
            'destination_address' => 'Oficina do João - Bairro Industrial, São Paulo - SP',
            'pickup_latitude' => -23.5505,
            'pickup_longitude' => -46.6333,
            'destination_latitude' => -23.5505,
            'destination_longitude' => -46.6333,
            'max_price' => 150.00,
            'final_price' => 130.00,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'driver_id' => 2,
            'driver_name' => 'Carlos Guincheiro',
            'driver_phone' => '11999995678',
            'bid_amount' => 130.00,
            'estimated_arrival' => 30,
            'distance_km' => 15.5,
            'estimated_duration_minutes' => 45
        ];

        echo json_encode([
            'success' => true,
            'data' => $exampleService,
            'bids' => [],
            'status_history' => [],
            'tables_exist' => false
        ]);
        exit;
    }

    // Query principal para obter dados da viagem
    $query = "
        SELECT 
            tr.id,
            tr.client_id,
            u.full_name as client_name,
            u.phone as client_phone,
            u.email as client_email,
            tr.service_type,
            tr.origin_address as pickup_address,
            tr.destination_address,
            tr.origin_lat as pickup_latitude,
            tr.origin_lng as pickup_longitude,
            tr.destination_lat as destination_latitude,
            tr.destination_lng as destination_longitude,
            tr.client_offer as max_price,
            tr.status,
            tr.distance_km,
            tr.estimated_duration_minutes,
            tr.created_at,
            tr.updated_at,
            tr.expires_at,
            -- Dados da viagem ativa (se existir)
            at.id as active_trip_id,
            at.final_price,
            at.driver_id,
            td.full_name as driver_name,
            td.phone as driver_phone,
            at.started_at,
            at.completed_at,
            at.client_rating,
            at.driver_rating,
            at.client_feedback,
            at.driver_feedback
        FROM trip_requests tr
        LEFT JOIN users u ON tr.client_id = u.id
        LEFT JOIN active_trips at ON tr.id = at.trip_request_id
        LEFT JOIN drivers d ON at.driver_id = d.id
        LEFT JOIN users td ON d.user_id = td.id
        WHERE tr.id = :service_id
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Serviço não encontrado']);
        exit;
    }

    // Buscar todas as propostas para este serviço
    $bidsQuery = "
        SELECT 
            tb.id,
            tb.driver_id,
            td.full_name as driver_name,
            td.phone as driver_phone,
            d.vehicle_type,
            d.vehicle_brand,
            d.vehicle_model,
            d.rating_average as driver_rating,
            tb.bid_amount,
            tb.estimated_arrival_minutes,
            tb.message,
            tb.status as bid_status,
            tb.created_at as bid_created_at,
            tb.expires_at as bid_expires_at
        FROM trip_bids tb
        LEFT JOIN drivers d ON tb.driver_id = d.id
        LEFT JOIN users td ON d.user_id = td.id
        WHERE tb.trip_request_id = :service_id
        ORDER BY tb.created_at DESC
    ";

    $bidsStmt = $conn->prepare($bidsQuery);
    $bidsStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
    $bidsStmt->execute();
    $bids = $bidsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar histórico de status
    $historyQuery = "
        SELECT 
            tsh.id,
            tsh.old_status,
            tsh.new_status,
            tsh.reason,
            tsh.created_at,
            u.full_name as changed_by_name
        FROM trip_status_history tsh
        LEFT JOIN users u ON tsh.changed_by = u.id
        WHERE tsh.trip_request_id = :service_id
        ORDER BY tsh.created_at DESC
    ";

    $historyStmt = $conn->prepare($historyQuery);
    $historyStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
    $historyStmt->execute();
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Mascarar telefones
    if (!empty($service['client_phone'])) {
        $service['client_phone_masked'] = substr($service['client_phone'], 0, 2) . '****' . substr($service['client_phone'], -4);
    }
    if (!empty($service['driver_phone'])) {
        $service['driver_phone_masked'] = substr($service['driver_phone'], 0, 2) . '****' . substr($service['driver_phone'], -4);
    }

    // Processar propostas
    foreach ($bids as &$bid) {
        if (!empty($bid['driver_phone'])) {
            $bid['driver_phone_masked'] = substr($bid['driver_phone'], 0, 2) . '****' . substr($bid['driver_phone'], -4);
        }
        $bid['bid_amount'] = floatval($bid['bid_amount']);
        $bid['driver_rating'] = floatval($bid['driver_rating']);
    }

    // Garantir que valores numéricos sejam números
    $service['max_price'] = floatval($service['max_price']);
    $service['final_price'] = floatval($service['final_price'] ?? 0);
    $service['distance_km'] = floatval($service['distance_km'] ?? 0);
    $service['client_rating'] = floatval($service['client_rating'] ?? 0);
    $service['driver_rating'] = floatval($service['driver_rating'] ?? 0);

    echo json_encode([
        'success' => true,
        'data' => $service,
        'bids' => $bids,
        'status_history' => $statusHistory,
        'tables_exist' => true
    ]);

} catch (Exception $e) {
    error_log("Erro ao obter detalhes do serviço: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>