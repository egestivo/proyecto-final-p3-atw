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
require_once __DIR__ . '/../../src/controllers/FacturaController.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $controller = new App\Controllers\FacturaController();
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    
    // Obtener segmentos de la URL
    $segments = explode('/', trim($pathInfo, '/'));
    $id = !empty($segments[0]) && is_numeric($segments[0]) ? (int)$segments[0] : null;
    $action = $segments[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($id && $action === 'xml') {
                // GET /api/facturas.php/123/xml - Funcionalidad no implementada
                http_response_code(501);
                echo json_encode(['success' => false, 'error' => 'Funcionalidad XML no implementada']);
            } elseif ($id && $action === 'pdf') {
                // GET /api/facturas.php/123/pdf - Funcionalidad no implementada
                http_response_code(501);
                echo json_encode(['success' => false, 'error' => 'Funcionalidad PDF no implementada']);
            } elseif ($action === 'estadisticas') {
                // GET /api/facturas.php/estadisticas - Funcionalidad no implementada
                http_response_code(501);
                echo json_encode(['success' => false, 'error' => 'Estadísticas no implementadas']);
            } elseif ($id) {
                // GET /api/facturas.php/123 - Mostrar factura específica
                $controller->show($id);
            } else {
                // GET /api/facturas.php - Listar facturas
                $controller->index();
            }
            break;
            
        case 'POST':
            if ($id && $action === 'autorizar') {
                // POST /api/facturas.php/123/autorizar - Autorizar factura
                $controller->autorizar($id);
            } else {
                // POST /api/facturas.php - Generar factura desde venta
                $controller->store();
            }
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/facturas.php/123 - Actualizar factura (solo campos limitados)
                $controller->update($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para actualizar']);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/facturas.php/123 - Anular factura
                $controller->delete($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para anular']);
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
