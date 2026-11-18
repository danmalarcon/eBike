<?php

declare(strict_types=1);

$title     = 'Crear cuenta | eBike EMS';
$layout    = 'public';
$mainClass = 'acceso acceso--centrado';
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="contenedor acceso__rejilla">
    <section class="formulario-caja" aria-labelledby="titulo-registro">
        <h1 id="titulo-registro">Crear cuenta</h1>
        <p>Con tu cuenta puedes alquilar bicicletas y consultar tu historial de viajes.</p>

        <!-- Contenedor de alertas de la vista -->
        <div id="registerAlert" class="aviso" role="alert" aria-live="polite" hidden></div>

        <form id="registerForm" class="formulario formulario--rejilla"
              action="/register" method="POST" novalidate>
            <div class="campo campo--completo">
                <label for="nombre">Nombre completo</label>
                <input id="nombre" name="nombre" type="text" autocomplete="name"
                       required minlength="3" maxlength="150" placeholder="Laura Gómez">
            </div>

            <div class="campo campo--completo">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" autocomplete="email"
                       required maxlength="150" placeholder="nombre@correo.com">
            </div>

            <div class="campo campo--completo">
                <label for="telefono">Teléfono celular</label>
                <input id="telefono" name="telefono" type="tel" autocomplete="tel"
                       inputmode="numeric" pattern="3[0-9]{9}" maxlength="10"
                       placeholder="3001112233" aria-describedby="ayuda-telefono">
                <span class="ayuda" id="ayuda-telefono">
                    Opcional. Si lo ingresas, usa 10 dígitos y empieza por 3.
                </span>
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password"
                       autocomplete="new-password" required minlength="8"
                       aria-describedby="ayuda-clave">
            </div>

            <div class="campo">
                <label for="clave2">Repite la contraseña</label>
                <input id="clave2" name="clave2" type="password"
                       autocomplete="new-password" required minlength="8">
            </div>

            <span class="ayuda campo--completo" id="ayuda-clave">
                Mínimo 8 caracteres. Tu contraseña se guarda cifrada con bcrypt.
            </span>

            <div class="campo campo--check campo--completo">
                <input id="acepto" name="acepto" type="checkbox" required>
                <label for="acepto">
                    Acepto los términos de uso y el tratamiento de mis datos personales.
                </label>
            </div>

            <div class="acciones campo--completo">
                <button class="boton" type="submit" id="submitBtn">
                    <span id="btnText">Crear cuenta</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"
                          role="status" aria-hidden="true"></span>
                </button>
                <a href="/login">Ya tengo cuenta</a>
            </div>
        </form>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form       = document.getElementById('registerForm');
    const alertBox   = document.getElementById('registerAlert');
    const submitBtn  = document.getElementById('submitBtn');
    const btnText    = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombre   = document.getElementById('nombre').value.trim();
        const email    = document.getElementById('email').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const password = document.getElementById('password').value;
        const clave2   = document.getElementById('clave2').value;
        const acepto   = document.getElementById('acepto').checked;

        clearAlert();

        if (!nombre || !email || !password || !clave2) {
            showAlert('Por favor completa los campos obligatorios.', 'warning');
            return;
        }

        if (password.length < 8) {
            showAlert('La contraseña debe tener un mínimo de 8 caracteres.', 'warning');
            return;
        }

        if (password !== clave2) {
            showAlert('Las contraseñas no coinciden. Verifícalas e inténtalo de nuevo.', 'warning');
            return;
        }

        if (!acepto) {
            showAlert('Debes aceptar los términos de uso para crear tu cuenta.', 'warning');
            return;
        }

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
