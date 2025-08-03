<?php
declare(strict_types=1);

namespace App\Entities;

class DetalleVenta
{
    private int $idVenta;
    private int $lineNumber;
    private int $idProducto;
    private int $cantidad;
    private float $precioUnitario;
    private float $subtotal;

    public function __construct(
        int $idVenta,
        int $lineNumber,
        int $idProducto,
        int $cantidad,
        float $precioUnitario
    ) {
        $this->idVenta = $idVenta;
        $this->lineNumber = $lineNumber;
        $this->idProducto = $idProducto;
        $this->cantidad = $cantidad;
        $this->precioUnitario = $precioUnitario;
        $this->calcularSubtotal();
    }

    // Getters
    public function getIdVenta(): int
    {
        return $this->idVenta;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getIdProducto(): int
    {
        return $this->idProducto;
    }

    public function getCantidad(): int
    {
        return $this->cantidad;
    }

    public function getPrecioUnitario(): float
    {
        return $this->precioUnitario;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    // Setters
    public function setIdVenta(int $idVenta): void
    {
        $this->idVenta = $idVenta;
    }

    public function setLineNumber(int $lineNumber): void
    {
        if ($lineNumber <= 0) {
            throw new \InvalidArgumentException('El número de línea debe ser mayor a cero');
        }
        $this->lineNumber = $lineNumber;
    }

    public function setIdProducto(int $idProducto): void
    {
        if ($idProducto <= 0) {
            throw new \InvalidArgumentException('El ID del producto debe ser mayor a cero');
        }
        $this->idProducto = $idProducto;
    }

    public function setCantidad(int $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero');
        }
        $this->cantidad = $cantidad;
        $this->calcularSubtotal();
    }

    public function setPrecioUnitario(float $precioUnitario): void
    {
        if ($precioUnitario < 0) {
            throw new \InvalidArgumentException('El precio unitario no puede ser negativo');
        }
        $this->precioUnitario = $precioUnitario;
        $this->calcularSubtotal();
    }

    // Métodos de negocio
    private function calcularSubtotal(): void
    {
        $this->subtotal = $this->cantidad * $this->precioUnitario;
    }

    public function recalcularSubtotal(): void
    {
        $this->calcularSubtotal();
    }

    public function aplicarDescuento(float $porcentajeDescuento): void
    {
        if ($porcentajeDescuento < 0 || $porcentajeDescuento > 100) {
            throw new \InvalidArgumentException('El porcentaje de descuento debe estar entre 0 y 100');
        }
        
        $descuento = ($this->precioUnitario * $porcentajeDescuento) / 100;
        $this->precioUnitario -= $descuento;
        $this->calcularSubtotal();
    }

    public function aplicarDescuentoMonto(float $montoDescuento): void
    {
        if ($montoDescuento < 0) {
            throw new \InvalidArgumentException('El monto de descuento no puede ser negativo');
        }
        
        if ($montoDescuento > $this->precioUnitario) {
            throw new \InvalidArgumentException('El descuento no puede ser mayor al precio unitario');
        }
        
        $this->precioUnitario -= $montoDescuento;
        $this->calcularSubtotal();
    }

    public function getValorImpuesto(float $porcentajeImpuesto = 12.0): float
    {
        return ($this->subtotal * $porcentajeImpuesto) / 100;
    }

    public function getSubtotalConImpuesto(float $porcentajeImpuesto = 12.0): float
    {
        return $this->subtotal + $this->getValorImpuesto($porcentajeImpuesto);
    }

    public function duplicar(int $nuevaLinea): DetalleVenta
    {
        return new DetalleVenta(
            $this->idVenta,
            $nuevaLinea,
            $this->idProducto,
            $this->cantidad,
            $this->precioUnitario
        );
    }

    public function validar(): bool
    {
        // Validar IDs positivos
        if ($this->idVenta <= 0 || $this->idProducto <= 0) {
            return false;
        }

        // Validar número de línea positivo
        if ($this->lineNumber <= 0) {
            return false;
        }

        // Validar cantidad positiva
        if ($this->cantidad <= 0) {
            return false;
        }

        // Validar precio unitario no negativo
        if ($this->precioUnitario < 0) {
            return false;
        }

        // Verificar que el subtotal sea correcto
        $subtotalCalculado = $this->cantidad * $this->precioUnitario;
        if (abs($this->subtotal - $subtotalCalculado) > 0.01) { // Margen de error por flotantes
            return false;
        }

        return true;
    }

    public function esIgualA(DetalleVenta $otro): bool
    {
        return $this->idProducto === $otro->idProducto &&
               $this->cantidad === $otro->cantidad &&
               abs($this->precioUnitario - $otro->precioUnitario) < 0.01;
    }

    public function toArray(): array
    {
        return [
            'id_venta' => $this->idVenta,
            'line_number' => $this->lineNumber,
            'id_producto' => $this->idProducto,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precioUnitario,
            'subtotal' => $this->subtotal
        ];
    }
}
