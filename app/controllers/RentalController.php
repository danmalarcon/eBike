<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Bike;
use App\Models\Rental;
use App\Models\Station;

/**
 * Controlador RentalController
 *
 * Gestiona el ciclo de vida de los alquileres (inicio y fin de viaje).
 * Aplica la regla RN-007 (batería > 20%) antes de confirmar el inicio.
 * Calcula la duración y el costo (RF-004: $500 COP / minuto) al finalizar.
 *
 * Todas las respuestas son estrictamente en formato JSON.
 */
class RentalController
{
    private Rental  $rentalModel;
    private Bike    $bikeModel;
    private Station $stationModel;

    public function __construct()
    {
        $this->rentalModel  = new Rental();
        $this->bikeModel    = new Bike();
        $this->stationModel = new Station();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INICIO DE VIAJE  (RF-003)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/rental/start
     *
     * Body JSON esperado: { "id_bicicleta": int, "estacion_orig": int }
     *
     * Validaciones:
     *   1. Sesión activa (Session::isLoggedIn()).
     *   2. Campos obligatorios presentes.
     *   3. Que el usuario NO tenga ya un viaje en curso.
     *   4. Que la bicicleta exista, esté 'disponible' y batería > 20% (RN-007).
     */
    public function startRental(): void
    {
        // 1. Autorización: solo clientes autenticados pueden alquilar.
        AuthMiddleware::requireClient();

        $input  = $this->getRequestData();
        $userId = (int)Session::getUserId();

        // 2. Campos obligatorios
        $bikeId    = isset($input['id_bicicleta'])  ? (int)$input['id_bicicleta']  : 0;
        $stationId = isset($input['estacion_orig']) ? (int)$input['estacion_orig'] : 0;

        if ($bikeId <= 0 || $stationId <= 0) {
            $this->jsonResponse(false, 'Debes seleccionar una bicicleta y una estación de origen.', null, 400);
            return;
        }

        // 3. Verificar que no haya viaje en curso para este usuario
        $activeRental = $this->rentalModel->getActiveRentalByUser($userId);
        if ($activeRental) {
            $this->jsonResponse(
                false,
                'Ya tienes un viaje en curso. Debes finalizarlo antes de iniciar uno nuevo.',
                ['id_alquiler' => $activeRental['id_alquiler']],
                409
            );
            return;
        }

        // 4. Verificar bicicleta: disponibilidad + regla RN-007
        $bike = $this->bikeModel->findById($bikeId);
        if (!$bike) {
            $this->jsonResponse(false, 'La bicicleta seleccionada no existe.', null, 404);
            return;
        }

        if ($bike['estado'] !== 'disponible') {
            $this->jsonResponse(
                false,
                "La bicicleta no está disponible (estado: {$bike['estado']}).",
                null,
                409
            );
            return;
        }

        // RN-007: batería estrictamente mayor al 20%
        if ((float)$bike['nivel_bateria'] <= 20.00) {
            $this->jsonResponse(
                false,
                "No se puede iniciar el viaje: nivel de batería insuficiente " .
                "({$bike['nivel_bateria']}%). Se requiere más del 20% (RN-007).",
                null,
                422
            );
            return;
        }

        // 5. Iniciar el alquiler (transacción atómica en el modelo)
        try {
            $rental = $this->rentalModel->startRental($userId, $bikeId, $stationId);
            $this->jsonResponse(true, '¡Viaje iniciado exitosamente! Disfruta tu recorrido.', $rental, 201);
        } catch (\RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (\Throwable $e) {
            $this->jsonResponse(false, 'Error interno al iniciar el viaje.', null, 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FIN DE VIAJE  (RF-004)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/rental/end
     *
     * Body JSON esperado: { "id_alquiler": int, "estacion_dest": int }
     *
     * Calcula automáticamente:
     *   - Duración en minutos (diferencia entre NOW() y fecha_inicio).
     *   - Costo total: duracion_minutos × $500 COP (RF-004).
     */
    public function endRental(): void
    {
        // 1. Autorización: solo clientes autenticados pueden finalizar sus viajes.
        AuthMiddleware::requireClient();

        $input  = $this->getRequestData();
        $userId = (int)Session::getUserId();

        // 2. Campos obligatorios
        $rentalId    = isset($input['id_alquiler'])   ? (int)$input['id_alquiler']   : 0;
        $endStationId = isset($input['estacion_dest']) ? (int)$input['estacion_dest'] : 0;

        if ($rentalId <= 0 || $endStationId <= 0) {
            $this->jsonResponse(false, 'Debes indicar el alquiler y la estación de destino.', null, 400);
            return;
        }

        // 3. Cargar el alquiler y verificar que pertenezca al usuario autenticado
        $rental = $this->rentalModel->findById($rentalId);
        if (!$rental) {
            $this->jsonResponse(false, 'El alquiler indicado no existe.', null, 404);
            return;
        }

        if ((int)$rental['id_usuario'] !== $userId) {
            $this->jsonResponse(false, 'No tienes permiso para finalizar este alquiler.', null, 403);
            return;
        }

        if ($rental['estado'] !== 'en_curso') {
            $this->jsonResponse(
                false,
                "Este alquiler no puede finalizarse (estado actual: {$rental['estado']}).",
                null,
                409
            );
            return;
        }

        // 4. Verificar que la estación de destino exista
        $endStation = $this->stationModel->findById($endStationId);
        if (!$endStation) {
            $this->jsonResponse(false, 'La estación de destino seleccionada no existe.', null, 404);
            return;
        }

        // 5. Calcular duración y costo (RF-004)
        //    duracion_minutos es VIRTUAL en BD → lo calculamos en PHP
        $fechaInicio  = new \DateTimeImmutable($rental['fecha_inicio']);
        $fechaFin     = new \DateTimeImmutable();
        $intervalSecs = $fechaFin->getTimestamp() - $fechaInicio->getTimestamp();
        $duration     = (int)max(1, ceil($intervalSecs / 60)); // mínimo 1 minuto

        $cost = round($duration * Rental::TARIFA_POR_MINUTO, 2); // $500 COP × minutos

        // 6. Finalizar el alquiler (transacción atómica en el modelo)
        try {
            $result = $this->rentalModel->endRental($rentalId, $endStationId, $duration, $cost);
            $this->jsonResponse(true, '¡Viaje finalizado exitosamente!', array_merge($result, [
                'estacion_dest_nombre' => $endStation['nombre'],
                'tarifa_por_minuto'    => Rental::TARIFA_POR_MINUTO,
            ]), 200);
        } catch (\RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (\Throwable $e) {
            $this->jsonResponse(false, 'Error interno al finalizar el viaje.', null, 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSULTAS AUXILIARES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/rental/active  — Retorna el viaje activo del usuario autenticado.
     */
    public function getActive(): void
    {
        AuthMiddleware::requireLogin();

        $rental = $this->rentalModel->getActiveRentalByUser((int)Session::getUserId());
        $this->jsonResponse(true, 'Viaje activo recuperado.', $rental);
    }

    /**
     * GET /api/rental/history  — Retorna los últimos 10 viajes completados del usuario.
     */
    public function getHistory(): void
    {
        AuthMiddleware::requireLogin();

        $history = $this->rentalModel->getRentalHistoryByUser((int)Session::getUserId());
        $this->jsonResponse(true, 'Historial de viajes recuperado.', $history);
    }

    /**
     * GET /api/bikes/by-station  — Retorna bicicletas disponibles en una estación (RN-007).
     * Query param: ?estacion_id=N
     */
    public function getAvailableByStation(): void
    {
        AuthMiddleware::requireLogin();

        $stationId = isset($_GET['estacion_id']) ? (int)$_GET['estacion_id'] : 0;

        if ($stationId <= 0) {
            // Si no se filtra por estación, retornar todas las disponibles
            $bikes = $this->bikeModel->getAvailableBikes();
        } else {
            $bikes = $this->getAvailableBikesByStation($stationId);
        }

        $this->jsonResponse(true, 'Bicicletas disponibles recuperadas.', $bikes);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MÉTODOS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filtra las bicicletas disponibles (RN-007) en una estación específica.
     */
    private function getAvailableBikesByStation(int $stationId): array
    {
        $all = $this->bikeModel->getAvailableBikes();
        return array_values(array_filter($all, fn($b) => (int)$b['estacion_act'] === $stationId));
    }

    /**
     * Lee el body de la petición como JSON o como $_POST.
     */
    private function getRequestData(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }

    /**
     * Emite la respuesta HTTP estructurada en JSON y detiene la ejecución.
     */
    private function jsonResponse(bool $success, string $message, mixed $data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = ['success' => $success, 'message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
