<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
$loginEmail = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
?>
<main class="page-shell login-page">
    <div class="container login-layout">
        <section class="login-intro" aria-labelledby="loginIntroTitle">
            <span class="eyebrow">Tu espacio en Devioz FX</span>
            <h2 id="loginIntroTitle">Una vista más clara<br>del <em>mercado.</em></h2>
            <p>Entra a tu cuenta para explorar tendencias y comparar lo que viene.</p>
            <figure class="login-media">
                <div class="login-photo-wrap">
                    <img class="login-photo" src="<?= BASE_URL ?>assets/images/login/nyse.jpg" alt="Fachada de la Bolsa de Nueva York en Wall Street" width="500" height="586" decoding="async">
                    <div class="login-photo-caption"><span>Wall Street · Nueva York</span><strong>Una perspectiva global.</strong></div>
                    <div class="login-global-badge">
                        <img id="loginGlobe" src="<?= BASE_URL ?>assets/images/login/earth-still.svg" data-animated-src="<?= BASE_URL ?>assets/images/login/earth.gif" data-static-src="<?= BASE_URL ?>assets/images/login/earth-still.svg" width="60" height="60" alt="" decoding="async">
                        <span>Mercado global<small>Divisas y criptomonedas</small></span>
                    </div>
                </div>
                <figcaption class="login-media-credit">Foto: <a href="https://commons.wikimedia.org/wiki/File:NYC_NYSE.jpg" target="_blank" rel="noopener noreferrer">Arnoldius y colaboradores</a> · GIF: <a href="https://commons.wikimedia.org/wiki/File:Rotating_earth_(large).gif" target="_blank" rel="noopener noreferrer">Marvel / NASA</a> · <a href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 3.0</a></figcaption>
            </figure>
            <div class="login-media-toolbar">
                <div class="login-visual-assets"><span>Dólar</span><span>Euro</span><span>Bitcoin</span><span>Ethereum</span></div>
                <button id="loginMotionToggle" class="login-motion-toggle" type="button" aria-pressed="false" hidden>Pausar animación</button>
            </div>
            <ul class="login-benefits">
                <li><span aria-hidden="true">01</span><div><strong>El mercado en una vista</strong><small>Consulta el panorama desde Overview.</small></div></li>
                <li><span aria-hidden="true">02</span><div><strong>Gráficos para comparar</strong><small>Explora las predicciones de tus activos.</small></div></li>
            </ul>
            <a class="login-back" href="<?= BASE_URL ?>">← Volver al inicio</a>
        </section>

        <section class="panel account-panel login-card" aria-labelledby="loginTitle">
            <span class="login-card-kicker">Bienvenido de nuevo</span>
            <h1 id="loginTitle">Iniciar sesión</h1>
            <p class="login-card-description">Introduce tus datos para continuar.</p>
            <?php if (isset($_GET['registrado'])): ?>
            <p class="login-notice login-notice-success" role="status">Cuenta creada correctamente. Ya puedes iniciar sesión.</p>
            <?php elseif (isset($_GET['restringido'])): ?>
            <p class="login-notice" role="status">Inicia sesión para abrir esta herramienta. Si aún no tienes cuenta, puedes crearla abajo.</p>
            <?php endif; ?>
            <?php if ($error): ?><p class="login-notice login-notice-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>login">
                <input type="hidden" name="csrf" value="<?= Auth::csrf() ?>">
                <div class="field"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="190" placeholder="tu@correo.com" value="<?= htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8') ?>" required></div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="login-password-field">
                        <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Tu contraseña" required>
                        <button id="loginPasswordToggle" class="login-password-toggle" type="button" aria-controls="password" aria-label="Mostrar contraseña" aria-pressed="false" hidden>Mostrar</button>
                    </div>
                </div>
                <button class="btn primary login-submit" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
            </form>
            <div class="login-register"><span>¿Primera vez aquí?</span><a href="<?= BASE_URL ?>registro">Crear una cuenta <span aria-hidden="true">↗</span></a></div>
            <p class="login-privacy">Tu contraseña es privada. No la compartas con otras personas.</p>
        </section>
    </div>
</main>
<script src="<?= BASE_URL ?>assets/js/login.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/login.js') ?>"></script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
