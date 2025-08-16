<?php
/**
 * API Principal - Guincho App
 * Arquivo de roteamento e configuração da API interna
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/Service.php';
require_once 'models/Request.php';
require_once 'controllers/AuthController.php';
require_once 'controllers/ServiceController.php';
require_once 'controllers/RequestController.php';

// Roteamento simples
$request_method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/guincho/api/', '', $path);
$path_parts = explode('/', trim($path, '/'));

try {
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    // Roteamento baseado no caminho
    switch($path_parts[0]) {
        case 'auth':
            $controller = new AuthController($db);
            handleAuthRoutes($controller, $path_parts, $request_method);
            break;
            
        case 'services':
            $controller = new ServiceController($db);
            handleServiceRoutes($controller, $path_parts, $request_method);
            break;
            
        case 'requests':
            $controller = new RequestController($db);
            handleRequestRoutes($controller, $path_parts, $request_method);
            break;
            
        case 'test':
            // Endpoint de teste da conexão
            $database = new Database();
            $result = $database->testConnection();
            echo json_encode($result);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Endpoint não encontrado',
                'available_endpoints' => [
                    'auth' => ['POST /auth/login', 'POST /auth/register', 'POST /auth/logout'],
                    'services' => ['GET /services', 'GET /services/{id}'],
                    'requests' => ['POST /requests', 'GET /requests', 'PUT /requests/{id}'],
                    'test' => ['GET /test']
                ]
            ]);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}

/**
 * Roteamento de autenticação
 */
function handleAuthRoutes($controller, $path_parts, $method) {
    switch($method) {
        case 'POST':
            if(isset($path_parts[1])) {
                switch($path_parts[1]) {
                    case 'login':
                        $controller->login();
                        break;
                    case 'register':
                        $controller->register();
                        break;
                    case 'logout':
                        $controller->logout();
                        break;
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Rota de autenticação não encontrada']);
                }
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
}

/**
 * Roteamento de serviços
 */
function handleServiceRoutes($controller, $path_parts, $method) {
    switch($method) {
        case 'GET':
            if(isset($path_parts[1])) {
                $controller->getService($path_parts[1]);
            } else {
                $controller->getAllServices();
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
}

/**
 * Roteamento de solicitações
 */
function handleRequestRoutes($controller, $path_parts, $method) {
    switch($method) {
        case 'GET':
            if(isset($path_parts[1])) {
                $controller->getRequest($path_parts[1]);
            } else {
                $controller->getUserRequests();
            }
            break;
        case 'POST':
            $controller->createRequest();
            break;
        case 'PUT':
            if(isset($path_parts[1])) {
                $controller->updateRequest($path_parts[1]);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
}

/**
 * Função helper para validar dados de entrada
 */
function validateInput($data, $required_fields) {
    $missing_fields = [];
    foreach($required_fields as $field) {
        if(!isset($data[$field]) || empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if(!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Campos obrigatórios faltando',
            'missing_fields' => $missing_fields
        ]);
        exit();
    }
    
    return true;
}
?>