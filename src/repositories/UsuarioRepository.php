<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Entities\Usuario;
use PDO;
use PDOException;

class UsuarioRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crear un nuevo usuario
     */
    public function crear(Usuario $usuario): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (email, password_hash, nombres, apellidos, rol, estado) 
                VALUES (:email, :password_hash, :nombres, :apellidos, :rol, :estado)
            ");

            $stmt->execute([
                ':email' => $usuario->getEmail(),
                ':password_hash' => $usuario->getPasswordHash(),
                ':nombres' => $usuario->getNombres(),
                ':apellidos' => $usuario->getApellidos(),
                ':rol' => $usuario->getRol(),
                ':estado' => $usuario->getEstado()
            ]);

            $usuario->setId((int)$this->db->lastInsertId());
            return true;

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al crear usuario: ' . $e->getMessage());
        }
    }

    /**
     * Buscar usuario por ID
     */
    public function buscarPorId(int $id): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id AND estado != 'eliminado'");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAUsuario($row);
    }

    /**
     * Buscar usuario por email
     */
    public function buscarPorEmail(string $email): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email AND estado != 'eliminado'");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapearFilaAUsuario($row);
    }

    /**
     * Listar usuarios con filtros
     */
    public function listar(?string $rol = null, ?string $estado = null, int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM usuarios WHERE estado != 'eliminado'";
        $params = [];

        if ($rol) {
            $sql .= " AND rol = :rol";
            $params[':rol'] = $rol;
        }

        if ($estado) {
            $sql .= " AND estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY nombres, apellidos LIMIT :limite OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $usuarios = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usuarios[] = $this->mapearFilaAUsuario($row);
        }

        return $usuarios;
    }

    /**
     * Actualizar usuario
     */
    public function actualizar(Usuario $usuario): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE usuarios 
                SET email = :email, nombres = :nombres, apellidos = :apellidos, 
                    rol = :rol, estado = :estado 
                WHERE id = :id
            ");

            return $stmt->execute([
                ':email' => $usuario->getEmail(),
                ':nombres' => $usuario->getNombres(),
                ':apellidos' => $usuario->getApellidos(),
                ':rol' => $usuario->getRol(),
                ':estado' => $usuario->getEstado(),
                ':id' => $usuario->getId()
            ]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar contraseña de un usuario
     */
    public function actualizarPassword(int $id, string $passwordHash): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET password_hash = :password_hash WHERE id = :id");
            return $stmt->execute([
                ':password_hash' => $passwordHash,
                ':id' => $id
            ]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al actualizar contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar último acceso
     */
    public function actualizarUltimoAcceso(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
            return $stmt->execute([':id' => $id]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al actualizar último acceso: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function eliminar(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET estado = 'eliminado' WHERE id = :id");
            return $stmt->execute([':id' => $id]);

        } catch (PDOException $e) {
            throw new \RuntimeException('Error al eliminar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Verificar si existe un email (excluyendo un ID específico)
     */
    public function existeEmail(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email AND estado != 'eliminado'";
        $params = [':email' => $email];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Contar total de usuarios
     */
    public function contarTotal(?string $rol = null, ?string $estado = null): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE estado != 'eliminado'";
        $params = [];

        if ($rol) {
            $sql .= " AND rol = :rol";
            $params[':rol'] = $rol;
        }

        if ($estado) {
            $sql .= " AND estado = :estado";
            $params[':estado'] = $estado;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function obtenerEstadisticas(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_usuarios,
                SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as usuarios_activos,
                SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as usuarios_inactivos,
                SUM(CASE WHEN rol = 'administrador' THEN 1 ELSE 0 END) as administradores,
                SUM(CASE WHEN rol = 'vendedor' THEN 1 ELSE 0 END) as vendedores,
                COUNT(CASE WHEN ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as activos_ultimo_mes
            FROM usuarios 
            WHERE estado != 'eliminado'
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ===== MÉTODOS PRIVADOS =====

    private function mapearFilaAUsuario(array $row): Usuario
    {
        $usuario = new Usuario(
            $row['email'],
            $row['password_hash'],
            $row['nombres'],
            $row['apellidos'],
            $row['rol'],
            $row['estado']
        );

        $usuario->setId((int)$row['id']);
        
        if ($row['ultimo_acceso']) {
            $usuario->setUltimoAcceso(new \DateTime($row['ultimo_acceso']));
        }

        return $usuario;
    }
}
