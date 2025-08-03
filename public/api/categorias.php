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
require_once __DIR__ . '/../../src/controllers/CategoriaController.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $controller = new App\Controllers\CategoriaController();
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    
    // Obtener segmentos de la URL
    $segments = explode('/', trim($pathInfo, '/'));
    $id = !empty($segments[0]) && is_numeric($segments[0]) ? (int)$segments[0] : null;
    $action = $segments[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($id && $action === 'subcategorias') {
                // GET /api/categorias.php/123/subcategorias - Usar index con parámetro id_padre
                $_GET['id_padre'] = $id;
                $controller->index();
            } elseif ($id && $action === 'ancestros') {
                // GET /api/categorias.php/123/ancestros - Funcionalidad no implementada
                http_response_code(501);
                echo json_encode(['success' => false, 'error' => 'Funcionalidad ancestros no implementada']);
            } elseif ($action === 'raices') {
                // GET /api/categorias.php/raices - Usar index con jerarquía
                $_GET['jerarquia'] = true;
                $controller->index();
            } elseif ($action === 'estadisticas') {
                // GET /api/categorias.php/estadisticas - Funcionalidad no implementada
                http_response_code(501);
                echo json_encode(['success' => false, 'error' => 'Estadísticas no implementadas']);
            } elseif ($id) {
                // GET /api/categorias.php/123 - Mostrar categoría específica
                $controller->show($id);
            } else {
                // GET /api/categorias.php - Listar categorías
                $controller->index();
            }
            break;
            
        case 'POST':
            // POST /api/categorias.php - Crear categoría
            $controller->store();
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/categorias.php/123 - Actualizar categoría
                $controller->update($id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido para actualizar']);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/categorias.php/123 - Eliminar categoría
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
