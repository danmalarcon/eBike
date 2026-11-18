<?php

declare(strict_types=1);

/** @var array|null $activeRental      Viaje en curso o null */
/** @var array      $operativeStations Estaciones operativas */
/** @var array      $history           Historial de viajes */

use App\Helpers\Session;
use App\Models\Rental;

$user   = $user ?? Session::getUser();
$title  = 'Mi Panel - eBike EMS';
$layout = 'app';
require_once dirname(__DIR__) . '/layouts/header.php';

$hasActiveRental = !empty($activeRental);
$userName        = $user['nombre'] ?? 'Usuario';
?>

<aside class="lateral">
    <a class="marca" href="/user/dashboard">
        <img src="/assets/img/logo.svg" alt="" width="36" height="36">
        eBike <span>EMS</span>
    </a>

    <div class="lateral__perfil">
        <strong><?= htmlspecialchars($userName) ?></strong>
        <span>Cliente</span>
        <span class="perfil__nivel" aria-hidden="true">
            <i class="activo"></i><i></i><i></i><i></i>
        </span>
    </div>

    <nav aria-label="Secciones de cliente">
        <a href="#alquiler" class="actual">Mi alquiler</a>
        <?php if (!$hasActiveRental): ?>
            <a href="#alquiler" id="navBikesLink">Bicicletas disponibles</a>
        <?php endif; ?>
        <a href="#historial">Historial</a>
    </nav>

    <div class="lateral__pie">
        <a href="/user/dashboard">Mi panel</a>
        <a href="/logout">Cerrar sesión</a>
    </div>
</aside>

