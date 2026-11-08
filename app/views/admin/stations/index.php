<?php
declare(strict_types=1);

$title = 'Gestión de Estaciones - Panel Admin';
require_once dirname(__DIR__, 2) . '/layouts/header.php';

// Conteo de métricas de estaciones
$totalEstaciones = count($stations);
$operativas = count(array_filter($stations, fn($s) => $s['estado'] === 'operativa'));
$enMantenimiento = count(array_filter($stations, fn($s) => $s['estado'] === 'mantenimiento'));
$inactivas = count(array_filter($stations, fn($s) => $s['estado'] === 'inactiva'));
?>

<div class="container py-4">
    <!-- Encabezado y Navegación -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/admin/stations" class="text-decoration-none">Administración</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Estaciones</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0">Gestión de Estaciones de Recarga y Estacionamiento</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/bikes" class="btn btn-outline-secondary">
                <i class="bi bi-bicycle me-1"></i>Ver Bicicletas
            </a>
            <button type="button" class="btn btn-success" onclick="openCreateModal()">
                <i class="bi bi-plus-circle me-1"></i>Nueva Estación
            </button>
        </div>
    </div>

    <!-- Tarjetas de Métricas Rápidas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <span class="text-muted small fw-semibold">Total Estaciones</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= $totalEstaciones ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4">
                <div class="card-body">
                    <span class="text-success small fw-semibold">Operativas</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success"><?= $operativas ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-warning border-4">
                <div class="card-body">
                    <span class="text-warning small fw-semibold">En Mantenimiento</span>
                    <h3 class="fw-bold mb-0 mt-1 text-warning"><?= $enMantenimiento ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-secondary border-4">
                <div class="card-body">
                    <span class="text-secondary small fw-semibold">Inactivas</span>
                    <h3 class="fw-bold mb-0 mt-1 text-secondary"><?= $inactivas ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas Dinámicas -->
    <div id="stationAlert" class="alert d-none" role="alert"></div>

    <!-- Tabla Principal de Estaciones -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Nombre</th>
                            <th>Dirección y Coordenadas</th>
                            <th>Ocupación / Capacidad</th>
                            <th>Energía Disp.</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-geo-alt display-6 d-block mb-2 text-secondary"></i>
                                    No hay estaciones registradas aún.<br>
                                    <button class="btn btn-sm btn-success mt-2" onclick="openCreateModal()">Crear la primera estación</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stations as $station): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($station['codigo']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($station['nombre']) ?></div>
                                        <small class="text-muted">ID: #<?= (int)$station['id_estacion'] ?></small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($station['direccion']) ?></div>
                                        <small class="text-muted"><i class="bi bi-geo-fill me-1"></i><?= htmlspecialchars($station['coordenadas']) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold"><?= (int)($station['total_bicicletas'] ?? 0) ?></span> / <span class="text-muted"><?= (int)$station['capacidad'] ?></span>
                                            <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                                <?php 
                                                    $pct = $station['capacidad'] > 0 ? min(100, round(($station['total_bicicletas'] / $station['capacidad']) * 100)) : 0;
                                                    $barColor = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary');
                                                ?>
                                                <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format((float)($station['energia_disp'] ?? 0), 2) ?></span> <small class="text-muted">kWh</small>
                                    </td>
                                    <td>
                                        <?php if ($station['estado'] === 'operativa'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Operativa</span>
                                        <?php elseif ($station['estado'] === 'mantenimiento'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-tools me-1"></i>Mantenimiento</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-x-circle me-1"></i>Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" title="Editar estación" onclick="openEditModal(<?= (int)$station['id_estacion'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Opciones</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li><h6 class="dropdown-header">Cambiar Estado</h6></li>
                                                <li><a class="dropdown-item small" href="#" onclick="changeStatus(<?= (int)$station['id_estacion'] ?>, 'operativa')"><i class="bi bi-check-circle text-success me-2"></i>Marcar Operativa</a></li>
                                                <li><a class="dropdown-item small" href="#" onclick="changeStatus(<?= (int)$station['id_estacion'] ?>, 'mantenimiento')"><i class="bi bi-tools text-warning me-2"></i>Marcar Mantenimiento</a></li>
                                                <li><a class="dropdown-item small" href="#" onclick="changeStatus(<?= (int)$station['id_estacion'] ?>, 'inactiva')"><i class="bi bi-x-circle text-secondary me-2"></i>Marcar Inactiva</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item small text-danger" href="#" onclick="deleteStation(<?= (int)$station['id_estacion'] ?>, '<?= htmlspecialchars(addslashes($station['nombre'])) ?>')"><i class="bi bi-trash me-2"></i>Eliminar</a></li>
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

<!-- Modal para Crear / Editar Estación -->
<div class="modal fade" id="stationModal" tabindex="-1" aria-labelledby="stationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="stationModalTitle">Nueva Estación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="stationForm" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="stationId" name="id_estacion">

                    <div id="modalAlert" class="alert d-none" role="alert"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="codigo" class="form-label fw-semibold">Código Único <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="codigo" name="codigo" placeholder="Ej: EST-01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="capacidad" class="form-label fw-semibold">Capacidad Bicis <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" placeholder="Ej: 15" required>
                        </div>
                        <div class="col-12">
                            <label for="nombre" class="form-label fw-semibold">Nombre de la Estación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Estación Parque Central" required>
                        </div>
                        <div class="col-12">
                            <label for="direccion" class="form-label fw-semibold">Dirección <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Ej: Carrera 43A # 1-50" required>
                        </div>
                        <div class="col-md-6">
                            <label for="coordenadas" class="form-label fw-semibold">Coordenadas GPS <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="coordenadas" name="coordenadas" placeholder="-75.567, 6.245" required>
                        </div>
                        <div class="col-md-6">
                            <label for="energia_disp" class="form-label fw-semibold">Energía Disp. (kWh)</label>
                            <input type="number" step="0.01" class="form-control" id="energia_disp" name="energia_disp" placeholder="0.00" value="0.00">
                        </div>
                        <div class="col-12">
                            <label for="estado" class="form-label fw-semibold">Estado de la Estación</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="operativa">Operativa</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="inactiva">Inactiva</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="saveStationBtn" class="btn btn-success">
                        <span id="saveBtnText"><i class="bi bi-save me-1"></i>Guardar Estación</span>
                        <span id="saveBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let stationModalInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    stationModalInstance = new bootstrap.Modal(document.getElementById('stationModal'));

    const form = document.getElementById('stationForm');
    form.addEventListener('submit', handleFormSubmit);
});

function openCreateModal() {
    document.getElementById('stationModalTitle').textContent = 'Nueva Estación';
    document.getElementById('stationForm').reset();
    document.getElementById('stationId').value = '';
    document.getElementById('modalAlert').className = 'alert d-none';
    stationModalInstance.show();
}

async function openEditModal(id) {
    document.getElementById('stationModalTitle').textContent = 'Editar Estación';
    document.getElementById('stationForm').reset();
    document.getElementById('modalAlert').className = 'alert d-none';

    try {
        const response = await fetch(`/admin/stations/get?id=${id}`);
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data;
            document.getElementById('stationId').value = data.id_estacion;
            document.getElementById('codigo').value = data.codigo;
            document.getElementById('nombre').value = data.nombre;
            document.getElementById('direccion').value = data.direccion;
            document.getElementById('coordenadas').value = data.coordenadas;
            document.getElementById('capacidad').value = data.capacidad;
            document.getElementById('energia_disp').value = data.energia_disp;
            document.getElementById('estado').value = data.estado;
            stationModalInstance.show();
        } else {
            showPageAlert(result.message || 'Error al cargar los datos de la estación.', 'danger');
        }
    } catch (e) {
        showPageAlert('Error al comunicarse con el servidor.', 'danger');
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();

    const id = document.getElementById('stationId').value;
    const isEdit = id !== '';
    const url = isEdit ? '/admin/stations/update' : '/admin/stations/create';

    const payload = {
        id_estacion: id ? parseInt(id) : undefined,
        codigo: document.getElementById('codigo').value.trim(),
        nombre: document.getElementById('nombre').value.trim(),
        direccion: document.getElementById('direccion').value.trim(),
        coordenadas: document.getElementById('coordenadas').value.trim(),
        capacidad: parseInt(document.getElementById('capacidad').value),
        energia_disp: parseFloat(document.getElementById('energia_disp').value) || 0.00,
        estado: document.getElementById('estado').value
    };

    const modalAlert = document.getElementById('modalAlert');
    const saveBtn = document.getElementById('saveStationBtn');
    const btnText = document.getElementById('saveBtnText');
    const btnSpinner = document.getElementById('saveBtnSpinner');

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
            stationModalInstance.hide();
            showPageAlert(result.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            modalAlert.textContent = result.message || 'Error al guardar la estación.';
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

async function changeStatus(id, newStatus) {
    try {
        const response = await fetch('/admin/stations/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id_estacion: id, estado: newStatus })
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

async function deleteStation(id, name) {
    if (!confirm(`¿Estás seguro de eliminar la estación "${name}"? Esta acción no se puede deshacer.`)) {
        return;
    }

    try {
        const response = await fetch('/admin/stations/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id_estacion: id })
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
    const alertBox = document.getElementById('stationAlert');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type} d-block mb-4 shadow-sm`;
}
</script>

<?php require_once dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
