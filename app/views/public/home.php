<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <span class="eyebrow">Plataforma Forex</span>
                <h1>Divisas y criptomonedas en una sola plataforma.</h1>
                <p>Consulta cotizaciones actuales, convierte monedas y revisa equivalencias obtenidas mediante una API financiera.</p>
                <div class="actions">
                    <a class="btn primary" href="<?= BASE_URL ?>calculadora">Abrir calculadora</a>
                    <a class="btn secondary" href="<?= BASE_URL ?>mercado">Ver mercado</a>
                </div>
            </div>

            <div class="hero-card">
                <span class="eyebrow">Arquitectura</span>
                <strong>MVC + API REST</strong>
                <p>PHP consulta la API desde el servidor y JavaScript actualiza la interfaz sin exponer la API Key.</p>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
