<?php
declare(strict_types=1);

namespace App\Entities;

class Permiso
{
    private int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;

    public function __construct(
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        ?int $id = null
    ) {
        $this->codigo = $codigo;
        $this->nombre = $nombre;
        $this->descripcion = $descripcion;
        if ($id !== null) {
            $this->id = $id;
        }
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    // Setters
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setCodigo(string $codigo): void
    {
        $this->codigo = $codigo;
    }

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function setDescripcion(?string $descripcion): void
    {
        $this->descripcion = $descripcion;
    }

    // Métodos de negocio
    public function validar(): bool
    {
        // El código no puede estar vacío
        if (empty(trim($this->codigo))) {
            return false;
        }

        // El nombre no puede estar vacío
        if (empty(trim($this->nombre))) {
            return false;
        }

        // El código debe seguir un patrón (letras mayúsculas, números y guiones bajos)
        if (!preg_match('/^[A-Z_0-9]+$/', $this->codigo)) {
            return false;
        }

        return true;
    }

    public function esIgualA(Permiso $otro): bool
    {
        return $this->codigo === $otro->codigo;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion
        ];
    }

    // Constantes para permisos comunes
    public const CRUD_CLIENTE = 'CRUD_CLIENTE';
    public const VER_CLIENTE = 'VER_CLIENTE';
    public const CREAR_CLIENTE = 'CREAR_CLIENTE';
    public const EDITAR_CLIENTE = 'EDITAR_CLIENTE';
    public const ELIMINAR_CLIENTE = 'ELIMINAR_CLIENTE';
    
    public const CRUD_PRODUCTO = 'CRUD_PRODUCTO';
    public const VER_PRODUCTO = 'VER_PRODUCTO';
    public const CREAR_PRODUCTO = 'CREAR_PRODUCTO';
    public const EDITAR_PRODUCTO = 'EDITAR_PRODUCTO';
    public const ELIMINAR_PRODUCTO = 'ELIMINAR_PRODUCTO';
    
    public const CRUD_CATEGORIA = 'CRUD_CATEGORIA';
    public const VER_CATEGORIA = 'VER_CATEGORIA';
    public const CREAR_CATEGORIA = 'CREAR_CATEGORIA';
    public const EDITAR_CATEGORIA = 'EDITAR_CATEGORIA';
    public const ELIMINAR_CATEGORIA = 'ELIMINAR_CATEGORIA';
    
    public const CRUD_VENTA = 'CRUD_VENTA';
    public const VER_VENTA = 'VER_VENTA';
    public const CREAR_VENTA = 'CREAR_VENTA';
    public const EDITAR_VENTA = 'EDITAR_VENTA';
    public const ELIMINAR_VENTA = 'ELIMINAR_VENTA';
    public const ANULAR_VENTA = 'ANULAR_VENTA';
    
    public const CRUD_FACTURA = 'CRUD_FACTURA';
    public const VER_FACTURA = 'VER_FACTURA';
    public const CREAR_FACTURA = 'CREAR_FACTURA';
    public const EMITIR_FACTURA = 'EMITIR_FACTURA';
    public const ANULAR_FACTURA = 'ANULAR_FACTURA';
    
    public const VER_REPORTES = 'VER_REPORTES';
    public const GENERAR_REPORTES = 'GENERAR_REPORTES';
    public const EXPORTAR_REPORTES = 'EXPORTAR_REPORTES';
    
    public const ADMINISTRAR_USUARIOS = 'ADMINISTRAR_USUARIOS';
    public const ADMINISTRAR_ROLES = 'ADMINISTRAR_ROLES';
    public const ADMINISTRAR_PERMISOS = 'ADMINISTRAR_PERMISOS';
    
    public const CONFIGURAR_SISTEMA = 'CONFIGURAR_SISTEMA';
}
