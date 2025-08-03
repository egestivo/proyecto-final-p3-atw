<?php
declare(strict_types=1);

namespace App\Entities;

abstract class Producto
{
    protected int $id;
    protected string $nombre;
    protected ?string $descripcion;
    protected float $precioUnitario;
    protected int $stock;
    protected int $idCategoria;
    protected string $tipoProducto;

    public function __construct(
        string $nombre,
        float $precioUnitario,
        int $idCategoria,
        ?string $descripcion = null,
        int $stock = 0,
        ?int $id = null
    ) {
        $this->nombre = $nombre;
        $this->precioUnitario = $precioUnitario;
        $this->idCategoria = $idCategoria;
        $this->descripcion = $descripcion;
        $this->stock = $stock;
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

    public function getPrecioUnitario(): float
    {
        return $this->precioUnitario;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getIdCategoria(): int
    {
        return $this->idCategoria;
    }

    public function getTipoProducto(): string
    {
        return $this->tipoProducto;
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

    public function setPrecioUnitario(float $precioUnitario): void
    {
        if ($precioUnitario < 0) {
            throw new \InvalidArgumentException('El precio unitario no puede ser negativo');
        }
        $this->precioUnitario = $precioUnitario;
    }

    public function setStock(int $stock): void
    {
        if ($stock < 0) {
            throw new \InvalidArgumentException('El stock no puede ser negativo');
        }
        $this->stock = $stock;
    }

    public function setIdCategoria(int $idCategoria): void
    {
        $this->idCategoria = $idCategoria;
    }

    // Métodos de negocio
    public function hayStock(int $cantidad): bool
    {
        return $this->stock >= $cantidad;
    }

    public function descontarStock(int $cantidad): void
    {
        if (!$this->hayStock($cantidad)) {
            throw new \InvalidArgumentException('No hay suficiente stock disponible');
        }
        $this->stock -= $cantidad;
    }

    public function aumentarStock(int $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero');
        }
        $this->stock += $cantidad;
    }

    abstract public function validar(): bool;
    abstract public function afectaInventario(): bool;
    abstract public function getInformacionEspecifica(): array;
}
