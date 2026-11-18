<?php

declare(strict_types=1);

$title     = 'Ingresar | eBike EMS';
$layout    = 'public';
$mainClass = 'acceso';
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="contenedor acceso__rejilla">
    <section class="formulario-caja" aria-labelledby="titulo-login">
        <h1 id="titulo-login">Ingresar</h1>
        <p>Usa el correo con el que creaste tu cuenta.</p>

        <!-- Contenedor de alertas de la vista -->
        <div id="loginAlert" class="aviso" role="alert" aria-live="polite" hidden></div>

        <form id="loginForm" class="formulario" action="/login" method="POST" novalidate>
            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" autocomplete="email"
                       required placeholder="nombre@correo.com">
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password"
                       autocomplete="current-password" required minlength="8">
            </div>

            <button class="boton boton--ancho" type="submit" id="submitBtn">
                <span id="btnText">Ingresar</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"
                      role="status" aria-hidden="true"></span>
            </button>
        </form>

        <p class="pie-formulario">
            ¿Aún no tienes cuenta? <a href="/register">Crea una cuenta</a>
        </p>
    </section>

    <section class="demo" aria-labelledby="titulo-demo">
        <h2 id="titulo-demo">Un solo acceso, dos experiencias</h2>
        <p>
            El sistema identifica tu rol automáticamente y te lleva al panel que te corresponde.
            Tu contraseña se valida de forma segura y nunca se almacena en texto plano.
        </p>

        <div class="demo__lista">
            <article class="demo__item demo__item--simple">
                <strong>Clientes</strong>
                <span>Alquila bicicletas, consulta tu viaje activo y revisa tu historial.</span>
            </article>

            <article class="demo__item demo__item--simple">
                <strong>Administradores</strong>
                <span>Gestiona estaciones, bicicletas y la operación diaria de la flota.</span>
            </article>

            <article class="demo__item demo__item--simple">
                <strong>Sesión protegida</strong>
                <span>Acceso con contraseñas cifradas y regeneración segura del identificador de sesión.</span>
            </article>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form       = document.getElementById('loginForm');
    const alertBox   = document.getElementById('loginAlert');
    const submitBtn  = document.getElementById('submitBtn');
    const btnText    = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        clearAlert();

        if (!email || !password) {
            showAlert('Por favor completa todos los campos requeridos.', 'warning');
            return;
        }

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

                const redirectUrl = (result.data && result.data.redirect)
                    ? result.data.redirect
                    : '/login';

                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 900);
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
        const variant = type === 'danger'
            ? 'aviso--rojo'
            : (type === 'warning' ? 'aviso--ambar' : 'aviso--verde');

        alertBox.className = `aviso ${variant}`;
        alertBox.innerHTML = '';

        const icon = document.createElement('span');
        icon.className = 'aviso__icono';
        icon.textContent = type === 'success' ? '✓' : '!';

        const text = document.createElement('div');
        text.textContent = message;

        alertBox.append(icon, text);
        alertBox.hidden = false;
    }

    function clearAlert() {
        alertBox.hidden = true;
        alertBox.className = 'aviso';
        alertBox.textContent = '';
    }

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        btnText.classList.toggle('d-none', isLoading);
        btnSpinner.classList.toggle('d-none', !isLoading);
    }
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
