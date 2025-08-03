<?php
declare(strict_types=1);

namespace App\Entities;

class PersonaJuridica extends Cliente
{
    private string $razonSocial;
    private string $ruc;
    private ?string $representanteLegal;

    public function __construct(
        string $razonSocial,
        string $ruc,
        string $email,
        ?string $representanteLegal = null,
        ?string $telefono = null,
        ?string $direccion = null,
        ?int $id = null
    ) {
        parent::__construct($email, $telefono, $direccion, $id);
        $this->razonSocial = $razonSocial;
        $this->ruc = $ruc;
        $this->representanteLegal = $representanteLegal;
        $this->tipoCliente = 'juridico';
    }

    // Getters
    public function getRazonSocial(): string
    {
        return $this->razonSocial;
    }

    public function getRuc(): string
    {
        return $this->ruc;
    }

    public function getRepresentanteLegal(): ?string
    {
        return $this->representanteLegal;
    }

    // Setters
    public function setRazonSocial(string $razonSocial): void
    {
        $this->razonSocial = $razonSocial;
    }

    public function setRuc(string $ruc): void
    {
        $this->ruc = $ruc;
    }

    public function setRepresentanteLegal(?string $representanteLegal): void
    {
        $this->representanteLegal = $representanteLegal;
    }

    public function getNombreCompleto(): string
    {
        return $this->razonSocial;
    }

    public function validar(): bool
    {
        // Validar email
        if (!$this->validarEmail($this->email)) {
            return false;
        }

        // Validar que razón social no esté vacía
        if (empty(trim($this->razonSocial))) {
            return false;
        }

        // Validar RUC ecuatoriano
        return $this->validarRucEcuatoriano($this->ruc);
    }

    /**
     * Valida RUC ecuatoriano
     * Formatos válidos:
     * - Persona Natural: 10 dígitos + 001
     * - Sociedad: 13 dígitos, tercer dígito 9
     * - Sector público: 13 dígitos, tercer dígito 6
     */
    private function validarRucEcuatoriano(string $ruc): bool
    {
        // Debe tener exactamente 13 dígitos
        if (!preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }

        // Los dos primeros dígitos deben corresponder a una provincia válida (01-24)
        $provincia = (int)substr($ruc, 0, 2);
        if ($provincia < 1 || $provincia > 24) {
            return false;
        }

        $tercerDigito = (int)$ruc[2];

        // Validar según el tercer dígito
        if ($tercerDigito < 6) {
            // Persona natural: validar cédula + 001
            $cedula = substr($ruc, 0, 10);
            $establecimiento = substr($ruc, 10, 3);
            
            return $establecimiento === '001' && $this->validarCedulaEcuatoriana($cedula);
        } elseif ($tercerDigito === 6) {
            // Sector público
            return $this->validarRucSectorPublico($ruc);
        } elseif ($tercerDigito === 9) {
            // Sociedad
            return $this->validarRucSociedad($ruc);
        }

        return false;
    }

    private function validarCedulaEcuatoriana(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        $digitos = str_split($cedula);
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $digito = (int)$digitos[$i];
            
            if ($i % 2 === 0) {
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

    private function validarRucSectorPublico(string $ruc): bool
    {
        $digitos = str_split($ruc);
        $coeficientes = [3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += (int)$digitos[$i] * $coeficientes[$i];
        }

        $digitoVerificador = 11 - ($suma % 11);
        if ($digitoVerificador === 11) $digitoVerificador = 0;
        if ($digitoVerificador === 10) return false;

        return $digitoVerificador === (int)$digitos[8];
    }

    private function validarRucSociedad(string $ruc): bool
    {
        $digitos = str_split($ruc);
        $coeficientes = [4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $suma += (int)$digitos[$i] * $coeficientes[$i];
        }

        $digitoVerificador = 11 - ($suma % 11);
        if ($digitoVerificador === 11) $digitoVerificador = 0;
        if ($digitoVerificador === 10) return false;

        return $digitoVerificador === (int)$digitos[9];
    }
}
