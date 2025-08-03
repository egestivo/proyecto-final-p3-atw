<?php
declare(strict_types=1);

namespace App\Entities;

class Rol
{
    private int $id;
    private string $nombre;
    private ?string $descripcion;
    private array $permisos; // Array de Permiso

    public function __construct(
        string $nombre,
        ?string $descripcion = null,
        ?int $id = null
    ) {
        $this->nombre = $nombre;
        $this->descripcion = $descripcion;
        $this->permisos = [];
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

    public function getPermisos(): array
    {
        return $this->permisos;
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

    public function setPermisos(array $permisos): void
    {
        $this->permisos = $permisos;
    }

    // Métodos de negocio
    public function agregarPermiso(Permiso $permiso): void
    {
        // Verificar que no tenga ya este permiso
        foreach ($this->permisos as $permisoExistente) {
            if ($permisoExistente->getCodigo() === $permiso->getCodigo()) {
                throw new \InvalidArgumentException('El rol ya tiene este permiso asignado');
            }
        }
        $this->permisos[] = $permiso;
    }

    public function removerPermiso(string $codigoPermiso): void
    {
        foreach ($this->permisos as $index => $permiso) {
            if ($permiso->getCodigo() === $codigoPermiso) {
                unset($this->permisos[$index]);
                $this->permisos = array_values($this->permisos); // Reindexar
                return;
            }
        }
        throw new \InvalidArgumentException('El rol no tiene este permiso asignado');
    }

    public function tienePermiso(string $codigoPermiso): bool
    {
        foreach ($this->permisos as $permiso) {
            if ($permiso->getCodigo() === $codigoPermiso) {
                return true;
            }
        }
        return false;
    }

    public function tienePermisos(array $codigosPermisos): bool
    {
        foreach ($codigosPermisos as $codigo) {
            if (!$this->tienePermiso($codigo)) {
                return false;
            }
        }
        return true;
    }

    public function tieneAlgunPermiso(array $codigosPermisos): bool
    {
        foreach ($codigosPermisos as $codigo) {
            if ($this->tienePermiso($codigo)) {
                return true;
            }
        }
        return false;
    }

    public function getCodigosPermisos(): array
    {
        return array_map(fn($permiso) => $permiso->getCodigo(), $this->permisos);
    }

    public function getCantidadPermisos(): int
    {
        return count($this->permisos);
    }

    public function limpiarPermisos(): void
    {
        $this->permisos = [];
    }

    public function esAdmin(): bool
    {
        return $this->nombre === 'ADMIN';
    }

    public function esVendedor(): bool
    {
        return $this->nombre === 'VENDEDOR';
    }

    public function esContador(): bool
    {
        return $this->nombre === 'CONTADOR';
    }

    // Métodos para configurar roles predefinidos
    public static function crearRolAdmin(): Rol
    {
        $rol = new Rol('ADMIN', 'Administrador del sistema');
        
        // Un admin tiene todos los permisos
        $permisosAdmin = [
            new Permiso(Permiso::CRUD_CLIENTE, 'Gestión completa de clientes'),
            new Permiso(Permiso::CRUD_PRODUCTO, 'Gestión completa de productos'),
            new Permiso(Permiso::CRUD_CATEGORIA, 'Gestión completa de categorías'),
            new Permiso(Permiso::CRUD_VENTA, 'Gestión completa de ventas'),
            new Permiso(Permiso::CRUD_FACTURA, 'Gestión completa de facturas'),
            new Permiso(Permiso::VER_REPORTES, 'Ver reportes'),
            new Permiso(Permiso::GENERAR_REPORTES, 'Generar reportes'),
            new Permiso(Permiso::EXPORTAR_REPORTES, 'Exportar reportes'),
            new Permiso(Permiso::ADMINISTRAR_USUARIOS, 'Administrar usuarios'),
            new Permiso(Permiso::ADMINISTRAR_ROLES, 'Administrar roles'),
            new Permiso(Permiso::ADMINISTRAR_PERMISOS, 'Administrar permisos'),
            new Permiso(Permiso::CONFIGURAR_SISTEMA, 'Configurar sistema')
        ];
        
        foreach ($permisosAdmin as $permiso) {
            $rol->agregarPermiso($permiso);
        }
        
        return $rol;
    }

    public static function crearRolVendedor(): Rol
    {
        $rol = new Rol('VENDEDOR', 'Vendedor del sistema');
        
        // Un vendedor puede gestionar clientes, ver productos, crear ventas
        $permisosVendedor = [
            new Permiso(Permiso::CRUD_CLIENTE, 'Gestión de clientes'),
            new Permiso(Permiso::VER_PRODUCTO, 'Ver productos'),
            new Permiso(Permiso::CRUD_VENTA, 'Gestión de ventas'),
            new Permiso(Permiso::VER_FACTURA, 'Ver facturas'),
            new Permiso(Permiso::CREAR_FACTURA, 'Crear facturas'),
            new Permiso(Permiso::EMITIR_FACTURA, 'Emitir facturas')
        ];
        
        foreach ($permisosVendedor as $permiso) {
            $rol->agregarPermiso($permiso);
        }
        
        return $rol;
    }

    public static function crearRolContador(): Rol
    {
        $rol = new Rol('CONTADOR', 'Contador del sistema');
        
        // Un contador puede ver todo, generar reportes, gestionar facturas
        $permisosContador = [
            new Permiso(Permiso::VER_CLIENTE, 'Ver clientes'),
            new Permiso(Permiso::VER_PRODUCTO, 'Ver productos'),
            new Permiso(Permiso::VER_CATEGORIA, 'Ver categorías'),
            new Permiso(Permiso::VER_VENTA, 'Ver ventas'),
            new Permiso(Permiso::CRUD_FACTURA, 'Gestión completa de facturas'),
            new Permiso(Permiso::VER_REPORTES, 'Ver reportes'),
            new Permiso(Permiso::GENERAR_REPORTES, 'Generar reportes'),
            new Permiso(Permiso::EXPORTAR_REPORTES, 'Exportar reportes')
        ];
        
        foreach ($permisosContador as $permiso) {
            $rol->agregarPermiso($permiso);
        }
        
        return $rol;
    }

    public function validar(): bool
    {
        // El nombre no puede estar vacío
        if (empty(trim($this->nombre))) {
            return false;
        }

        // El nombre debe seguir un patrón (letras mayúsculas y guiones bajos)
        if (!preg_match('/^[A-Z_]+$/', $this->nombre)) {
            return false;
        }

        // Validar todos los permisos
        foreach ($this->permisos as $permiso) {
            if (!$permiso->validar()) {
                return false;
            }
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'permisos' => array_map(fn($permiso) => $permiso->toArray(), $this->permisos),
            'cantidad_permisos' => $this->getCantidadPermisos(),
            'codigos_permisos' => $this->getCodigosPermisos()
        ];
    }
}
