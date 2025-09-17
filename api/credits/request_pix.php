<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database_local.php';
require_once '../classes/CreditSystem.php';
require_once '../classes/PixIntegration.php';
require_once '../middleware/auth.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
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
    
    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados da requisição inválidos');
    }
    
    $amount_requested = floatval($input['amount_requested'] ?? 0);
    $pix_key = trim($input['pix_key'] ?? '');
    $pix_key_type = trim($input['pix_key_type'] ?? '');
    
    if ($amount_requested <= 0) {
        throw new Exception('Valor da recarga deve ser maior que zero');
    }
    
    if (empty($pix_key)) {
        throw new Exception('Chave PIX é obrigatória');
    }
    
    if (!in_array($pix_key_type, ['cpf', 'cnpj', 'email', 'phone', 'random'])) {
        throw new Exception('Tipo de chave PIX inválido');
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
    
    // Verificar se há solicitações pendentes
    $checkQuery = "SELECT COUNT(*) as pending_count FROM pix_credit_requests 
                  WHERE driver_id = :driver_id AND status = 'pending' AND expires_at > NOW()";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->bindParam(':driver_id', $driver_id);
    $stmt->execute();
    $pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
    
    if ($pendingCount > 0) {
        throw new Exception('Você já possui uma solicitação de recarga pendente. Aguarde o processamento ou cancele a solicitação atual.');
    }
    
    // Criar solicitação PIX
    $creditSystem = new CreditSystem($pdo);
    $result = $creditSystem->createPixRequest($driver_id, $amount_requested, $pix_key, $pix_key_type);
    
    // Tentar gerar QR Code PIX se integração estiver habilitada
    try {
        $pixIntegration = new PixIntegration($pdo);
        $qrData = $pixIntegration->generatePixQRCode(
            $amount_requested, 
            'Recarga de créditos Iguincho - ID: ' . $result['request_id'],
            [
                'email' => $user['email'] ?? 'usuario@iguincho.com',
                'driver_id' => $driver_id,
                'request_id' => $result['request_id']
            ]
        );
        
        // Adicionar dados do QR Code ao resultado
        $result['qr_code'] = $qrData['qr_code'] ?? null;
        $result['qr_code_base64'] = $qrData['qr_code_base64'] ?? null;
        $result['payment_id'] = $qrData['payment_id'] ?? null;
        $result['pix_provider'] = $qrData['provider'] ?? 'manual';
        
        // Atualizar solicitação com dados do pagamento
        if (isset($qrData['payment_id'])) {
            $updateQuery = "UPDATE pix_credit_requests SET pix_transaction_id = :payment_id WHERE id = :request_id";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->bindParam(':payment_id', $qrData['payment_id']);
            $stmt->bindParam(':request_id', $result['request_id']);
            $stmt->execute();
        }
        
    } catch (Exception $e) {
        // Se falhar na geração do QR Code, continuar com modo manual
        error_log('Erro ao gerar QR Code PIX: ' . $e->getMessage());
        $result['qr_error'] = $e->getMessage();
        $result['pix_provider'] = 'manual';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitação de recarga criada com sucesso',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>