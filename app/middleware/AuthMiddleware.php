<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Session;

/**
 * Middleware de autorización - eBike EMS
 *
 * Centraliza la protección de rutas privadas por sesión y rol.
 * Responde con redirección HTML o JSON según el tipo de petición.
 */
class AuthMiddleware
{
    /**
     * Datos del usuario autenticado (o arreglo vacío).
     */
    public static function user(): array
    {
        return Session::getUser() ?? [];
    }

    /**
     * Determina si un usuario es administrador (rol_id = 1 / código 'admin').
     */
    public static function isAdmin(?array $user = null): bool
    {
        $user ??= self::user();

        return (int)($user['rol_id'] ?? 0) === 1
            || (($user['codigo_rol'] ?? '') === 'admin');
    }

    /**
     * Determina si un usuario es cliente (rol_id = 2 / código 'cliente').
     */
    public static function isClient(?array $user = null): bool
    {
        $user ??= self::user();

        return (int)($user['rol_id'] ?? 0) === 2
            || (($user['codigo_rol'] ?? '') === 'cliente');
    }

    /**
     * Exige una sesión activa. Si no existe, redirige a /login o responde 401 JSON.
     */
    public static function requireLogin(): void
    {
        if (!Session::isLoggedIn()) {
            self::deny(
                'Acceso no autorizado. Por favor inicia sesión.',
                '/login',
                401
            );
        }
    }

    /**
     * Exige rol de administrador. Los clientes son enviados a su panel.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (!self::isAdmin()) {
            self::deny(
                'No tienes permisos para acceder a esta sección.',
                '/user/dashboard',
                403
            );
        }
    }

    /**
     * Exige rol de cliente. Los administradores son enviados a su panel.
     */
    public static function requireClient(): void
    {
        self::requireLogin();

        if (!self::isClient()) {
            self::deny(
                'Esta sección está disponible únicamente para clientes.',
                '/admin/dashboard',
                403
            );
        }
    }

    /**
     * Emite la respuesta de denegación y detiene la ejecución.
     */
    private static function deny(string $message, string $redirect, int $status): never
    {
        if (self::wantsJson()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            exit;
        }

        header('Location: ' . $redirect, true, 302);
        exit;
    }

    /**
     * Detecta si la petición espera una respuesta JSON (API o Fetch/AJAX).
     */
    private static function wantsJson(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

        return str_starts_with($path, '/api/')
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
            || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    }
}
