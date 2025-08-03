<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoriaRepository;

class CategoriaController
{
    private CategoriaRepository $categoriaRepository;

    public function __construct()
    {
        $this->categoriaRepository = new CategoriaRepository();
    }

    /**
     * GET /api/categorias
     * Listar todas las categorías
     */
    public function index(): void
    {
        try {
            $incluirJerarquia = $_GET['jerarquia'] ?? false;
            $idPadre = $_GET['id_padre'] ?? null;

            if ($incluirJerarquia) {
                // Obtener jerarquía completa
                $categorias = $this->categoriaRepository->obtenerJerarquia();
            } elseif ($idPadre !== null) {
                // Obtener subcategorías de una categoría padre
                $categorias = $this->categoriaRepository->listarSubcategorias((int)$idPadre);
            } else {
                // Listar todas las categorías
                $categorias = $this->categoriaRepository->listarTodas();
            }

            $response = [
                'success' => true,
                'data' => array_map([$this, 'formatearCategoria'], $categorias)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener categorías: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/categorias/{id}
     * Obtener una categoría específica
     */
    public function show(int $id): void
    {
        try {
            $categoria = $this->categoriaRepository->buscarPorId($id);

            if (!$categoria) {
                $this->errorResponse('Categoría no encontrada', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => $this->formatearCategoria($categoria)
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener categoría: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/categorias
     * Crear una nueva categoría
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
            $this->validateRequired($data, ['nombre']);

            $categoria = new \App\Entities\Categoria(
                $data['nombre'],
                $data['descripcion'] ?? '',
                true, // estado activo por defecto
                isset($data['id_padre']) ? (int)$data['id_padre'] : null
            );

            if ($this->categoriaRepository->crear($categoria)) {
                $response = [
                    'success' => true,
                    'message' => 'Categoría creada exitosamente',
                    'data' => [
                        'id' => $categoria->getId(),
                        'nombre' => $categoria->getNombre()
                    ]
                ];
                $this->jsonResponse($response, 201);
            } else {
                $this->errorResponse('Error al crear categoría', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al crear categoría: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/categorias/{id}
     * Actualizar una categoría
     */
    public function update(int $id): void
    {
        try {
            $categoria = $this->categoriaRepository->buscarPorId($id);
            if (!$categoria) {
                $this->errorResponse('Categoría no encontrada', 404);
                return;
            }

            $data = $this->getJsonInput();
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Actualizar campos si están presentes
            if (isset($data['nombre'])) {
                $categoria->setNombre($data['nombre']);
            }
            if (isset($data['descripcion'])) {
                $categoria->setDescripcion($data['descripcion']);
            }
            if (isset($data['id_padre'])) {
                $categoria->setIdPadre($data['id_padre'] ? (int)$data['id_padre'] : null);
            }

            if ($this->categoriaRepository->actualizar($categoria)) {
                $response = [
                    'success' => true,
                    'message' => 'Categoría actualizada exitosamente',
                    'data' => $this->formatearCategoria($categoria)
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar categoría', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al actualizar categoría: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/categorias/{id}
     * Eliminar una categoría
     */
    public function delete(int $id): void
    {
        try {
            $categoria = $this->categoriaRepository->buscarPorId($id);
            if (!$categoria) {
                $this->errorResponse('Categoría no encontrada', 404);
                return;
            }

            // Verificar si tiene subcategorías
            $subcategorias = $this->categoriaRepository->listarSubcategorias($id);
            if (!empty($subcategorias)) {
                $this->errorResponse('No se puede eliminar una categoría que tiene subcategorías', 400);
                return;
            }

            if ($this->categoriaRepository->eliminar($id)) {
                $response = [
                    'success' => true,
                    'message' => 'Categoría eliminada exitosamente'
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al eliminar categoría', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al eliminar categoría: ' . $e->getMessage());
        }
    }

    // ===== MÉTODOS PRIVADOS =====

    private function formatearCategoria($categoria): array
    {
        return [
            'id' => $categoria->getId(),
            'nombre' => $categoria->getNombre(),
            'descripcion' => $categoria->getDescripcion(),
            'id_padre' => $categoria->getIdPadre()
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
