<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClienteRepository;
use App\Entities\PersonaNatural;
use App\Entities\PersonaJuridica;

class ClienteController
{
    private ClienteRepository $clienteRepository;

    public function __construct()
    {
        $this->clienteRepository = new ClienteRepository();
    }

    /**
     * GET /api/clientes
     * Listar todos los clientes con paginación
     */
    public function index(): void
    {
        try {
            $limite = (int)($_GET['limite'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $busqueda = $_GET['busqueda'] ?? '';

            if ($busqueda) {
                $clientes = $this->clienteRepository->buscarPorNombre($busqueda);
            } else {
                $clientes = $this->clienteRepository->listarTodos($limite, $offset);
            }

            $total = $this->clienteRepository->contarTotal();

            $response = [
                'success' => true,
                'data' => array_map(fn($cliente) => $cliente->toArray ? $cliente->toArray() : [
                    'id' => $cliente->getId(),
                    'email' => $cliente->getEmail(),
                    'telefono' => $cliente->getTelefono(),
                    'direccion' => $cliente->getDireccion(),
                    'tipo_cliente' => $cliente->getTipoCliente(),
                    'nombre_completo' => $cliente->getNombreCompleto()
                ], $clientes),
                'pagination' => [
                    'total' => $total,
                    'limite' => $limite,
                    'offset' => $offset,
                    'has_more' => ($offset + $limite) < $total
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener clientes: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/clientes/{id}
     * Obtener un cliente específico
     */
    public function show(int $id): void
    {
        try {
            $cliente = $this->clienteRepository->buscarPorId($id);

            if (!$cliente) {
                $this->errorResponse('Cliente no encontrado', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $cliente->getId(),
                    'email' => $cliente->getEmail(),
                    'telefono' => $cliente->getTelefono(),
                    'direccion' => $cliente->getDireccion(),
                    'tipo_cliente' => $cliente->getTipoCliente(),
                    'nombre_completo' => $cliente->getNombreCompleto()
                ]
            ];

            // Agregar datos específicos según el tipo
            if ($cliente instanceof PersonaNatural) {
                $response['data']['nombres'] = $cliente->getNombres();
                $response['data']['apellidos'] = $cliente->getApellidos();
                $response['data']['cedula'] = $cliente->getCedula();
            } elseif ($cliente instanceof PersonaJuridica) {
                $response['data']['razon_social'] = $cliente->getRazonSocial();
                $response['data']['ruc'] = $cliente->getRuc();
                $response['data']['representante_legal'] = $cliente->getRepresentanteLegal();
            }

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener cliente: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/clientes
     * Crear un nuevo cliente
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
            $this->validateRequired($data, ['email', 'tipo_cliente']);

            // Verificar que el email no exista
            if ($this->clienteRepository->existeEmail($data['email'])) {
                $this->errorResponse('El email ya está registrado', 400);
                return;
            }

            // Crear cliente según el tipo
            if ($data['tipo_cliente'] === 'natural') {
                $cliente = $this->crearPersonaNatural($data);
            } elseif ($data['tipo_cliente'] === 'juridico') {
                $cliente = $this->crearPersonaJuridica($data);
            } else {
                $this->errorResponse('Tipo de cliente inválido', 400);
                return;
            }

            // Validar la entidad
            if (!$cliente->validar()) {
                $this->errorResponse('Datos del cliente inválidos', 400);
                return;
            }

            // Guardar en base de datos
            if ($this->clienteRepository->crear($cliente)) {
                $response = [
                    'success' => true,
                    'message' => 'Cliente creado exitosamente',
                    'data' => [
                        'id' => $cliente->getId(),
                        'nombre_completo' => $cliente->getNombreCompleto(),
                        'email' => $cliente->getEmail(),
                        'tipo_cliente' => $cliente->getTipoCliente()
                    ]
                ];
                $this->jsonResponse($response, 201);
            } else {
                $this->errorResponse('Error al crear cliente', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al crear cliente: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/clientes/{id}
     * Actualizar un cliente existente
     */
    public function update(int $id): void
    {
        try {
            $cliente = $this->clienteRepository->buscarPorId($id);
            if (!$cliente) {
                $this->errorResponse('Cliente no encontrado', 404);
                return;
            }

            $data = $this->getJsonInput();
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Verificar que el email no exista (excluyendo el cliente actual)
            if (isset($data['email']) && $this->clienteRepository->existeEmail($data['email'], $id)) {
                $this->errorResponse('El email ya está registrado', 400);
                return;
            }

            // Actualizar datos base
            if (isset($data['email'])) $cliente->setEmail($data['email']);
            if (isset($data['telefono'])) $cliente->setTelefono($data['telefono']);
            if (isset($data['direccion'])) $cliente->setDireccion($data['direccion']);

            // Actualizar datos específicos según el tipo
            if ($cliente instanceof PersonaNatural) {
                if (isset($data['nombres'])) $cliente->setNombres($data['nombres']);
                if (isset($data['apellidos'])) $cliente->setApellidos($data['apellidos']);
                if (isset($data['cedula'])) {
                    if ($this->clienteRepository->existeCedula($data['cedula'], $id)) {
                        $this->errorResponse('La cédula ya está registrada', 400);
                        return;
                    }
                    $cliente->setCedula($data['cedula']);
                }
            } elseif ($cliente instanceof PersonaJuridica) {
                if (isset($data['razon_social'])) $cliente->setRazonSocial($data['razon_social']);
                if (isset($data['representante_legal'])) $cliente->setRepresentanteLegal($data['representante_legal']);
                if (isset($data['ruc'])) {
                    if ($this->clienteRepository->existeRuc($data['ruc'], $id)) {
                        $this->errorResponse('El RUC ya está registrado', 400);
                        return;
                    }
                    $cliente->setRuc($data['ruc']);
                }
            }

            // Validar la entidad
            if (!$cliente->validar()) {
                $this->errorResponse('Datos del cliente inválidos', 400);
                return;
            }

            // Actualizar en base de datos
            if ($this->clienteRepository->actualizar($cliente)) {
                $response = [
                    'success' => true,
                    'message' => 'Cliente actualizado exitosamente',
                    'data' => [
                        'id' => $cliente->getId(),
                        'nombre_completo' => $cliente->getNombreCompleto(),
                        'email' => $cliente->getEmail()
                    ]
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar cliente', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al actualizar cliente: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/clientes/{id}
     * Eliminar un cliente (soft delete)
     */
    public function delete(int $id): void
    {
        try {
            $cliente = $this->clienteRepository->buscarPorId($id);
            if (!$cliente) {
                $this->errorResponse('Cliente no encontrado', 404);
                return;
            }

            if ($this->clienteRepository->eliminar($id)) {
                $response = [
                    'success' => true,
                    'message' => 'Cliente eliminado exitosamente'
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al eliminar cliente', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al eliminar cliente: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/clientes/buscar/{documento}
     * Buscar cliente por documento (cédula o RUC)
     */
    public function buscarPorDocumento(string $documento): void
    {
        try {
            $cliente = $this->clienteRepository->buscarPorDocumento($documento);

            if (!$cliente) {
                $this->errorResponse('Cliente no encontrado', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $cliente->getId(),
                    'nombre_completo' => $cliente->getNombreCompleto(),
                    'email' => $cliente->getEmail(),
                    'telefono' => $cliente->getTelefono(),
                    'direccion' => $cliente->getDireccion(),
                    'tipo_cliente' => $cliente->getTipoCliente()
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al buscar cliente: ' . $e->getMessage());
        }
    }

    // ===== MÉTODOS PRIVADOS =====

    private function crearPersonaNatural(array $data): PersonaNatural
    {
        $this->validateRequired($data, ['nombres', 'apellidos', 'cedula']);

        // Verificar que la cédula no exista
        if ($this->clienteRepository->existeCedula($data['cedula'])) {
            throw new \InvalidArgumentException('La cédula ya está registrada');
        }

        return new PersonaNatural(
            $data['nombres'],
            $data['apellidos'],
            $data['cedula'],
            $data['email'],
            $data['telefono'] ?? null,
            $data['direccion'] ?? null
        );
    }

    private function crearPersonaJuridica(array $data): PersonaJuridica
    {
        $this->validateRequired($data, ['razon_social', 'ruc']);

        // Verificar que el RUC no exista
        if ($this->clienteRepository->existeRuc($data['ruc'])) {
            throw new \InvalidArgumentException('El RUC ya está registrado');
        }

        return new PersonaJuridica(
            $data['razon_social'],
            $data['ruc'],
            $data['email'],
            $data['representante_legal'] ?? null,
            $data['telefono'] ?? null,
            $data['direccion'] ?? null
        );
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
