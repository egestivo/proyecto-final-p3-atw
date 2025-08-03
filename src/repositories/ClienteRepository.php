<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Entities\Cliente;
use App\Entities\PersonaNatural;
use App\Entities\PersonaJuridica;
use PDO;
use PDOException;

class ClienteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crear un nuevo cliente (PersonaNatural o PersonaJuridica)
     */
    public function crear(Cliente $cliente): bool
    {
        try {
            $this->db->beginTransaction();

            // Insertar en tabla base clientes
            $stmt = $this->db->prepare("
                INSERT INTO clientes (email, telefono, direccion, tipo_cliente) 
                VALUES (:email, :telefono, :direccion, :tipo_cliente)
            ");

            $stmt->execute([
                ':email' => $cliente->getEmail(),
                ':telefono' => $cliente->getTelefono(),
                ':direccion' => $cliente->getDireccion(),
                ':tipo_cliente' => $cliente->getTipoCliente()
            ]);

            $idCliente = (int)$this->db->lastInsertId();
            $cliente->setId($idCliente);

            // Insertar en tabla específica según el tipo
            if ($cliente instanceof PersonaNatural) {
                $this->insertarPersonaNatural($cliente);
            } elseif ($cliente instanceof PersonaJuridica) {
                $this->insertarPersonaJuridica($cliente);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al crear cliente: ' . $e->getMessage());
        }
    }

    /**
     * Buscar cliente por ID
     */
    public function buscarPorId(int $id): ?Cliente
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_clientes_completa 
            WHERE id = :id AND estado = 1
        ");
        
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaACliente($row);
    }

    /**
     * Buscar cliente por email
     */
    public function buscarPorEmail(string $email): ?Cliente
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_clientes_completa 
            WHERE email = :email AND estado = 1
        ");
        
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaACliente($row);
    }

    /**
     * Buscar cliente por documento (cédula o RUC)
     */
    public function buscarPorDocumento(string $documento): ?Cliente
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_clientes_completa 
            WHERE documento = :documento AND estado = 1
        ");
        
        $stmt->execute([':documento' => $documento]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaACliente($row);
    }

    /**
     * Listar todos los clientes activos
     */
    public function listarTodos(int $limite = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_clientes_completa 
            WHERE estado = 1 
            ORDER BY nombre_completo 
            LIMIT :limite OFFSET :offset
        ");
        
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $clientes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clientes[] = $this->mapearFilaACliente($row);
        }

        return $clientes;
    }

    /**
     * Buscar clientes por nombre/razón social
     */
    public function buscarPorNombre(string $nombre): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_clientes_completa 
            WHERE nombre_completo LIKE :nombre AND estado = 1 
            ORDER BY nombre_completo 
            LIMIT 50
        ");
        
        $stmt->execute([':nombre' => "%$nombre%"]);

        $clientes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clientes[] = $this->mapearFilaACliente($row);
        }

        return $clientes;
    }

    /**
     * Actualizar cliente
     */
    public function actualizar(Cliente $cliente): bool
    {
        try {
            $this->db->beginTransaction();

            // Actualizar tabla base
            $stmt = $this->db->prepare("
                UPDATE clientes 
                SET email = :email, telefono = :telefono, direccion = :direccion 
                WHERE id = :id
            ");

            $stmt->execute([
                ':email' => $cliente->getEmail(),
                ':telefono' => $cliente->getTelefono(),
                ':direccion' => $cliente->getDireccion(),
                ':id' => $cliente->getId()
            ]);

            // Actualizar tabla específica
            if ($cliente instanceof PersonaNatural) {
                $this->actualizarPersonaNatural($cliente);
            } elseif ($cliente instanceof PersonaJuridica) {
                $this->actualizarPersonaJuridica($cliente);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Error al actualizar cliente: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar cliente (soft delete)
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE clientes SET estado = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Contar total de clientes activos
     */
    public function contarTotal(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM clientes WHERE estado = 1");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Verificar si existe cliente con email
     */
    public function existeEmail(string $email, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM clientes WHERE email = :email AND estado = 1";
        $params = [':email' => $email];

        if ($excluirId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verificar si existe cliente con cédula
     */
    public function existeCedula(string $cedula, ?int $excluirId = null): bool
    {
        $sql = "
            SELECT COUNT(*) FROM clientes c 
            INNER JOIN clientes_naturales cn ON c.id = cn.id_cliente 
            WHERE cn.cedula = :cedula AND c.estado = 1
        ";
        $params = [':cedula' => $cedula];

        if ($excluirId) {
            $sql .= " AND c.id != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verificar si existe cliente con RUC
     */
    public function existeRuc(string $ruc, ?int $excluirId = null): bool
    {
        $sql = "
            SELECT COUNT(*) FROM clientes c 
            INNER JOIN clientes_juridicos cj ON c.id = cj.id_cliente 
            WHERE cj.ruc = :ruc AND c.estado = 1
        ";
        $params = [':ruc' => $ruc];

        if ($excluirId) {
            $sql .= " AND c.id != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    // ===== MÉTODOS PRIVADOS =====

    private function insertarPersonaNatural(PersonaNatural $cliente): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clientes_naturales (id_cliente, nombres, apellidos, cedula) 
            VALUES (:id_cliente, :nombres, :apellidos, :cedula)
        ");

        $stmt->execute([
            ':id_cliente' => $cliente->getId(),
            ':nombres' => $cliente->getNombres(),
            ':apellidos' => $cliente->getApellidos(),
            ':cedula' => $cliente->getCedula()
        ]);
    }

    private function insertarPersonaJuridica(PersonaJuridica $cliente): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clientes_juridicos (id_cliente, razon_social, ruc, representante_legal) 
            VALUES (:id_cliente, :razon_social, :ruc, :representante_legal)
        ");

        $stmt->execute([
            ':id_cliente' => $cliente->getId(),
            ':razon_social' => $cliente->getRazonSocial(),
            ':ruc' => $cliente->getRuc(),
            ':representante_legal' => $cliente->getRepresentanteLegal()
        ]);
    }

    private function actualizarPersonaNatural(PersonaNatural $cliente): void
    {
        $stmt = $this->db->prepare("
            UPDATE clientes_naturales 
            SET nombres = :nombres, apellidos = :apellidos, cedula = :cedula 
            WHERE id_cliente = :id_cliente
        ");

        $stmt->execute([
            ':nombres' => $cliente->getNombres(),
            ':apellidos' => $cliente->getApellidos(),
            ':cedula' => $cliente->getCedula(),
            ':id_cliente' => $cliente->getId()
        ]);
    }

    private function actualizarPersonaJuridica(PersonaJuridica $cliente): void
    {
        $stmt = $this->db->prepare("
            UPDATE clientes_juridicos 
            SET razon_social = :razon_social, ruc = :ruc, representante_legal = :representante_legal 
            WHERE id_cliente = :id_cliente
        ");

        $stmt->execute([
            ':razon_social' => $cliente->getRazonSocial(),
            ':ruc' => $cliente->getRuc(),
            ':representante_legal' => $cliente->getRepresentanteLegal(),
            ':id_cliente' => $cliente->getId()
        ]);
    }

    private function mapearFilaACliente(array $row): Cliente
    {
        if ($row['tipo_cliente'] === 'natural') {
            return new PersonaNatural(
                $row['nombres'],
                $row['apellidos'],
                $row['cedula'],
                $row['email'],
                $row['telefono'],
                $row['direccion'],
                $row['id']
            );
        } else {
            return new PersonaJuridica(
                $row['razon_social'],
                $row['ruc'],
                $row['email'],
                $row['representante_legal'],
                $row['telefono'],
                $row['direccion'],
                $row['id']
            );
        }
    }
}
