<?php

declare(strict_types=1);

use App\Helpers\Session;

$title     = 'eBike EMS | Alquila una bicicleta eléctrica en tu ciudad';
$layout    = 'public';
$mainClass = '';

$isLoggedIn = Session::isLoggedIn();
$currentUser = $isLoggedIn ? (Session::getUser() ?? []) : [];
$isAdmin = (int)($currentUser['rol_id'] ?? 0) === 1
    || (($currentUser['codigo_rol'] ?? '') === 'admin');
$dashboardUrl = $isAdmin ? '/admin/dashboard' : '/user/dashboard';
$rentalUrl    = $isLoggedIn ? ($isAdmin ? '/admin/dashboard' : '/user/dashboard#alquiler') : '/login';
$rentalLabel  = $isAdmin ? 'Ver panel' : 'Alquilar';

$batteryClass = static function (float $level): string {
    if ($level <= 20.0) {
        return 'bateria--critico';
    }
    if ($level <= 50.0) {
        return 'bateria--medio';
    }
    return '';
};

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<!-- Vista principal: RF-007 consulta de disponibilidad, RF-008 inicio de alquiler -->
<section class="portada" aria-labelledby="titulo-portada">
    <div class="contenedor portada__rejilla">
        <div class="portada__texto">
            <h1 id="titulo-portada">Toma una bicicleta eléctrica en una estación y devuélvela en otra.</h1>
            <p>
                Escanea el código QR, pedalea con asistencia del motor y paga solo el tiempo que la usaste.
                Tú eliges dónde empezar y dónde terminar.
            </p>
            <div class="acciones">
                <?php if ($isLoggedIn): ?>
                    <a class="boton" href="<?= htmlspecialchars($dashboardUrl) ?>">Ir a mi panel</a>
                <?php else: ?>
                    <a class="boton" href="/register">Crear cuenta</a>
                <?php endif; ?>
                <a class="boton boton--secundario" href="#disponibles">Ver bicicletas disponibles</a>
            </div>
            <p class="portada__tarifa texto-suave">
                Pagas <strong>$500</strong> por cada minuto que usas la bicicleta.
            </p>
        </div>

        <section class="tablero" id="disponibles" aria-labelledby="titulo-tablero">
            <header class="tablero__cabecera">
                <h2 id="titulo-tablero">Disponibles ahora</h2>
                <span>Actualizado a las <span id="updatedAt"><?= htmlspecialchars(date('H:i')) ?></span></span>
            </header>

            <?php if (!empty($availableBikes)): ?>
                <ul>
                    <?php foreach (array_slice($availableBikes, 0, 3) as $bike): ?>
                        <?php
                        $batteryLevel   = (float)$bike['nivel_bateria'];
                        $batteryPercent = (int)round($batteryLevel);
                        $stationName    = $bike['estacion_nombre'] ?? 'Sin estación asignada';
                        ?>
                        <li class="tablero__fila">
                            <span class="tablero__codigo"><?= str_pad((string)$bike['id_bicicleta'], 3, '0', STR_PAD_LEFT) ?></span>
                            <div>
                                <span class="tablero__modelo"><?= htmlspecialchars($bike['modelo']) ?></span>
                                <span class="tablero__lugar"><?= htmlspecialchars($stationName) ?></span>
                            </div>
                            <span class="bateria <?= $batteryClass($batteryLevel) ?>"
                                  style="--nivel: <?= htmlspecialchars((string)$batteryLevel) ?>%"
                                  role="img" aria-label="Batería al <?= $batteryPercent ?> %">
                                <span class="bateria__celda"><span class="bateria__nivel"></span></span>
                                <span><?= $batteryPercent ?> %</span>
                            </span>
                            <a class="boton boton--claro boton--s" href="<?= htmlspecialchars($rentalUrl) ?>">
                                <?= htmlspecialchars($rentalLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="tablero__nota">
                    <?php if (count($availableBikes) > 3): ?>
                        Hay <?= count($availableBikes) ?> bicicletas disponibles. Inicia sesión para verlas todas.
                    <?php else: ?>
                        Una bicicleta se ofrece cuando su batería tiene al menos 20 %.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="tablero__nota">
                    No hay bicicletas disponibles en este momento. Vuelve a consultar más tarde.
                </p>
            <?php endif; ?>
        </section>
    </div>
</section>

<section class="seccion seccion--blanca" id="como-funciona" aria-labelledby="titulo-pasos">
    <div class="contenedor">
        <div class="seccion__intro">
            <h2 id="titulo-pasos">Cómo funciona</h2>
            <p>De la estación a tu destino en cuatro pasos.</p>
        </div>
        <ol class="pasos">
            <li><h3>Elige una bicicleta</h3><p>Mira cuáles están libres en cada estación y cuánta batería tienen.</p></li>
            <li><h3>Escanea el código QR</h3><p>Al escanearlo se registra tu salida y empieza a contar el tiempo.</p></li>
            <li><h3>Devuélvela en otra estación</h3><p>Déjala en un espacio libre y finaliza el alquiler desde tu cuenta.</p></li>
            <li><h3>Paga con tu medio registrado</h3><p>El cobro se genera al finalizar y queda en tu historial de pagos.</p></li>
        </ol>
    </div>
</section>

<!-- RF-024 consulta de estaciones -->
<section class="seccion" id="estaciones" aria-labelledby="titulo-estaciones">
    <div class="contenedor">
        <div class="seccion__intro">
            <h2 id="titulo-estaciones">Estaciones</h2>
            <p>
                Cada barra es un espacio de la estación. Las verdes tienen una bicicleta lista para salir
                y las amarillas, una en mantenimiento.
            </p>
        </div>

        <?php if (!empty($stations)): ?>
            <div class="estaciones">
                <?php foreach ($stations as $station): ?>
                    <?php
                    $stationId   = (int)$station['id_estacion'];
                    $available   = (int)($availableByStation[$stationId] ?? 0);
                    $capacity    = max(0, (int)$station['capacidad']);
                    $isOperative = $station['estado'] === 'operativa';
                    $slots       = min($capacity, 12);
                    $occupied    = min($available, $slots);
                    ?>
                    <article class="estacion">
                        <p class="estacion__codigo"><?= htmlspecialchars($station['codigo']) ?></p>
                        <h3><?= htmlspecialchars($station['nombre']) ?></h3>
                        <address><?= htmlspecialchars($station['direccion']) ?></address>

                        <?php if (!$isOperative): ?>
                            <p class="estado estado--ambar">
                                <?= $station['estado'] === 'mantenimiento' ? 'En mantenimiento' : 'Inactiva' ?>
                            </p>
                        <?php endif; ?>

                        <div class="muelle" role="img"
                             aria-label="<?= $available ?> bicicletas disponibles de <?= $capacity ?> espacios">
                            <?php for ($slot = 0; $slot < $slots; $slot++): ?>
                                <?php if ($slot < $occupied): ?>
                                    <span class="ranura ranura--ocupada"></span>
                                <?php elseif (!$isOperative && $slot === 0): ?>
                                    <span class="ranura ranura--mant"></span>
                                <?php else: ?>
                                    <span class="ranura"></span>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <p class="estacion__cifra">
                            <span>Disponibles</span>
                            <strong><?= $available ?> de <?= $capacity ?></strong>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="aviso aviso--ambar">
                <span class="aviso__icono">!</span>
                <div>
                    <strong>Aún no hay estaciones registradas.</strong>
                    <p>Cuando el administrador cree las primeras estaciones, aparecerán aquí.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- RF-012 cálculo de tarifa -->
<section class="seccion seccion--blanca" id="tarifa" aria-labelledby="titulo-tarifa">
    <div class="contenedor tarifa">
        <div>
            <h2 id="titulo-tarifa">Una sola tarifa, fácil de calcular</h2>
            <p class="texto-suave" style="margin: 0.75rem 0 1.25rem; max-width: 30rem;">
                El valor se calcula al finalizar el alquiler, con los minutos exactos que usaste la bicicleta.
            </p>
            <p class="tarifa__precio"><strong>$500</strong><span>por minuto</span></p>
        </div>
        <div class="cuenta">
            <h3>Ejemplo: un recorrido de 30 minutos</h3>
            <dl>
                <div><dt>30 minutos × $500</dt><dd>$15.000</dd></div>
                <div class="total"><dt>Total a pagar</dt><dd>$15.000</dd></div>
            </dl>
        </div>
    </div>
</section>

<!-- Perfiles reales del sistema: rol_id 1 (admin) y rol_id 2 (cliente) -->
<section class="seccion" id="perfiles" aria-labelledby="titulo-perfiles">
    <div class="contenedor">
        <div class="seccion__intro">
            <h2 id="titulo-perfiles">Cada perfil ve lo que necesita</h2>
            <p>
                El sistema tiene dos roles: cliente y administrador. Cada uno accede únicamente
                a las funciones que le corresponden.
            </p>
        </div>
        <div class="perfiles perfiles--dos">
            <article class="perfil">
                <span class="perfil__nivel" aria-hidden="true"><i class="activo"></i><i></i><i></i><i></i></span>
                <h3>Cliente</h3>
                <p>Alquila bicicletas y revisa lo que ha usado y pagado.</p>
                <ul>
                    <li>Alquilar y finalizar viajes</li>
                    <li>Historial de alquileres</li>
                    <li>Viaje activo y tarifa</li>
                </ul>
                <a class="boton boton--secundario boton--s" href="/login">Ingresar como cliente</a>
            </article>
            <article class="perfil">
                <span class="perfil__nivel" aria-hidden="true"><i class="activo"></i><i class="activo"></i><i class="activo"></i><i class="activo"></i></span>
                <h3>Administrador</h3>
                <p>Gestiona la flota, las estaciones y la operación del sistema.</p>
                <ul>
                    <li>Estaciones y bicicletas</li>
                    <li>Estado de baterías</li>
                    <li>Panel de operación</li>
                </ul>
                <a class="boton boton--secundario boton--s" href="/login">Ingresar como administrador</a>
            </article>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const updatedAt = document.getElementById('updatedAt');
    if (updatedAt) {
        updatedAt.textContent = new Date().toLocaleTimeString('es-CO', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    }
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
