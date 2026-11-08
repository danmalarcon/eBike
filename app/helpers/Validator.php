<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Clase Validator
 * 
 * Helper estático para validación y sanitización de datos de entrada.
 * Cumple con las directivas de seguridad OWASP para prevenir inyecciones y XSS.
 */
class Validator
{
    /**
     * Sanitiza una cadena de texto eliminando etiquetas HTML y caracteres especiales.
     * 
     * @param string|null $input
     * @return string
     */
    public static function sanitizeString(?string $input): string
    {
        if ($input === null) {
            return '';
        }

        $trimmed = trim($input);
        return htmlspecialchars(strip_tags($trimmed), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Valida si un correo electrónico cumple con el formato estándar RFC 822.
     * 
     * @param string|null $email
     * @return bool
     */
    public static function validateEmail(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $sanitized = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($sanitized, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitiza una dirección de correo electrónico normalizándola a minúsculas.
     * 
     * @param string|null $email
     * @return string
     */
    public static function sanitizeEmail(?string $email): string
    {
        if ($input = trim((string)$email)) {
            return strtolower(filter_var($input, FILTER_SANITIZE_EMAIL));
        }

        return '';
    }

    /**
     * Valida que una contraseña cumpla con la longitud mínima requerida.
     * 
     * @param string $password
     * @param int $minLength
     * @return bool
     */
    public static function validatePassword(string $password, int $minLength = 8): bool
    {
        return mb_strlen($password) >= $minLength;
    }

    /**
     * Sanitiza un número telefónico permitiendo únicamente dígitos, +, - y espacios.
     * 
     * @param string|null $phone
     * @return string|null
     */
    public static function sanitizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d+\-\s]/', '', trim($phone));
        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Comprueba que los campos requeridos existan y no estén vacíos en un arreglo.
     * 
     * @param array $data
     * @param array $requiredFields
     * @return array Lista de campos faltantes o vacíos.
     */
    public static function checkRequired(array $data, array $requiredFields): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}
