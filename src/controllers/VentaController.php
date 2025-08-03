<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\VentaRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\ClienteRepository;
use App\Entities\Venta;
use App\Entities\DetalleVenta;

class VentaController
{
    private VentaRepository $ventaRepository;
    private ProductoRepository $productoRepository;
    private ClienteRepository $clienteRepository;

    public function __construct()
    {
        $this->ventaRepository = new VentaRepository();
        $this->productoRepository = new ProductoRepository();
        $this->clienteRepository = new ClienteRepository();
    }

    /**
     * GET /api/ventas
     * Listar ventas con filtros
     */
    public function index(): void
    {
        try {
            $estado = $_GET['estado'] ?? null;
            $idCliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : null;
            $fechaDesde = isset($_GET['fecha_desde']) ? new \DateTime($_GET['fecha_desde']) : null;
            $fechaHasta = isset($_GET['fecha_hasta']) ? new \DateTime($_GET['fecha_hasta']) : null;
            $limite = (int)($_GET['limite'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            $ventas = $this->ventaRepository->listar($estado, $idCliente, $fechaDesde, $fechaHasta, $limite, $offset);

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearVenta'], $ventas),
                'pagination' => [
                    'limite' => $limite,
                    'offset' => $offset,
                    'has_more' => count($ventas) === $limite
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener ventas: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/ventas/{id}
     * Obtener una venta específica con sus detalles
     */
    public function show(int $id): void
    {
        try {
            $venta = $this->ventaRepository->buscarPorId($id);

            if (!$venta) {
                $this->errorResponse('Venta no encontrada', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => $this->formatearVentaCompleta($venta)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener venta: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/ventas
     * Crear una nueva venta en borrador
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
            $this->validateRequired($data, ['id_cliente', 'detalles']);

            // Validar que el cliente exista
            $cliente = $this->clienteRepository->buscarPorId((int)$data['id_cliente']);
            if (!$cliente) {
                $this->errorResponse('Cliente no encontrado', 400);
                return;
            }

            // Validar que hay detalles
            if (empty($data['detalles']) || !is_array($data['detalles'])) {
                $this->errorResponse('La venta debe tener al menos un detalle', 400);
                return;
            }

            // Crear venta
            $venta = new Venta(
                (int)$data['id_cliente'],
                new \DateTime(),
                0.0, // Se calculará con los detalles
                'borrador'
            );

            // Procesar detalles
            $total = 0.0;
            $detalles = [];
            $lineNumber = 1;

            foreach ($data['detalles'] as $detalleData) {
                $this->validateRequired($detalleData, ['id_producto', 'cantidad']);

                $idProducto = (int)$detalleData['id_producto'];
                $cantidad = (int)$detalleData['cantidad'];

                // Validar que el producto exista
                $producto = $this->productoRepository->buscarPorId($idProducto);
                if (!$producto) {
                    $this->errorResponse("Producto con ID $idProducto no encontrado", 400);
                    return;
                }

                // Usar precio del producto o precio específico
                $precioUnitario = isset($detalleData['precio_unitario']) 
                    ? (float)$detalleData['precio_unitario']
                    : $producto->getPrecioUnitario(); // Usar método correcto

                $detalle = new DetalleVenta(
                    0, // Se asignará cuando se guarde la venta
                    $lineNumber++,
                    $idProducto,
                    $cantidad,
                    $precioUnitario
                );

                $detalles[] = $detalle;
                $total += $detalle->getSubtotal();
            }

            $venta->setTotal($total);
            $venta->setDetalles($detalles);

            // Validar la venta
            if (!$venta->validar()) {
                $this->errorResponse('Datos de la venta inválidos', 400);
                return;
            }

            // Guardar en base de datos
            if ($this->ventaRepository->crear($venta)) {
                $response = [
                    'success' => true,
                    'message' => 'Venta creada exitosamente',
                    'data' => [
                        'id' => $venta->getId(),
                        'total' => $venta->getTotal(),
                        'estado' => $venta->getEstado(),
                        'fecha' => $venta->getFecha()->format('Y-m-d H:i:s')
                    ]
                ];
                $this->jsonResponse($response, 201);
            } else {
                $this->errorResponse('Error al crear venta', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al crear venta: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/ventas/{id}
     * Actualizar una venta (solo borradores)
     */
    public function update(int $id): void
    {
        try {
            $venta = $this->ventaRepository->buscarPorId($id);
            if (!$venta) {
                $this->errorResponse('Venta no encontrada', 404);
                return;
            }

            if ($venta->getEstado() !== 'borrador') {
                $this->errorResponse('Solo se pueden editar ventas en borrador', 400);
                return;
            }

            $data = $this->getJsonInput();
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Actualizar cliente si se proporciona
            if (isset($data['id_cliente'])) {
                $cliente = $this->clienteRepository->buscarPorId((int)$data['id_cliente']);
                if (!$cliente) {
                    $this->errorResponse('Cliente no encontrado', 400);
                    return;
                }
                $venta->setIdCliente((int)$data['id_cliente']);
            }

            // Actualizar detalles si se proporcionan
            if (isset($data['detalles']) && is_array($data['detalles'])) {
                $total = 0.0;
                $detalles = [];
                $lineNumber = 1;

                foreach ($data['detalles'] as $detalleData) {
                    $this->validateRequired($detalleData, ['id_producto', 'cantidad']);

                    $idProducto = (int)$detalleData['id_producto'];
                    $cantidad = (int)$detalleData['cantidad'];

                    // Validar que el producto exista
                    $producto = $this->productoRepository->buscarPorId($idProducto);
                    if (!$producto) {
                        $this->errorResponse("Producto con ID $idProducto no encontrado", 400);
                        return;
                    }

                    // Usar precio del producto o precio específico
                    $precioUnitario = isset($detalleData['precio_unitario']) 
                        ? (float)$detalleData['precio_unitario']
                        : $producto->getPrecioUnitario();

                    $detalle = new DetalleVenta(
                        $venta->getId(),
                        $lineNumber++,
                        $idProducto,
                        $cantidad,
                        $precioUnitario
                    );

                    $detalles[] = $detalle;
                    $total += $detalle->getSubtotal();
                }

                $venta->setTotal($total);
                $venta->setDetalles($detalles);
            }

            // Actualizar en base de datos
            if ($this->ventaRepository->actualizar($venta)) {
                $response = [
                    'success' => true,
                    'message' => 'Venta actualizada exitosamente',
                    'data' => [
                        'id' => $venta->getId(),
                        'total' => $venta->getTotal(),
                        'estado' => $venta->getEstado()
                    ]
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar venta', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al actualizar venta: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/ventas/{id}/emitir
     * Emitir una venta (cambiar de borrador a emitida)
     */
    public function emitir(int $id): void
    {
        try {
            if ($this->ventaRepository->emitir($id)) {
                $venta = $this->ventaRepository->buscarPorId($id);
                $response = [
                    'success' => true,
                    'message' => 'Venta emitida exitosamente',
                    'data' => [
                        'id' => $id,
                        'estado' => $venta->getEstado(),
                        'fecha' => $venta->getFecha()->format('Y-m-d H:i:s')
                    ]
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al emitir venta', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al emitir venta: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/ventas/{id}/anular
     * Anular una venta emitida
     */
    public function anular(int $id): void
    {
        try {
            if ($this->ventaRepository->anular($id)) {
                $response = [
                    'success' => true,
                    'message' => 'Venta anulada exitosamente',
                    'data' => ['id' => $id, 'estado' => 'anulada']
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al anular venta', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al anular venta: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/ventas/{id}
     * Eliminar una venta (solo borradores)
     */
    public function delete(int $id): void
    {
        try {
            if ($this->ventaRepository->eliminar($id)) {
                $response = [
                    'success' => true,
                    'message' => 'Venta eliminada exitosamente'
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al eliminar venta', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al eliminar venta: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/ventas/cliente/{id}
     * Listar ventas de un cliente específico
     */
    public function porCliente(int $idCliente): void
    {
        try {
            $limite = (int)($_GET['limite'] ?? 20);
            $ventas = $this->ventaRepository->listarPorCliente($idCliente, $limite);

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearVenta'], $ventas)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener ventas del cliente: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/ventas/fecha/{fecha}
     * Listar ventas de una fecha específica
     */
    public function porFecha(string $fecha): void
    {
        try {
            $fechaObj = new \DateTime($fecha);
            $ventas = $this->ventaRepository->listarPorFecha($fechaObj);

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearVenta'], $ventas)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener ventas por fecha: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/ventas/estadisticas
     * Obtener estadísticas de ventas
     */
    public function estadisticas(): void
    {
        try {
            $fechaDesde = isset($_GET['fecha_desde']) 
                ? new \DateTime($_GET['fecha_desde']) 
                : new \DateTime('first day of this month');
            
            $fechaHasta = isset($_GET['fecha_hasta']) 
                ? new \DateTime($_GET['fecha_hasta']) 
                : new \DateTime('last day of this month');

            $stats = $this->ventaRepository->obtenerEstadisticas($fechaDesde, $fechaHasta);

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

    private function formatearVenta(Venta $venta): array
    {
        return [
            'id' => $venta->getId(),
            'id_cliente' => $venta->getIdCliente(),
            'fecha' => $venta->getFecha()->format('Y-m-d H:i:s'),
            'total' => $venta->getTotal(),
            'estado' => $venta->getEstado()
        ];
    }

    private function formatearVentaCompleta(Venta $venta): array
    {
        $data = $this->formatearVenta($venta);
        
        // Agregar detalles
        $data['detalles'] = array_map(function($detalle) {
            return [
                'line_number' => $detalle->getLineNumber(),
                'id_producto' => $detalle->getIdProducto(),
                'cantidad' => $detalle->getCantidad(),
                'precio_unitario' => $detalle->getPrecioUnitario(),
                'subtotal' => $detalle->getSubtotal()
            ];
        }, $venta->getDetalles());

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
            if (!isset($data[$field])) {
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
