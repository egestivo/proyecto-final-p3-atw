<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Entities\Producto;
use App\Entities\ProductoFisico;
use App\Entities\ProductoDigital;
use PDO;
use PDOException;

class ProductoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crear un nuevo producto (ProductoFisico o ProductoDigital)
     */
    public function crear(Producto $producto): bool
    {
        try {
            $this->db->beginTransaction();

            // Insertar en tabla base productos
            $stmt = $this->db->prepare("
                INSERT INTO productos (nombre, descripcion, precio_unitario, stock, id_categoria, tipo_producto) 
                VALUES (:nombre, :descripcion, :precio_unitario, :stock, :id_categoria, :tipo_producto)
            ");

            $stmt->execute([
                ':nombre' => $producto->getNombre(),
                ':descripcion' => $producto->getDescripcion(),
                ':precio_unitario' => $producto->getPrecioUnitario(),
                ':stock' => $producto->getStock(),
                ':id_categoria' => $producto->getIdCategoria(),
                ':tipo_producto' => $producto->getTipoProducto()
            ]);

            $idProducto = (int)$this->db->lastInsertId();
            $producto->setId($idProducto);

            // Insertar en tabla específica según el tipo
            if ($producto instanceof ProductoFisico) {
                $this->insertarProductoFisico($producto);
            } elseif ($producto instanceof ProductoDigital) {
                $this->insertarProductoDigital($producto);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al crear producto: ' . $e->getMessage());
        }
    }

    /**
     * Buscar producto por ID
     */
    public function buscarPorId(int $id): ?Producto
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_productos_completa 
            WHERE id = :id AND estado = 1
        ");
        
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAProducto($row);
    }

    /**
     * Listar productos por categoría
     */
    public function listarPorCategoria(int $idCategoria, int $limite = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_productos_completa 
            WHERE id_categoria = :id_categoria AND estado = 1 
            ORDER BY nombre 
            LIMIT :limite OFFSET :offset
        ");
        
        $stmt->bindValue(':id_categoria', $idCategoria, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $productos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearFilaAProducto($row);
        }

        return $productos;
    }

    /**
     * Buscar productos por nombre
     */
    public function buscarPorNombre(string $nombre): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_productos_completa 
            WHERE nombre LIKE :nombre AND estado = 1 
            ORDER BY nombre 
            LIMIT 50
        ");
        
        $stmt->execute([':nombre' => "%$nombre%"]);

        $productos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearFilaAProducto($row);
        }

        return $productos;
    }

    /**
     * Listar productos con stock bajo
     */
    public function listarConStockBajo(int $stockMinimo = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_productos_completa 
            WHERE stock <= :stock_minimo AND tipo_producto = 'fisico' AND estado = 1 
            ORDER BY stock ASC, nombre
        ");
        
        $stmt->execute([':stock_minimo' => $stockMinimo]);

        $productos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearFilaAProducto($row);
        }

        return $productos;
    }

    /**
     * Listar productos digitales próximos a expirar
     */
    public function listarDigitalesProximosAExpirar(int $dias = 30): array
    {
        $fechaLimite = date('Y-m-d H:i:s', strtotime("+$dias days"));
        
        $stmt = $this->db->prepare("
            SELECT * FROM v_productos_completa 
            WHERE tipo_producto = 'digital' 
            AND fecha_expiracion IS NOT NULL 
            AND fecha_expiracion <= :fecha_limite 
            AND fecha_expiracion > NOW()
            AND estado = 1 
            ORDER BY fecha_expiracion ASC
        ");
        
        $stmt->execute([':fecha_limite' => $fechaLimite]);

        $productos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearFilaAProducto($row);
        }

        return $productos;
    }

    /**
     * Actualizar producto
     */
    public function actualizar(Producto $producto): bool
    {
        try {
            $this->db->beginTransaction();

            // Actualizar tabla base
            $stmt = $this->db->prepare("
                UPDATE productos 
                SET nombre = :nombre, descripcion = :descripcion, precio_unitario = :precio_unitario, 
                    stock = :stock, id_categoria = :id_categoria 
                WHERE id = :id
            ");

            $stmt->execute([
                ':nombre' => $producto->getNombre(),
                ':descripcion' => $producto->getDescripcion(),
                ':precio_unitario' => $producto->getPrecioUnitario(),
                ':stock' => $producto->getStock(),
                ':id_categoria' => $producto->getIdCategoria(),
                ':id' => $producto->getId()
            ]);

            // Actualizar tabla específica
            if ($producto instanceof ProductoFisico) {
                $this->actualizarProductoFisico($producto);
            } elseif ($producto instanceof ProductoDigital) {
                $this->actualizarProductoDigital($producto);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al actualizar producto: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar stock del producto
     */
    public function actualizarStock(int $idProducto, int $nuevoStock): bool
    {
        $stmt = $this->db->prepare("UPDATE productos SET stock = :stock WHERE id = :id");
        return $stmt->execute([
            ':stock' => $nuevoStock,
            ':id' => $idProducto
        ]);
    }

    /**
     * Descontar stock usando procedimiento almacenado
     */
    public function descontarStock(int $idProducto, int $cantidad): bool
    {
        $stmt = $this->db->prepare("CALL sp_descontar_stock(:id_producto, :cantidad, @resultado)");
        $stmt->execute([
            ':id_producto' => $idProducto,
            ':cantidad' => $cantidad
        ]);

        // Obtener el resultado
        $result = $this->db->query("SELECT @resultado as resultado")->fetch(PDO::FETCH_ASSOC);
        return (bool)$result['resultado'];
    }

    /**
     * Validar stock usando procedimiento almacenado
     */
    public function validarStock(int $idProducto, int $cantidad): array
    {
        $stmt = $this->db->prepare("CALL sp_validar_stock(:id_producto, :cantidad, @resultado, @stock_disponible)");
        $stmt->execute([
            ':id_producto' => $idProducto,
            ':cantidad' => $cantidad
        ]);

        // Obtener los resultados
        $result = $this->db->query("SELECT @resultado as resultado, @stock_disponible as stock_disponible")->fetch(PDO::FETCH_ASSOC);
        
        return [
            'valido' => (bool)$result['resultado'],
            'stock_disponible' => (int)$result['stock_disponible']
        ];
    }

    /**
     * Eliminar producto (soft delete)
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE productos SET estado = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Listar todos los productos activos
     */
    public function listarTodos(int $limite = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_productos_completa 
            WHERE estado = 1 
            ORDER BY nombre 
            LIMIT :limite OFFSET :offset
        ");
        
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $productos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearFilaAProducto($row);
        }

        return $productos;
    }

    /**
     * Contar total de productos activos
     */
    public function contarTotal(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM productos WHERE estado = 1");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Contar productos por categoría
     */
    public function contarPorCategoria(int $idCategoria): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM productos WHERE id_categoria = :id_categoria AND estado = 1");
        $stmt->execute([':id_categoria' => $idCategoria]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Registrar descarga de producto digital
     */
    public function registrarDescarga(int $idProducto): bool
    {
        $stmt = $this->db->prepare("
            UPDATE productos_digitales 
            SET descargas_realizadas = descargas_realizadas + 1 
            WHERE id_producto = :id_producto
        ");
        
        return $stmt->execute([':id_producto' => $idProducto]);
    }

    // ===== MÉTODOS PRIVADOS =====

    private function insertarProductoFisico(ProductoFisico $producto): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO productos_fisicos (id_producto, peso, alto, ancho, profundidad) 
            VALUES (:id_producto, :peso, :alto, :ancho, :profundidad)
        ");

        $stmt->execute([
            ':id_producto' => $producto->getId(),
            ':peso' => $producto->getPeso(),
            ':alto' => $producto->getAlto(),
            ':ancho' => $producto->getAncho(),
            ':profundidad' => $producto->getProfundidad()
        ]);
    }

    private function insertarProductoDigital(ProductoDigital $producto): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO productos_digitales (id_producto, url_descarga, licencia, clave_activacion, fecha_expiracion, max_descargas, descargas_realizadas) 
            VALUES (:id_producto, :url_descarga, :licencia, :clave_activacion, :fecha_expiracion, :max_descargas, :descargas_realizadas)
        ");

        $stmt->execute([
            ':id_producto' => $producto->getId(),
            ':url_descarga' => $producto->getUrlDescarga(),
            ':licencia' => $producto->getLicencia(),
            ':clave_activacion' => $producto->getClaveActivacion(),
            ':fecha_expiracion' => $producto->getFechaExpiracion()?->format('Y-m-d H:i:s'),
            ':max_descargas' => $producto->getMaxDescargas(),
            ':descargas_realizadas' => $producto->getDescargasRealizadas()
        ]);
    }

    private function actualizarProductoFisico(ProductoFisico $producto): void
    {
        $stmt = $this->db->prepare("
            UPDATE productos_fisicos 
            SET peso = :peso, alto = :alto, ancho = :ancho, profundidad = :profundidad 
            WHERE id_producto = :id_producto
        ");

        $stmt->execute([
            ':peso' => $producto->getPeso(),
            ':alto' => $producto->getAlto(),
            ':ancho' => $producto->getAncho(),
            ':profundidad' => $producto->getProfundidad(),
            ':id_producto' => $producto->getId()
        ]);
    }

    private function actualizarProductoDigital(ProductoDigital $producto): void
    {
        $stmt = $this->db->prepare("
            UPDATE productos_digitales 
            SET url_descarga = :url_descarga, licencia = :licencia, clave_activacion = :clave_activacion, 
                fecha_expiracion = :fecha_expiracion, max_descargas = :max_descargas, descargas_realizadas = :descargas_realizadas 
            WHERE id_producto = :id_producto
        ");

        $stmt->execute([
            ':url_descarga' => $producto->getUrlDescarga(),
            ':licencia' => $producto->getLicencia(),
            ':clave_activacion' => $producto->getClaveActivacion(),
            ':fecha_expiracion' => $producto->getFechaExpiracion()?->format('Y-m-d H:i:s'),
            ':max_descargas' => $producto->getMaxDescargas(),
            ':descargas_realizadas' => $producto->getDescargasRealizadas(),
            ':id_producto' => $producto->getId()
        ]);
    }

    private function mapearFilaAProducto(array $row): Producto
    {
        if ($row['tipo_producto'] === 'fisico') {
            return new ProductoFisico(
                $row['nombre'],
                (float)$row['precio_unitario'],
                (int)$row['id_categoria'],
                (float)$row['peso'],
                (float)$row['alto'],
                (float)$row['ancho'],
                (float)$row['profundidad'],
                $row['descripcion'],
                (int)$row['stock'],
                (int)$row['id']
            );
        } else {
            $fechaExpiracion = $row['fecha_expiracion'] ? new \DateTime($row['fecha_expiracion']) : null;
            
            return new ProductoDigital(
                $row['nombre'],
                (float)$row['precio_unitario'],
                (int)$row['id_categoria'],
                $row['url_descarga'],
                $row['licencia'],
                $row['clave_activacion'],
                $fechaExpiracion,
                (int)$row['max_descargas'],
                $row['descripcion'],
                (int)$row['stock'],
                (int)$row['id']
            );
        }
    }
}
