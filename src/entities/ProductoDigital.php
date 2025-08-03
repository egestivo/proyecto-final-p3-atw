<?php
declare(strict_types=1);

namespace App\Entities;

class ProductoDigital extends Producto
{
    private ?string $urlDescarga;
    private ?string $licencia;
    private ?string $claveActivacion;
    private ?\DateTime $fechaExpiracion;
    private int $maxDescargas;
    private int $descargasRealizadas;

    public function __construct(
        string $nombre,
        float $precioUnitario,
        int $idCategoria,
        ?string $urlDescarga = null,
        ?string $licencia = null,
        ?string $claveActivacion = null,
        ?\DateTime $fechaExpiracion = null,
        int $maxDescargas = -1, // -1 = ilimitadas
        ?string $descripcion = null,
        int $stock = 999999, // Stock alto por defecto para digitales
        ?int $id = null
    ) {
        parent::__construct($nombre, $precioUnitario, $idCategoria, $descripcion, $stock, $id);
        $this->urlDescarga = $urlDescarga;
        $this->licencia = $licencia;
        $this->claveActivacion = $claveActivacion;
        $this->fechaExpiracion = $fechaExpiracion;
        $this->maxDescargas = $maxDescargas;
        $this->descargasRealizadas = 0;
        $this->tipoProducto = 'digital';
    }

    // Getters
    public function getUrlDescarga(): ?string
    {
        return $this->urlDescarga;
    }

    public function getLicencia(): ?string
    {
        return $this->licencia;
    }

    public function getClaveActivacion(): ?string
    {
        return $this->claveActivacion;
    }

    public function getFechaExpiracion(): ?\DateTime
    {
        return $this->fechaExpiracion;
    }

    public function getMaxDescargas(): int
    {
        return $this->maxDescargas;
    }

    public function getDescargasRealizadas(): int
    {
        return $this->descargasRealizadas;
    }

    // Setters
    public function setUrlDescarga(?string $urlDescarga): void
    {
        $this->urlDescarga = $urlDescarga;
    }

    public function setLicencia(?string $licencia): void
    {
        $this->licencia = $licencia;
    }

    public function setClaveActivacion(?string $claveActivacion): void
    {
        $this->claveActivacion = $claveActivacion;
    }

    public function setFechaExpiracion(?\DateTime $fechaExpiracion): void
    {
        $this->fechaExpiracion = $fechaExpiracion;
    }

    public function setMaxDescargas(int $maxDescargas): void
    {
        $this->maxDescargas = $maxDescargas;
    }

    public function setDescargasRealizadas(int $descargasRealizadas): void
    {
        if ($descargasRealizadas < 0) {
            throw new \InvalidArgumentException('Las descargas realizadas no pueden ser negativas');
        }
        $this->descargasRealizadas = $descargasRealizadas;
    }

    // Métodos de negocio específicos
    public function puedeDescargar(): bool
    {
        // Verificar si no ha expirado
        if ($this->fechaExpiracion && $this->fechaExpiracion < new \DateTime()) {
            return false;
        }

        // Verificar límite de descargas
        if ($this->maxDescargas > 0 && $this->descargasRealizadas >= $this->maxDescargas) {
            return false;
        }

        return true;
    }

    public function registrarDescarga(): void
    {
        if (!$this->puedeDescargar()) {
            throw new \RuntimeException('No se puede realizar la descarga');
        }

        $this->descargasRealizadas++;
    }

    public function getDescargasRestantes(): int
    {
        if ($this->maxDescargas === -1) {
            return -1; // Ilimitadas
        }

        return max(0, $this->maxDescargas - $this->descargasRealizadas);
    }

    public function estaExpirado(): bool
    {
        return $this->fechaExpiracion && $this->fechaExpiracion < new \DateTime();
    }

    public function diasParaExpiracion(): ?int
    {
        if (!$this->fechaExpiracion) {
            return null; // No expira
        }

        $hoy = new \DateTime();
        if ($this->fechaExpiracion < $hoy) {
            return 0; // Ya expiró
        }

        return $hoy->diff($this->fechaExpiracion)->days;
    }

    // Generar nueva clave de activación
    public function generarNuevaClaveActivacion(): string
    {
        $this->claveActivacion = $this->generarClaveAleatoria();
        return $this->claveActivacion;
    }

    private function generarClaveAleatoria(int $longitud = 16): string
    {
        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $clave = '';
        
        for ($i = 0; $i < $longitud; $i++) {
            $clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
            
            // Agregar guiones cada 4 caracteres
            if (($i + 1) % 4 === 0 && $i < $longitud - 1) {
                $clave .= '-';
            }
        }
        
        return $clave;
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

        // Para productos digitales, validar URL si está presente
        if ($this->urlDescarga && !filter_var($this->urlDescarga, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Validar que las descargas realizadas no excedan el máximo
        if ($this->maxDescargas > 0 && $this->descargasRealizadas > $this->maxDescargas) {
            return false;
        }

        return true;
    }

    public function afectaInventario(): bool
    {
        // Los productos digitales normalmente no afectan inventario físico
        // Pero pueden tener límites de licencias
        return false;
    }

    public function getInformacionEspecifica(): array
    {
        return [
            'tipo' => 'digital',
            'url_descarga' => $this->urlDescarga,
            'licencia' => $this->licencia,
            'clave_activacion' => $this->claveActivacion,
            'fecha_expiracion' => $this->fechaExpiracion?->format('Y-m-d H:i:s'),
            'max_descargas' => $this->maxDescargas,
            'descargas_realizadas' => $this->descargasRealizadas,
            'descargas_restantes' => $this->getDescargasRestantes(),
            'puede_descargar' => $this->puedeDescargar(),
            'esta_expirado' => $this->estaExpirado(),
            'dias_para_expiracion' => $this->diasParaExpiracion()
        ];
    }

    // Override del método descontarStock para productos digitales
    public function descontarStock(int $cantidad): void
    {
        // Los productos digitales no necesariamente descartan stock
        // Solo si tienen límite de licencias
        if ($this->afectaInventario()) {
            parent::descontarStock($cantidad);
        }
    }
}
