<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="page-shell">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Seguimiento</span>
            <h1>Alertas del mercado</h1>
            <p>Configuración de avisos cuando una cotización alcance una condición definida.</p>
        </div>

        <div class="panel">
            <strong>Estado: 0%</strong>
            <p>La estructura de alertas todavía no está implementada.</p>
            <p><strong>Pendiente:</strong> crear alertas por precio, variación porcentual y cambio de tendencia; guardarlas, evaluarlas automáticamente y mostrar su estado.</p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