<main class="principal" id="contenido">
    <header class="principal__cabecera">
        <div>
            <h1>Hola, <?= htmlspecialchars($userName) ?></h1>
            <p>
                <?php if ($hasActiveRental): ?>
                    Tienes un alquiler en curso. Revisa el tiempo, el costo estimado y finaliza el viaje al llegar.
                <?php else: ?>
                    ¿Listo para tu próximo viaje? Elige una estación, revisa las bicicletas disponibles
                    y empieza a pedalear. Tu aventura te está esperando.
                <?php endif; ?>
            </p>
        </div>

        <a href="#alquiler" class="boton boton--s">
            <?= $hasActiveRental ? 'Ver mi alquiler' : 'Alquilar una bicicleta' ?>
        </a>
    </header>

    <div class="bloques">
        <!-- Contenedor de alertas de operaciones -->
        <div id="rentalAlert" class="aviso" role="alert" aria-live="polite" hidden></div>

        <?php if ($hasActiveRental): ?>
            <!-- ════════════════════════════════════════════════════════════════
                 VIAJE EN CURSO
                 ════════════════════════════════════════════════════════════════ -->
            <section class="panel" id="alquiler" aria-labelledby="titulo-alquiler">
                <header class="panel__cabecera">
                    <h2 id="titulo-alquiler">Mi alquiler en curso</h2>
                    <p>
                        Iniciado el
                        <?= htmlspecialchars(
                            (new \DateTimeImmutable($activeRental['fecha_inicio']))->format('d/m/Y H:i')
                        ) ?>
                    </p>
                </header>

                <div class="panel__cuerpo">
                    <div class="curso">
                        <div class="curso__datos">
                            <div class="curso__bici">
                                <span class="codigo">
                                    <?= str_pad((string)$activeRental['id_bicicleta'], 3, '0', STR_PAD_LEFT) ?>
                                </span>
                                <div>
                                    <strong><?= htmlspecialchars($activeRental['bike_modelo']) ?></strong><br>
                                    <span class="texto-suave"><?= htmlspecialchars($activeRental['bike_qr']) ?></span>
                                </div>
                            </div>

                            <dl class="curso__lista">
                                <dt>Salida</dt>
                                <dd>
                                    <?= htmlspecialchars($activeRental['estacion_orig_nombre']) ?>
                                    (<?= htmlspecialchars($activeRental['estacion_orig_codigo']) ?>)
                                </dd>

                                <dt>Inicio</dt>
                                <dd>
                                    <?= htmlspecialchars(
                                        (new \DateTimeImmutable($activeRental['fecha_inicio']))->format('d/m/Y H:i')
                                    ) ?>
                                </dd>

                                <dt>Tiempo</dt>
                                <dd><span id="elapsedTimer">calculando...</span></dd>

                                <dt>Batería</dt>
                                <dd>
                                    <?php
                                    $bateria = (float)$activeRental['nivel_bateria'];
                                    $bateriaClase = $bateria > 50
                                        ? ''
                                        : ($bateria > 20 ? 'bateria--medio' : 'bateria--critico');
                                    ?>
                                    <span class="bateria <?= $bateriaClase ?>"
                                          style="--nivel: <?= $bateria ?>%"
                                          role="img"
                                          aria-label="Batería al <?= number_format($bateria, 1) ?> %">
                                        <span class="bateria__celda"><span class="bateria__nivel"></span></span>
                                        <span><?= number_format($bateria, 1) ?> %</span>
                                    </span>
                                </dd>
                            </dl>
                        </div>

                        <form class="curso__cobro"
                              onsubmit="event.preventDefault(); endRental(<?= (int)$activeRental['id_alquiler'] ?>);">
                            <div>
                                <span class="cifra" id="estimatedCost">$0</span><br>
                                <span class="cifra-etiqueta">Costo estimado hasta ahora</span>
                            </div>

                            <div class="campo">
                                <label for="endStationSelect">Estación donde la devuelves</label>
                                <select id="endStationSelect" required>
                                    <option value="">Elige una estación</option>
                                    <?php foreach ($operativeStations as $station): ?>
                                        <option value="<?= (int)$station['id_estacion'] ?>">
                                            <?= htmlspecialchars($station['nombre']) ?>
                                            (<?= htmlspecialchars($station['codigo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button id="endRentalBtn" class="boton boton--claro" type="submit">
                                <span id="endBtnText">Finalizar alquiler</span>
                                <span id="endBtnSpinner" class="spinner-border spinner-border-sm d-none"
                                      role="status" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <!-- ════════════════════════════════════════════════════════════════
                 SELECCIÓN DE ESTACIÓN DE ORIGEN
                 ════════════════════════════════════════════════════════════════ -->
            <section class="panel" id="alquiler" aria-labelledby="titulo-alquiler">
                <header class="panel__cabecera">
                    <h2 id="titulo-alquiler">Iniciar un nuevo viaje</h2>
                    <p>Selecciona la estación de recogida para ver las bicicletas disponibles.</p>
                </header>

                <div class="panel__cuerpo">
                    <div class="formulario" style="max-width: 28rem;">
                        <div class="campo">
                            <label for="originStationSelect">Estación de origen</label>
                            <select id="originStationSelect" onchange="loadBikes(this.value)">
                                <option value="">Selecciona una estación</option>
                                <?php foreach ($operativeStations as $station): ?>
                                    <option value="<?= (int)$station['id_estacion'] ?>">
                                        <?= htmlspecialchars($station['nombre']) ?>
                                        — <?= htmlspecialchars($station['codigo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="aviso">
                        <span class="aviso__icono">i</span>
                        <div>
                            <strong>Disponibilidad estricta</strong>
                            <span>Solo se muestran bicicletas con batería superior al 20 % (RN-007).</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════════════════════════════════════════════════════════════
                 BICICLETAS DISPONIBLES
                 ════════════════════════════════════════════════════════════════ -->
            <section class="panel" id="bikeListSection" aria-labelledby="titulo-bicicletas" hidden>
                <header class="panel__cabecera">
                    <h2 id="titulo-bicicletas">
                        Bicicletas disponibles
                        <span id="bikeCountBadge" class="estado estado--azul">0</span>
                    </h2>
                    <p>Selecciona una unidad para iniciar el viaje.</p>
                </header>

                <div class="panel__cuerpo">
                    <div id="bikeList" class="bicis"></div>

                    <div id="noBikesMsg" class="aviso aviso--ambar" hidden>
                        <span class="aviso__icono">!</span>
                        <div>
                            No hay bicicletas disponibles en esta estación. Intenta con otra.
                        </div>
                    </div>

                    <div id="startSection" hidden>
                        <div class="aviso aviso--verde">
                            <span class="aviso__icono">✓</span>
                            <div>
                                <strong>Bicicleta seleccionada</strong>
                                <span id="selectedBikeInfo">—</span>
                            </div>
                        </div>

                        <div class="acciones">
                            <button id="startRentalBtn" class="boton" type="button" onclick="startRental()">
                                <span id="startBtnText">Iniciar viaje</span>
                                <span id="startBtnSpinner" class="spinner-border spinner-border-sm d-none"
                                      role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════════════════════════════
             HISTORIAL DE VIAJES
             ════════════════════════════════════════════════════════════════════ -->
        <section class="panel" id="historial" aria-labelledby="titulo-historial">
            <header class="panel__cabecera">
                <h2 id="titulo-historial">Mi historial de alquileres</h2>
                <p>Últimos viajes completados.</p>
            </header>

            <?php if (!empty($history)): ?>
                <div class="panel__cuerpo panel__cuerpo--tabla">
                    <div class="tabla-contenedor">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Bicicleta</th>
                                    <th>Recorrido</th>
                                    <th>Duración</th>
                                    <th class="derecha">Costo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                    <?php
                                    $inicio = new \DateTimeImmutable($item['fecha_inicio']);
                                    $duracion = $item['duracion_minutos'] !== null
                                        ? (int)$item['duracion_minutos'] . ' min'
                                        : '—';
                                    $costo = $item['costo_total'] !== null
                                        ? '$' . number_format((float)$item['costo_total'], 0, ',', '.')
                                        : '—';
                                    ?>
                                    <tr>
                                        <td>
                                            <?= $inicio->format('d/m/Y') ?>
                                            <span class="secundario"><?= $inicio->format('H:i') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['bike_modelo']) ?></strong>
                                            <span class="secundario">Alquiler #<?= (int)$item['id_alquiler'] ?></span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($item['estacion_orig_nombre'] ?? '—') ?>
                                            <span class="texto-suave">→</span>
                                            <?= htmlspecialchars($item['estacion_dest_nombre'] ?? '—') ?>
                                        </td>
                                        <td class="numero"><?= $duracion ?></td>
                                        <td class="derecha numero"><?= $costo ?></td>
                                        <td><span class="estado">Completado</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="panel__cuerpo">
                    <p class="texto-suave">
                        Aún no tienes viajes finalizados. Cuando completes tu primer alquiler,
                        aparecerá aquí con su duración y costo.
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
let selectedBikeId    = null;
let selectedStationId = null;
let selectedStationName = '';

<?php if ($hasActiveRental): ?>
/* ─── Temporizador del viaje activo ─────────────────────────────────── */
(function () {
    const startMs    = <?= (new \DateTimeImmutable($activeRental['fecha_inicio']))->getTimestamp() * 1000 ?>;
    const ratePerMin = <?= (float)Rental::TARIFA_POR_MINUTO ?>;

    function update() {
        const elapsed = Math.floor((Date.now() - startMs) / 1000);
        const mins    = Math.floor(elapsed / 60);
        const secs    = elapsed % 60;
        const display = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        document.getElementById('elapsedTimer').textContent = display;

        const billable = Math.max(1, Math.ceil(elapsed / 60));
        const cost     = billable * ratePerMin;
        document.getElementById('estimatedCost').textContent =
            '$' + cost.toLocaleString('es-CO');
    }

    update();
    setInterval(update, 1000);
})();

/* ─── Finalizar el viaje ────────────────────────────────────────────── */
async function endRental(rentalId) {
    const stationId = parseInt(document.getElementById('endStationSelect').value, 10);

    if (!stationId) {
        showAlert('Por favor selecciona la estación de entrega.', 'warning');
        return;
    }

    setEndLoading(true);

    try {
        const response = await fetch('/api/rental/end', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id_alquiler: rentalId, estacion_dest: stationId })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            const data = result.data;
            showAlert(
                `✅ ${result.message} Duración: ${data.duracion_min} min | ` +
                `Costo: $${Number(data.costo_total).toLocaleString('es-CO')} COP`,
                'success'
            );
            setTimeout(() => window.location.reload(), 2500);
        } else {
            showAlert(result.message || 'Error al finalizar el viaje.', 'danger');
            setEndLoading(false);
        }
    } catch (error) {
        showAlert('Error de conexión con el servidor.', 'danger');
        setEndLoading(false);
    }
}

function setEndLoading(isLoading) {
    document.getElementById('endRentalBtn').disabled = isLoading;
    document.getElementById('endBtnText').classList.toggle('d-none', isLoading);
    document.getElementById('endBtnSpinner').classList.toggle('d-none', !isLoading);
}
<?php else: ?>
/* ─── Enlace lateral: llevar el foco al selector de estación ─────────── */
const navBikesLink = document.getElementById('navBikesLink');
if (navBikesLink) {
    navBikesLink.addEventListener('click', () => {
        setTimeout(() => {
            document.getElementById('originStationSelect')?.focus();
        }, 0);
    });
}

/* ─── Cargar bicicletas disponibles por estación ─────────────────────── */
async function loadBikes(stationId) {
    selectedBikeId    = null;
    selectedStationId = stationId ? parseInt(stationId, 10) : null;

    const stationSelect = document.getElementById('originStationSelect');
    selectedStationName = (stationSelect && stationSelect.selectedIndex >= 0)
        ? stationSelect.options[stationSelect.selectedIndex].text.trim()
        : '';

    const section      = document.getElementById('bikeListSection');
    const noBikes      = document.getElementById('noBikesMsg');
    const startSection = document.getElementById('startSection');
    const bikeList     = document.getElementById('bikeList');

    section.hidden      = true;
    noBikes.hidden      = true;
    startSection.hidden = true;
    bikeList.innerHTML  = '';

    if (!stationId) {
        return;
    }

    try {
        const response = await fetch(
            `/api/bikes/by-station?estacion_id=${encodeURIComponent(stationId)}`,
            { headers: { 'Accept': 'application/json' } }
        );
        const result = await response.json();
        const bikes  = Array.isArray(result.data) ? result.data : [];

        document.getElementById('bikeCountBadge').textContent = bikes.length;

        if (bikes.length === 0) {
            noBikes.hidden = false;
            section.hidden = false;
            return;
        }

        bikes.forEach((bike) => {
            const bateria      = parseFloat(bike.nivel_bateria);
            const bateriaClase = bateria > 50
                ? ''
                : (bateria > 20 ? 'bateria--medio' : 'bateria--critico');
            const kilometraje  = Number(bike.kilometraje || 0).toFixed(1);
            const codigo       = String(bike.id_bicicleta).padStart(3, '0');

            const card = document.createElement('article');
            card.className = 'bici';
            card.id = `bikeCard${bike.id_bicicleta}`;
            card.tabIndex = 0;
            card.setAttribute('role', 'button');
            card.setAttribute('aria-pressed', 'false');
            card.setAttribute(
                'aria-label',
                `Seleccionar ${bike.modelo}, batería ${bateria.toFixed(1)} %`
            );
            card.addEventListener('click', () => {
                selectBike(bike.id_bicicleta, bike.modelo, bike.codigo_qr);
            });
            card.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectBike(bike.id_bicicleta, bike.modelo, bike.codigo_qr);
                }
            });

            card.innerHTML = `
                <span class="bici__codigo">${escapeHtml(codigo)}</span>
                <div>
                    <strong>${escapeHtml(bike.modelo)}</strong>
                    <p class="bici__lugar"><strong>${escapeHtml(selectedStationName)}</strong></p>
                    <span class="texto-suave">${escapeHtml(bike.codigo_qr)}</span>
                </div>
                <span class="bateria ${bateriaClase}"
                      style="--nivel: ${bateria}%"
                      role="img"
                      aria-label="Batería al ${bateria.toFixed(1)} %">
                    <span class="bateria__celda"><span class="bateria__nivel"></span></span>
                    <span>${bateria.toFixed(1)} %</span>
                </span>
                <div class="bici__meta">
                    <span class="texto-suave">${kilometraje} km recorridos</span>
                    <span class="boton boton--s">Seleccionar</span>
                </div>`;

            bikeList.appendChild(card);
        });

        section.hidden = false;
    } catch (error) {
        showAlert('Error al cargar las bicicletas. Intenta de nuevo.', 'danger');
    }
}

