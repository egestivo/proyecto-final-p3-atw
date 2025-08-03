<?php
declare(strict_types=1);

namespace App\Entities;

class Categoria
{
    private int $id;
    private string $nombre;
    private ?string $descripcion;
    private bool $estado;
    private ?int $idPadre;

    public function __construct(
        string $nombre,
        ?string $descripcion = null,
        bool $estado = true,
        ?int $idPadre = null,
        ?int $id = null
    ) {
        $this->nombre = $nombre;
        $this->descripcion = $descripcion;
        $this->estado = $estado;
        $this->idPadre = $idPadre;
        if ($id !== null) {
            $this->id = $id;
        }
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function getEstado(): bool
    {
        return $this->estado;
    }

    public function getIdPadre(): ?int
    {
        return $this->idPadre;
    }

    public function estaActiva(): bool
    {
        return $this->estado;
    }

    public function esSubcategoria(): bool
    {
        return $this->idPadre !== null;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function setDescripcion(?string $descripcion): void
    {
        $this->descripcion = $descripcion;
    }

    public function setEstado(bool $estado): void
    {
        $this->estado = $estado;
    }

    public function setIdPadre(?int $idPadre): void
    {
        $this->idPadre = $idPadre;
    }

    // Métodos de negocio
    public function activar(): void
    {
        $this->estado = true;
    }

    public function desactivar(): void
    {
        $this->estado = false;
    }

    public function validar(): bool
    {
        // El nombre no puede estar vacío
        if (empty(trim($this->nombre))) {
            return false;
        }

        // Si tiene padre, el id padre debe ser válido (mayor a 0)
        if ($this->idPadre !== null && $this->idPadre <= 0) {
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'id_padre' => $this->idPadre,
            'es_subcategoria' => $this->esSubcategoria()
        ];
    }
}
