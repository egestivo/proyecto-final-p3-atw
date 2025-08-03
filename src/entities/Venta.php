<?php
declare(strict_types=1);

namespace App\Entities;

class Venta
{
    private int $id;
    private \DateTime $fecha;
    private int $idCliente;
    private float $total;
    private string $estado; // 'borrador', 'emitida', 'anulada'
    private array $detalles; // Array de DetalleVenta

    public function __construct(
        int $idCliente,
        ?\DateTime $fecha = null,
        float $total = 0.0,
        string $estado = 'borrador',
        ?int $id = null
    ) {
        $this->idCliente = $idCliente;
        $this->fecha = $fecha ?? new \DateTime();
        $this->total = $total;
        $this->estado = $estado;
        $this->detalles = [];
        if ($id !== null) {
            $this->id = $id;
        }
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getFecha(): \DateTime
    {
        return $this->fecha;
    }

    public function getIdCliente(): int
    {
        return $this->idCliente;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getDetalles(): array
    {
        return $this->detalles;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setFecha(\DateTime $fecha): void
    {
        $this->fecha = $fecha;
    }

    public function setIdCliente(int $idCliente): void
    {
        if ($this->estado !== 'borrador') {
            throw new \RuntimeException('No se puede modificar el cliente de una venta que no está en borrador');
        }
        $this->idCliente = $idCliente;
    }

    public function setTotal(float $total): void
    {
        if ($total < 0) {
            throw new \InvalidArgumentException('El total no puede ser negativo');
        }
        $this->total = $total;
    }

    public function setEstado(string $estado): void
    {
        $estadosValidos = ['borrador', 'emitida', 'anulada'];
        if (!in_array($estado, $estadosValidos)) {
            throw new \InvalidArgumentException('Estado de venta no válido');
        }
        $this->estado = $estado;
    }

    public function setDetalles(array $detalles): void
    {
        $this->detalles = $detalles;
    }

    // Métodos de negocio
    public function agregarDetalle(DetalleVenta $detalle): void
    {
        if ($this->estado !== 'borrador') {
            throw new \RuntimeException('No se pueden agregar detalles a una venta que no está en borrador');
        }
        $this->detalles[] = $detalle;
        $this->recalcularTotal();
    }

    public function eliminarDetalle(int $lineNumber): void
    {
        if ($this->estado !== 'borrador') {
            throw new \RuntimeException('No se pueden eliminar detalles de una venta que no está en borrador');
        }
        
        foreach ($this->detalles as $index => $detalle) {
            if ($detalle->getLineNumber() === $lineNumber) {
                unset($this->detalles[$index]);
                $this->detalles = array_values($this->detalles); // Reindexar array
                break;
            }
        }
        $this->recalcularTotal();
    }

    public function recalcularTotal(): void
    {
        $total = 0.0;
        foreach ($this->detalles as $detalle) {
            $total += $detalle->getSubtotal();
        }
        $this->total = $total;
    }

    public function emitir(): void
    {
        if ($this->estado !== 'borrador') {
            throw new \RuntimeException('Solo se pueden emitir ventas en estado borrador');
        }
        
        if (empty($this->detalles)) {
            throw new \RuntimeException('No se puede emitir una venta sin detalles');
        }
        
        $this->estado = 'emitida';
        $this->fecha = new \DateTime(); // Actualizar fecha de emisión
    }

    public function anular(): void
    {
        if ($this->estado === 'anulada') {
            throw new \RuntimeException('La venta ya está anulada');
        }
        
        if ($this->estado === 'borrador') {
            throw new \RuntimeException('No se puede anular una venta en borrador, elimínela directamente');
        }
        
        $this->estado = 'anulada';
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function estaEmitida(): bool
    {
        return $this->estado === 'emitida';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'anulada';
    }

    public function getCantidadItems(): int
    {
        $cantidad = 0;
        foreach ($this->detalles as $detalle) {
            $cantidad += $detalle->getCantidad();
        }
        return $cantidad;
    }

    public function getCantidadLineas(): int
    {
        return count($this->detalles);
    }

    public function validar(): bool
    {
        // Debe tener un cliente válido
        if ($this->idCliente <= 0) {
            return false;
        }

        // El total no puede ser negativo
        if ($this->total < 0) {
            return false;
        }

        // El estado debe ser válido
        $estadosValidos = ['borrador', 'emitida', 'anulada'];
        if (!in_array($this->estado, $estadosValidos)) {
            return false;
        }

        // Validar todos los detalles
        foreach ($this->detalles as $detalle) {
            if (!$detalle->validar()) {
                return false;
            }
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d H:i:s'),
            'id_cliente' => $this->idCliente,
            'total' => $this->total,
            'estado' => $this->estado,
            'cantidad_items' => $this->getCantidadItems(),
            'cantidad_lineas' => $this->getCantidadLineas(),
            'puede_editarse' => $this->puedeEditarse(),
            'detalles' => array_map(fn($detalle) => $detalle->toArray(), $this->detalles)
        ];
    }
}
