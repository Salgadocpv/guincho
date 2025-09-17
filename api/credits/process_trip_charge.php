<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../classes/CreditSystem.php';

function processTrip($trip_id, $driver_id) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $creditSystem = new CreditSystem($pdo);
        
        // Obter configurações
        $settings = $creditSystem->getCreditSettings();
        $creditPerTrip = floatval($settings['credit_per_trip']);
        
        // Verificar se o guincheiro tem saldo suficiente
        if (!$creditSystem->canAcceptTrip($driver_id)) {
            throw new Exception('Saldo de créditos insuficiente para realizar a viagem');
        }
        
        // Verificar se a viagem já foi cobrada
        $checkQuery = "SELECT COUNT(*) as charged FROM credit_transactions 
                      WHERE driver_id = :driver_id AND trip_id = :trip_id AND transaction_type = 'spend'";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->bindParam(':driver_id', $driver_id);
        $stmt->bindParam(':trip_id', $trip_id);
        $stmt->execute();
        $alreadyCharged = $stmt->fetch(PDO::FETCH_ASSOC)['charged'] > 0;
        
        if ($alreadyCharged) {
            return [
                'success' => true,
                'message' => 'Viagem já foi cobrada anteriormente',
                'already_charged' => true
            ];
        }
        
        // Cobrar créditos
        $result = $creditSystem->spendCredits(
            $driver_id, 
            $creditPerTrip, 
            $trip_id, 
            'Cobrança por viagem realizada'
        );
        
        return [
            'success' => true,
            'message' => 'Créditos cobrados com sucesso',
            'amount_charged' => $creditPerTrip,
            'new_balance' => $result['new_balance']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Se o arquivo for chamado diretamente via HTTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Dados da requisição inválidos');
        }
        
        $trip_id = intval($input['trip_id'] ?? 0);
        $driver_id = intval($input['driver_id'] ?? 0);
        
        if ($trip_id <= 0 || $driver_id <= 0) {
            throw new Exception('IDs de viagem e guincheiro são obrigatórios');
        }
        
        $result = processTrip($trip_id, $driver_id);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>