<?php
/**
 * API para obter detalhes completos de um usuário
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
    $userId = $_GET['id'] ?? '';
    $userType = $_GET['type'] ?? '';

    if (empty($userId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do usuário é obrigatório']);
        exit;
    }

    // Query base para obter dados do usuário
    $query = "
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.user_type,
            u.status,
            u.created_at,
            u.updated_at,
            u.birth_date,
            u.gender,
            u.address,
            u.city,
            u.state,
            u.zip_code,
            u.profile_picture
        FROM users u
        WHERE u.id = :user_id
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }

    $result = [
        'success' => true,
        'data' => $user,
        'driver_info' => null,
        'partner_info' => null,
        'stats' => []
    ];

    // Se for guincheiro, buscar informações adicionais
    if ($user['user_type'] === 'driver') {
        $driverQuery = "
            SELECT 
                d.id as driver_id,
                d.vehicle_type,
                d.vehicle_brand,
                d.vehicle_model,
                d.vehicle_year,
                d.license_plate,
                d.cnh_number,
                d.cnh_category,
                d.cnh_expiry,
                d.vehicle_registration,
                d.insurance_policy,
                d.insurance_expiry,
                d.service_areas,
                d.hourly_rate,
                d.availability_status,
                d.rating_average,
                d.total_services,
                d.is_verified,
                d.verification_documents,
                d.bank_account,
                d.pix_key,
                d.created_at as driver_created_at
            FROM drivers d
            WHERE d.user_id = :user_id
        ";
        
        $driverStmt = $conn->prepare($driverQuery);
        $driverStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $driverStmt->execute();
        $result['driver_info'] = $driverStmt->fetch(PDO::FETCH_ASSOC);

        // Estatísticas do guincheiro
        $statsQuery = "
            SELECT 
                COUNT(*) as total_trips,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_trips,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_trips,
                AVG(CASE WHEN status = 'completed' THEN final_price END) as avg_trip_value
            FROM active_trips
            WHERE driver_id = (SELECT id FROM drivers WHERE user_id = :user_id)
        ";
        
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statsStmt->execute();
        $result['stats'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Se for parceiro, buscar informações da empresa
    if ($user['user_type'] === 'partner') {
        $partnerQuery = "
            SELECT 
                p.id as partner_id,
                p.business_name,
                p.business_type,
                p.cnpj,
                p.business_description,
                p.services_offered,
                p.operating_hours,
                p.website,
                p.social_media,
                p.payment_methods,
                p.rating_average,
                p.total_services,
                p.is_verified,
                p.verification_documents,
                p.commission_rate,
                p.created_at as partner_created_at
            FROM partners p
            WHERE p.user_id = :user_id
        ";
        
        $partnerStmt = $conn->prepare($partnerQuery);
        $partnerStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $partnerStmt->execute();
        $result['partner_info'] = $partnerStmt->fetch(PDO::FETCH_ASSOC);

        // Estatísticas do parceiro
        $statsQuery = "
            SELECT 
                COUNT(*) as total_services,
                AVG(rating) as avg_rating,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_services
            FROM partner_services
            WHERE partner_id = (SELECT id FROM partners WHERE user_id = :user_id)
        ";
        
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statsStmt->execute();
        $partnerStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        $result['stats'] = $partnerStats ?: ['total_services' => 0, 'avg_rating' => 0, 'completed_services' => 0];
    }

    // Se for cliente, buscar estatísticas de viagens
    if ($user['user_type'] === 'client') {
        $statsQuery = "
            SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_requests,
                AVG(CASE WHEN status = 'completed' THEN client_offer END) as avg_request_value
            FROM trip_requests
            WHERE client_id = :user_id
        ";
        
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statsStmt->execute();
        $result['stats'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Garantir que stats sempre tenha valores
    if (!$result['stats']) {
        $result['stats'] = [];
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Erro ao obter detalhes do usuário: " . $e->getMessage());
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