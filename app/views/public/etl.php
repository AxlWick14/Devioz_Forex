<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="page-shell">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Datos</span>
            <h1>Proceso ETL</h1>
            <p>Control del proceso de extracción, transformación y carga de información financiera.</p>
        </div>

        <div class="panel">
            <strong>Estado: 15%</strong>
            <p>La extracción actual desde Twelve Data funciona de forma temporal mediante cache JSON.</p>
            <p><strong>Pendiente:</strong> limpiar y normalizar datos, guardar cotizaciones en MySQL, evitar duplicados y programar la ejecución automática.</p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
