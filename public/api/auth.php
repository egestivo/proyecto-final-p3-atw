<?php
declare(strict_types=1);

// Configuración básica para APIs
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuración
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('America/Guayaquil');

// Autoload
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/controllers/AuthController.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $controller = new App\Controllers\AuthController();
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    
    // Obtener acción de la URL
    $segments = explode('/', trim($pathInfo, '/'));
    $action = $segments[0] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'me') {
                // GET /api/auth.php/me - Información del usuario autenticado
                $controller->me();
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'login':
                    // POST /api/auth.php/login - Iniciar sesión
                    $controller->login();
                    break;
                    
                case 'register':
                    // POST /api/auth.php/register - Registrar usuario
                    $controller->register();
                    break;
                    
                case 'logout':
                    // POST /api/auth.php/logout - Cerrar sesión
                    $controller->logout();
                    break;
                    
                case 'refresh-token':
                    // POST /api/auth.php/refresh-token - Renovar token
                    $controller->refreshToken();
                    break;
                    
                default:
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Acción no encontrada']);
                    break;
            }
            break;
            
        case 'PUT':
            if ($action === 'change-password') {
                // PUT /api/auth.php/change-password - Cambiar contraseña
                $controller->changePassword();
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    
    $response = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ];
    
    if (defined('DEBUG') && DEBUG) {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
