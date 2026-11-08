<?php
declare(strict_types=1);

$title = 'Gestión de Bicicletas - Panel Admin';
require_once dirname(__DIR__, 2) . '/layouts/header.php';

// Conteo de métricas de flota
$totalBicis = count($bikes);
// Regla RN-007: disponible y batería > 20
$disponibles = count(array_filter($bikes, fn($b) => $b['estado'] === 'disponible' && (float)$b['nivel_bateria'] > 20.00));
$alquiladas = count(array_filter($bikes, fn($b) => $b['estado'] === 'alquilada'));
$enMantenimiento = count(array_filter($bikes, fn($b) => $b['estado'] === 'mantenimiento'));
$bateriaCritica = count(array_filter($bikes, fn($b) => (float)$b['nivel_bateria'] <= 20.00));
?>

<div class="container py-4">
    <!-- Encabezado y Navegación -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/admin/stations" class="text-decoration-none">Administración</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bicicletas</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0">Gestión de Flota de Bicicletas Eléctricas</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/stations" class="btn btn-outline-secondary">
                <i class="bi bi-geo-alt me-1"></i>Ver Estaciones
            </a>
            <button type="button" class="btn btn-success" onclick="openCreateBikeModal()">
                <i class="bi bi-plus-circle me-1"></i>Nueva Bicicleta
            </button>
        </div>
    </div>

    <!-- Tarjetas de Métricas Rápidas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <span class="text-muted small fw-semibold">Flota Total</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= $totalBicis ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4">
                <div class="card-body">
                    <span class="text-success small fw-semibold">Disponibles (RN-007)</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success"><?= $disponibles ?></h3>
                    <small class="text-muted" style="font-size: 0.75rem;">Batería &gt; 20%</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-primary border-4">
                <div class="card-body">
                    <span class="text-primary small fw-semibold">En Alquiler</span>
                    <h3 class="fw-bold mb-0 mt-1 text-primary"><?= $alquiladas ?></h3>
                    <small class="text-muted" style="font-size: 0.75rem;">En viaje</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-danger border-4">
                <div class="card-body">
                    <span class="text-danger small fw-semibold">Batería Crítica / Taller</span>
                    <h3 class="fw-bold mb-0 mt-1 text-danger"><?= $bateriaCritica + $enMantenimiento ?></h3>
                    <small class="text-muted" style="font-size: 0.75rem;">≤ 20% o Mantenimiento</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Regla de Negocio RN-007 Alerta Informativa -->
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
        <div class="small">
            <strong>Regla RN-007 (Disponibilidad Estricta):</strong> Solo las bicicletas en estado <code>disponible</code> y con nivel de batería estrictamente <code>&gt; 20.00%</code> son ofertadas a los clientes. Las demás quedan bloqueadas automáticamente.
        </div>
    </div>

    <!-- Alertas Dinámicas de Operaciones -->
    <div id="bikeAlert" class="alert d-none" role="alert"></div>

    <!-- Tabla Principal de Bicicletas -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Código / QR</th>
                            <th>Modelo y N° Serie</th>
                            <th>Estación Actual</th>
                            <th>Nivel de Batería</th>
                            <th>Kilometraje</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bikes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-bicycle display-6 d-block mb-2 text-secondary"></i>
                                    No hay bicicletas registradas en el inventario.<br>
                                    <button class="btn btn-sm btn-success mt-2" onclick="openCreateBikeModal()">Agregar la primera bicicleta</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bikes as $bike): ?>
                                <?php 
                                    $bat = (float)$bike['nivel_bateria'];
                                    // Determinar colores de batería según porcentaje
                                    if ($bat > 70) {
                                        $batColor = 'success';
                                        $batIcon = 'bi-battery-full';
                                    } elseif ($bat > 20) {
                                        $batColor = 'warning';
                                        $batIcon = 'bi-battery-half';
                                    } else {
                                        $batColor = 'danger';
                                        $batIcon = 'bi-battery';
                                    }
                                    $isBlockedByBat = ($bat <= 20.00);
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($bike['codigo_qr']) ?></div>
                                        <small class="text-muted">ID: #<?= (int)$bike['id_bicicleta'] ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($bike['modelo']) ?></div>
                                        <small class="text-muted">S/N: <code><?= htmlspecialchars($bike['num_serie']) ?></code></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($bike['estacion_nombre'])): ?>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($bike['estacion_nombre']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">
                                                <i class="bi bi-signpost-2 me-1"></i>En Tránsito / Sin Estación
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi <?= $batIcon ?> fs-5 text-<?= $batColor ?>"></i>
                                            <div style="width: 80px;">
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?= $batColor ?>" role="progressbar" style="width: <?= $bat ?>%"></div>
                                                </div>
                                            </div>
                                            <span class="fw-bold text-<?= $batColor ?> small"><?= number_format($bat, 1) ?>%</span>
                                        </div>
                                        <?php if ($isBlockedByBat): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.65rem;">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Bloqueada (RN-007)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format((float)$bike['kilometraje'], 1) ?></span> <small class="text-muted">km</small>
                                    </td>
                                    <td>
                                        <?php if ($bike['estado'] === 'disponible'): ?>
                                            <?php if ($bat > 20.00): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Disponible</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-danger border border-warning-subtle px-2 py-1"><i class="bi bi-battery-charging me-1"></i>Batería Baja</span>
                                            <?php endif; ?>
                                        <?php elseif ($bike['estado'] === 'alquilada'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-person-walking me-1"></i>Alquilada</span>
                                        <?php elseif ($bike['estado'] === 'mantenimiento'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-tools me-1"></i>Mantenimiento</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark-subtle text-dark border border-dark-subtle px-2 py-1"><i class="bi bi-slash-circle me-1"></i>Baja</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" title="Editar bicicleta" onclick="openEditBikeModal(<?= (int)$bike['id_bicicleta'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Opciones</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li><h6 class="dropdown-header">Gestión de Bicicleta</h6></li>
                                                <li><a class="dropdown-item small" href="#" onclick="quickAdjustBattery(<?= (int)$bike['id_bicicleta'] ?>, <?= $bat ?>)"><i class="bi bi-battery-charging text-warning me-2"></i>Ajustar Nivel Batería</a></li>
                                                <li><a class="dropdown-item small" href="#" onclick="quickReassignStation(<?= (int)$bike['id_bicicleta'] ?>, '<?= $bike['estacion_act'] ?? '' ?>')"><i class="bi bi-geo-alt text-primary me-2"></i>Reubicar Estación</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><h6 class="dropdown-header">Cambiar Estado</h6></li>
                                                <?php if ($bike['estado'] !== 'disponible'): ?>
                                                    <li><a class="dropdown-item small" href="#" onclick="changeBikeStatus(<?= (int)$bike['id_bicicleta'] ?>, 'disponible')"><i class="bi bi-check-circle text-success me-2"></i>Marcar Disponible</a></li>
                                                <?php endif; ?>
                                                <?php if ($bike['estado'] !== 'mantenimiento'): ?>
                                                    <li><a class="dropdown-item small" href="#" onclick="changeBikeStatus(<?= (int)$bike['id_bicicleta'] ?>, 'mantenimiento')"><i class="bi bi-tools text-warning me-2"></i>Enviar a Taller</a></li>
                                                <?php endif; ?>
                                                <?php if ($bike['estado'] !== 'baja'): ?>
                                                    <li><a class="dropdown-item small text-secondary" href="#" onclick="changeBikeStatus(<?= (int)$bike['id_bicicleta'] ?>, 'baja')"><i class="bi bi-slash-circle me-2"></i>Dar de Baja</a></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item small text-danger" href="#" onclick="deleteBike(<?= (int)$bike['id_bicicleta'] ?>, '<?= htmlspecialchars(addslashes($bike['modelo'] . ' - ' . $bike['num_serie'])) ?>')"><i class="bi bi-trash me-2"></i>Eliminar del Inventario</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear / Editar Bicicleta -->
<div class="modal fade" id="bikeModal" tabindex="-1" aria-labelledby="bikeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="bikeModalTitle">Nueva Bicicleta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="bikeForm" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="bikeId" name="id_bicicleta">

                    <div id="modalBikeAlert" class="alert d-none" role="alert"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modelo" class="form-label fw-semibold">Modelo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modelo" name="modelo" placeholder="Ej: Urban Glide Pro" required>
                        </div>
                        <div class="col-md-6">
                            <label for="num_serie" class="form-label fw-semibold">N° Serie Único <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase font-monospace" id="num_serie" name="num_serie" placeholder="Ej: SN-2026-001" required>
                        </div>
                        <div class="col-md-6">
                            <label for="codigo_qr" class="form-label fw-semibold">Código QR</label>
                            <input type="text" class="form-control font-monospace" id="codigo_qr" name="codigo_qr" placeholder="Automático si se deja vacío">
                            <div class="form-text">Si está vacío, se autogenera.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nivel_bateria" class="form-label fw-semibold">Nivel Batería (%) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" min="0" max="100" class="form-control" id="nivel_bateria" name="nivel_bateria" value="100.0" required>
                            <div class="form-text text-muted">&gt;20% para estar disponible (RN-007).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="estacion_act" class="form-label fw-semibold">Estación Asignada</label>
                            <select class="form-select" id="estacion_act" name="estacion_act">
                                <option value="">-- Sin Estación (En Tránsito) --</option>
                                <?php foreach ($stations as $st): ?>
                                    <option value="<?= (int)$st['id_estacion'] ?>">
                                        <?= htmlspecialchars($st['nombre']) ?> (<?= htmlspecialchars($st['codigo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="estado" class="form-label fw-semibold">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="disponible">Disponible</option>
                                <option value="alquilada">Alquilada</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="baja">Baja</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="kilometraje" class="form-label fw-semibold">Kilometraje (km)</label>
                            <input type="number" step="0.1" min="0" class="form-control" id="kilometraje" name="kilometraje" value="0.0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="saveBikeBtn" class="btn btn-success">
                        <span id="saveBikeBtnText"><i class="bi bi-save me-1"></i>Guardar Bicicleta</span>
                        <span id="saveBikeBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let bikeModalInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    bikeModalInstance = new bootstrap.Modal(document.getElementById('bikeModal'));
    const form = document.getElementById('bikeForm');
    form.addEventListener('submit', handleBikeFormSubmit);
});

function openCreateBikeModal() {
    document.getElementById('bikeModalTitle').textContent = 'Nueva Bicicleta';
    document.getElementById('bikeForm').reset();
    document.getElementById('bikeId').value = '';
    document.getElementById('nivel_bateria').value = '100.0';
    document.getElementById('kilometraje').value = '0.0';
    document.getElementById('modalBikeAlert').className = 'alert d-none';
    bikeModalInstance.show();
}

async function openEditBikeModal(id) {
    document.getElementById('bikeModalTitle').textContent = 'Editar Bicicleta';
    document.getElementById('bikeForm').reset();
    document.getElementById('modalBikeAlert').className = 'alert d-none';

    try {
        const response = await fetch(`/admin/bikes/get?id=${id}`);
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data;
            document.getElementById('bikeId').value = data.id_bicicleta;
            document.getElementById('modelo').value = data.modelo;
            document.getElementById('num_serie').value = data.num_serie;
            document.getElementById('codigo_qr').value = data.codigo_qr;
            document.getElementById('nivel_bateria').value = data.nivel_bateria;
            document.getElementById('estacion_act').value = data.estacion_act || '';
            document.getElementById('estado').value = data.estado;
            document.getElementById('kilometraje').value = data.kilometraje;
            bikeModalInstance.show();
        } else {
            showPageAlert(result.message || 'Error al cargar bicicleta.', 'danger');
        }
    } catch (e) {
        showPageAlert('Error al conectar con el servidor.', 'danger');
    }
}

async function handleBikeFormSubmit(e) {
    e.preventDefault();

    const id = document.getElementById('bikeId').value;
    const isEdit = id !== '';
    const url = isEdit ? '/admin/bikes/update' : '/admin/bikes/create';

    const payload = {
        id_bicicleta: id ? parseInt(id) : undefined,
        modelo: document.getElementById('modelo').value.trim(),
        num_serie: document.getElementById('num_serie').value.trim(),
        codigo_qr: document.getElementById('codigo_qr').value.trim(),
        estacion_act: document.getElementById('estacion_act').value ? parseInt(document.getElementById('estacion_act').value) : null,
        nivel_bateria: parseFloat(document.getElementById('nivel_bateria').value),
        estado: document.getElementById('estado').value,
        kilometraje: parseFloat(document.getElementById('kilometraje').value) || 0.0
    };

    const modalAlert = document.getElementById('modalBikeAlert');
    const saveBtn = document.getElementById('saveBikeBtn');
    const btnText = document.getElementById('saveBikeBtnText');
    const btnSpinner = document.getElementById('saveBikeBtnSpinner');

    saveBtn.disabled = true;
    btnText.classList.add('d-none');
    btnSpinner.classList.remove('d-none');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok && result.success) {
            bikeModalInstance.hide();
            showPageAlert(result.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            modalAlert.textContent = result.message || 'Error al guardar la bicicleta.';
            modalAlert.className = 'alert alert-danger d-block mb-3';
        }
    } catch (err) {
        modalAlert.textContent = 'Error de conexión con el servidor.';
        modalAlert.className = 'alert alert-danger d-block mb-3';
    } finally {
        saveBtn.disabled = false;
        btnText.classList.remove('d-none');
        btnSpinner.classList.add('d-none');
    }
}

async function changeBikeStatus(id, newStatus) {
    try {
        const response = await fetch('/admin/bikes/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id_bicicleta: id, estado: newStatus })
        });
        const result = await response.json();
        if (result.success) {
            showPageAlert(result.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showPageAlert(result.message, 'danger');
        }
    } catch (e) {
        showPageAlert('Error al conectar con el servidor.', 'danger');
    }
}

async function quickAdjustBattery(id, currentBat) {
    const newVal = prompt(`Ingresa el nuevo porcentaje de batería (0 - 100):`, currentBat.toString());
    if (newVal === null) return;

    const parsed = parseFloat(newVal);
    if (isNaN(parsed) || parsed < 0 || parsed > 100) {
        alert('Por favor ingresa un número válido entre 0 y 100.');
        return;
    }

    try {
        const response = await fetch('/admin/bikes/battery', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id_bicicleta: id, nivel_bateria: parsed })
        });
        const result = await response.json();
        if (result.success) {
            showPageAlert(result.message, 'success');
            setTimeout(() => window.location.reload(), 900);
        } else {
            showPageAlert(result.message, 'danger');
        }
    } catch (e) {
        showPageAlert('Error al ajustar la batería.', 'danger');
    }
}

async function quickReassignStation(id, currentStationId) {
    const selectOptions = document.getElementById('estacion_act').innerHTML;
    const newStationStr = prompt('Ingresa el ID de la nueva estación (o deja en blanco para sin estación):', currentStationId);
    if (newStationStr === null) return;

    const stationId = newStationStr.trim() !== '' ? parseInt(newStationStr) : null;

    try {
        const response = await fetch('/admin/bikes/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                id_bicicleta: id,
                estacion_act: stationId
            })
        });
        const result = await response.json();
        if (result.success) {
            showPageAlert('Estación reasignada.', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showPageAlert(result.message, 'danger');
        }
    } catch (e) {
        showPageAlert('Error al reasignar estación.', 'danger');
    }
}

async function deleteBike(id, label) {
    if (!confirm(`¿Estás seguro de eliminar la bicicleta "${label}"? Esta acción removerá el vehículo del inventario permanente.`)) {
        return;
    }

    try {
        const response = await fetch('/admin/bikes/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id_bicicleta: id })
        });
        const result = await response.json();
        if (result.success) {
            showPageAlert(result.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showPageAlert(result.message, 'danger');
        }
    } catch (e) {
        showPageAlert('Error al conectar con el servidor.', 'danger');
    }
}

function showPageAlert(message, type) {
    const alertBox = document.getElementById('bikeAlert');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type} d-block mb-4 shadow-sm`;
}
</script>

<?php require_once dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
