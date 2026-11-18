<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use Exception;

/**
 * Controlador AuthController
 * 
 * Gestiona el flujo de autenticación, registro y sesiones del sistema.
 * Todas las respuestas hacia el cliente se emiten estrictamente en formato JSON.
 */
class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Muestra la vista de formulario de inicio de sesión.
     */
    public function showLogin(): void
    {
        $this->redirectIfAuthenticated();
        require_once dirname(__DIR__) . '/views/auth/login.php';
    }

    /**
     * Muestra la vista de formulario de registro.
     */
    public function showRegister(): void
    {
        $this->redirectIfAuthenticated();
        require_once dirname(__DIR__) . '/views/auth/register.php';
    }

    /**
     * Procesa la solicitud de registro de nuevos usuarios (RF-001).
     * 
     * Espera payload JSON o FormData con: nombre, email, password, telefono (opcional).
     * Retorna respuesta JSON con HTTP status code correspondiente.
     */
    public function register(): void
    {
        $input = $this->getRequestData();

        // Validar presencia de campos requeridos
        $missing = Validator::checkRequired($input, ['nombre', 'email', 'password']);
        if (!empty($missing)) {
            $this->jsonResponse(false, 'Campos obligatorios faltantes: ' . implode(', ', $missing), null, 400);
            return;
        }

        // Sanitización y normalización de entradas
        $nombre   = Validator::sanitizeString($input['nombre']);
        $email    = Validator::sanitizeEmail($input['email']);
        $telefono = Validator::sanitizePhone($input['telefono'] ?? null);
        $password = (string)$input['password'];

        // Validar formato de email
        if (!Validator::validateEmail($email)) {
            $this->jsonResponse(false, 'El formato del correo electrónico es inválido.', null, 400);
            return;
        }

        // Validar longitud mínima de contraseña
        if (!Validator::validatePassword($password, 8)) {
            $this->jsonResponse(false, 'La contraseña debe contener al menos 8 caracteres.', null, 400);
            return;
        }

        // Comprobar unicidad de email (RN-001)
        if ($this->userModel->emailExists($email)) {
            $this->jsonResponse(false, 'El correo electrónico ya se encuentra registrado en el sistema.', null, 409);
            return;
        }

        try {
            // Persistir usuario (User model genera UUID y hashea con bcrypt - RN-004)
            $created = $this->userModel->create([
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'telefono' => $telefono,
                'rol_id'   => 2, // Rol cliente por defecto (RF-001)
                'estado'   => 'activo',
            ]);

            if ($created) {
                $this->jsonResponse(true, 'Usuario registrado exitosamente.', [
                    'email'  => $email,
                    'nombre' => $nombre,
                ], 201);
            } else {
                $this->jsonResponse(false, 'No se pudo completar el registro del usuario.', null, 500);
            }
        } catch (Exception $e) {
            // Registrar el detalle en el log de PHP/Apache para diagnóstico (no exponerlo al cliente)
            error_log('[AuthController::register] ' . get_class($e) . ': ' . $e->getMessage());
            $this->jsonResponse(false, 'Error interno del servidor al procesar el registro.', null, 500);
        }
    }

    /**
     * Procesa la solicitud de inicio de sesión (RF-002).
     * 
     * Espera payload JSON o FormData con: email, password.
     * Retorna respuesta JSON y guarda la sesión segura ante éxito.
     */
    public function login(): void
    {
        $input = $this->getRequestData();

        // Validar presencia de campos requeridos
        $missing = Validator::checkRequired($input, ['email', 'password']);
        if (!empty($missing)) {
            $this->jsonResponse(false, 'Campos obligatorios faltantes: ' . implode(', ', $missing), null, 400);
            return;
        }

        $email    = Validator::sanitizeEmail($input['email']);
        $password = (string)$input['password'];

        if (!Validator::validateEmail($email)) {
            $this->jsonResponse(false, 'El correo electrónico suministrado no es válido.', null, 400);
            return;
        }

        // Buscar usuario por correo
        $user = $this->userModel->findByEmail($email);

        // Prevenir enumeración de usuarios respondiendo mensaje genérico ante credencial errónea
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->jsonResponse(false, 'Credenciales de acceso inválidas.', null, 401);
            return;
        }

        // Verificar estado de la cuenta
        if ($user['estado'] !== 'activo') {
            $this->jsonResponse(false, "La cuenta se encuentra en estado '{$user['estado']}'. Contacte al administrador.", null, 403);
            return;
        }

        // Iniciar sesión segura y guardar datos del usuario
        Session::setUser($user);

        // Redirección post-login según el rol (RF-002):
        // rol_id = 1 → Administrador, rol_id = 2 → Cliente.
        $rolId       = (int)($user['rol_id'] ?? 0);
        $redirectUrl = $rolId === 1 ? '/admin/dashboard' : '/user/dashboard';

        $contentType   = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept        = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJsonRequest = stripos($contentType, 'application/json') !== false
            || stripos($accept, 'application/json') !== false;

        // Formularios tradicionales: redirección HTTP real.
        if (!$isJsonRequest) {
            header('Location: ' . $redirectUrl, true, 302);
            exit;
        }

        // Peticiones Fetch/AJAX: se devuelve el destino para que el cliente navegue.
        $this->jsonResponse(true, 'Inicio de sesión exitoso.', [
            'uuid'     => $user['uuid'],
            'nombre'   => $user['nombre'],
            'email'    => $user['email'],
            'rol'      => $user['codigo_rol'] ?? $user['rol_id'],
            'redirect' => $redirectUrl,
        ], 200);
    }

    /**
     * Cierra la sesión activa del usuario.
     */
    public function logout(): void
    {
        Session::destroy();

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (stripos($contentType, 'application/json') !== false || stripos($accept, 'application/json') !== false) {
            $this->jsonResponse(true, 'Sesión finalizada exitosamente.', null, 200);
        } else {
            header('Location: /');
            exit;
        }
    }

    /**
     * Redirige a los usuarios ya autenticados fuera de las vistas de invitado.
     */
    private function redirectIfAuthenticated(): void
    {
        if (!Session::isLoggedIn()) {
            return;
        }

        $target = AuthMiddleware::isAdmin() ? '/admin/dashboard' : '/user/dashboard';
        header('Location: ' . $target, true, 302);
        exit;
    }

    /**
     * Obtiene y normaliza los datos de la petición HTTP (admite JSON y POST estándar).
     * 
     * @return array
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $data = json_decode($rawBody, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    /**
     * Emite una respuesta HTTP estructurada estrictamente en JSON y finaliza la ejecución.
     * 
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     */
    private function jsonResponse(bool $success, string $message, mixed $data = null, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
