<?php
declare(strict_types=1);

namespace App\Entities;

abstract class Cliente
{
    protected int $id;
    protected string $email;
    protected ?string $telefono;
    protected ?string $direccion;
    protected string $tipoCliente;

    public function __construct(
        string $email,
        ?string $telefono = null,
        ?string $direccion = null,
        ?int $id = null
    ) {
        $this->email = $email;
        $this->telefono = $telefono;
        $this->direccion = $direccion;
        if ($id !== null) {
            $this->id = $id;
        }
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

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function getTipoCliente(): string
    {
        return $this->tipoCliente;
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

    public function setTelefono(?string $telefono): void
    {
        $this->telefono = $telefono;
    }

    public function setDireccion(?string $direccion): void
    {
        $this->direccion = $direccion;
    }

    // Método abstracto para validación específica
    abstract public function validar(): bool;

    // Método abstracto para obtener nombre/identificación
    abstract public function getNombreCompleto(): string;

    // Método para validar email
    protected function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
