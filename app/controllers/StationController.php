<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Validator;
use App\Models\Station;
use Exception;

/**
 * Controlador StationController
 * 
 * Gestiona la administración de estaciones de bicicletas para el panel de administración.
 * Despacha vistas HTML y provee endpoints JSON para operaciones asíncronas.
 */
class StationController
{
    private Station $stationModel;

    public function __construct()
    {
        $this->stationModel = new Station();
    }

    /**
     * Renderiza la vista principal del listado y gestión de estaciones (Admin).
     */
    public function index(): void
    {
        $stations = $this->stationModel->getAll();
        require_once dirname(__DIR__) . '/views/admin/stations/index.php';
    }

    /**
     * Retorna los datos de una estación en formato JSON para rellenar el formulario modal de edición.
     */
    public function getStationJson(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de estación no válido.', null, 400);
            return;
        }

        $station = $this->stationModel->findById($id);
        if (!$station) {
            $this->jsonResponse(false, 'Estación no encontrada.', null, 404);
            return;
        }

        $this->jsonResponse(true, 'Estación recuperada.', $station);
    }

    /**
     * Procesa la creación de una nueva estación (RF-005).
     */
    public function create(): void
    {
        $input = $this->getRequestData();

        $missing = Validator::checkRequired($input, ['codigo', 'nombre', 'direccion', 'coordenadas', 'capacidad']);
        if (!empty($missing)) {
            $this->jsonResponse(false, 'Campos obligatorios faltantes: ' . implode(', ', $missing), null, 400);
            return;
        }

        $codigo      = strtoupper(trim(Validator::sanitizeString($input['codigo'])));
        $nombre      = Validator::sanitizeString($input['nombre']);
        $direccion   = Validator::sanitizeString($input['direccion']);
        $coordenadas = Validator::sanitizeString($input['coordenadas']);
        $capacidad   = (int)$input['capacidad'];
        $energiaDisp = isset($input['energia_disp']) && $input['energia_disp'] !== '' ? (float)$input['energia_disp'] : 0.00;
        $estado      = $input['estado'] ?? 'operativa';

        if ($capacidad <= 0) {
            $this->jsonResponse(false, 'La capacidad de la estación debe ser un entero mayor a 0.', null, 400);
            return;
        }

        $validStates = ['operativa', 'mantenimiento', 'inactiva'];
        if (!in_array($estado, $validStates, true)) {
            $this->jsonResponse(false, 'Estado no válido. Valores permitidos: operativa, mantenimiento, inactiva.', null, 400);
            return;
        }

        if ($this->stationModel->codeExists($codigo)) {
            $this->jsonResponse(false, "El código de estación '{$codigo}' ya está registrado.", null, 409);
            return;
        }

        try {
            $created = $this->stationModel->create([
                'codigo'       => $codigo,
                'nombre'       => $nombre,
                'direccion'    => $direccion,
                'coordenadas'  => $coordenadas,
                'capacidad'    => $capacidad,
                'energia_disp' => $energiaDisp,
                'estado'       => $estado,
            ]);

            if ($created) {
                $this->jsonResponse(true, 'Estación creada exitosamente.', null, 201);
            } else {
                $this->jsonResponse(false, 'No se pudo registrar la estación.', null, 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, 'Error interno del servidor al crear la estación.', null, 500);
        }
    }

    /**
     * Procesa la actualización de datos de una estación existente.
     */
    public function update(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_estacion'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, 'Identificador de estación inválido.', null, 400);
            return;
        }

        $missing = Validator::checkRequired($input, ['codigo', 'nombre', 'direccion', 'coordenadas', 'capacidad']);
        if (!empty($missing)) {
            $this->jsonResponse(false, 'Campos obligatorios faltantes: ' . implode(', ', $missing), null, 400);
            return;
        }

        $codigo      = strtoupper(trim(Validator::sanitizeString($input['codigo'])));
        $nombre      = Validator::sanitizeString($input['nombre']);
        $direccion   = Validator::sanitizeString($input['direccion']);
        $coordenadas = Validator::sanitizeString($input['coordenadas']);
        $capacidad   = (int)$input['capacidad'];
        $energiaDisp = isset($input['energia_disp']) && $input['energia_disp'] !== '' ? (float)$input['energia_disp'] : 0.00;
        $estado      = $input['estado'] ?? 'operativa';

        if ($capacidad <= 0) {
            $this->jsonResponse(false, 'La capacidad de la estación debe ser un entero mayor a 0.', null, 400);
            return;
        }

        $validStates = ['operativa', 'mantenimiento', 'inactiva'];
        if (!in_array($estado, $validStates, true)) {
            $this->jsonResponse(false, 'Estado de estación no válido.', null, 400);
            return;
        }

        if ($this->stationModel->codeExists($codigo, $id)) {
            $this->jsonResponse(false, "El código '{$codigo}' ya está asignado a otra estación.", null, 409);
            return;
        }

        try {
            $updated = $this->stationModel->update($id, [
                'codigo'       => $codigo,
                'nombre'       => $nombre,
                'direccion'    => $direccion,
                'coordenadas'  => $coordenadas,
                'capacidad'    => $capacidad,
                'energia_disp' => $energiaDisp,
                'estado'       => $estado,
            ]);

            if ($updated) {
                $this->jsonResponse(true, 'Estación actualizada exitosamente.', null, 200);
            } else {
                $this->jsonResponse(false, 'No se pudo actualizar la estación.', null, 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, 'Error interno al actualizar la estación.', null, 500);
        }
    }

    /**
     * Procesa el cambio rápido de estado de una estación.
     */
    public function changeStatus(): void
    {
        $input = $this->getRequestData();

        $id = (int)($input['id_estacion'] ?? 0);
        $status = (string)($input['estado'] ?? '');

        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de estación inválido.', null, 400);
            return;
        }

        $validStates = ['operativa', 'mantenimiento', 'inactiva'];
        if (!in_array($status, $validStates, true)) {
            $this->jsonResponse(false, 'Estado no válido.', null, 400);
            return;
        }

        $updated = $this->stationModel->updateStatus($id, $status);
        if ($updated) {
            $this->jsonResponse(true, "Estado de la estación cambiado a '{$status}'.", null, 200);
        } else {
            $this->jsonResponse(false, 'No se pudo cambiar el estado de la estación.', null, 500);
        }
    }

    /**
     * Procesa la eliminación de una estación.
     */
    public function delete(): void
    {
        $input = $this->getRequestData();
        $id = (int)($input['id_estacion'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(false, 'ID de estación no válido.', null, 400);
            return;
        }

        $bikesCount = $this->stationModel->getBikesCount($id);
        if ($bikesCount > 0) {
            $this->jsonResponse(false, "No se puede eliminar la estación porque tiene {$bikesCount} bicicleta(s) estacionadas.", null, 409);
            return;
        }

        $deleted = $this->stationModel->delete($id);
        if ($deleted) {
            $this->jsonResponse(true, 'Estación eliminada correctamente.', null, 200);
        } else {
            $this->jsonResponse(false, 'No se pudo eliminar la estación (puede contener registros asociados en el historial).', null, 409);
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
     * Emite una respuesta en formato JSON estructurado y finaliza la ejecución.
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
