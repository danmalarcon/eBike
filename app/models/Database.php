<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Clase Database (Patrón Singleton)
 * 
 * Gestiona una única instancia de conexión PDO a MariaDB/MySQL.
 * Cumple con el estándar PSR-4 y las directrices de @architecture.md y @database.md.
 */
class Database
{
    /** @var self|null Instancia única de la clase */
    private static ?self $instance = null;

    /** @var PDO Conexión PDO activa */
    private PDO $connection;

    /**
     * Constructor privado para prevenir instanciación externa directa.
     * Carga la configuración desde app/config/database.php y establece la conexión PDO.
     * 
     * @throws RuntimeException Si el archivo de configuración no existe o es inválido.
     * @throws PDOException Si falla la conexión con el motor de base de datos.
     */
    private function __construct()
    {
        $configFile = dirname(__DIR__) . '/config/database.php';

        if (!file_exists($configFile)) {
            throw new RuntimeException("Archivo de configuración de base de datos no encontrado en: {$configFile}");
        }

        $config = require $configFile;

        if (!is_array($config)) {
            throw new RuntimeException("El archivo de configuración de base de datos debe retornar un array asociativo.");
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? 'ebike_ems',
            $config['charset'] ?? 'utf8mb4'
        );

        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                $options
            );
        } catch (PDOException $e) {
            throw new PDOException("Error de conexión con la base de datos '{$config['database']}': " . $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Prevenir la clonación del objeto Singleton.
     */
    private function __clone(): void
    {
    }

    /**
     * Prevenir la deserialización del objeto Singleton.
     * 
     * @throws RuntimeException
     */
    public function __wakeup(): void
    {
        throw new RuntimeException("No se permite deserializar una instancia de Database (Singleton).");
    }

    /**
     * Obtiene la instancia única de Database.
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Obtiene la conexión PDO activa.
     * 
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Método estático auxiliar para obtener directamente el objeto PDO.
     * 
     * @return PDO
     */
    public static function getPDO(): PDO
    {
        return self::getInstance()->getConnection();
    }
}
