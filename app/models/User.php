<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo User
 * 
 * Gestiona el acceso y persistencia de datos para la entidad `usuarios`.
 * Sigue el patrón MVC y utiliza exclusivamente PDO con Prepared Statements.
 * Esquema de datos referenciado desde @database.md.
 */
class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Genera un identificador universal único (UUID v4) conforme a RFC 4122.
     * 
     * @return string
     */
    public static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // versión 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // variante RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Registra un nuevo usuario en la base de datos.
     * Encripta la contraseña usando bcrypt (cost 12) y asigna un UUID único.
     * Rol por defecto: 2 (Cliente, según RF-001 de @requirements.md).
     * 
     * @param array $data ['nombre', 'email', 'password', 'telefono', 'rol_id' (opcional), 'estado' (opcional)]
     * @return bool
     * @throws RuntimeException|PDOException
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO usuarios (rol_id, uuid, nombre, email, password_hash, telefono, estado)
                VALUES (:rol_id, :uuid, :nombre, :email, :password_hash, :telefono, :estado)";

        $stmt = $this->db->prepare($sql);

        // Generar UUID único
        $uuid = $data['uuid'] ?? self::generateUuid();

        // Cifrado obligatorio con bcrypt (cost 12) según RN-004
        $password = $data['password'] ?? '';
        if (empty($password) && empty($data['password_hash'])) {
            throw new RuntimeException("Se requiere una contraseña para registrar el usuario.");
        }

        $passwordHash = !empty($password)
            ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
            : $data['password_hash'];

        // Parámetros basados estrictamente en @database.md
        $rolId = (int)($data['rol_id'] ?? 2); // 2 = cliente por defecto (RF-001)
        $estado = $data['estado'] ?? 'activo';
        $telefono = !empty($data['telefono']) ? $data['telefono'] : null;

        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->bindValue(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $telefono, $telefono === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Busca un usuario por su dirección de correo electrónico (para login y verificación de unicidad).
     * 
     * @param string $email
     * @return array|null Datos del usuario y su rol, o null si no se encuentra.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT u.id_usuario, u.rol_id, u.uuid, u.nombre, u.email, u.password_hash,
                       u.telefono, u.estado, u.fecha_registro,
                       r.codigo_rol, r.nombre_rol
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id_rol
                WHERE u.email = :email
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Busca un usuario por su ID primario.
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT u.id_usuario, u.rol_id, u.uuid, u.nombre, u.email,
                       u.telefono, u.estado, u.fecha_registro,
                       r.codigo_rol, r.nombre_rol
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id_rol
                WHERE u.id_usuario = :id_usuario
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_usuario', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Comprueba si un correo ya se encuentra registrado (RN-001).
     * 
     * @param string $email
     * @return bool
     */
    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }
}
