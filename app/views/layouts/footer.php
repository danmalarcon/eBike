<?php

declare(strict_types=1);

use App\Helpers\Session;

$layout = $layout ?? 'public';

if ($layout === 'app'):
?>
        </div><!-- /.app -->
<?php else: ?>
        </main>

        <footer class="pie">
            <div class="contenedor pie__fila">
                <div>
                    <strong>eBike Management System</strong>
                    <p>Sistema de alquiler de bicicletas eléctricas compartidas.</p>
                    <p>Proyecto formativo SENA, Técnico en Desarrollo de Software.</p>
                </div>
                <nav aria-label="Accesos del sistema">
                    <strong>Accesos</strong>
                    <ul>
                        <li><a href="/">Inicio</a></li>
                        <?php if (Session::isLoggedIn()): ?>
                            <?php
                            $footerUser    = Session::getUser();
                            $footerRolId   = (int)($footerUser['rol_id'] ?? 0);
                            $footerIsAdmin = $footerRolId === 1 || (($footerUser['codigo_rol'] ?? '') === 'admin');
                            ?>
                            <li>
                                <a href="<?= $footerIsAdmin ? '/admin/dashboard' : '/user/dashboard' ?>">
                                    Mi panel
                                </a>
                            </li>
                            <li><a href="/logout">Cerrar sesión</a></li>
                        <?php else: ?>
                            <li><a href="/login">Ingresar</a></li>
                            <li><a href="/register">Crear cuenta</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </footer>
<?php endif; ?>

    <!-- Bootstrap 5.3 Bundle JS con Popper CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script base del cliente -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
