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
require_once __DIR__ . '/../../src/controllers/ProductoController.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $controller = new App\Controllers\ProductoController();
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    
    // Obtener segmentos de la URL
    $segments = explode('/', trim($pathInfo, '/'));
    $id = !empty($segments[0]) && is_numeric($segments[0]) ? (int)$segments[0] : null;
    $action = $segments[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($id && $action === 'stock') {
                // GET /api/productos.php/123/stock - Ver stock del producto
                $controller->show($id);
            } elseif ($action === 'categoria' && !empty($segments[1]) && is_numeric($segments[1])) {
                // GET /api/productos.php/categoria/123 - Productos por categoría
                $controller->porCategoria((int)$segments[1]);
            } elseif ($action === 'stock-bajo') {
                // GET /api/productos.php/stock-bajo - Productos con stock bajo
                $controller->stockBajo();
            } elseif ($action === 'estadisticas') {
                // GET /api/productos.php/estadisticas - Estadísticas de productos
                $controller->estadisticas();
            } elseif ($id) {
                // GET /api/productos.php/123 - Mostrar producto específico
                $controller->show($id);
            } else {
                // GET /api/productos.php - Listar productos
                $controller->index();
            }
            break;
            
        case 'POST':
            // POST /api/productos.php - Crear producto
            $controller->store();
            break;
            
        case 'PUT':
            if ($id && $action === 'stock') {
                // PUT /api/productos.php/123/stock - Actualizar stock
                $controller->actualizarStock($id);
            } elseif ($id) {
                // PUT /api/productos.php/123 - Actualizar producto
                $controller->update($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para actualizar']);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/productos.php/123 - Eliminar producto
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
