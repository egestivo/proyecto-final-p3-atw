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
ini_set('display_errors', '0'); // No mostrar errores en APIs
date_default_timezone_set('America/Guayaquil');

// Autoload
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/controllers/ClienteController.php';

// Iniciar sesión si es necesario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $controller = new App\Controllers\ClienteController();
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    
    // Obtener ID de la URL si existe
    $segments = explode('/', trim($pathInfo, '/'));
    $id = !empty($segments[0]) && is_numeric($segments[0]) ? (int)$segments[0] : null;
    $action = $segments[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($id && $action === 'documento') {
                // GET /api/clientes.php/buscar/documento
                $documento = $_GET['documento'] ?? '';
                if ($documento) {
                    $controller->buscarPorDocumento($documento);
                } else {
                    throw new InvalidArgumentException('Documento requerido');
                }
            } elseif ($id) {
                // GET /api/clientes.php/123 - Mostrar cliente específico
                $controller->show($id);
            } else {
                // GET /api/clientes.php - Listar clientes
                $controller->index();
            }
            break;
            
        case 'POST':
            // POST /api/clientes.php - Crear cliente
            $controller->store();
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/clientes.php/123 - Actualizar cliente
                $controller->update($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para actualizar']);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/clientes.php/123 - Eliminar cliente
                $controller->delete($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para eliminar']);
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
    
    // En modo debug, agregar más información
    if (defined('DEBUG') && DEBUG) {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
