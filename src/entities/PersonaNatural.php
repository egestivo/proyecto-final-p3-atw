<?php
declare(strict_types=1);

namespace App\Entities;

class PersonaNatural extends Cliente
{
    private string $nombres;
    private string $apellidos;
    private string $cedula;

    public function __construct(
        string $nombres,
        string $apellidos,
        string $cedula,
        string $email,
        ?string $telefono = null,
        ?string $direccion = null,
        ?int $id = null
    ) {
        parent::__construct($email, $telefono, $direccion, $id);
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->cedula = $cedula;
        $this->tipoCliente = 'natural';
    }

    // Getters
    public function getNombres(): string
    {
        return $this->nombres;
    }

    public function getApellidos(): string
    {
        return $this->apellidos;
    }

    public function getCedula(): string
    {
        return $this->cedula;
    }

    // Setters
    public function setNombres(string $nombres): void
    {
        $this->nombres = $nombres;
    }

    public function setApellidos(string $apellidos): void
    {
        $this->apellidos = $apellidos;
    }

    public function setCedula(string $cedula): void
    {
        $this->cedula = $cedula;
    }

    public function getNombreCompleto(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }

    public function validar(): bool
    {
        // Validar email
        if (!$this->validarEmail($this->email)) {
            return false;
        }

        // Validar que nombres y apellidos no estén vacíos
        if (empty(trim($this->nombres)) || empty(trim($this->apellidos))) {
            return false;
        }

        // Validar cédula ecuatoriana (módulo 10)
        return $this->validarCedulaEcuatoriana($this->cedula);
    }

    /**
     * Valida cédula ecuatoriana usando algoritmo módulo 10
     */
    private function validarCedulaEcuatoriana(string $cedula): bool
    {
        // Debe tener exactamente 10 dígitos
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        // Los dos primeros dígitos deben corresponder a una provincia válida (01-24)
        $provincia = (int)substr($cedula, 0, 2);
        if ($provincia < 1 || $provincia > 24) {
            return false;
        }

        // Algoritmo módulo 10
        $digitos = str_split($cedula);
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $digito = (int)$digitos[$i];
            
            if ($i % 2 === 0) { // Posiciones pares (0, 2, 4, 6, 8)
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            
            $suma += $digito;
        }

        $digitoVerificador = (10 - ($suma % 10)) % 10;
        
        return $digitoVerificador === (int)$digitos[9];
    }
}
