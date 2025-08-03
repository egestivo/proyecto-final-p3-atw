<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UsuarioRepository;
use App\Entities\Usuario;

class AuthController
{
    private UsuarioRepository $usuarioRepository;

    public function __construct()
    {
        $this->usuarioRepository = new UsuarioRepository();
    }

    /**
     * POST /api/auth/login
     * Iniciar sesión
     */
    public function login(): void
    {
        try {
            $data = $this->getJsonInput();
            
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Validar datos requeridos
            $this->validateRequired($data, ['email', 'password']);

            $email = trim($data['email']);
            $password = $data['password'];

            // Buscar usuario por email
            $usuario = $this->usuarioRepository->buscarPorEmail($email);

            if (!$usuario) {
                $this->errorResponse('Credenciales inválidas', 401);
                return;
            }

            // Verificar que el usuario esté activo
            if ($usuario->getEstado() !== 'activo') {
                $this->errorResponse('Usuario inactivo', 401);
                return;
            }

            // Verificar password
            if (!password_verify($password, $usuario->getPasswordHash())) {
                $this->errorResponse('Credenciales inválidas', 401);
                return;
            }

            // Actualizar último acceso
            $this->usuarioRepository->actualizarUltimoAcceso($usuario->getId());

            // Generar token de sesión
            $token = $this->generarToken($usuario);

            // Iniciar sesión PHP
            session_start();
            $_SESSION['user_id'] = $usuario->getId();
            $_SESSION['user_email'] = $usuario->getEmail();
            $_SESSION['user_rol'] = $usuario->getRol();
            $_SESSION['token'] = $token;

            $response = [
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'data' => [
                    'user' => [
                        'id' => $usuario->getId(),
                        'email' => $usuario->getEmail(),
                        'nombres' => $usuario->getNombres(),
                        'apellidos' => $usuario->getApellidos(),
                        'rol' => $usuario->getRol(),
                        'ultimo_acceso' => $usuario->getUltimoAcceso() ? 
                            $usuario->getUltimoAcceso()->format('Y-m-d H:i:s') : null
                    ],
                    'token' => $token,
                    'expires_in' => 3600 // 1 hora
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error en el inicio de sesión: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/auth/register
     * Registrar nuevo usuario
     */
    public function register(): void
    {
        try {
            $data = $this->getJsonInput();
            
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Validar datos requeridos
            $this->validateRequired($data, ['email', 'password', 'nombres', 'apellidos']);

            $email = trim($data['email']);
            $password = $data['password'];

            // Validar que el email no exista
            if ($this->usuarioRepository->buscarPorEmail($email)) {
                $this->errorResponse('El email ya está registrado', 400);
                return;
            }

            // Validar fortaleza de la contraseña
            if (!$this->validarPassword($password)) {
                $this->errorResponse('La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números', 400);
                return;
            }

            // Crear usuario
            $usuario = new Usuario(
                $email,
                password_hash($password, PASSWORD_ARGON2ID),
                $data['nombres'],
                $data['apellidos'],
                $data['rol'] ?? 'vendedor', // Rol por defecto
                'activo'
            );

            // Validar la entidad
            if (!$usuario->validar()) {
                $this->errorResponse('Datos del usuario inválidos', 400);
                return;
            }

            // Guardar en base de datos
            if ($this->usuarioRepository->crear($usuario)) {
                $response = [
                    'success' => true,
                    'message' => 'Usuario registrado exitosamente',
                    'data' => [
                        'id' => $usuario->getId(),
                        'email' => $usuario->getEmail(),
                        'nombres' => $usuario->getNombres(),
                        'apellidos' => $usuario->getApellidos(),
                        'rol' => $usuario->getRol()
                    ]
                ];
                $this->jsonResponse($response, 201);
            } else {
                $this->errorResponse('Error al registrar usuario', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error en el registro: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/auth/logout
     * Cerrar sesión
     */
    public function logout(): void
    {
        try {
            session_start();
            session_destroy();

            $response = [
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al cerrar sesión: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/auth/me
     * Obtener información del usuario autenticado
     */
    public function me(): void
    {
        try {
            session_start();

            if (!isset($_SESSION['user_id'])) {
                $this->errorResponse('No autenticado', 401);
                return;
            }

            $usuario = $this->usuarioRepository->buscarPorId((int)$_SESSION['user_id']);

            if (!$usuario) {
                $this->errorResponse('Usuario no encontrado', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $usuario->getId(),
                    'email' => $usuario->getEmail(),
                    'nombres' => $usuario->getNombres(),
                    'apellidos' => $usuario->getApellidos(),
                    'rol' => $usuario->getRol(),
                    'estado' => $usuario->getEstado(),
                    'ultimo_acceso' => $usuario->getUltimoAcceso() ? 
                        $usuario->getUltimoAcceso()->format('Y-m-d H:i:s') : null
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al obtener información del usuario: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/auth/change-password
     * Cambiar contraseña del usuario autenticado
     */
    public function changePassword(): void
    {
        try {
            session_start();

            if (!isset($_SESSION['user_id'])) {
                $this->errorResponse('No autenticado', 401);
                return;
            }

            $data = $this->getJsonInput();
            
            if (!$data) {
                $this->errorResponse('Datos inválidos', 400);
                return;
            }

            // Validar datos requeridos
            $this->validateRequired($data, ['current_password', 'new_password']);

            $currentPassword = $data['current_password'];
            $newPassword = $data['new_password'];

            // Buscar usuario
            $usuario = $this->usuarioRepository->buscarPorId((int)$_SESSION['user_id']);

            if (!$usuario) {
                $this->errorResponse('Usuario no encontrado', 404);
                return;
            }

            // Verificar contraseña actual
            if (!password_verify($currentPassword, $usuario->getPasswordHash())) {
                $this->errorResponse('La contraseña actual es incorrecta', 400);
                return;
            }

            // Validar nueva contraseña
            if (!$this->validarPassword($newPassword)) {
                $this->errorResponse('La nueva contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números', 400);
                return;
            }

            // Actualizar contraseña
            $nuevoHash = password_hash($newPassword, PASSWORD_ARGON2ID);
            
            if ($this->usuarioRepository->actualizarPassword($usuario->getId(), $nuevoHash)) {
                $response = [
                    'success' => true,
                    'message' => 'Contraseña actualizada exitosamente'
                ];
                $this->jsonResponse($response);
            } else {
                $this->errorResponse('Error al actualizar contraseña', 500);
            }

        } catch (\Exception $e) {
            $this->errorResponse('Error al cambiar contraseña: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/auth/refresh-token
     * Renovar token de sesión
     */
    public function refreshToken(): void
    {
        try {
            session_start();

            if (!isset($_SESSION['user_id'])) {
                $this->errorResponse('No autenticado', 401);
                return;
            }

            $usuario = $this->usuarioRepository->buscarPorId((int)$_SESSION['user_id']);

            if (!$usuario || $usuario->getEstado() !== 'activo') {
                $this->errorResponse('Usuario no válido', 401);
                return;
            }

            // Generar nuevo token
            $token = $this->generarToken($usuario);
            $_SESSION['token'] = $token;

            $response = [
                'success' => true,
                'data' => [
                    'token' => $token,
                    'expires_in' => 3600
                ]
            ];

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            $this->errorResponse('Error al renovar token: ' . $e->getMessage());
        }
    }

    // ===== MÉTODOS PRIVADOS =====

    private function generarToken(Usuario $usuario): string
    {
        $payload = [
            'user_id' => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'rol' => $usuario->getRol(),
            'iat' => time(),
            'exp' => time() + 3600 // 1 hora
        ];

        // Token simple basado en hash (en producción usar JWT)
        return base64_encode(json_encode($payload) . '.' . hash_hmac('sha256', json_encode($payload), 'secret_key'));
    }

    private function validarPassword(string $password): bool
    {
        // Al menos 8 caracteres, una mayúscula, una minúscula y un número
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }

    private function getJsonInput(): ?array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    private function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new \InvalidArgumentException("El campo '$field' es requerido");
            }
        }
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function errorResponse(string $message, int $status = 500): void
    {
        $response = [
            'success' => false,
            'error' => $message
        ];
        $this->jsonResponse($response, $status);
    }
}
