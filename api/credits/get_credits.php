<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database_local.php';
require_once '../classes/CreditSystem.php';
require_once '../middleware/AuthSimple.php';

try {
    // Verificar autenticação
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        throw new Exception('Token de autorização necessário');
    }
    
    $token = substr($authHeader, 7);
    $auth = new Auth();
    $user = $auth->validateToken($token);
    
    if (!$user || $user['user_type'] !== 'driver') {
        throw new Exception('Acesso negado');
    }
    
    // Obter driver_id
    $database = new DatabaseLocal();
    $pdo = $database->getConnection();
    
    $query = "SELECT id FROM drivers WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        throw new Exception('Guincheiro não encontrado');
    }
    
    $driver_id = $driver['id'];
    
    // Obter dados de créditos
    $creditSystem = new CreditSystem($pdo);
    $credits = $creditSystem->getDriverCredits($driver_id);
    $settings = $creditSystem->getCreditSettings();
    $canAcceptTrip = $creditSystem->canAcceptTrip($driver_id);
    
    // Obter histórico recente
    $history = $creditSystem->getTransactionHistory($driver_id, 10);
    
    // Obter solicitações PIX pendentes
    $pixRequests = $creditSystem->getPixRequests($driver_id, 'pending');
    
    echo json_encode([
        'success' => true,
        'data' => [
            'credits' => $credits,
            'settings' => $settings,
            'can_accept_trip' => $canAcceptTrip,
            'recent_history' => $history,
            'pending_pix_requests' => $pixRequests
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>