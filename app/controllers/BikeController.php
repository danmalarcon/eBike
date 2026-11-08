<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Validator;
use App\Models\Bike;
use App\Models\Station;
use Exception;

/**
 * Controlador BikeController
 * 
 * Gestiona el inventario de bicicletas, telemetría de batería, asignación a estaciones
 * y cumplimiento de la regla de disponibilidad estricta (RN-007).
 */
class BikeController
{
    private Bike $bikeModel;
    private Station $stationModel;

    public function __construct()
    {
        $this->bikeModel = new Bike();
        $this->stationModel = new Station();
    }

    /**
     * Renderiza la vista principal de inventario de bicicletas (Admin).
     */
    public function index(): void
    {
        $bikes = $this->bikeModel->getAll();
        $stations = $this->stationModel->getAll();
        require_once dirname(__DIR__) . '/views/admin/bikes/index.php';
    }

    /**
     * Retorna los datos de una bicicleta en JSON para pre-cargar el modal de edición.
     */
    public function getBikeJson(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de bicicleta no válido.', null, 400);
            return;
        }

        $bike = $this->bikeModel->findById($id);
        if (!$bike) {
            $this->jsonResponse(false, 'Bicicleta no encontrada.', null, 404);
            return;
        }

        $this->jsonResponse(true, 'Bicicleta recuperada.', $bike);
    }

    /**
     * Retorna únicamente las bicicletas disponibles que cumplen la regla RN-007 (batería > 20% y estado 'disponible').
     */
    public function available(): void
    {
        $availableBikes = $this->bikeModel->getAvailableBikes();
        $this->jsonResponse(true, 'Listado de bicicletas disponibles.', $availableBikes);
    }

    /**
     * Procesa la creación de una nueva bicicleta (RF-005).
     */
    public function create(): void
    {
        $input = $this->getRequestData();

        $missing = Validator::checkRequired($input, ['modelo', 'num_serie']);
        if (!empty($missing)) {
            $this->jsonResponse(false, 'Campos obligatorios faltantes: ' . implode(', ', $missing), null, 400);
            return;
        }

        $modelo       = Validator::sanitizeString($input['modelo']);
        $numSerie     = strtoupper(trim(Validator::sanitizeString($input['num_serie'])));
        $codigoQr     = !empty($input['codigo_qr']) ? trim(Validator::sanitizeString($input['codigo_qr'])) : Bike::generateQrCode();
        $estacionAct  = !empty($input['estacion_act']) ? (int)$input['estacion_act'] : null;
        $nivelBateria = isset($input['nivel_bateria']) ? max(0.00, min(100.00, (float)$input['nivel_bateria'])) : 100.00;
        $estado       = $input['estado'] ?? 'disponible';
        $kilometraje  = isset($input['kilometraje']) ? max(0.00, (float)$input['kilometraje']) : 0.00;

        $validStates = ['disponible', 'alquilada', 'mantenimiento', 'baja'];
        if (!in_array($estado, $validStates, true)) {
            $this->jsonResponse(false, 'Estado no válido.', null, 400);
            return;
        }

        if ($this->bikeModel->numSerieExists($numSerie)) {
            $this->jsonResponse(false, "El número de serie '{$numSerie}' ya está registrado.", null, 409);
            return;
        }

        if ($this->bikeModel->qrExists($codigoQr)) {
            $this->jsonResponse(false, "El código QR '{$codigoQr}' ya está asignado.", null, 409);
            return;
        }

        try {
            $created = $this->bikeModel->create([
                'modelo'        => $modelo,
                'num_serie'     => $numSerie,
                'codigo_qr'     => $codigoQr,
                'estacion_act'  => $estacionAct,
                'nivel_bateria' => $nivelBateria,
                'estado'        => $estado,
                'kilometraje'   => $kilometraje,
            ]);

            if ($created) {
                $this->jsonResponse(true, 'Bicicleta registrada exitosamente.', null, 201);
            } else {
                $this->jsonResponse(false, 'No se pudo registrar la bicicleta.', null, 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, 'Error interno del servidor al crear la bicicleta.', null, 500);
        }
    }

    /**
     * Procesa la actualización de los datos de una bicicleta.
     */
    public function update(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_bicicleta'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de bicicleta no válido.', null, 400);
            return;
        }

        $missing = Validator::checkRequired($input, ['modelo', 'num_serie', 'codigo_qr']);
        if (!empty($missing)) {
            $this->jsonResponse(false, 'Campos obligatorios faltantes: ' . implode(', ', $missing), null, 400);
            return;
        }

        $modelo       = Validator::sanitizeString($input['modelo']);
        $numSerie     = strtoupper(trim(Validator::sanitizeString($input['num_serie'])));
        $codigoQr     = trim(Validator::sanitizeString($input['codigo_qr']));
        $estacionAct  = !empty($input['estacion_act']) ? (int)$input['estacion_act'] : null;
        $nivelBateria = isset($input['nivel_bateria']) ? max(0.00, min(100.00, (float)$input['nivel_bateria'])) : 100.00;
        $estado       = $input['estado'] ?? 'disponible';
        $kilometraje  = isset($input['kilometraje']) ? max(0.00, (float)$input['kilometraje']) : 0.00;

        $validStates = ['disponible', 'alquilada', 'mantenimiento', 'baja'];
        if (!in_array($estado, $validStates, true)) {
            $this->jsonResponse(false, 'Estado no válido.', null, 400);
            return;
        }

        if ($this->bikeModel->numSerieExists($numSerie, $id)) {
            $this->jsonResponse(false, "El número de serie '{$numSerie}' ya pertenece a otra bicicleta.", null, 409);
            return;
        }

        if ($this->bikeModel->qrExists($codigoQr, $id)) {
            $this->jsonResponse(false, "El código QR '{$codigoQr}' ya pertenece a otra bicicleta.", null, 409);
            return;
        }

        try {
            $updated = $this->bikeModel->update($id, [
                'modelo'        => $modelo,
                'num_serie'     => $numSerie,
                'codigo_qr'     => $codigoQr,
                'estacion_act'  => $estacionAct,
                'nivel_bateria' => $nivelBateria,
                'estado'        => $estado,
                'kilometraje'   => $kilometraje,
            ]);

            if ($updated) {
                $this->jsonResponse(true, 'Bicicleta actualizada exitosamente.', null, 200);
            } else {
                $this->jsonResponse(false, 'No se pudo actualizar la bicicleta.', null, 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, 'Error interno al actualizar la bicicleta.', null, 500);
        }
    }

    /**
     * Actualiza el nivel de batería de una bicicleta.
     */
    public function updateBattery(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_bicicleta'] ?? 0);
        if ($id <= 0 || !isset($input['nivel_bateria'])) {
            $this->jsonResponse(false, 'Parámetros incompletos.', null, 400);
            return;
        }

        $batteryLevel = max(0.00, min(100.00, (float)$input['nivel_bateria']));
        $updated = $this->bikeModel->updateBattery($id, $batteryLevel);

        if ($updated) {
            $msg = "Nivel de batería actualizado al {$batteryLevel}%.";
            if ($batteryLevel <= 20.00) {
                $msg .= " Nota: La bicicleta ha quedado bloqueada para alquileres por regla RN-007 (batería <= 20%).";
            }
            $this->jsonResponse(true, $msg, ['nivel_bateria' => $batteryLevel], 200);
        } else {
            $this->jsonResponse(false, 'No se pudo actualizar el nivel de batería.', null, 500);
        }
    }

    /**
     * Cambia el estado de una bicicleta.
     */
    public function updateStatus(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_bicicleta'] ?? 0);
        $status = (string)($input['estado'] ?? '');

        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de bicicleta inválido.', null, 400);
            return;
        }

        $validStates = ['disponible', 'alquilada', 'mantenimiento', 'baja'];
        if (!in_array($status, $validStates, true)) {
            $this->jsonResponse(false, 'Estado no válido.', null, 400);
            return;
        }

        $updated = $this->bikeModel->updateStatus($id, $status);
        if ($updated) {
            $this->jsonResponse(true, "Estado de la bicicleta cambiado a '{$status}'.", null, 200);
        } else {
            $this->jsonResponse(false, 'No se pudo cambiar el estado de la bicicleta.', null, 500);
        }
    }

    /**
     * Asigna o reubica una bicicleta en una estación.
     */
    public function assignStation(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_bicicleta'] ?? 0);
        $stationId = !empty($input['estacion_act']) ? (int)$input['estacion_act'] : null;

        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de bicicleta inválido.', null, 400);
            return;
        }

        $updated = $this->bikeModel->assignStation($id, $stationId);
        if ($updated) {
            $this->jsonResponse(true, 'Ubicación de la estación actualizada correctamente.', null, 200);
        } else {
            $this->jsonResponse(false, 'No se pudo reasignar la estación.', null, 500);
        }
    }

    /**
     * Registra el envío de una bicicleta a mantenimiento.
     */
    public function maintenance(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_bicicleta'] ?? 0);
        $proxManten = !empty($input['prox_manten']) ? (string)$input['prox_manten'] : null;

        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de bicicleta inválido.', null, 400);
            return;
        }

        $updated = $this->bikeModel->registerMaintenance($id, $proxManten);
        if ($updated) {
            $this->jsonResponse(true, 'Bicicleta enviada a mantenimiento. Estado actualizado a mantenimiento.', null, 200);
        } else {
            $this->jsonResponse(false, 'No se pudo registrar el mantenimiento.', null, 500);
        }
    }

    /**
     * Procesa la eliminación de una bicicleta.
     */
    public function delete(): void
    {
        $input = $this->getRequestData();
        $id = (int)($input['id_bicicleta'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de bicicleta inválido.', null, 400);
            return;
        }

        $deleted = $this->bikeModel->delete($id);
        if ($deleted) {
            $this->jsonResponse(true, 'Bicicleta eliminada correctamente del inventario.', null, 200);
        } else {
            $this->jsonResponse(false, 'No se pudo eliminar la bicicleta (puede estar asociada a viajes o alquileres históricos).', null, 409);
        }
    }

    /**
     * Obtiene y normaliza los datos de la petición (JSON o $_POST).
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
     * Emite una respuesta estructurada en formato JSON y finaliza la ejecución.
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
