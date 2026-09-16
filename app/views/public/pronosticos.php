<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="page-shell">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Machine Learning</span>
            <h1>Pronósticos</h1>
            <p>Estimaciones estadísticas sobre el posible comportamiento futuro de un par de divisas.</p>
        </div>

        <div class="panel">
            <strong>Estado: 0%</strong>
            <p>Este módulo todavía no tiene un modelo predictivo conectado.</p>
            <p><strong>Pendiente:</strong> preparar datos históricos, calcular promedios móviles y volatilidad, entrenar el modelo, mostrar la estimación y añadir el aviso de que no es una recomendación de inversión.</p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
