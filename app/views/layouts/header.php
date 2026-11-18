<?php

declare(strict_types=1);

use App\Helpers\Session;

Session::start();

$headerIsLoggedIn = Session::isLoggedIn();
$headerUser       = Session::getUser();
$headerRolId      = (int)($headerUser['rol_id'] ?? 0);
$headerIsAdmin    = $headerRolId === 1 || (($headerUser['codigo_rol'] ?? '') === 'admin');
$headerDisplayName = $headerUser['nombre'] ?? 'Usuario';
$headerRoleLabel   = $headerIsAdmin ? 'Administrador' : 'Cliente';
$headerHome        = '/';

$pageTitle = $title ?? 'eBike Management System (EMS)';
$layout    = $layout ?? 'public';
$bodyClass = $bodyClass ?? '';
$mainClass = $mainClass ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="eBike Management System (EMS) - Alquiler de bicicletas eléctricas compartidas.">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Favicon -->
    <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">

    <!-- Tipografías del diseño -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&amp;family=Barlow+Condensed:wght@400;600;700&amp;display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 (vistas administrativas y utilidades) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Diseño personalizado del frontend -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
    <a class="saltar" href="#contenido">Saltar al contenido</a>

    <?php if ($layout === 'app'): ?>
        <!-- Las vistas de dashboard abren su propio aside y main dentro de .app -->
        <div class="app">
    <?php else: ?>
        <header class="cabecera">
            <div class="contenedor cabecera__fila">
                <a class="marca" href="<?= htmlspecialchars($headerHome) ?>">
                    <img src="/assets/img/logo.svg" alt="" width="36" height="36">
                    eBike <span>EMS</span>
                </a>

                <?php if ($headerIsLoggedIn): ?>
                    <nav class="menu" aria-label="Principal">
                        <?php if ($headerIsAdmin): ?>
                            <a href="/admin/dashboard">Panel</a>
                            <a href="/admin/stations">Estaciones</a>
                            <a href="/admin/bikes">Bicicletas</a>
                        <?php else: ?>
                            <a href="/user/dashboard">Mi panel</a>
                            <a href="/user/dashboard#alquiler">Mi alquiler</a>
                            <a href="/user/dashboard#historial">Historial</a>
                        <?php endif; ?>
                    </nav>
                <?php else: ?>
                    <nav class="menu" aria-label="Principal">
                        <a href="/#como-funciona">Cómo funciona</a>
                        <a href="/#estaciones">Estaciones</a>
                        <a href="/#tarifa">Tarifa</a>
                        <a href="/#perfiles">Perfiles</a>
                    </nav>
                <?php endif; ?>

                <div class="cabecera__acciones <?= $headerIsLoggedIn ? '' : 'ms-auto' ?>">
                    <?php if ($headerIsLoggedIn): ?>
                        <span class="texto-suave d-none d-md-inline">
                            <strong><?= htmlspecialchars($headerDisplayName) ?></strong>
                            · <?= htmlspecialchars($headerRoleLabel) ?>
                        </span>
                        <a class="boton boton--peligro boton--s" href="/logout">
                            Cerrar sesión
                        </a>
                    <?php else: ?>
                        <a class="boton boton--secundario boton--s" href="/login">Ingresar</a>
                        <a class="boton boton--s" href="/register">Crear cuenta</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main id="contenido" class="<?= htmlspecialchars($mainClass) ?>">
    <?php endif; ?>
