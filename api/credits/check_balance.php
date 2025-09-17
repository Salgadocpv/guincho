<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database_local.php';
require_once '../classes/CreditSystem.php';
require_once '../middleware/auth.php';

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
    
    if (!driver) {
        throw new Exception('Guincheiro não encontrado');
    }
    
    $driver_id = $driver['id'];
    
    // Verificar saldo e configurações
    $creditSystem = new CreditSystem($pdo);
    $credits = $creditSystem->getDriverCredits($driver_id);
    $settings = $creditSystem->getCreditSettings();
    $canAcceptTrip = $creditSystem->canAcceptTrip($driver_id);
    
    $currentBalance = floatval($credits['current_balance']);
    $minimumBalance = floatval($settings['minimum_credit_balance']);
    $creditPerTrip = floatval($settings['credit_per_trip']);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'current_balance' => $currentBalance,
            'minimum_balance' => $minimumBalance,
            'credit_per_trip' => $creditPerTrip,
            'can_accept_trip' => $canAcceptTrip,
            'balance_sufficient' => $currentBalance >= $creditPerTrip,
            'trips_remaining' => $currentBalance > 0 ? floor($currentBalance / $creditPerTrip) : 0
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