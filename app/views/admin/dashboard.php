<?php

declare(strict_types=1);

use App\Helpers\Session;

$user   = $user ?? Session::getUser();
$title  = 'Panel de Administrador - eBike EMS';
$layout = 'app';
require_once dirname(__DIR__) . '/layouts/header.php';

// Métricas reales calculadas por DashboardController.
$metricas = [
    [
        'titulo'  => 'Total Bicicletas',
        'valor'   => $totalBikes ?? 0,
        'detalle' => 'unidades registradas en la flota',
    ],
    [
        'titulo'  => 'Estaciones Activas',
        'valor'   => $activeStations ?? 0,
        'detalle' => 'operativas en este momento',
    ],
    [
        'titulo'  => 'Alquileres Hoy',
        'valor'   => $todayRentals ?? 0,
        'detalle' => ($activeRentals ?? 0) . ' en curso ahora',
    ],
];
?>

<aside class="lateral">
    <a class="marca" href="/admin/dashboard">
        <img src="/assets/img/logo.svg" alt="" width="36" height="36">
        eBike <span>EMS</span>
    </a>

    <div class="lateral__perfil">
        <strong><?= htmlspecialchars($user['nombre'] ?? 'Administrador') ?></strong>
        <span>Administrador</span>
        <span class="perfil__nivel" aria-hidden="true">
            <i class="activo"></i><i class="activo"></i><i class="activo"></i><i></i>
        </span>
    </div>

    <nav aria-label="Secciones de administrador">
        <a href="/admin/dashboard" class="actual" aria-current="page">Resumen</a>
        <a href="/admin/stations">Estaciones</a>
        <a href="/admin/bikes">Bicicletas</a>
    </nav>

    <div class="lateral__pie">
        <a href="/admin/dashboard">Panel principal</a>
        <a href="/logout">Cerrar sesión</a>
    </div>
</aside>

<main class="principal" id="contenido">
    <header class="principal__cabecera">
        <div>
            <h1>Resumen de la operación</h1>
            <p>
                Bienvenido, <?= htmlspecialchars($user['nombre'] ?? 'Administrador') ?>.
                Usuarios, estaciones y bicicletas en un solo lugar.
            </p>
        </div>
        <span class="estado">Datos en tiempo real</span>
    </header>

    <div class="bloques">
        <div class="indicadores">
            <?php foreach ($metricas as $metrica): ?>
                <div class="indicador">
                    <span><?= htmlspecialchars($metrica['titulo']) ?></span>
                    <strong><?= (int)$metrica['valor'] ?></strong>
                    <small><?= htmlspecialchars($metrica['detalle']) ?></small>
                </div>
            <?php endforeach; ?>

            <div class="indicador indicador--alerta">
                <span>Alertas Abiertas</span>
                <strong><?= (int)($openAlerts ?? 0) ?></strong>
                <small>batería baja o en mantenimiento</small>
            </div>
        </div>

        <section class="panel" aria-labelledby="titulo-accesos">
            <header class="panel__cabecera">
                <h2 id="titulo-accesos">Accesos rápidos</h2>
                <p>Gestiona la operación desde aquí.</p>
            </header>

            <div class="panel__cuerpo">
                <div class="reportes">
                    <div class="reporte">
                        <strong>Estaciones</strong>
                        <span>Consulta, crea y actualiza las estaciones de la red.</span>
                        <a class="boton boton--secundario boton--s" href="/admin/stations">Abrir</a>
                    </div>

                    <div class="reporte">
                        <strong>Bicicletas</strong>
                        <span>Administra la flota, la batería y el estado de cada unidad.</span>
                        <a class="boton boton--secundario boton--s" href="/admin/bikes">Abrir</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
