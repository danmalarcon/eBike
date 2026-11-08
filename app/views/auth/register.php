<?php
declare(strict_types=1);

$title = 'Registro de Cuenta - eBike EMS';
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4 p-sm-5">
                    <div class="text-center mb-4">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                            <i class="bi bi-person-plus fs-2"></i>
                        </div>
                        <h2 class="fw-bold fs-4">Crear una Cuenta</h2>
                        <p class="text-muted small">Únete a eBike EMS y empieza a pedalear</p>
                    </div>

                    <!-- Contenedor dinámico de alertas -->
                    <div id="registerAlert" class="alert d-none" role="alert"></div>

                    <form id="registerForm" novalidate>
                        <div class="mb-3">
                            <label for="nombre" class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Carlos Ramírez" required autocomplete="name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="carlos@correo.com" required autocomplete="email">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="telefono" class="form-label fw-semibold">Teléfono Móvil</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="+57 300 123 4567" autocomplete="tel">
                            </div>
                            <div class="form-text">Opcional. Utilizado para notificaciones del servicio.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Mínimo 8 caracteres" minlength="8" required autocomplete="new-password">
                            </div>
                            <div class="form-text">Debe contener como mínimo 8 caracteres.</div>
                        </div>

                        <button type="submit" id="submitBtn" class="btn btn-success w-100 py-2 fw-semibold">
                            <span id="btnText"><i class="bi bi-check-circle me-1"></i>Registrar Cuenta</span>
                            <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </form>

                    <hr class="my-4 text-muted">

                    <div class="text-center">
                        <span class="text-muted small">¿Ya tienes una cuenta registrada? </span>
                        <a href="/login" class="text-decoration-none fw-semibold text-success small">Inicia sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    const alertBox = document.getElementById('registerAlert');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombre = document.getElementById('nombre').value.trim();
        const email = document.getElementById('email').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const password = document.getElementById('password').value;

        // Limpiar alertas
        alertBox.className = 'alert d-none';
        alertBox.textContent = '';

        if (!nombre || !email || !password) {
            showAlert('Por favor completa los campos obligatorios (*).', 'warning');
            return;
        }

        if (password.length < 8) {
            showAlert('La contraseña debe tener un mínimo de 8 caracteres.', 'warning');
            return;
        }

        // Estado de carga
        setLoading(true);

        try {
            const response = await fetch('/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ nombre, email, telefono, password })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showAlert(result.message || 'Registro exitoso. Redirigiendo al login...', 'success');
                form.reset();
                setTimeout(() => {
                    window.location.href = '/login';
                }, 1500);
            } else {
                showAlert(result.message || 'Error al procesar el registro.', 'danger');
                setLoading(false);
            }
        } catch (error) {
            showAlert('Ocurrió un error al conectar con el servidor. Inténtalo de nuevo.', 'danger');
            setLoading(false);
        }
    });

    function showAlert(message, type) {
        alertBox.textContent = message;
        alertBox.className = `alert alert-${type} d-block mb-3`;
    }

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        if (isLoading) {
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
        } else {
            btnText.classList.remove('d-none');
            btnSpinner.classList.add('d-none');
        }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
