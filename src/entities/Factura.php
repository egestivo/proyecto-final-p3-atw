<?php
declare(strict_types=1);

namespace App\Entities;

class Factura
{
    private int $id;
    private int $idVenta;
    private string $numero;
    private ?string $claveAcceso;
    private \DateTime $fechaEmision;
    private string $estado; // 'pendiente', 'emitida', 'autorizada', 'anulada'
    private ?string $xmlAutorizado;
    private ?string $urlPdf;

    public function __construct(
        int $idVenta,
        string $numero,
        ?string $claveAcceso = null,
        ?\DateTime $fechaEmision = null,
        string $estado = 'pendiente',
        ?int $id = null
    ) {
        $this->idVenta = $idVenta;
        $this->numero = $numero;
        $this->claveAcceso = $claveAcceso;
        $this->fechaEmision = $fechaEmision ?? new \DateTime();
        $this->estado = $estado;
        $this->xmlAutorizado = null;
        $this->urlPdf = null;
        if ($id !== null) {
            $this->id = $id;
        }
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getIdVenta(): int
    {
        return $this->idVenta;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function getClaveAcceso(): ?string
    {
        return $this->claveAcceso;
    }

    public function getFechaEmision(): \DateTime
    {
        return $this->fechaEmision;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getXmlAutorizado(): ?string
    {
        return $this->xmlAutorizado;
    }

    public function getUrlPdf(): ?string
    {
        return $this->urlPdf;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setIdVenta(int $idVenta): void
    {
        if ($this->estado !== 'pendiente') {
            throw new \RuntimeException('No se puede modificar la venta de una factura que no está pendiente');
        }
        $this->idVenta = $idVenta;
    }

    public function setNumero(string $numero): void
    {
        if ($this->estado !== 'pendiente') {
            throw new \RuntimeException('No se puede modificar el número de una factura que no está pendiente');
        }
        $this->numero = $numero;
    }

    public function setClaveAcceso(?string $claveAcceso): void
    {
        $this->claveAcceso = $claveAcceso;
    }

    public function setFechaEmision(\DateTime $fechaEmision): void
    {
        $this->fechaEmision = $fechaEmision;
    }

    public function setEstado(string $estado): void
    {
        $estadosValidos = ['pendiente', 'emitida', 'autorizada', 'anulada'];
        if (!in_array($estado, $estadosValidos)) {
            throw new \InvalidArgumentException('Estado de factura no válido');
        }
        $this->estado = $estado;
    }

    public function setXmlAutorizado(?string $xmlAutorizado): void
    {
        $this->xmlAutorizado = $xmlAutorizado;
    }

    public function setUrlPdf(?string $urlPdf): void
    {
        $this->urlPdf = $urlPdf;
    }

    // Métodos de negocio
    public function generarClaveAcceso(): string
    {
        // Formato: ddmmaaaa + establecimiento + punto emisión + secuencial + código numérico + tipo emisión + dígito verificador
        $fecha = $this->fechaEmision->format('dmY');
        $establecimiento = '001'; // Por defecto
        $puntoEmision = '001'; // Por defecto
        $secuencial = str_pad($this->numero, 9, '0', STR_PAD_LEFT);
        $codigoNumerico = str_pad((string)random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1'; // Emisión normal
        
        $claveBase = $fecha . $establecimiento . $puntoEmision . $secuencial . $codigoNumerico . $tipoEmision;
        $digitoVerificador = $this->calcularDigitoVerificador($claveBase);
        
        $this->claveAcceso = $claveBase . $digitoVerificador;
        return $this->claveAcceso;
    }

    private function calcularDigitoVerificador(string $claveBase): string
    {
        $multiplicadores = [2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7];
        $suma = 0;
        
        for ($i = 0; $i < strlen($claveBase); $i++) {
            $suma += (int)$claveBase[$i] * $multiplicadores[$i];
        }
        
        $residuo = $suma % 11;
        
        if ($residuo === 0) {
            return '0';
        } elseif ($residuo === 1) {
            return '1';
        } else {
            return (string)(11 - $residuo);
        }
    }

    public function emitir(): void
    {
        if ($this->estado !== 'pendiente') {
            throw new \RuntimeException('Solo se pueden emitir facturas pendientes');
        }
        
        if (!$this->claveAcceso) {
            $this->generarClaveAcceso();
        }
        
        $this->estado = 'emitida';
        $this->fechaEmision = new \DateTime();
    }

    public function autorizar(string $xmlAutorizado): void
    {
        if ($this->estado !== 'emitida') {
            throw new \RuntimeException('Solo se pueden autorizar facturas emitidas');
        }
        
        $this->xmlAutorizado = $xmlAutorizado;
        $this->estado = 'autorizada';
    }

    public function anular(): void
    {
        if ($this->estado === 'anulada') {
            throw new \RuntimeException('La factura ya está anulada');
        }
        
        $this->estado = 'anulada';
    }

    public function generarPdf(): string
    {
        if ($this->estado !== 'autorizada') {
            throw new \RuntimeException('Solo se puede generar PDF de facturas autorizadas');
        }
        
        // Aquí iría la lógica para generar el PDF
        // Por ahora simulamos la URL
        $this->urlPdf = "/facturas/pdf/{$this->numero}.pdf";
        return $this->urlPdf;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function estaEmitida(): bool
    {
        return $this->estado === 'emitida';
    }

    public function estaAutorizada(): bool
    {
        return $this->estado === 'autorizada';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'anulada';
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function validarClaveAcceso(): bool
    {
        if (!$this->claveAcceso || strlen($this->claveAcceso) !== 49) {
            return false;
        }
        
        $claveBase = substr($this->claveAcceso, 0, 48);
        $digitoVerificador = substr($this->claveAcceso, 48, 1);
        
        return $this->calcularDigitoVerificador($claveBase) === $digitoVerificador;
    }

    public function validar(): bool
    {
        // Debe tener una venta válida
        if ($this->idVenta <= 0) {
            return false;
        }

        // El número no puede estar vacío
        if (empty(trim($this->numero))) {
            return false;
        }

        // El estado debe ser válido
        $estadosValidos = ['pendiente', 'emitida', 'autorizada', 'anulada'];
        if (!in_array($this->estado, $estadosValidos)) {
            return false;
        }

        // Si tiene clave de acceso, debe ser válida
        if ($this->claveAcceso && !$this->validarClaveAcceso()) {
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_venta' => $this->idVenta,
            'numero' => $this->numero,
            'clave_acceso' => $this->claveAcceso,
            'fecha_emision' => $this->fechaEmision->format('Y-m-d H:i:s'),
            'estado' => $this->estado,
            'xml_autorizado' => $this->xmlAutorizado !== null,
            'url_pdf' => $this->urlPdf,
            'puede_editarse' => $this->puedeEditarse(),
            'esta_autorizada' => $this->estaAutorizada()
        ];
    }
}
