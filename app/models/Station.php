<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo Station
 * 
 * Gestiona la persistencia y operaciones CRUD sobre la tabla `estaciones`.
 * Utiliza exclusivamente PDO con Prepared Statements.
 * Esquema de datos referenciado desde @database.md.
 */
class Station
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene todas las estaciones registradas en el sistema.
     * 
     * @return array
     */
    public function getAll(): array
    {
        $sql = "SELECT e.id_estacion, e.codigo, e.nombre, e.direccion, e.coordenadas, 
                       e.capacidad, e.energia_disp, e.estado,
                       (SELECT COUNT(*) FROM bicicletas b WHERE b.estacion_act = e.id_estacion) AS total_bicicletas
                FROM estaciones e
                ORDER BY e.id_estacion DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una estación por su clave primaria `id_estacion`.
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id_estacion, codigo, nombre, direccion, coordenadas, capacidad, energia_disp, estado
                FROM estaciones
                WHERE id_estacion = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        return $station !== false ? $station : null;
    }

    /**
     * Busca una estación por su código único.
     * 
     * @param string $code
     * @return array|null
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT id_estacion, codigo, nombre, direccion, coordenadas, capacidad, energia_disp, estado
                FROM estaciones
                WHERE codigo = :codigo
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':codigo', $code, PDO::PARAM_STR);
        $stmt->execute();

        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        return $station !== false ? $station : null;
    }

    /**
     * Verifica si un código de estación ya existe (para evitar duplicados).
     * 
     * @param string $code
     * @param int|null $excludeId ID de la estación a excluir en caso de edición.
     * @return bool
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM estaciones WHERE codigo = :codigo";
        if ($excludeId !== null) {
            $sql .= " AND id_estacion != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':codigo', $code, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crea una nueva estación en la base de datos (RF-005).
     * 
     * @param array $data ['codigo', 'nombre', 'direccion', 'coordenadas', 'capacidad', 'energia_disp', 'estado']
     * @return bool
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO estaciones (codigo, nombre, direccion, coordenadas, capacidad, energia_disp, estado)
                VALUES (:codigo, :nombre, :direccion, :coordenadas, :capacidad, :energia_disp, :estado)";

        $stmt = $this->db->prepare($sql);

        $energiaDisp = isset($data['energia_disp']) && $data['energia_disp'] !== '' ? (float)$data['energia_disp'] : 0.00;
        $estado = $data['estado'] ?? 'operativa';

        $stmt->bindValue(':codigo', $data['codigo'], PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':direccion', $data['direccion'], PDO::PARAM_STR);
        $stmt->bindValue(':coordenadas', $data['coordenadas'], PDO::PARAM_STR);
        $stmt->bindValue(':capacidad', (int)$data['capacidad'], PDO::PARAM_INT);
        $stmt->bindValue(':energia_disp', $energiaDisp);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Actualiza los datos de una estación existente.
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE estaciones 
                SET codigo = :codigo, 
                    nombre = :nombre, 
                    direccion = :direccion, 
                    coordenadas = :coordenadas, 
                    capacidad = :capacidad, 
                    energia_disp = :energia_disp, 
                    estado = :estado
                WHERE id_estacion = :id";

        $stmt = $this->db->prepare($sql);

        $energiaDisp = isset($data['energia_disp']) && $data['energia_disp'] !== '' ? (float)$data['energia_disp'] : 0.00;
        $estado = $data['estado'] ?? 'operativa';

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $data['codigo'], PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':direccion', $data['direccion'], PDO::PARAM_STR);
        $stmt->bindValue(':coordenadas', $data['coordenadas'], PDO::PARAM_STR);
        $stmt->bindValue(':capacidad', (int)$data['capacidad'], PDO::PARAM_INT);
        $stmt->bindValue(':energia_disp', $energiaDisp);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Actualiza únicamente el estado operativo de la estación.
     * 
     * @param int $id
     * @param string $status 'operativa' | 'mantenimiento' | 'inactiva'
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE estaciones SET estado = :estado WHERE id_estacion = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Elimina una estación física si no tiene bicicletas o alquileres asociados.
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $sql = "DELETE FROM estaciones WHERE id_estacion = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            // Manejar violación de integridad referencial si hay referencias activas
            return false;
        }
    }

    /**
     * Cuenta la cantidad de bicicletas estacionadas actualmente en la estación.
     * 
     * @param int $id
     * @return int
     */
    public function getBikesCount(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM bicicletas WHERE estacion_act = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
}
