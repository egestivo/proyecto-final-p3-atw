<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FacturaRepository;
use App\Repositories\VentaRepository;

class FacturaController
{
    private FacturaRepository $facturaRepository;
    private VentaRepository $ventaRepository;

    public function __construct()
    {
        $this->facturaRepository = new FacturaRepository();
        $this->ventaRepository = new VentaRepository();
    }

    /**
     * GET /api/facturas
     * Listar facturas con filtros
     */
    public function index(): void
    {
        try {
            $limite = (int)($_GET['limite'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $estado = $_GET['estado'] ?? null;
            
            // Crear fechas desde y hasta si se proporcionan
            $fechaDesde = null;
            $fechaHasta = null;
            
            if (isset($_GET['fecha_desde'])) {
                $fechaDesde = new \DateTime($_GET['fecha_desde']);
            }
            if (isset($_GET['fecha_hasta'])) {
                $fechaHasta = new \DateTime($_GET['fecha_hasta']);
            }
            
            $facturas = $this->facturaRepository->listar($estado, $fechaDesde, $fechaHasta, $limite, $offset);
            
            // Contar total manualmente ya que no hay método contarTotal
            $totalFacturas = $this->facturaRepository->listar($estado, $fechaDesde, $fechaHasta);
            $total = count($totalFacturas);

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearFactura'], $facturas),
                'pagination' => [
                    'total' => $total,
                    'limite' => $limite,
                    'offset' => $offset,
                    'has_more' => ($offset + $limite) < $total
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener facturas: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/facturas/{id}
     * Obtener una factura específica
     */
    public function show(int $id): void
    {
        try {
            $factura = $this->facturaRepository->buscarPorId($id);

            if (!$factura) {
                $this->errorResponse('Factura no encontrada', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => $this->formatearFactura($factura)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener factura: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/facturas
     * Crear una nueva factura desde una venta
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
            $this->validateRequired($data, ['id_venta']);

            $idVenta = (int)$data['id_venta'];

            // Verificar que la venta exista
            $venta = $this->ventaRepository->buscarPorId($idVenta);
            if (!$venta) {
                $this->errorResponse('La venta especificada no existe', 404);
                return;
            }

            // Verificar que no exista ya una factura para esta venta
            $facturaExistente = $this->facturaRepository->buscarPorVenta($idVenta);
            if ($facturaExistente) {
                $this->errorResponse('Ya existe una factura para esta venta', 400);
                return;
            }

            // Crear nueva factura
            $numeroFactura = $this->facturaRepository->generarSiguienteNumero();
            
            $factura = new \App\Entities\Factura(
                $idVenta,
                $numeroFactura,
                null, // clave_acceso se genera al emitir
                new \DateTime(), // fecha_emision
                'borrador' // estado inicial
            );

            if ($this->facturaRepository->crear($factura)) {
                $response = [
                    'success' => true,
                    'message' => 'Factura creada exitosamente',
                    'data' => [
                        'id' => $factura->getId(),
                        'numero' => $factura->getNumero(),
                        'fecha_emision' => $factura->getFechaEmision()->format('Y-m-d H:i:s'),
                        'estado' => $factura->getEstado()
                    ]
                ];
                $this->jsonResponse($response, 201);
            } else {
                $this->errorResponse('Error al crear factura', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al crear factura: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/facturas/{id}
     * Actualizar una factura
     */
    public function update(int $id): void
    {
        try {
            $factura = $this->facturaRepository->buscarPorId($id);
            if (!$factura) {
                $this->errorResponse('Factura no encontrada', 404);
                return;
            }

            $data = $this->getJsonInput();
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Solo permitir actualizar el estado
            if (isset($data['estado'])) {
                $factura->setEstado($data['estado']);
            }

            // Actualizar en base de datos
            if ($this->facturaRepository->actualizar($factura)) {
                $response = [
                    'success' => true,
                    'message' => 'Factura actualizada exitosamente',
                    'data' => $this->formatearFactura($factura)
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar factura', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al actualizar factura: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/facturas/{id}/autorizar
     * Autorizar una factura
     */
    public function autorizar(int $id): void
    {
        try {
            $factura = $this->facturaRepository->buscarPorId($id);
            if (!$factura) {
                $this->errorResponse('Factura no encontrada', 404);
                return;
            }

            // Simular autorización XML (en un caso real sería del SRI)
            $numeroAutorizacion = 'AUT-' . date('YmdHis') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            $xmlAutorizado = '<?xml version="1.0" encoding="UTF-8"?><autorizacion><numeroAutorizacion>' . $numeroAutorizacion . '</numeroAutorizacion></autorizacion>';
            
            if ($this->facturaRepository->autorizar($id, $xmlAutorizado)) {
                $response = [
                    'success' => true,
                    'message' => 'Factura autorizada exitosamente',
                    'data' => [
                        'id' => $id,
                        'numero_autorizacion' => $numeroAutorizacion,
                        'estado' => 'autorizada'
                    ]
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al autorizar factura', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al autorizar factura: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/facturas/{id}
     * Anular una factura
     */
    public function delete(int $id): void
    {
        try {
            $factura = $this->facturaRepository->buscarPorId($id);
            if (!$factura) {
                $this->errorResponse('Factura no encontrada', 404);
                return;
            }

            if ($this->facturaRepository->anular($id)) {
                $response = [
                    'success' => true,
                    'message' => 'Factura anulada exitosamente'
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al anular factura', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al anular factura: ' . $e->getMessage());
        }
    }

    // ===== MÉTODOS PRIVADOS =====

    private function formatearFactura($factura): array
    {
        return [
            'id' => $factura->getId(),
            'numero' => $factura->getNumero(),
            'id_venta' => $factura->getIdVenta(),
            'fecha_emision' => $factura->getFechaEmision()->format('Y-m-d H:i:s'),
            'estado' => $factura->getEstado(),
            'monto_total' => $factura->getMontoTotal()
        ];
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
