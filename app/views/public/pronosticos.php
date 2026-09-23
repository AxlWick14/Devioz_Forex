<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<main class="page-shell forecast-page">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Machine Learning</span>
            <h1>Pronósticos</h1>
            <p>Estimaciones basadas en tendencias históricas, volatilidad y comportamiento reciente del mercado.</p>
        </div>

        <div class="dashboard-summary forecast-summary">
            <article class="summary-card dashboard-card forecast-card">
                <span>Modelo</span>
                <strong>Trend + Vol.</strong>
                <small>Basado en histórico local</small>
            </article>
            <article class="summary-card dashboard-card forecast-card">
                <span>Máxima confianza</span>
                <strong id="forecastTopConfianza">--</strong>
                <small>Señal principal</small>
            </article>
            <article class="summary-card dashboard-card forecast-card">
                <span>Volatilidad</span>
                <strong id="forecastVolatilityAvg">--</strong>
                <small>Promedio del conjunto</small>
            </article>
            <article class="summary-card dashboard-card forecast-card">
                <span>Horizonte</span>
                <strong>7 días</strong>
                <small>Escenario corto plazo</small>
            </article>
        </div>

        <section class="dashboard-grid forecast-grid">
            <div class="panel dashboard-panel forecast-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Escenarios</span>
                        <h2>Pronósticos activos</h2>
                    </div>
                </div>

                <div id="forecastList" class="forecast-list"></div>
            </div>

            <div class="panel dashboard-panel forecast-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Indicadores</span>
                        <h2>Señales clave</h2>
                    </div>
                </div>

                <div id="forecastSignals" class="signal-list"></div>
            </div>
        </section>

        <section class="panel dashboard-panel forecast-panel full-width">
            <div class="panel-head">
                <div>
                    <span class="eyebrow">Nota</span>
                    <h2>Advertencia de uso</h2>
                </div>
            </div>

            <div class="forecast-warning">
                <p>Los pronósticos mostrados son estimaciones analíticas generadas a partir del histórico almacenado en la base de datos y no constituyen recomendación de inversión ni consejo financiero.</p>
            </div>
        </section>
    </section>
</main>

<script>
    window.forecastConfig = {
        apiUrl: <?= json_encode(BASE_URL . 'pronosticos-data', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= BASE_URL ?>assets/js/forecast.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/forecast.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
