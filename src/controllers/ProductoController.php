<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductoRepository;
use App\Repositories\CategoriaRepository;
use App\Entities\ProductoFisico;
use App\Entities\ProductoDigital;

class ProductoController
{
    private ProductoRepository $productoRepository;
    private CategoriaRepository $categoriaRepository;

    public function __construct()
    {
        $this->productoRepository = new ProductoRepository();
        $this->categoriaRepository = new CategoriaRepository();
    }

    /**
     * GET /api/productos
     * Listar productos con filtros
     */
    public function index(): void
    {
        try {
            $limite = (int)($_GET['limite'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $categoria = $_GET['categoria'] ?? null;
            $estado = $_GET['estado'] ?? null;
            $tipo = $_GET['tipo'] ?? null;
            $busqueda = $_GET['busqueda'] ?? '';

            if ($busqueda) {
                $productos = $this->productoRepository->buscarPorNombre($busqueda);
            } else {
                $productos = $this->productoRepository->listarTodos($limite, $offset);
            }

            $total = $this->productoRepository->contarTotal();

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearProducto'], $productos),
                'pagination' => [
                    'total' => $total,
                    'limite' => $limite,
                    'offset' => $offset,
                    'has_more' => ($offset + $limite) < $total
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener productos: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/productos/{id}
     * Obtener un producto específico
     */
    public function show(int $id): void
    {
        try {
            $producto = $this->productoRepository->buscarPorId($id);

            if (!$producto) {
                $this->errorResponse('Producto no encontrado', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => $this->formatearProducto($producto)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener producto: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/productos
     * Crear un nuevo producto
     */
    public function store(): void
    {
        try {
            $data = $this->getJsonInput();
            
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Validar datos requeridos
            $this->validateRequired($data, ['nombre', 'precio', 'tipo_producto', 'id_categoria']);

            // Validar que la categoría exista
            if (!$this->categoriaRepository->buscarPorId((int)$data['id_categoria'])) {
                $this->errorResponse('La categoría especificada no existe', 400);
                return;
            }

            // Crear producto según el tipo
            if ($data['tipo_producto'] === 'fisico') {
                $producto = $this->crearProductoFisico($data);
            } elseif ($data['tipo_producto'] === 'digital') {
                $producto = $this->crearProductoDigital($data);
            } else {
                $this->errorResponse('Tipo de producto inválido', 400);
                return;
            }

            // Validar la entidad
            if (!$producto->validar()) {
                $this->errorResponse('Datos del producto inválidos', 400);
                return;
            }

            // Guardar en base de datos
            if ($this->productoRepository->crear($producto)) {
                $response = [
                    'success' => true,
                    'message' => 'Producto creado exitosamente',
                    'data' => [
                        'id' => $producto->getId(),
                        'nombre' => $producto->getNombre(),
                        'precio' => $producto->getPrecioUnitario(),
                        'tipo_producto' => $producto->getTipoProducto()
                    ]
                ];
                $this->jsonResponse($response, 201);
            } else {
                $this->errorResponse('Error al crear producto', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al crear producto: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/productos/{id}
     * Actualizar un producto existente
     */
    public function update(int $id): void
    {
        try {
            $producto = $this->productoRepository->buscarPorId($id);
            if (!$producto) {
                $this->errorResponse('Producto no encontrado', 404);
                return;
            }

            $data = $this->getJsonInput();
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Validar categoría si se proporciona
            if (isset($data['id_categoria'])) {
                if (!$this->categoriaRepository->buscarPorId((int)$data['id_categoria'])) {
                    $this->errorResponse('La categoría especificada no existe', 400);
                    return;
                }
            }

            // Actualizar datos base
            if (isset($data['nombre'])) $producto->setNombre($data['nombre']);
            if (isset($data['descripcion'])) $producto->setDescripcion($data['descripcion']);
            if (isset($data['precio'])) $producto->setPrecioUnitario((float)$data['precio']);
            if (isset($data['id_categoria'])) $producto->setIdCategoria((int)$data['id_categoria']);

            // Actualizar datos específicos según el tipo
            if ($producto instanceof ProductoFisico) {
                if (isset($data['peso'])) $producto->setPeso((float)$data['peso']);
                if (isset($data['alto'])) $producto->setAlto((float)$data['alto']);
                if (isset($data['ancho'])) $producto->setAncho((float)$data['ancho']);
                if (isset($data['profundidad'])) $producto->setProfundidad((float)$data['profundidad']);
            } elseif ($producto instanceof ProductoDigital) {
                if (isset($data['url_descarga'])) $producto->setUrlDescarga($data['url_descarga']);
                if (isset($data['licencia'])) $producto->setLicencia($data['licencia']);
                if (isset($data['max_descargas'])) $producto->setMaxDescargas((int)$data['max_descargas']);
            }

            // Validar la entidad
            if (!$producto->validar()) {
                $this->errorResponse('Datos del producto inválidos', 400);
                return;
            }

            // Actualizar en base de datos
            if ($this->productoRepository->actualizar($producto)) {
                $response = [
                    'success' => true,
                    'message' => 'Producto actualizado exitosamente',
                    'data' => [
                        'id' => $producto->getId(),
                        'nombre' => $producto->getNombre(),
                        'precio' => $producto->getPrecioUnitario()
                    ]
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar producto', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al actualizar producto: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/productos/{id}
     * Eliminar un producto (soft delete)
     */
    public function delete(int $id): void
    {
        try {
            $producto = $this->productoRepository->buscarPorId($id);
            if (!$producto) {
                $this->errorResponse('Producto no encontrado', 404);
                return;
            }

            if ($this->productoRepository->eliminar($id)) {
                $response = [
                    'success' => true,
                    'message' => 'Producto eliminado exitosamente'
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al eliminar producto', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al eliminar producto: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/productos/categoria/{id}
     * Listar productos por categoría
     */
    public function porCategoria(int $idCategoria): void
    {
        try {
            $productos = $this->productoRepository->listarPorCategoria($idCategoria);

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearProducto'], $productos)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener productos por categoría: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/productos/stock-bajo
     * Listar productos con stock bajo
     */
    public function stockBajo(): void
    {
        try {
            $productos = $this->productoRepository->listarConStockBajo();

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearProducto'], $productos)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener productos con stock bajo: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/productos/{id}/stock
     * Actualizar stock de un producto físico
     */
    public function actualizarStock(int $id): void
    {
        try {
            $data = $this->getJsonInput();
            
            if (!isset($data['cantidad']) || !is_numeric($data['cantidad'])) {
                $this->errorResponse('La cantidad es requerida y debe ser numérica', 400);
                return;
            }

            $cantidad = (int)$data['cantidad'];
            $operacion = $data['operacion'] ?? 'suma'; // suma o resta

            if ($operacion === 'suma') {
                // Usar actualizarStock para incrementar
                $producto = $this->productoRepository->buscarPorId($id);
                if ($producto) {
                    $nuevoStock = $producto->getStock() + $cantidad;
                    $success = $this->productoRepository->actualizarStock($id, $nuevoStock);
                } else {
                    $success = false;
                }
            } elseif ($operacion === 'resta') {
                $success = $this->productoRepository->descontarStock($id, $cantidad);
            } else {
                $this->errorResponse('Operación inválida. Use "suma" o "resta"', 400);
                return;
            }

            if ($success) {
                $producto = $this->productoRepository->buscarPorId($id);
                $response = [
                    'success' => true,
                    'message' => 'Stock actualizado exitosamente',
                    'data' => [
                        'id' => $id,
                        'stock_actual' => $producto instanceof ProductoFisico ? $producto->getStock() : 0
                    ]
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar stock', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al actualizar stock: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/productos/estadisticas
     * Obtener estadísticas de productos
     */
    public function estadisticas(): void
    {
        try {
            // Estadísticas básicas usando métodos disponibles
            $total = $this->productoRepository->contarTotal();
            
            $stats = [
                'total_productos' => $total,
                'productos_stock_bajo' => count($this->productoRepository->listarConStockBajo()),
                'productos_digitales_por_expirar' => count($this->productoRepository->listarDigitalesProximosAExpirar())
            ];

            $response = [
                'success' => true,
                'data' => $stats
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage());
        }
    }

    // ===== MÉTODOS PRIVADOS =====

    private function crearProductoFisico(array $data): ProductoFisico
    {
        $this->validateRequired($data, ['peso', 'alto', 'ancho', 'profundidad']);

        return new ProductoFisico(
            $data['nombre'],
            (float)$data['precio'],
            (int)$data['id_categoria'],
            (float)$data['peso'],
            (float)$data['alto'],
            (float)$data['ancho'],
            (float)$data['profundidad'],
            $data['descripcion'] ?? null,
            (int)($data['stock'] ?? 0)
        );
    }

    private function crearProductoDigital(array $data): ProductoDigital
    {
        return new ProductoDigital(
            $data['nombre'],
            (float)$data['precio'],
            (int)$data['id_categoria'],
            $data['url_descarga'] ?? null,
            $data['licencia'] ?? 'Uso único',
            $data['clave_activacion'] ?? null,
            isset($data['fecha_expiracion']) ? new \DateTime($data['fecha_expiracion']) : null,
            (int)($data['max_descargas'] ?? -1),
            $data['descripcion'] ?? null
        );
    }

    private function formatearProducto($producto): array
    {
        $data = [
            'id' => $producto->getId(),
            'nombre' => $producto->getNombre(),
            'descripcion' => $producto->getDescripcion(),
            'precio_unitario' => $producto->getPrecioUnitario(),
            'stock' => $producto->getStock(),
            'id_categoria' => $producto->getIdCategoria(),
            'tipo_producto' => $producto->getTipoProducto()
        ];

        // Agregar datos específicos según el tipo
        if ($producto instanceof ProductoFisico) {
            $data['peso'] = $producto->getPeso();
            $data['alto'] = $producto->getAlto();
            $data['ancho'] = $producto->getAncho();
            $data['profundidad'] = $producto->getProfundidad();
            $data['volumen'] = $producto->getVolumen();
        } elseif ($producto instanceof ProductoDigital) {
            $data['url_descarga'] = $producto->getUrlDescarga();
            $data['licencia'] = $producto->getLicencia();
            $data['max_descargas'] = $producto->getMaxDescargas();
            $data['descargas_realizadas'] = $producto->getDescargasRealizadas();
            $data['fecha_expiracion'] = $producto->getFechaExpiracion()?->format('Y-m-d H:i:s');
        }

        return $data;
    }

    private function getJsonInput(): ?array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    private function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new \InvalidArgumentException("El campo '$field' es requerido");
            }
        }
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function errorResponse(string $message, int $status = 500): void
    {
        $response = [
            'success' => false,
            'error' => $message
        ];
        $this->jsonResponse($response, $status);
    }
}
