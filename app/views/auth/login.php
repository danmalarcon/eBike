<?php
declare(strict_types=1);

$title = 'Iniciar Sesión - eBike EMS';
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4 p-sm-5">
                    <div class="text-center mb-4">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                            <i class="bi bi-bicycle fs-2"></i>
                        </div>
                        <h2 class="fw-bold fs-4">Bienvenido a eBike EMS</h2>
                        <p class="text-muted small">Ingresa tus credenciales para acceder a la plataforma</p>
                    </div>

                    <!-- Contenedor dinámico para alertas y mensajes -->
                    <div id="loginAlert" class="alert d-none" role="alert"></div>

                    <form id="loginForm" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="ejemplo@correo.com" required autocomplete="email">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Tu contraseña" required autocomplete="current-password">
                            </div>
                        </div>

                        <button type="submit" id="submitBtn" class="btn btn-success w-100 py-2 fw-semibold">
                            <span id="btnText"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar Sesión</span>
                            <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </form>

                    <hr class="my-4 text-muted">

                    <div class="text-center">
                        <span class="text-muted small">¿Aún no tienes una cuenta? </span>
                        <a href="/register" class="text-decoration-none fw-semibold text-success small">Regístrate aquí</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const alertBox = document.getElementById('loginAlert');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        // Limpiar estado de alerta
        alertBox.className = 'alert d-none';
        alertBox.textContent = '';

        if (!email || !password) {
            showAlert('Por favor completa todos los campos requeridos.', 'warning');
            return;
        }

        // Estado de carga
        setLoading(true);

        try {
            const response = await fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showAlert(result.message || 'Inicio de sesión exitoso. Redirigiendo...', 'success');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 1200);
            } else {
                showAlert(result.message || 'Error al iniciar sesión.', 'danger');
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
