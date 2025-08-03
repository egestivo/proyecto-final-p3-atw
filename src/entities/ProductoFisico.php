<?php
declare(strict_types=1);

namespace App\Entities;

class ProductoFisico extends Producto
{
    private float $peso; 
    private float $alto; 
    private float $ancho;
    private float $profundidad;

    public function __construct(
        string $nombre,
        float $precioUnitario,
        int $idCategoria,
        float $peso,
        float $alto,
        float $ancho,
        float $profundidad,
        ?string $descripcion = null,
        int $stock = 0,
        ?int $id = null
    ) {
        parent::__construct($nombre, $precioUnitario, $idCategoria, $descripcion, $stock, $id);
        $this->peso = $peso;
        $this->alto = $alto;
        $this->ancho = $ancho;
        $this->profundidad = $profundidad;
        $this->tipoProducto = 'fisico';
    }

    // Getters
    public function getPeso(): float
    {
        return $this->peso;
    }

    public function getAlto(): float
    {
        return $this->alto;
    }

    public function getAncho(): float
    {
        return $this->ancho;
    }

    public function getProfundidad(): float
    {
        return $this->profundidad;
    }

    // Setters
    public function setPeso(float $peso): void
    {
        if ($peso <= 0) {
            throw new \InvalidArgumentException('El peso debe ser mayor a cero');
        }
        $this->peso = $peso;
    }

    public function setAlto(float $alto): void
    {
        if ($alto <= 0) {
            throw new \InvalidArgumentException('El alto debe ser mayor a cero');
        }
        $this->alto = $alto;
    }

    public function setAncho(float $ancho): void
    {
        if ($ancho <= 0) {
            throw new \InvalidArgumentException('El ancho debe ser mayor a cero');
        }
        $this->ancho = $ancho;
    }

    public function setProfundidad(float $profundidad): void
    {
        if ($profundidad <= 0) {
            throw new \InvalidArgumentException('La profundidad debe ser mayor a cero');
        }
        $this->profundidad = $profundidad;
    }

    // Métodos de negocio específicos
    public function getVolumen(): float
    {
        return $this->alto * $this->ancho * $this->profundidad;
    }

    public function getPesoVolumetrico(float $factorConversion = 167.0): float
    {
        // Factor de conversión estándar para envíos (kg/m³)
        return $this->getVolumen() / $factorConversion;
    }

    public function getPesoFacturable(): float
    {
        // El mayor entre el peso real y el peso volumétrico
        return max($this->peso, $this->getPesoVolumetrico());
    }

    // Implementación de métodos abstractos
    public function validar(): bool
    {
        // Validar campos básicos
        if (empty(trim($this->nombre))) {
            return false;
        }

        if ($this->precioUnitario < 0) {
            return false;
        }

        if ($this->stock < 0) {
            return false;
        }

        // Validar dimensiones físicas
        if ($this->peso <= 0 || $this->alto <= 0 || $this->ancho <= 0 || $this->profundidad <= 0) {
            return false;
        }

        return true;
    }

    public function afectaInventario(): bool
    {
        // Los productos físicos siempre afectan el inventario
        return true;
    }

    public function getInformacionEspecifica(): array
    {
        return [
            'tipo' => 'fisico',
            'peso' => $this->peso,
            'dimensiones' => [
                'alto' => $this->alto,
                'ancho' => $this->ancho,
                'profundidad' => $this->profundidad
            ],
            'volumen' => $this->getVolumen(),
            'peso_volumetrico' => $this->getPesoVolumetrico(),
            'peso_facturable' => $this->getPesoFacturable()
        ];
    }

    // Método para determinar si el producto es frágil (basado en criterios)
    public function esFragil(): bool
    {
        // Criterio ejemplo: productos con volumen mayor a 1000 cm³ y peso menor a 1kg
        return $this->getVolumen() > 1000 && $this->peso < 1.0;
    }

    // Método para calcular costo de envío estimado
    public function calcularCostoEnvio(float $tarifaPorKg = 2.50): float
    {
        return $this->getPesoFacturable() * $tarifaPorKg;
    }
}
