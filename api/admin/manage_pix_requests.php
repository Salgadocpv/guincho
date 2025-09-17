<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database_local.php';
require_once '../classes/CreditSystem.php';
require_once '../middleware/AdminAuth.php';

try {
    // Verificar autenticação de admin
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        throw new Exception('Token de autorização necessário');
    }
    
    $token = substr($authHeader, 7);
    $adminAuth = new AdminAuth();
    $admin = $adminAuth->validateToken($token);
    
    if (!$admin) {
        throw new Exception('Acesso negado - Admin apenas');
    }
    
    $database = new DatabaseLocal();
    $pdo = $database->getConnection();
    $creditSystem = new CreditSystem($pdo);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Listar solicitações PIX
        $status = $_GET['status'] ?? 'all';
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        
        $query = "SELECT pr.*, u.full_name as driver_name, u.phone, u.email 
                 FROM pix_credit_requests pr
                 JOIN drivers d ON pr.driver_id = d.id
                 JOIN users u ON d.user_id = u.id";
        
        $params = [];
        
        if ($status !== 'all') {
            $query .= " WHERE pr.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY pr.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM pix_credit_requests pr";
        if ($status !== 'all') {
            $countQuery .= " WHERE pr.status = :status";
        }
        
        $stmt = $pdo->prepare($countQuery);
        if ($status !== 'all') {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'requests' => $requests,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
        
    } elseif ($method === 'POST') {
        // Processar solicitação PIX
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Dados da requisição inválidos');
        }
        
        $action = $input['action'] ?? '';
        $request_id = intval($input['request_id'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        
        if ($request_id <= 0) {
            throw new Exception('ID da solicitação é obrigatório');
        }
        
        // Buscar solicitação
        $query = "SELECT * FROM pix_credit_requests WHERE id = :request_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':request_id', $request_id);
        $stmt->execute();
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Solicitação não encontrada');
        }
        
        if ($request['status'] !== 'pending') {
            throw new Exception('Solicitação já foi processada');
        }
        
        $pdo->beginTransaction();
        
        if ($action === 'approve') {
            // Aprovar e adicionar créditos
            $credits_to_add = floatval($request['credits_to_receive']);
            
            // Adicionar créditos
            $pix_data = [
                'transaction_id' => 'PIX-' . $request_id,
                'pix_key' => $request['pix_key'],
                'pix_amount' => $request['amount_requested']
            ];
            
            $creditResult = $creditSystem->addCredits(
                $request['driver_id'],
                $credits_to_add,
                'add',
                'Recarga via PIX aprovada',
                $pix_data
            );
            
            // Atualizar status da solicitação
            $updateQuery = "UPDATE pix_credit_requests 
                           SET status = 'credits_added',
                               confirmed_by = :admin_id,
                               confirmed_at = NOW(),
                               confirmation_notes = :notes
                           WHERE id = :request_id";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->bindParam(':admin_id', $admin['id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':request_id', $request_id);
            $stmt->execute();
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Solicitação aprovada e créditos adicionados com sucesso',
                'data' => [
                    'credits_added' => $credits_to_add,
                    'new_balance' => $creditResult['new_balance']
                ]
            ]);
            
        } elseif ($action === 'reject') {
            // Rejeitar solicitação
            $updateQuery = "UPDATE pix_credit_requests 
                           SET status = 'cancelled',
                               confirmed_by = :admin_id,
                               confirmed_at = NOW(),
                               confirmation_notes = :notes
                           WHERE id = :request_id";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->bindParam(':admin_id', $admin['id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':request_id', $request_id);
            $stmt->execute();
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Solicitação rejeitada com sucesso'
            ]);
            
        } else {
            throw new Exception('Ação inválida. Use "approve" ou "reject"');
        }
        
    } else {
        throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>