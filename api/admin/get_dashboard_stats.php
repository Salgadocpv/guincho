<?php
/**
 * API para obter estatísticas do dashboard administrativo
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

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

    // Contar clientes ativos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'client' AND status = 'active'");
    $stmt->execute();
    $clientsCount = $stmt->fetch()['count'];

    // Contar guincheiros ativos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'driver' AND status = 'active'");
    $stmt->execute();
    $driversCount = $stmt->fetch()['count'];

    // Contar parceiros ativos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'partner' AND status = 'active'");
    $stmt->execute();
    $partnersCount = $stmt->fetch()['count'];

    // Contar serviços/solicitações realizadas
    $servicesCount = 0;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM trip_requests WHERE status IN ('completed', 'finished')");
        $stmt->execute();
        $servicesCount = $stmt->fetch()['count'];
    } catch (Exception $e) {
        // Tabela trip_requests pode não existir ainda
        $servicesCount = 0;
    }

    // Contar usuários pendentes de aprovação
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'pending_approval'");
    $stmt->execute();
    $pendingApprovals = $stmt->fetch()['count'];

    // Calcular crescimento mensal (comparar com mês anterior)
    $currentMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));

    // Crescimento de clientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'client' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $clientsThisMonth = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'client' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$lastMonth]);
    $clientsLastMonth = $stmt->fetch()['count'];

    $clientsGrowth = $clientsLastMonth > 0 ? round((($clientsThisMonth - $clientsLastMonth) / $clientsLastMonth) * 100) : ($clientsThisMonth > 0 ? 100 : 0);

    // Crescimento de guincheiros
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'driver' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $driversThisMonth = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'driver' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$lastMonth]);
    $driversLastMonth = $stmt->fetch()['count'];

    $driversGrowth = $driversLastMonth > 0 ? round((($driversThisMonth - $driversLastMonth) / $driversLastMonth) * 100) : ($driversThisMonth > 0 ? 100 : 0);

    // Crescimento de parceiros
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'partner' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $partnersThisMonth = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'partner' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$lastMonth]);
    $partnersLastMonth = $stmt->fetch()['count'];

    $partnersGrowth = $partnersLastMonth > 0 ? round((($partnersThisMonth - $partnersLastMonth) / $partnersLastMonth) * 100) : ($partnersThisMonth > 0 ? 100 : 0);

    // Obter atividade recente
    $recentActivity = [];
    try {
        // Últimas 5 atividades dos logs de auditoria
        $stmt = $conn->prepare("
            SELECT al.action, al.created_at, u.full_name, al.table_name 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            $timeAgo = $this->timeAgo($row['created_at']);
            $activity = $this->formatActivity($row['action'], $row['full_name'], $row['table_name'], $timeAgo);
            if ($activity) {
                $recentActivity[] = $activity;
            }
        }
        
        // Se não há logs, criar atividades genéricas baseadas em registros recentes
        if (empty($recentActivity)) {
            $stmt = $conn->prepare("
                SELECT 'user_registered' as action, created_at, full_name, user_type 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 3
            ");
            $stmt->execute();
            
            while ($row = $stmt->fetch()) {
                $timeAgo = $this->timeAgo($row['created_at']);
                $recentActivity[] = [
                    'icon' => $this->getUserTypeIcon($row['user_type']),
                    'text' => 'Novo ' . $this->getUserTypeName($row['user_type']) . ' cadastrado: ' . $row['full_name'],
                    'time' => $timeAgo,
                    'color' => $this->getUserTypeColor($row['user_type'])
                ];
            }
        }
    } catch (Exception $e) {
        // Se tabelas não existem, usar atividades genéricas
        $recentActivity = [
            [
                'icon' => 'fas fa-cog',
                'text' => 'Sistema inicializado com sucesso',
                'time' => 'Há alguns minutos',
                'color' => '#28a745'
            ]
        ];
    }

    // Retornar dados
    echo json_encode([
        'success' => true,
        'stats' => [
            'clients' => [
                'count' => (int)$clientsCount,
                'growth' => $clientsGrowth > 0 ? "+{$clientsGrowth}%" : "{$clientsGrowth}%"
            ],
            'drivers' => [
                'count' => (int)$driversCount,
                'growth' => $driversGrowth > 0 ? "+{$driversGrowth}%" : "{$driversGrowth}%"
            ],
            'partners' => [
                'count' => (int)$partnersCount,
                'growth' => $partnersGrowth > 0 ? "+{$partnersGrowth}%" : "{$partnersGrowth}%"
            ],
            'services' => [
                'count' => (int)$servicesCount,
                'growth' => "+0%"
            ],
            'pending_approvals' => (int)$pendingApprovals
        ],
        'recent_activity' => $recentActivity
    ]);

} catch (Exception $e) {
    error_log("Erro ao obter estatísticas do dashboard: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

// Função para calcular tempo decorrido
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Há poucos segundos';
    if ($time < 3600) return 'Há ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'Há ' . floor($time/3600) . ' horas';
    if ($time < 604800) return 'Há ' . floor($time/86400) . ' dias';
    
    return date('d/m/Y H:i', strtotime($datetime));
}

// Função para formatar atividade
function formatActivity($action, $userName, $tableName, $timeAgo) {
    $activities = [
        'user_registered' => [
            'icon' => 'fas fa-user-plus',
            'text' => 'Novo usuário cadastrado: ' . $userName,
            'color' => '#28a745'
        ],
        'driver_registered' => [
            'icon' => 'fas fa-truck',
            'text' => 'Novo guincheiro cadastrado: ' . $userName,
            'color' => '#28a745'
        ],
        'partner_registered' => [
            'icon' => 'fas fa-store',
            'text' => 'Novo parceiro cadastrado: ' . $userName,
            'color' => '#ffc107'
        ],
        'user_login' => [
            'icon' => 'fas fa-sign-in-alt',
            'text' => 'Login realizado: ' . $userName,
            'color' => '#17a2b8'
        ]
    ];
    
    if (isset($activities[$action])) {
        return array_merge($activities[$action], ['time' => $timeAgo]);
    }
    
    return null;
}

// Função para obter ícone do tipo de usuário
function getUserTypeIcon($userType) {
    $icons = [
        'client' => 'fas fa-user',
        'driver' => 'fas fa-truck',
        'partner' => 'fas fa-store',
        'admin' => 'fas fa-shield-alt'
    ];
    
    return $icons[$userType] ?? 'fas fa-user';
}

// Função para obter nome do tipo de usuário
function getUserTypeName($userType) {
    $names = [
        'client' => 'cliente',
        'driver' => 'guincheiro',
        'partner' => 'parceiro',
        'admin' => 'administrador'
    ];
    
    return $names[$userType] ?? 'usuário';
}

// Função para obter cor do tipo de usuário
function getUserTypeColor($userType) {
    $colors = [
        'client' => '#007bff',
        'driver' => '#28a745',
        'partner' => '#ffc107',
        'admin' => '#dc3545'
    ];
    
    return $colors[$userType] ?? '#6c757d';
}
?>