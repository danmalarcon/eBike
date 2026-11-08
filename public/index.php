<?php

declare(strict_types=1);

/**
 * Front Controller (Entry Point) - eBike Management System (EMS)
 * 
 * Canaliza todas las peticiones hacia el enrutador central y la arquitectura MVC.
 */

// 1. Carga del Autoloader de Composer (PSR-4)
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Database;

try {
    // 2. Inicializar conexión a la base de datos mediante el Singleton PDO
    Database::getPDO();

    // 3. Cargar y despachar el enrutador de la aplicación
    require_once dirname(__DIR__) . '/app/config/routes.php';

} catch (\PDOException $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Conexión - eBike EMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center min-vh-100">
        <div class="container">
            <div class="card shadow-sm border-danger mx-auto" style="max-width: 600px;">
                <div class="card-body p-4 text-center">
                    <h1 class="text-danger h3 mb-3">Fallo en la Conexión a la Base de Datos 🚨</h1>
                    <p class="text-muted">No se pudo establecer comunicación con el motor MariaDB/MySQL.</p>
                    <div class="alert alert-warning text-start small mb-3">
                        <strong>Detalle técnico:</strong> <?= htmlspecialchars($e->getMessage()) ?>
                    </div>
                    <p class="small text-muted mb-0">Verifica que tu servidor MySQL esté activo y que los parámetros en <code>app/config/database.php</code> sean correctos.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
} catch (\Throwable $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error del Sistema - eBike EMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center min-vh-100">
        <div class="container">
            <div class="card shadow-sm border-warning mx-auto" style="max-width: 600px;">
                <div class="card-body p-4 text-center">
                    <h1 class="text-warning h3 mb-3">Error en la Aplicación ⚠️</h1>
                    <div class="alert alert-light border text-start small mb-3">
                        <strong>Mensaje:</strong> <?= htmlspecialchars($e->getMessage()) ?>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
