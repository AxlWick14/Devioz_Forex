<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="page-shell">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Panel principal</span>
            <h1>Dashboard Forex</h1>
            <p>Vista central para reunir cotizaciones, variaciones, tendencias, gráficos, pronósticos y alertas.</p>
        </div>

        <div class="panel">
            <strong>Estado: 20%</strong>
            <p>Ya existe la consulta de cotizaciones y el módulo Trader. Falta integrar aquí los datos de mercado, el historial, los pronósticos y las alertas.</p>
            <p><strong>Pendiente:</strong> tarjetas resumen, gráficos principales, tendencias, pronósticos recientes, alertas activas y última actualización.</p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
