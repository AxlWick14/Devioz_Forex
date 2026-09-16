<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="page-shell">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Mercado</span>
            <h1>Forex + Crypto</h1>
            <p>Las cotizaciones actuales se muestran en la calculadora. Esta vista queda preparada para ampliar el dashboard.</p>
        </div>

        <div class="panel">
            <p>Abre la calculadora para ver precios actuales y convertir el mismo monto a otras divisas y criptomonedas.</p>
            <a class="btn primary" href="<?= BASE_URL ?>calculadora">Ver cotizaciones</a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