/* ─── Seleccionar una bicicleta ─────────────────────────────────────── */
function selectBike(bikeId, modelo, qr) {
    document.querySelectorAll('.bici').forEach((element) => {
        element.classList.remove('bici--seleccionada');
        element.setAttribute('aria-pressed', 'false');
    });

    const card = document.getElementById(`bikeCard${bikeId}`);
    if (card) {
        card.classList.add('bici--seleccionada');
        card.setAttribute('aria-pressed', 'true');
    }

    selectedBikeId = bikeId;
    document.getElementById('selectedBikeInfo').textContent = `${modelo} · QR: ${qr}`;
    document.getElementById('startSection').hidden = false;
}

/* ─── Iniciar el viaje ──────────────────────────────────────────────── */
async function startRental() {
    if (!selectedBikeId || !selectedStationId) {
        showAlert('Selecciona una estación y una bicicleta antes de continuar.', 'warning');
        return;
    }

    setStartLoading(true);

    try {
        const response = await fetch('/api/rental/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                id_bicicleta: selectedBikeId,
                estacion_orig: selectedStationId
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            showAlert('🚴 ' + result.message + ' Redirigiendo a tu panel...', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showAlert(result.message || 'Error al iniciar el viaje.', 'danger');
            setStartLoading(false);
        }
    } catch (error) {
        showAlert('Error de conexión con el servidor.', 'danger');
        setStartLoading(false);
    }
}

function setStartLoading(isLoading) {
    document.getElementById('startRentalBtn').disabled = isLoading;
    document.getElementById('startBtnText').classList.toggle('d-none', isLoading);
    document.getElementById('startBtnSpinner').classList.toggle('d-none', !isLoading);
}
<?php endif; ?>

/* ─── Utilidades compartidas ────────────────────────────────────────── */
function showAlert(message, type) {
    const box     = document.getElementById('rentalAlert');
    const variant = type === 'danger'
        ? 'aviso--rojo'
        : (type === 'warning' ? 'aviso--ambar' : 'aviso--verde');

    box.className = `aviso ${variant}`;
    box.innerHTML = '';

    const icon = document.createElement('span');
    icon.className = 'aviso__icono';
    icon.textContent = type === 'success' ? '✓' : '!';

    const text = document.createElement('div');
    text.textContent = message;

    box.append(icon, text);
    box.hidden = false;
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
