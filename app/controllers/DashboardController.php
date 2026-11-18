<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\Bike;
use App\Models\Rental;
use App\Models\Station;

/**
 * Controlador DashboardController
 *
 * Renderiza los paneles post-login de Administrador y Cliente.
 * Valida que exista una sesión activa y aplica control de acceso por rol.
 */
class DashboardController
{
    private Rental  $rentalModel;
    private Station $stationModel;
    private Bike    $bikeModel;

    public function __construct()
    {
        $this->rentalModel  = new Rental();
        $this->stationModel = new Station();
        $this->bikeModel    = new Bike();
    }

    /**
     * GET /admin/dashboard
     *
     * Panel exclusivo para usuarios con rol_id = 1 (Administrador).
     */
    public function adminIndex(): void
    {
        $user = $this->requireSession();

        // Un cliente no puede acceder al panel administrativo.
        if (!$this->isAdmin($user)) {
            $this->redirect('/user/dashboard');
        }

        // Métricas reales de la operación.
        $bikes           = $this->bikeModel->getAll();
        $stations        = $this->stationModel->getAll();
        $totalBikes      = count($bikes);
        $activeStations  = count(array_filter(
            $stations,
            fn(array $station): bool => $station['estado'] === 'operativa'
        ));
        $todayRentals    = $this->rentalModel->countToday();
        $activeRentals   = $this->rentalModel->countActive();
        $openAlerts      = count(array_filter(
            $bikes,
            fn(array $bike): bool => (float)$bike['nivel_bateria'] <= 20.00
                || $bike['estado'] === 'mantenimiento'
        ));

        require_once dirname(__DIR__) . '/views/admin/dashboard.php';
    }

    /**
     * GET /user/dashboard
     *
     * Panel para clientes autenticados (rol_id = 2).
     * Carga el viaje activo, las estaciones operativas y el historial reciente.
     */
    public function userIndex(): void
    {
        $user = $this->requireSession();

        // El administrador tiene su propio panel.
        if ($this->isAdmin($user)) {
            $this->redirect('/admin/dashboard');
        }

        $userId = (int)($user['id_usuario'] ?? 0);

        $activeRental = $this->rentalModel->getActiveRentalByUser($userId);
        $stations     = $this->stationModel->getAll();
        $history      = $this->rentalModel->getRentalHistoryByUser($userId);

        // Filtrar solo estaciones operativas para el selector de origen/destino.
        $operativeStations = array_values(
            array_filter($stations, fn(array $station): bool => $station['estado'] === 'operativa')
        );

        require_once dirname(__DIR__) . '/views/user/dashboard.php';
    }

    /**
     * Alias de compatibilidad con el nombre anterior del método.
     *
     * @deprecated Usar userIndex().
     */
    public function userDashboard(): void
    {
        $this->userIndex();
    }

    /**
     * Garantiza que exista una sesión activa antes de renderizar un dashboard.
     *
     * @return array Datos del usuario autenticado.
     */
    private function requireSession(): array
    {
        if (!Session::isLoggedIn()) {
            $this->redirect('/login');
        }

        return Session::getUser() ?? [];
    }

    /**
     * Determina si el usuario autenticado es administrador.
     */
    private function isAdmin(array $user): bool
    {
        return (int)($user['rol_id'] ?? 0) === 1
            || ($user['codigo_rol'] ?? '') === 'admin';
    }

    /**
     * Emite una redirección HTTP y detiene la ejecución.
     */
    private function redirect(string $location): never
    {
        header('Location: ' . $location, true, 302);
        exit;
    }
}
