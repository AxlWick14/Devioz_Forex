<?php
$canOverview = Access::permite('dashboard');
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="home-page">
    <div class="container home-shell">
        <section class="home-hero">
            <div class="home-hero-copy">
                <span class="eyebrow">Centro de operaciones</span>
                <h1>Todo el mercado.<br><em>Una lectura clara.</em></h1>
                <p>Consulta cotizaciones, convierte divisas y revisa el comportamiento de tus activos desde un mismo espacio.</p>
            </div>

            <aside class="home-command-card">
                <div class="home-card-topline">
                    <span class="home-live"><i></i> Plataforma</span>
                    <span class="home-card-code">FX / LIVE</span>
                </div>
                <div class="home-card-value">En línea</div>
                <p>Herramientas de mercado, conversión y seguimiento en un solo espacio.</p>
                <div class="home-card-footer">
                    <span>Fuente de datos</span>
                    <strong>Twelve Data</strong>
                </div>
            </aside>
        </section>

        <div class="home-toolbar">
            <?php if (Access::permite('dashboard')): ?>
            <a class="btn primary" href="<?= BASE_URL ?>dashboard"><span aria-hidden="true">↗</span> Abrir overview</a>
            <?php endif; ?>
            <a class="btn secondary" href="<?= BASE_URL ?>mercado">Ver mercado</a>
        </div>

        <section class="home-overview" aria-label="Herramientas principales">
            <div class="home-section-heading">
                <div>
                    <span class="eyebrow">Workspace</span>
                    <h2>Empieza por lo importante</h2>
                </div>
                <span class="home-section-count">03 herramientas</span>
            </div>

            <div class="home-tools">
                <a class="home-tool home-tool-featured<?= $canOverview ? '' : ' home-tool-locked' ?>" href="<?= BASE_URL ?><?= $canOverview ? 'dashboard' : 'login?restringido=1' ?>"<?= $canOverview ? '' : ' aria-label="Overview: inicia sesión o crea una cuenta para acceder"' ?>>
                    <span class="home-tool-number">01</span>
                    <div><strong>Overview</strong><small><?= $canOverview ? 'El pulso del mercado en una sola vista.' : 'Inicia sesión o crea una cuenta para acceder.' ?></small></div>
                    <?php if ($canOverview): ?>
                    <span class="home-tool-arrow" aria-hidden="true">↗</span>
                    <?php else: ?>
                    <svg class="home-tool-lock" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
                    <?php endif; ?>
                </a>
                <a class="home-tool" href="<?= BASE_URL ?>calculadora">
                    <span class="home-tool-number">02</span>
                    <div><strong>Calculadora</strong><small>Convierte valores con rapidez.</small></div>
                    <span class="home-tool-arrow" aria-hidden="true">↗</span>
                </a>
                <a class="home-tool" href="<?= BASE_URL ?>historial">
                    <span class="home-tool-number">03</span>
                    <div><strong>Histórico</strong><small>Consulta movimientos anteriores.</small></div>
                    <span class="home-tool-arrow" aria-hidden="true">↗</span>
                </a>
            </div>
        </section>

        <section class="home-bottom-grid">
            <div class="home-note">
                <span class="eyebrow">Diseñado para decidir</span>
                <h2>Menos ruido.<br>Más contexto.</h2>
                <p>La información que necesitas, organizada para comparar, interpretar y actuar sin cambiar de pantalla.</p>
            </div>
            <div class="home-tech panel">
                <div class="home-tech-row"><span>Arquitectura</span><strong>MVC + REST</strong></div>
                <div class="home-tech-row"><span>Datos</span><strong>Divisas + crypto</strong></div>
                <div class="home-tech-row"><span>Seguridad</span><strong>API Key protegida</strong></div>
            </div>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
