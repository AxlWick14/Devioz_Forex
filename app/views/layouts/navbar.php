<header class="site-header">
    <div class="container nav-wrap">
        <div class="brand-controls">
            <a class="brand" href="<?= BASE_URL ?>">
                <span class="brand-mark">FX</span>
                <span>Devioz FX</span>
            </a>
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Cambiar tema. Modo actual: modo nocturno" title="Cambiar a modo atardecer carmesí">
                <svg class="theme-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>
            </button>
            <?php if (Auth::usuario()): ?>
            <form class="nav-session session-access" method="post" action="<?= BASE_URL ?>logout">
                <span title="<?= htmlspecialchars(Auth::usuario()['nombre'], ENT_QUOTES, 'UTF-8') ?>"><?= Auth::rol() === 'admin' ? 'Administrador' : 'Usuario' ?></span>
                <input type="hidden" name="csrf" value="<?= Auth::csrf() ?>">
                <button type="submit">Salir</button>
            </form>
            <?php else: ?>
            <a class="session-login session-access" href="<?= BASE_URL ?>login">Iniciar sesión</a>
            <?php endif; ?>
        </div>

        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menú">☰</button>

        <nav class="main-nav" id="mainNav">
            <a href="<?= BASE_URL ?>">HOME</a>
            <a href="<?= BASE_URL ?>mercado">MERCADO</a>
            <a href="<?= BASE_URL ?>calculadora">CALCULADORA</a>
            <a href="<?= BASE_URL ?>historial">HISTORICO</a>
            <?php if (Access::permite('dashboard')): ?>
            <a href="<?= BASE_URL ?>dashboard">OVERVIEW</a>
            <a href="<?= BASE_URL ?>pronosticos">PRONÓSTICOS</a>
            <?php endif; ?>
            <?php if (Access::permite('admin')): ?>
            <a class="admin-enchanted" href="<?= BASE_URL ?>admin" <?= str_starts_with(trim($_GET['url'] ?? '', '/') . '/', 'admin/') ? 'aria-current="page"' : '' ?>><span class="admin-enchanted-label">ADMINISTRACIÓN</span></a>
            <?php endif; ?>
        </nav>
    </div>
</header>
