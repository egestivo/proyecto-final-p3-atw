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
require_once __DIR__ . '/../../src/controllers/VentaController.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $controller = new App\Controllers\VentaController();
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    
    // Obtener segmentos de la URL
    $segments = explode('/', trim($pathInfo, '/'));
    $id = !empty($segments[0]) && is_numeric($segments[0]) ? (int)$segments[0] : null;
    $action = $segments[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($action === 'cliente' && !empty($segments[1]) && is_numeric($segments[1])) {
                // GET /api/ventas.php/cliente/123 - Ventas por cliente
                $controller->porCliente((int)$segments[1]);
            } elseif ($action === 'fecha' && !empty($segments[1])) {
                // GET /api/ventas.php/fecha/2024-01-01 - Ventas por fecha
                $controller->porFecha($segments[1]);
            } elseif ($action === 'estadisticas') {
                // GET /api/ventas.php/estadisticas - Estadísticas de ventas
                $controller->estadisticas();
            } elseif ($id) {
                // GET /api/ventas.php/123 - Mostrar venta específica
                $controller->show($id);
            } else {
                // GET /api/ventas.php - Listar ventas
                $controller->index();
            }
            break;
            
        case 'POST':
            if ($id && $action === 'emitir') {
                // POST /api/ventas.php/123/emitir - Emitir venta
                // Este es el flujo clave del proyecto.md
                $controller->emitir($id);
            } elseif ($id && $action === 'anular') {
                // POST /api/ventas.php/123/anular - Anular venta
                $controller->anular($id);
            } else {
                // POST /api/ventas.php - Crear nueva venta
                // Flujo: Frontend invoca POST /api/ventas con JSON {cabecera, detalles}
                $controller->store();
            }
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/ventas.php/123 - Actualizar venta (solo borradores)
                $controller->update($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para actualizar']);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/ventas.php/123 - Eliminar venta (solo borradores)
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
    
    if (defined('DEBUG') && DEBUG) {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
