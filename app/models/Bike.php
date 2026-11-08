<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo Bike
 * 
 * Gestiona el inventario, estado y operaciones CRUD sobre la tabla `bicicletas`.
 * Implementa la regla RN-007 para filtrado estricto de disponibilidad.
 * Esquema de datos referenciado desde @database.md.
 */
class Bike
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Genera un código QR identificador único para una bicicleta.
     * 
     * @return string
     */
    public static function generateQrCode(): string
    {
        return 'BIKE-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
    }

    /**
     * Obtiene el listado completo de bicicletas con la información de su estación actual.
     * 
     * @return array
     */
    public function getAll(): array
    {
        $sql = "SELECT b.id_bicicleta, b.estacion_act, b.codigo_qr, b.modelo, b.num_serie,
                       b.nivel_bateria, b.estado, b.kilometraje, b.ult_manten, b.prox_manten,
                       e.nombre AS estacion_nombre, e.codigo AS estacion_codigo
                FROM bicicletas b
                LEFT JOIN estaciones e ON b.estacion_act = e.id_estacion
                ORDER BY b.id_bicicleta DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una bicicleta por su ID primario.
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT b.id_bicicleta, b.estacion_act, b.codigo_qr, b.modelo, b.num_serie,
                       b.nivel_bateria, b.estado, b.kilometraje, b.ult_manten, b.prox_manten,
                       e.nombre AS estacion_nombre, e.codigo AS estacion_codigo
                FROM bicicletas b
                LEFT JOIN estaciones e ON b.estacion_act = e.id_estacion
                WHERE b.id_bicicleta = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $bike = $stmt->fetch(PDO::FETCH_ASSOC);
        return $bike !== false ? $bike : null;
    }

    /**
     * Obtiene únicamente las bicicletas elegibles para nuevo alquiler.
     * 
     * REGLA DE NEGOCIO RN-007 (Disponibilidad Estricta):
     * Una bicicleta solo se puede ofrecer si:
     * 1. Su estado es 'disponible'.
     * 2. Su nivel_bateria es estrictamente mayor al 20.00%.
     * 
     * @return array
     */
    public function getAvailableBikes(): array
    {
        $sql = "SELECT b.id_bicicleta, b.estacion_act, b.codigo_qr, b.modelo, b.num_serie,
                       b.nivel_bateria, b.estado, b.kilometraje, b.ult_manten, b.prox_manten,
                       e.nombre AS estacion_nombre, e.codigo AS estacion_codigo
                FROM bicicletas b
                LEFT JOIN estaciones e ON b.estacion_act = e.id_estacion
                WHERE b.estado = 'disponible' AND b.nivel_bateria > 20.00
                ORDER BY b.nivel_bateria DESC, b.id_bicicleta ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comprueba si un número de serie ya existe en la base de datos.
     * 
     * @param string $numSerie
     * @param int|null $excludeId ID a excluir en edición.
     * @return bool
     */
    public function numSerieExists(string $numSerie, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM bicicletas WHERE num_serie = :num_serie";
        if ($excludeId !== null) {
            $sql .= " AND id_bicicleta != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':num_serie', $numSerie, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Comprueba si un código QR ya existe.
     * 
     * @param string $qr
     * @param int|null $excludeId ID a excluir en edición.
     * @return bool
     */
    public function qrExists(string $qr, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM bicicletas WHERE codigo_qr = :codigo_qr";
        if ($excludeId !== null) {
            $sql .= " AND id_bicicleta != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':codigo_qr', $qr, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Registra una nueva bicicleta en el sistema (RF-005).
     * 
     * @param array $data ['modelo', 'num_serie', 'estacion_act', 'nivel_bateria', 'codigo_qr', 'estado', 'kilometraje']
     * @return bool
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO bicicletas (estacion_act, codigo_qr, modelo, num_serie, nivel_bateria, estado, kilometraje, ult_manten, prox_manten)
                VALUES (:estacion_act, :codigo_qr, :modelo, :num_serie, :nivel_bateria, :estado, :kilometraje, :ult_manten, :prox_manten)";

        $stmt = $this->db->prepare($sql);

        $codigoQr = !empty($data['codigo_qr']) ? $data['codigo_qr'] : self::generateQrCode();
        $estacionAct = !empty($data['estacion_act']) ? (int)$data['estacion_act'] : null;
        $nivelBateria = isset($data['nivel_bateria']) ? (float)$data['nivel_bateria'] : 100.00;
        $estado = $data['estado'] ?? 'disponible';
        $kilometraje = isset($data['kilometraje']) ? (float)$data['kilometraje'] : 0.00;
        $ultManten = !empty($data['ult_manten']) ? $data['ult_manten'] : null;
        $proxManten = !empty($data['prox_manten']) ? $data['prox_manten'] : null;

        $stmt->bindValue(':estacion_act', $estacionAct, $estacionAct === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':codigo_qr', $codigoQr, PDO::PARAM_STR);
        $stmt->bindValue(':modelo', $data['modelo'], PDO::PARAM_STR);
        $stmt->bindValue(':num_serie', $data['num_serie'], PDO::PARAM_STR);
        $stmt->bindValue(':nivel_bateria', $nivelBateria);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':kilometraje', $kilometraje);
        $stmt->bindValue(':ult_manten', $ultManten, $ultManten === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':prox_manten', $proxManten, $proxManten === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Actualiza la información de una bicicleta.
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE bicicletas 
                SET estacion_act = :estacion_act,
                    codigo_qr = :codigo_qr,
                    modelo = :modelo,
                    num_serie = :num_serie,
                    nivel_bateria = :nivel_bateria,
                    estado = :estado,
                    kilometraje = :kilometraje,
                    ult_manten = :ult_manten,
                    prox_manten = :prox_manten
                WHERE id_bicicleta = :id";

        $stmt = $this->db->prepare($sql);

        $estacionAct = !empty($data['estacion_act']) ? (int)$data['estacion_act'] : null;
        $nivelBateria = isset($data['nivel_bateria']) ? (float)$data['nivel_bateria'] : 100.00;
        $estado = $data['estado'] ?? 'disponible';
        $kilometraje = isset($data['kilometraje']) ? (float)$data['kilometraje'] : 0.00;
        $ultManten = !empty($data['ult_manten']) ? $data['ult_manten'] : null;
        $proxManten = !empty($data['prox_manten']) ? $data['prox_manten'] : null;

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estacion_act', $estacionAct, $estacionAct === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':codigo_qr', $data['codigo_qr'], PDO::PARAM_STR);
        $stmt->bindValue(':modelo', $data['modelo'], PDO::PARAM_STR);
        $stmt->bindValue(':num_serie', $data['num_serie'], PDO::PARAM_STR);
        $stmt->bindValue(':nivel_bateria', $nivelBateria);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':kilometraje', $kilometraje);
        $stmt->bindValue(':ult_manten', $ultManten, $ultManten === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':prox_manten', $proxManten, $proxManten === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Actualiza el nivel de batería de la bicicleta.
     * 
     * @param int $id
     * @param float $batteryLevel
     * @return bool
     */
    public function updateBattery(int $id, float $batteryLevel): bool
    {
        $batteryLevel = max(0.00, min(100.00, $batteryLevel));

        $sql = "UPDATE bicicletas SET nivel_bateria = :nivel_bateria WHERE id_bicicleta = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nivel_bateria', $batteryLevel);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza el estado de la bicicleta ('disponible', 'alquilada', 'mantenimiento', 'baja').
     * 
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE bicicletas SET estado = :estado WHERE id_bicicleta = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Asigna la bicicleta a una estación específica (o la deja sin estación si está en ruta).
     * 
     * @param int $id
     * @param int|null $stationId
     * @return bool
     */
    public function assignStation(int $id, ?int $stationId): bool
    {
        $sql = "UPDATE bicicletas SET estacion_act = :estacion_act WHERE id_bicicleta = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estacion_act', $stationId, $stationId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Registra el inicio de un mantenimiento para la bicicleta.
     * 
     * @param int $id
     * @param string|null $proxManten Fecha estimada de próximo mantenimiento.
     * @return bool
     */
    public function registerMaintenance(int $id, ?string $proxManten = null): bool
    {
        $sql = "UPDATE bicicletas 
                SET estado = 'mantenimiento',
                    ult_manten = CURRENT_TIMESTAMP,
                    prox_manten = :prox_manten
                WHERE id_bicicleta = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':prox_manten', $proxManten, $proxManten === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Elimina una bicicleta si no tiene alquileres vinculados.
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $sql = "DELETE FROM bicicletas WHERE id_bicicleta = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}
