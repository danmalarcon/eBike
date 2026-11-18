<?php

declare(strict_types=1);

/**
 * Enrutador de la Aplicación - eBike Management System (EMS)
 *
 * Mapea las peticiones HTTP (método + URI) hacia los controladores correspondientes.
 */

use App\Controllers\AuthController;
use App\Controllers\BikeController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\RentalController;
use App\Controllers\StationController;

$method     = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Normalizar la ruta eliminando prefijos de subdirectorios (ej: /ebike/public)
if ($scriptName !== '/' && $scriptName !== '' && strpos($requestUri, $scriptName) === 0) {
    $path = substr($requestUri, strlen($scriptName));
} else {
    $path = $requestUri;
}

// Remover prefijo /index.php si existe en la URL
if (strpos($path, '/index.php') === 0) {
    $path = substr($path, strlen('/index.php'));
}

$path = '/' . trim($path, '/');

// Compatibilidad con enlaces del diseño estático del frontend.
$legacyRedirects = [
    '/index.html'    => '/',
    '/login.html'    => '/login',
    '/registro.html' => '/register',
];

if ($method === 'GET' && isset($legacyRedirects[$path])) {
    header('Location: ' . $legacyRedirects[$path], true, 301);
    exit;
}

$homeController      = new HomeController();
$authController      = new AuthController();
$stationController   = new StationController();
$bikeController      = new BikeController();
$rentalController    = new RentalController();
$dashboardController = new DashboardController();

// Mapeo y despacho de rutas
switch ("{$method} {$path}") {
    // ─────────────────────────────────────────────────────────────────────────
    // Portada pública
    // ─────────────────────────────────────────────────────────────────────────
    case 'GET /':
        $homeController->index();
        break;

    // ─────────────────────────────────────────────────────────────────────────
    // Módulo IAM (Autenticación y Sesiones)
    // ─────────────────────────────────────────────────────────────────────────
    case 'GET /login':
        $authController->showLogin();
        break;

    case 'POST /login':
        $authController->login();
        break;

    case 'GET /register':
        $authController->showRegister();
        break;

    case 'POST /register':
        $authController->register();
        break;

    case 'GET /logout':
    case 'POST /logout':
        $authController->logout();
        break;

    // ─────────────────────────────────────────────────────────────────────────
    // Paneles post-login (Dashboards)
    // ─────────────────────────────────────────────────────────────────────────
    case 'GET /admin/dashboard':
        $dashboardController->adminIndex();
        break;

    case 'GET /user/dashboard':
        $dashboardController->userIndex();
        break;

    // ─────────────────────────────────────────────────────────────────────────
    // Core Engine: Alquileres (API JSON)
    // ─────────────────────────────────────────────────────────────────────────
    case 'POST /api/rental/start':
        $rentalController->startRental();
        break;

    case 'POST /api/rental/end':
        $rentalController->endRental();
        break;

    case 'GET /api/rental/active':
        $rentalController->getActive();
        break;

    case 'GET /api/rental/history':
        $rentalController->getHistory();
        break;

    case 'GET /api/bikes/by-station':
        $rentalController->getAvailableByStation();
        break;

    // ─────────────────────────────────────────────────────────────────────────
    // Módulo Gestión de Flota: Estaciones (Admin)
    // ─────────────────────────────────────────────────────────────────────────
    case 'GET /admin/stations':
        $stationController->index();
        break;

    case 'GET /admin/stations/get':
        $stationController->getStationJson();
        break;

    case 'POST /admin/stations/create':
        $stationController->create();
        break;

    case 'POST /admin/stations/update':
        $stationController->update();
        break;

    case 'POST /admin/stations/status':
        $stationController->changeStatus();
        break;

    case 'POST /admin/stations/delete':
        $stationController->delete();
        break;

    // ─────────────────────────────────────────────────────────────────────────
    // Módulo Gestión de Flota: Bicicletas (Admin)
    // ─────────────────────────────────────────────────────────────────────────
    case 'GET /admin/bikes':
        $bikeController->index();
        break;

    case 'GET /admin/bikes/get':
        $bikeController->getBikeJson();
        break;

    case 'GET /admin/bikes/available':
        $bikeController->available();
        break;

    case 'POST /admin/bikes/create':
        $bikeController->create();
        break;

    case 'POST /admin/bikes/update':
        $bikeController->update();
        break;

    case 'POST /admin/bikes/battery':
        $bikeController->updateBattery();
        break;

    case 'POST /admin/bikes/status':
        $bikeController->updateStatus();
        break;

    case 'POST /admin/bikes/maintenance':
        $bikeController->maintenance();
        break;

    case 'POST /admin/bikes/delete':
        $bikeController->delete();
        break;

    // ─────────────────────────────────────────────────────────────────────────
    // 404 — Recurso no encontrado
    // ─────────────────────────────────────────────────────────────────────────
    default:
        http_response_code(404);
        $title = '404 - Página no encontrada';
        require_once dirname(__DIR__) . '/views/layouts/header.php';
        ?>
        <div class="container py-5 text-center">
            <div class="my-5">
                <i class="bi bi-exclamation-triangle text-warning display-1"></i>
                <h1 class="display-5 fw-bold mt-3">404 - Página No Encontrada</h1>
                <p class="lead text-muted">La ruta <code><?= htmlspecialchars($path) ?></code> no existe en el sistema.</p>
                <a href="/" class="btn btn-success mt-3">
                    <i class="bi bi-house-door me-1"></i>Volver al Inicio
                </a>
            </div>
        </div>
        <?php
        require_once dirname(__DIR__) . '/views/layouts/footer.php';
        break;
}
