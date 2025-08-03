<?php
declare(strict_types=1);

namespace App\Entities;

class Usuario
{
    private int $id;
    private string $email;
    private string $passwordHash;
    private string $nombres;
    private string $apellidos;
    private string $rol;
    private string $estado;
    private ?\DateTime $ultimoAcceso;

    public function __construct(
        string $email,
        string $passwordHash,
        string $nombres,
        string $apellidos,
        string $rol,
        string $estado = 'activo'
    ) {
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->rol = $rol;
        $this->estado = $estado;
        $this->ultimoAcceso = null;
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getNombres(): string
    {
        return $this->nombres;
    }

    public function getApellidos(): string
    {
        return $this->apellidos;
    }

    public function getRol(): string
    {
        return $this->rol;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getUltimoAcceso(): ?\DateTime
    {
        return $this->ultimoAcceso;
    }

    public function getNombreCompleto(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function setNombres(string $nombres): void
    {
        $this->nombres = $nombres;
    }

    public function setApellidos(string $apellidos): void
    {
        $this->apellidos = $apellidos;
    }

    public function setRol(string $rol): void
    {
        $this->rol = $rol;
    }

    public function setEstado(string $estado): void
    {
        $this->estado = $estado;
    }

    public function setUltimoAcceso(?\DateTime $ultimoAcceso): void
    {
        $this->ultimoAcceso = $ultimoAcceso;
    }

    // Métodos de utilidad
    public function verificarPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function cambiarPassword(string $nuevoPassword): void
    {
        $this->passwordHash = password_hash($nuevoPassword, PASSWORD_ARGON2ID);
    }

    public function registrarAcceso(): void
    {
        $this->ultimoAcceso = new \DateTime();
    }

    public function esAdministrador(): bool
    {
        return $this->rol === 'administrador';
    }

    public function esVendedor(): bool
    {
        return $this->rol === 'vendedor';
    }

    // Validación
    public function validar(): bool
    {
        // Validar email
        if (empty(trim($this->email)) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Validar nombres
        if (empty(trim($this->nombres)) || strlen(trim($this->nombres)) < 2) {
            return false;
        }

        // Validar apellidos
        if (empty(trim($this->apellidos)) || strlen(trim($this->apellidos)) < 2) {
            return false;
        }

        // Validar rol
        if (!in_array($this->rol, ['administrador', 'vendedor'])) {
            return false;
        }

        // Validar estado
        if (!in_array($this->estado, ['activo', 'inactivo', 'eliminado'])) {
            return false;
        }

        // Validar password hash
        if (empty($this->passwordHash)) {
            return false;
        }

        return true;
    }

    // Serialización
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->getNombreCompleto(),
            'rol' => $this->rol,
            'estado' => $this->estado,
            'ultimo_acceso' => $this->ultimoAcceso ? $this->ultimoAcceso->format('Y-m-d H:i:s') : null,
            'es_activo' => $this->estaActivo(),
            'es_administrador' => $this->esAdministrador(),
            'es_vendedor' => $this->esVendedor()
        ];
    }

    public function __toString(): string
    {
        return $this->getNombreCompleto() . " ({$this->email})";
    }
}
