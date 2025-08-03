<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Entities\Venta;
use App\Entities\DetalleVenta;
use PDO;
use PDOException;

class VentaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crear una nueva venta con sus detalles
     */
    public function crear(Venta $venta): bool
    {
        try {
            $this->db->beginTransaction();

            // Insertar cabecera de venta
            $stmt = $this->db->prepare("
                INSERT INTO ventas (fecha, id_cliente, total, estado) 
                VALUES (:fecha, :id_cliente, :total, :estado)
            ");

            $stmt->execute([
                ':fecha' => $venta->getFecha()->format('Y-m-d H:i:s'),
                ':id_cliente' => $venta->getIdCliente(),
                ':total' => $venta->getTotal(),
                ':estado' => $venta->getEstado()
            ]);

            $idVenta = (int)$this->db->lastInsertId();
            $venta->setId($idVenta);

            // Insertar detalles de venta
            $this->insertarDetalles($venta);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al crear venta: ' . $e->getMessage());
        }
    }

    /**
     * Buscar venta por ID con sus detalles
     */
    public function buscarPorId(int $id): ?Venta
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_ventas_completa 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $venta = $this->mapearFilaAVenta($row);
        
        // Cargar detalles
        $detalles = $this->buscarDetallesPorVenta($id);
        $venta->setDetalles($detalles);

        return $venta;
    }

    /**
     * Listar ventas con filtros
     */
    public function listar(
        ?string $estado = null,
        ?int $idCliente = null,
        ?\DateTime $fechaDesde = null,
        ?\DateTime $fechaHasta = null,
        int $limite = 50,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM v_ventas_completa WHERE 1=1";
        $params = [];

        if ($estado) {
            $sql .= " AND estado = :estado";
            $params[':estado'] = $estado;
        }

        if ($idCliente) {
            $sql .= " AND id_cliente = :id_cliente";
            $params[':id_cliente'] = $idCliente;
        }

        if ($fechaDesde) {
            $sql .= " AND fecha >= :fecha_desde";
            $params[':fecha_desde'] = $fechaDesde->format('Y-m-d H:i:s');
        }

        if ($fechaHasta) {
            $sql .= " AND fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $fechaHasta->format('Y-m-d H:i:s');
        }

        $sql .= " ORDER BY fecha DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $ventas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ventas[] = $this->mapearFilaAVenta($row);
        }

        return $ventas;
    }

    /**
     * Listar ventas de un cliente
     */
    public function listarPorCliente(int $idCliente, int $limite = 20): array
    {
        return $this->listar(null, $idCliente, null, null, $limite);
    }

    /**
     * Listar ventas por fecha
     */
    public function listarPorFecha(\DateTime $fecha): array
    {
        $fechaInicio = clone $fecha;
        $fechaInicio->setTime(0, 0, 0);
        
        $fechaFin = clone $fecha;
        $fechaFin->setTime(23, 59, 59);

        return $this->listar(null, null, $fechaInicio, $fechaFin);
    }

    /**
     * Actualizar venta
     */
    public function actualizar(Venta $venta): bool
    {
        try {
            $this->db->beginTransaction();

            // Actualizar cabecera
            $stmt = $this->db->prepare("
                UPDATE ventas 
                SET fecha = :fecha, id_cliente = :id_cliente, total = :total, estado = :estado 
                WHERE id = :id
            ");

            $stmt->execute([
                ':fecha' => $venta->getFecha()->format('Y-m-d H:i:s'),
                ':id_cliente' => $venta->getIdCliente(),
                ':total' => $venta->getTotal(),
                ':estado' => $venta->getEstado(),
                ':id' => $venta->getId()
            ]);

            // Eliminar detalles existentes y insertar nuevos
            $this->eliminarDetalles($venta->getId());
            $this->insertarDetalles($venta);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al actualizar venta: ' . $e->getMessage());
        }
    }

    /**
     * Emitir venta (cambiar estado y validar stock)
     */
    public function emitir(int $idVenta): bool
    {
        try {
            $this->db->beginTransaction();

            $venta = $this->buscarPorId($idVenta);
            if (!$venta) {
                throw new \RuntimeException('Venta no encontrada');
            }

            if ($venta->getEstado() !== 'borrador') {
                throw new \RuntimeException('Solo se pueden emitir ventas en borrador');
            }

            // Validar y descontar stock para cada detalle
            foreach ($venta->getDetalles() as $detalle) {
                $productoRepo = new ProductoRepository();
                $stockValidation = $productoRepo->validarStock($detalle->getIdProducto(), $detalle->getCantidad());
                
                if (!$stockValidation['valido']) {
                    throw new \RuntimeException("Stock insuficiente para el producto ID: {$detalle->getIdProducto()}");
                }

                // Descontar stock
                if (!$productoRepo->descontarStock($detalle->getIdProducto(), $detalle->getCantidad())) {
                    throw new \RuntimeException("Error al descontar stock para el producto ID: {$detalle->getIdProducto()}");
                }
            }

            // Actualizar estado de la venta
            $stmt = $this->db->prepare("UPDATE ventas SET estado = 'emitida', fecha = NOW() WHERE id = :id");
            $stmt->execute([':id' => $idVenta]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al emitir venta: ' . $e->getMessage());
        }
    }

    /**
     * Anular venta (devolver stock)
     */
    public function anular(int $idVenta): bool
    {
        try {
            $this->db->beginTransaction();

            $venta = $this->buscarPorId($idVenta);
            if (!$venta) {
                throw new \RuntimeException('Venta no encontrada');
            }

            if ($venta->getEstado() !== 'emitida') {
                throw new \RuntimeException('Solo se pueden anular ventas emitidas');
            }

            // Devolver stock usando procedimiento almacenado
            $stmt = $this->db->prepare("CALL sp_devolver_stock(:id_venta)");
            $stmt->execute([':id_venta' => $idVenta]);

            // Actualizar estado de la venta
            $stmt = $this->db->prepare("UPDATE ventas SET estado = 'anulada' WHERE id = :id");
            $stmt->execute([':id' => $idVenta]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al anular venta: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar venta (solo borradores)
     */
    public function eliminar(int $id): bool
    {
        $venta = $this->buscarPorId($id);
        if (!$venta) {
            throw new \RuntimeException('Venta no encontrada');
        }

        if ($venta->getEstado() !== 'borrador') {
            throw new \RuntimeException('Solo se pueden eliminar ventas en borrador');
        }

        try {
            $this->db->beginTransaction();

            // Eliminar detalles
            $this->eliminarDetalles($id);

            // Eliminar venta
            $stmt = $this->db->prepare("DELETE FROM ventas WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al eliminar venta: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de ventas
     */
    public function obtenerEstadisticas(\DateTime $fechaDesde, \DateTime $fechaHasta): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_ventas,
                SUM(CASE WHEN estado = 'emitida' THEN 1 ELSE 0 END) as ventas_emitidas,
                SUM(CASE WHEN estado = 'emitida' THEN total ELSE 0 END) as total_facturado,
                AVG(CASE WHEN estado = 'emitida' THEN total ELSE NULL END) as promedio_venta,
                MAX(CASE WHEN estado = 'emitida' THEN total ELSE NULL END) as venta_maxima,
                MIN(CASE WHEN estado = 'emitida' THEN total ELSE NULL END) as venta_minima
            FROM ventas 
            WHERE fecha BETWEEN :fecha_desde AND :fecha_hasta
        ");

        $stmt->execute([
            ':fecha_desde' => $fechaDesde->format('Y-m-d H:i:s'),
            ':fecha_hasta' => $fechaHasta->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar detalles de una venta
     */
    public function buscarDetallesPorVenta(int $idVenta): array
    {
        $stmt = $this->db->prepare("
            SELECT dv.*, p.nombre as producto_nombre 
            FROM detalle_ventas dv
            INNER JOIN productos p ON dv.id_producto = p.id
            WHERE dv.id_venta = :id_venta 
            ORDER BY dv.line_number
        ");
        
        $stmt->execute([':id_venta' => $idVenta]);

        $detalles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $detalles[] = new DetalleVenta(
                (int)$row['id_venta'],
                (int)$row['line_number'],
                (int)$row['id_producto'],
                (int)$row['cantidad'],
                (float)$row['precio_unitario']
            );
        }

        return $detalles;
    }

    // ===== MÉTODOS PRIVADOS =====

    private function insertarDetalles(Venta $venta): void
    {
        if (empty($venta->getDetalles())) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO detalle_ventas (id_venta, line_number, id_producto, cantidad, precio_unitario, subtotal) 
            VALUES (:id_venta, :line_number, :id_producto, :cantidad, :precio_unitario, :subtotal)
        ");

        foreach ($venta->getDetalles() as $detalle) {
            $stmt->execute([
                ':id_venta' => $venta->getId(),
                ':line_number' => $detalle->getLineNumber(),
                ':id_producto' => $detalle->getIdProducto(),
                ':cantidad' => $detalle->getCantidad(),
                ':precio_unitario' => $detalle->getPrecioUnitario(),
                ':subtotal' => $detalle->getSubtotal()
            ]);
        }
    }

    private function eliminarDetalles(int $idVenta): void
    {
        $stmt = $this->db->prepare("DELETE FROM detalle_ventas WHERE id_venta = :id_venta");
        $stmt->execute([':id_venta' => $idVenta]);
    }

    private function mapearFilaAVenta(array $row): Venta
    {
        return new Venta(
            (int)$row['id_cliente'],
            new \DateTime($row['fecha']),
            (float)$row['total'],
            $row['estado'],
            (int)$row['id']
        );
    }
}
