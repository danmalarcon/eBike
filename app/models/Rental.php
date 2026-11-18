<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo Rental
 *
 * Gestiona el ciclo de vida completo de los alquileres sobre la tabla `alquileres`.
 * Utiliza transacciones PDO (beginTransaction / commit / rollBack) para garantizar
 * la integridad atómica de las operaciones de inicio y fin de viaje.
 *
 * COLUMNAS VIRTUALES (READ-ONLY según @database.md):
 *   - `duracion_minutos` (GENERATED ALWAYS): jamás se inserta ni actualiza desde PHP.
 *
 * TARIFA: $500 COP por minuto (RF-004 de @requirements.md).
 */
class Rental
{
    private PDO $db;

    /** Tarifa base por minuto en pesos colombianos (RF-004). */
    public const TARIFA_POR_MINUTO = 500.00;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Genera un UUID v4 conforme a RFC 4122 para identificar el alquiler.
     */
    public static function generateUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INICIO DE ALQUILER (RF-003)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Inicia un nuevo alquiler de forma atómica.
     *
     * Pasos dentro de la transacción:
     *   1. Re-verifica que la bicicleta siga disponible y con batería > 20% (RN-007).
     *   2. Inserta el registro en `alquileres` con estado 'en_curso'.
     *   3. Actualiza el estado de la bicicleta a 'alquilada'.
     *
     * @param int $userId      ID del usuario autenticado.
     * @param int $bikeId      ID de la bicicleta seleccionada.
     * @param int $stationId   ID de la estación de origen.
     * @return array           Datos del alquiler recién creado.
     * @throws RuntimeException Si la bicicleta ya no está disponible o hay fallo de BD.
     */
    public function startRental(int $userId, int $bikeId, int $stationId): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Re-verificar disponibilidad en la misma transacción (evita race conditions)
            $checkSql = "SELECT id_bicicleta, nivel_bateria, estado
                         FROM bicicletas
                         WHERE id_bicicleta = :id
                         LIMIT 1
                         FOR UPDATE";

            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->bindValue(':id', $bikeId, PDO::PARAM_INT);
            $checkStmt->execute();
            $bike = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$bike) {
                throw new RuntimeException('La bicicleta seleccionada no existe en el sistema.');
            }

            if ($bike['estado'] !== 'disponible') {
                throw new RuntimeException("La bicicleta ya no está disponible (estado actual: {$bike['estado']}).");
            }

            if ((float)$bike['nivel_bateria'] <= 20.00) {
                throw new RuntimeException(
                    "Nivel de batería insuficiente ({$bike['nivel_bateria']}%). " .
                    'Se requiere más del 20% para iniciar un viaje (RN-007).'
                );
            }

            // 2. Insertar el nuevo alquiler
            $uuid    = self::generateUuid();
            $sqlIns  = "INSERT INTO alquileres
                            (id_usuario, id_bicicleta, estacion_orig, uuid_alquiler, fecha_inicio, estado)
                        VALUES
                            (:id_usuario, :id_bicicleta, :estacion_orig, :uuid_alquiler, NOW(), 'en_curso')";

            $insStmt = $this->db->prepare($sqlIns);
            $insStmt->bindValue(':id_usuario',    $userId,    PDO::PARAM_INT);
            $insStmt->bindValue(':id_bicicleta',  $bikeId,    PDO::PARAM_INT);
            $insStmt->bindValue(':estacion_orig', $stationId, PDO::PARAM_INT);
            $insStmt->bindValue(':uuid_alquiler', $uuid,      PDO::PARAM_STR);
            $insStmt->execute();

            $rentalId = (int)$this->db->lastInsertId();

            // 3. Marcar la bicicleta como 'alquilada'
            $updSql  = "UPDATE bicicletas SET estado = 'alquilada' WHERE id_bicicleta = :id";
            $updStmt = $this->db->prepare($updSql);
            $updStmt->bindValue(':id', $bikeId, PDO::PARAM_INT);
            $updStmt->execute();

            $this->db->commit();

            return [
                'id_alquiler'   => $rentalId,
                'uuid_alquiler' => $uuid,
                'id_usuario'    => $userId,
                'id_bicicleta'  => $bikeId,
                'estacion_orig' => $stationId,
                'estado'        => 'en_curso',
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FINALIZACIÓN DE ALQUILER (RF-004)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Finaliza un alquiler de forma atómica.
     *
     * Pasos dentro de la transacción:
     *   1. Verifica que el alquiler exista y esté 'en_curso'.
     *   2. Registra fecha_fin, estacion_dest y costo_total en `alquileres`.
     *   3. Cambia estado del alquiler a 'completado'.
     *   4. Devuelve la bicicleta a 'disponible' y la ubica en la estación de destino.
     *
     * NOTA: `duracion_minutos` es columna VIRTUAL GENERATED — NUNCA se escribe.
     *       El costo se calcula en PHP: duracion * $500 COP/min.
     *
     * @param int    $rentalId     ID del alquiler a cerrar.
     * @param int    $endStationId ID de la estación de entrega.
     * @param int    $duration     Minutos transcurridos (calculado en RentalController).
     * @param float  $cost         Costo total calculado ($500 COP × minutos).
     * @return array               Datos del alquiler cerrado.
     * @throws RuntimeException    Si el alquiler no existe, ya fue cerrado o hay fallo de BD.
     */
    public function endRental(int $rentalId, int $endStationId, int $duration, float $cost): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Verificar estado actual del alquiler (con lock)
            $checkSql = "SELECT a.id_alquiler, a.id_bicicleta, a.estado, a.fecha_inicio
                         FROM alquileres a
                         WHERE a.id_alquiler = :id
                         LIMIT 1
                         FOR UPDATE";

            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->bindValue(':id', $rentalId, PDO::PARAM_INT);
            $checkStmt->execute();
            $rental = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$rental) {
                throw new RuntimeException('El alquiler indicado no existe en el sistema.');
            }

            if ($rental['estado'] !== 'en_curso') {
                throw new RuntimeException(
                    "El alquiler no puede finalizarse porque su estado actual es '{$rental['estado']}'."
                );
            }

            $bikeId = (int)$rental['id_bicicleta'];

            // 2 & 3. Actualizar el alquiler: fecha_fin, estacion_dest, costo_total, estado
            //         NO se toca `duracion_minutos` (columna GENERATED VIRTUAL).
            $updRentalSql = "UPDATE alquileres
                             SET fecha_fin      = NOW(),
                                 estacion_dest  = :estacion_dest,
                                 costo_total    = :costo_total,
                                 estado         = 'completado'
                             WHERE id_alquiler  = :id";

            $updRentalStmt = $this->db->prepare($updRentalSql);
            $updRentalStmt->bindValue(':id',           $rentalId,    PDO::PARAM_INT);
            $updRentalStmt->bindValue(':estacion_dest', $endStationId, PDO::PARAM_INT);
            $updRentalStmt->bindValue(':costo_total',  $cost);
            $updRentalStmt->execute();

            // 4. Devolver bicicleta a 'disponible' y ubicarla en la estación de destino
            $updBikeSql  = "UPDATE bicicletas
                            SET estado       = 'disponible',
                                estacion_act = :estacion_act
                            WHERE id_bicicleta = :id";

            $updBikeStmt = $this->db->prepare($updBikeSql);
            $updBikeStmt->bindValue(':estacion_act', $endStationId, PDO::PARAM_INT);
            $updBikeStmt->bindValue(':id',           $bikeId,       PDO::PARAM_INT);
            $updBikeStmt->execute();

            $this->db->commit();

            return [
                'id_alquiler'   => $rentalId,
                'id_bicicleta'  => $bikeId,
                'estacion_dest' => $endStationId,
                'duracion_min'  => $duration,
                'costo_total'   => $cost,
                'estado'        => 'completado',
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Busca el viaje activo (en_curso) de un usuario.
     * Un usuario solo puede tener un viaje activo a la vez.
     *
     * @param int $userId
     * @return array|null  Datos del alquiler con bicicleta y estación de origen, o null.
     */
    public function getActiveRentalByUser(int $userId): ?array
    {
        $sql = "SELECT a.id_alquiler, a.id_bicicleta, a.estacion_orig, a.uuid_alquiler,
                       a.fecha_inicio, a.estado,
                       b.modelo       AS bike_modelo,
                       b.codigo_qr    AS bike_qr,
                       b.nivel_bateria,
                       eo.nombre      AS estacion_orig_nombre,
                       eo.codigo      AS estacion_orig_codigo
                FROM alquileres a
                JOIN bicicletas  b  ON a.id_bicicleta = b.id_bicicleta
                JOIN estaciones  eo ON a.estacion_orig = eo.id_estacion
                WHERE a.id_usuario = :id_usuario
                  AND a.estado     = 'en_curso'
                ORDER BY a.fecha_inicio DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_usuario', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $rental = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rental !== false ? $rental : null;
    }

    /**
     * Obtiene un alquiler por su ID primario.
     *
     * @param int $rentalId
     * @return array|null
     */
    public function findById(int $rentalId): ?array
    {
        $sql = "SELECT a.id_alquiler, a.id_usuario, a.id_bicicleta,
                       a.estacion_orig, a.estacion_dest, a.uuid_alquiler,
                       a.fecha_inicio, a.fecha_fin, a.duracion_minutos,
                       a.costo_total, a.estado,
                       b.modelo    AS bike_modelo,
                       b.codigo_qr AS bike_qr,
                       eo.nombre   AS estacion_orig_nombre,
                       ed.nombre   AS estacion_dest_nombre
                FROM alquileres a
                JOIN bicicletas b   ON a.id_bicicleta  = b.id_bicicleta
                JOIN estaciones eo  ON a.estacion_orig  = eo.id_estacion
                LEFT JOIN estaciones ed ON a.estacion_dest = ed.id_estacion
                WHERE a.id_alquiler = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $rentalId, PDO::PARAM_INT);
        $stmt->execute();

        $rental = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rental !== false ? $rental : null;
    }

    /**
     * Obtiene el historial de viajes completados de un usuario (máx. 10 últimos).
     *
     * @param int $userId
     * @return array
     */
    public function getRentalHistoryByUser(int $userId): array
    {
        $sql = "SELECT a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
                       a.duracion_minutos, a.costo_total, a.estado,
                       b.modelo     AS bike_modelo,
                       eo.nombre    AS estacion_orig_nombre,
                       ed.nombre    AS estacion_dest_nombre
                FROM alquileres a
                JOIN bicicletas b    ON a.id_bicicleta = b.id_bicicleta
                JOIN estaciones eo   ON a.estacion_orig = eo.id_estacion
                LEFT JOIN estaciones ed ON a.estacion_dest = ed.id_estacion
                WHERE a.id_usuario = :id_usuario
                  AND a.estado     = 'completado'
                ORDER BY a.fecha_inicio DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_usuario', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta los alquileres iniciados durante el día actual.
     */
    public function countToday(): int
    {
        $sql = "SELECT COUNT(*) FROM alquileres WHERE DATE(fecha_inicio) = CURDATE()";

        return (int)$this->db->query($sql)->fetchColumn();
    }

    /**
     * Cuenta los alquileres que se encuentran en curso.
     */
    public function countActive(): int
    {
        $sql = "SELECT COUNT(*) FROM alquileres WHERE estado = 'en_curso'";

        return (int)$this->db->query($sql)->fetchColumn();
    }
}
