<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Bike;
use App\Models\Station;

/**
 * Controlador HomeController
 *
 * Renderiza la página principal pública del sistema (landing page).
 * Expone la disponibilidad real de bicicletas y estaciones registradas.
 */
class HomeController
{
    private Bike $bikeModel;
    private Station $stationModel;

    public function __construct()
    {
        $this->bikeModel    = new Bike();
        $this->stationModel = new Station();
    }

    /**
     * GET /
     *
     * Carga las bicicletas disponibles (RN-007) y las estaciones registradas
     * para mostrarlas en la portada pública.
     */
    public function index(): void
    {
        $availableBikes = $this->bikeModel->getAvailableBikes();
        $stations       = $this->stationModel->getAll();

        // Bicicletas disponibles agrupadas por estación (para el muelle visual).
        $availableByStation = [];
        foreach ($availableBikes as $bike) {
            $stationId = (int)($bike['estacion_act'] ?? 0);
            if ($stationId > 0) {
                $availableByStation[$stationId] = ($availableByStation[$stationId] ?? 0) + 1;
            }
        }

        require_once dirname(__DIR__) . '/views/home/index.php';
    }
}
