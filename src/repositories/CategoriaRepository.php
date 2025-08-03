<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Entities\Categoria;
use PDO;
use PDOException;

class CategoriaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crear una nueva categoría
     */
    public function crear(Categoria $categoria): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO categorias (nombre, descripcion, id_padre) 
                VALUES (:nombre, :descripcion, :id_padre)
            ");

            $stmt->execute([
                ':nombre' => $categoria->getNombre(),
                ':descripcion' => $categoria->getDescripcion(),
                ':id_padre' => $categoria->getIdPadre()
            ]);

            $categoria->setId((int)$this->db->lastInsertId());
            return true;

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al crear categoría: ' . $e->getMessage());
        }
    }

    /**
     * Buscar categoría por ID
     */
    public function buscarPorId(int $id): ?Categoria
    {
        $stmt = $this->db->prepare("
            SELECT * FROM categorias 
            WHERE id = :id AND estado = 1
        ");
        
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaACategoria($row);
    }

    /**
     * Buscar categoría por nombre
     */
    public function buscarPorNombre(string $nombre): ?Categoria
    {
        $stmt = $this->db->prepare("
            SELECT * FROM categorias 
            WHERE nombre = :nombre AND estado = 1
        ");
        
        $stmt->execute([':nombre' => $nombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaACategoria($row);
    }

    /**
     * Listar todas las categorías activas
     */
    public function listarTodas(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM categorias 
            WHERE estado = 1 
            ORDER BY nombre
        ");

        $categorias = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $this->mapearFilaACategoria($row);
        }

        return $categorias;
    }

    /**
     * Listar categorías principales (sin padre)
     */
    public function listarPrincipales(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM categorias 
            WHERE id_padre IS NULL AND estado = 1 
            ORDER BY nombre
        ");

        $categorias = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $this->mapearFilaACategoria($row);
        }

        return $categorias;
    }

    /**
     * Listar subcategorías de una categoría padre
     */
    public function listarSubcategorias(int $idPadre): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM categorias 
            WHERE id_padre = :id_padre AND estado = 1 
            ORDER BY nombre
        ");
        
        $stmt->execute([':id_padre' => $idPadre]);

        $categorias = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $this->mapearFilaACategoria($row);
        }

        return $categorias;
    }

    /**
     * Obtener estructura jerárquica completa
     */
    public function obtenerJerarquia(): array
    {
        $principales = $this->listarPrincipales();
        $jerarquia = [];

        foreach ($principales as $principal) {
            $categoria = $principal->toArray();
            $categoria['subcategorias'] = [];
            
            $subcategorias = $this->listarSubcategorias($principal->getId());
            foreach ($subcategorias as $sub) {
                $categoria['subcategorias'][] = $sub->toArray();
            }
            
            $jerarquia[] = $categoria;
        }

        return $jerarquia;
    }

    /**
     * Actualizar categoría
     */
    public function actualizar(Categoria $categoria): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE categorias 
                SET nombre = :nombre, descripcion = :descripcion, id_padre = :id_padre 
                WHERE id = :id
            ");

            return $stmt->execute([
                ':nombre' => $categoria->getNombre(),
                ':descripcion' => $categoria->getDescripcion(),
                ':id_padre' => $categoria->getIdPadre(),
                ':id' => $categoria->getId()
            ]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al actualizar categoría: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar categoría (soft delete)
     */
    public function eliminar(int $id): bool
    {
        // Verificar que no tenga productos asociados
        if ($this->tieneProductos($id)) {
            throw new \RuntimeException('No se puede eliminar la categoría porque tiene productos asociados');
        }

        // Verificar que no tenga subcategorías
        $subcategorias = $this->listarSubcategorias($id);
        if (!empty($subcategorias)) {
            throw new \RuntimeException('No se puede eliminar la categoría porque tiene subcategorías');
        }

        $stmt = $this->db->prepare("UPDATE categorias SET estado = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Activar/Desactivar categoría
     */
    public function cambiarEstado(int $id, bool $estado): bool
    {
        $stmt = $this->db->prepare("UPDATE categorias SET estado = :estado WHERE id = :id");
        return $stmt->execute([
            ':estado' => $estado ? 1 : 0,
            ':id' => $id
        ]);
    }

    /**
     * Verificar si la categoría tiene productos
     */
    public function tieneProductos(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM productos 
            WHERE id_categoria = :id_categoria AND estado = 1
        ");
        
        $stmt->execute([':id_categoria' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Contar productos por categoría
     */
    public function contarProductos(int $id): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM productos 
            WHERE id_categoria = :id_categoria AND estado = 1
        ");
        
        $stmt->execute([':id_categoria' => $id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Verificar si existe categoría con el mismo nombre
     */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM categorias WHERE nombre = :nombre AND estado = 1";
        $params = [':nombre' => $nombre];

        if ($excluirId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verificar si puede ser padre (evitar ciclos)
     */
    public function puedeSerPadre(int $idCategoria, int $idPadrePropuesto): bool
    {
        // Una categoría no puede ser padre de sí misma
        if ($idCategoria === $idPadrePropuesto) {
            return false;
        }

        // Verificar que el padre propuesto no sea descendiente de la categoría
        $stmt = $this->db->prepare("
            SELECT id_padre FROM categorias 
            WHERE id = :id AND estado = 1
        ");
        
        $idActual = $idPadrePropuesto;
        
        while ($idActual !== null) {
            $stmt->execute([':id' => $idActual]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                break;
            }
            
            $idActual = $row['id_padre'];
            
            // Si encontramos la categoría original en la cadena de padres, hay un ciclo
            if ($idActual === $idCategoria) {
                return false;
            }
        }

        return true;
    }

    private function mapearFilaACategoria(array $row): Categoria
    {
        return new Categoria(
            $row['nombre'],
            $row['descripcion'],
            (bool)$row['estado'],
            $row['id_padre'] ? (int)$row['id_padre'] : null,
            (int)$row['id']
        );
    }
}
