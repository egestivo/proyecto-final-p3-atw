<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Entities\Factura;
use PDO;
use PDOException;

class FacturaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crear una nueva factura
     */
    public function crear(Factura $factura): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO facturas (id_venta, numero, clave_acceso, fecha_emision, estado) 
                VALUES (:id_venta, :numero, :clave_acceso, :fecha_emision, :estado)
            ");

            $stmt->execute([
                ':id_venta' => $factura->getIdVenta(),
                ':numero' => $factura->getNumero(),
                ':clave_acceso' => $factura->getClaveAcceso(),
                ':fecha_emision' => $factura->getFechaEmision()->format('Y-m-d H:i:s'),
                ':estado' => $factura->getEstado()
            ]);

            $factura->setId((int)$this->db->lastInsertId());
            return true;

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al crear factura: ' . $e->getMessage());
        }
    }

    /**
     * Buscar factura por ID
     */
    public function buscarPorId(int $id): ?Factura
    {
        $stmt = $this->db->prepare("
            SELECT * FROM facturas 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAFactura($row);
    }

    /**
     * Buscar factura por número
     */
    public function buscarPorNumero(string $numero): ?Factura
    {
        $stmt = $this->db->prepare("
            SELECT * FROM facturas 
            WHERE numero = :numero
        ");
        
        $stmt->execute([':numero' => $numero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAFactura($row);
    }

    /**
     * Buscar factura por clave de acceso
     */
    public function buscarPorClaveAcceso(string $claveAcceso): ?Factura
    {
        $stmt = $this->db->prepare("
            SELECT * FROM facturas 
            WHERE clave_acceso = :clave_acceso
        ");
        
        $stmt->execute([':clave_acceso' => $claveAcceso]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAFactura($row);
    }

    /**
     * Buscar factura por venta
     */
    public function buscarPorVenta(int $idVenta): ?Factura
    {
        $stmt = $this->db->prepare("
            SELECT * FROM facturas 
            WHERE id_venta = :id_venta
        ");
        
        $stmt->execute([':id_venta' => $idVenta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAFactura($row);
    }

    /**
     * Listar facturas con filtros
     */
    public function listar(
        ?string $estado = null,
        ?\DateTime $fechaDesde = null,
        ?\DateTime $fechaHasta = null,
        int $limite = 50,
        int $offset = 0
    ): array {
        $sql = "
            SELECT f.*, v.id_cliente, vc.cliente_nombre, vc.cliente_documento
            FROM facturas f
            INNER JOIN ventas v ON f.id_venta = v.id
            INNER JOIN v_ventas_completa vc ON v.id = vc.id
            WHERE 1=1
        ";
        $params = [];

        if ($estado) {
            $sql .= " AND f.estado = :estado";
            $params[':estado'] = $estado;
        }

        if ($fechaDesde) {
            $sql .= " AND f.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $fechaDesde->format('Y-m-d H:i:s');
        }

        if ($fechaHasta) {
            $sql .= " AND f.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $fechaHasta->format('Y-m-d H:i:s');
        }

        $sql .= " ORDER BY f.fecha_emision DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $facturas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $facturas[] = $this->mapearFilaAFactura($row);
        }

        return $facturas;
    }

    /**
     * Listar facturas por fecha
     */
    public function listarPorFecha(\DateTime $fecha): array
    {
        $fechaInicio = clone $fecha;
        $fechaInicio->setTime(0, 0, 0);
        
        $fechaFin = clone $fecha;
        $fechaFin->setTime(23, 59, 59);

        return $this->listar(null, $fechaInicio, $fechaFin);
    }

    /**
     * Actualizar factura
     */
    public function actualizar(Factura $factura): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE facturas 
                SET id_venta = :id_venta, numero = :numero, clave_acceso = :clave_acceso,
                    fecha_emision = :fecha_emision, estado = :estado, xml_autorizado = :xml_autorizado,
                    url_pdf = :url_pdf
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id_venta' => $factura->getIdVenta(),
                ':numero' => $factura->getNumero(),
                ':clave_acceso' => $factura->getClaveAcceso(),
                ':fecha_emision' => $factura->getFechaEmision()->format('Y-m-d H:i:s'),
                ':estado' => $factura->getEstado(),
                ':xml_autorizado' => $factura->getXmlAutorizado(),
                ':url_pdf' => $factura->getUrlPdf(),
                ':id' => $factura->getId()
            ]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al actualizar factura: ' . $e->getMessage());
        }
    }

    /**
     * Emitir factura (generar clave de acceso)
     */
    public function emitir(int $idFactura): bool
    {
        try {
            $this->db->beginTransaction();

            $factura = $this->buscarPorId($idFactura);
            if (!$factura) {
                throw new \RuntimeException('Factura no encontrada');
            }

            if ($factura->getEstado() !== 'pendiente') {
                throw new \RuntimeException('Solo se pueden emitir facturas pendientes');
            }

            // Generar clave de acceso si no existe
            if (!$factura->getClaveAcceso()) {
                $factura->generarClaveAcceso();
            }

            $factura->emitir();

            // Actualizar en base de datos
            $stmt = $this->db->prepare("
                UPDATE facturas 
                SET clave_acceso = :clave_acceso, estado = :estado, fecha_emision = :fecha_emision
                WHERE id = :id
            ");

            $stmt->execute([
                ':clave_acceso' => $factura->getClaveAcceso(),
                ':estado' => $factura->getEstado(),
                ':fecha_emision' => $factura->getFechaEmision()->format('Y-m-d H:i:s'),
                ':id' => $factura->getId()
            ]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al emitir factura: ' . $e->getMessage());
        }
    }

    /**
     * Autorizar factura (guardar XML del SRI)
     */
    public function autorizar(int $idFactura, string $xmlAutorizado): bool
    {
        try {
            $factura = $this->buscarPorId($idFactura);
            if (!$factura) {
                throw new \RuntimeException('Factura no encontrada');
            }

            if ($factura->getEstado() !== 'emitida') {
                throw new \RuntimeException('Solo se pueden autorizar facturas emitidas');
            }

            $stmt = $this->db->prepare("
                UPDATE facturas 
                SET xml_autorizado = :xml_autorizado, estado = 'autorizada'
                WHERE id = :id
            ");

            return $stmt->execute([
                ':xml_autorizado' => $xmlAutorizado,
                ':id' => $idFactura
            ]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al autorizar factura: ' . $e->getMessage());
        }
    }

    /**
     * Anular factura
     */
    public function anular(int $idFactura): bool
    {
        try {
            $factura = $this->buscarPorId($idFactura);
            if (!$factura) {
                throw new \RuntimeException('Factura no encontrada');
            }

            if ($factura->getEstado() === 'anulada') {
                throw new \RuntimeException('La factura ya está anulada');
            }

            $stmt = $this->db->prepare("UPDATE facturas SET estado = 'anulada' WHERE id = :id");
            return $stmt->execute([':id' => $idFactura]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al anular factura: ' . $e->getMessage());
        }
    }

    /**
     * Generar siguiente número de factura
     */
    public function generarSiguienteNumero(): string
    {
        $stmt = $this->db->query("
            SELECT numero FROM facturas 
            WHERE numero REGEXP '^[0-9]{3}-[0-9]{3}-[0-9]{9}$'
            ORDER BY id DESC 
            LIMIT 1
        ");
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Primera factura
            return '001-001-000000001';
        }
        
        $ultimoNumero = $row['numero'];
        $partes = explode('-', $ultimoNumero);
        
        $establecimiento = $partes[0];
        $puntoEmision = $partes[1];
        $secuencial = (int)$partes[2] + 1;
        
        return sprintf('%s-%s-%09d', $establecimiento, $puntoEmision, $secuencial);
    }

    /**
     * Verificar si existe número de factura
     */
    public function existeNumero(string $numero, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM facturas WHERE numero = :numero";
        $params = [':numero' => $numero];

        if ($excluirId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Obtener estadísticas de facturación
     */
    public function obtenerEstadisticas(\DateTime $fechaDesde, \DateTime $fechaHasta): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_facturas,
                SUM(CASE WHEN f.estado = 'autorizada' THEN 1 ELSE 0 END) as facturas_autorizadas,
                SUM(CASE WHEN f.estado = 'autorizada' THEN v.total ELSE 0 END) as total_facturado,
                AVG(CASE WHEN f.estado = 'autorizada' THEN v.total ELSE NULL END) as promedio_factura,
                MAX(CASE WHEN f.estado = 'autorizada' THEN v.total ELSE NULL END) as factura_maxima,
                MIN(CASE WHEN f.estado = 'autorizada' THEN v.total ELSE NULL END) as factura_minima
            FROM facturas f
            INNER JOIN ventas v ON f.id_venta = v.id
            WHERE f.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
        ");

        $stmt->execute([
            ':fecha_desde' => $fechaDesde->format('Y-m-d H:i:s'),
            ':fecha_hasta' => $fechaHasta->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Marcar PDF generado
     */
    public function marcarPdfGenerado(int $idFactura, string $urlPdf): bool
    {
        $stmt = $this->db->prepare("UPDATE facturas SET url_pdf = :url_pdf WHERE id = :id");
        return $stmt->execute([
            ':url_pdf' => $urlPdf,
            ':id' => $idFactura
        ]);
    }

    private function mapearFilaAFactura(array $row): Factura
    {
        return new Factura(
            (int)$row['id_venta'],
            $row['numero'],
            $row['clave_acceso'],
            new \DateTime($row['fecha_emision']),
            $row['estado'],
            (int)$row['id']
        );
    }
}
