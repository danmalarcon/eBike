<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Clase Session
 * 
 * Helper estático para gestión segura de sesiones nativas de PHP.
 * Implementa medidas de endurecimiento contra fijación de sesión y robo de cookies (OWASP).
 */
class Session
{
    /**
     * Inicia la sesión PHP nativa de forma segura si no está activa.
     * Configura flags HttpOnly, SameSite y Secure (en entornos HTTPS).
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

                // Endurecimiento de parámetros de la cookie de sesión
                session_set_cookie_params([
                    'lifetime' => 0, // Expira al cerrar el navegador
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                ini_set('session.use_only_cookies', '1');
                ini_set('session.use_strict_mode', '1');

                session_start();
            } else {
                @session_start();
            }
        }
    }

    /**
     * Guarda los datos del usuario autenticado en la sesión.
     * Regenera el ID de sesión para prevenir ataques de fijación de sesión (Session Fixation).
     * 
     * @param array $user Datos del usuario (se excluye password_hash por seguridad)
     */
    public static function setUser(array $user): void
    {
        self::start();

        // Regenerar ID de sesión eliminando la sesión anterior
        session_regenerate_id(true);

        // Excluir hash de contraseña de la sesión
        unset($user['password_hash']);

        $_SESSION['user'] = $user;
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_at'] = time();
    }

    /**
     * Obtiene los datos del usuario en sesión.
     * 
     * @return array|null
     */
    public static function getUser(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Comprueba si existe un usuario autenticado en la sesión.
     * 
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::start();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user']);
    }

    /**
     * Obtiene el ID del usuario en sesión.
     * 
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        $user = self::getUser();
        return isset($user['id_usuario']) ? (int)$user['id_usuario'] : null;
    }

    /**
     * Obtiene el código o ID de rol del usuario autenticado.
     * 
     * @return string|int|null
     */
    public static function getRole(): string|int|null
    {
        $user = self::getUser();
        return $user['codigo_rol'] ?? $user['rol_id'] ?? null;
    }

    /**
     * Destruye completamente la sesión activa y elimina su cookie.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }
}
