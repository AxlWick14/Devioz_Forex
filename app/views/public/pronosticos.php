<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">

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
                    <button id="forecastSoundBtn" class="icon-btn" type="button" aria-label="Activar sonidos de pronóstico" title="Activar sonidos de pronóstico"><span aria-hidden="true">◖</span></button>
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

        <section class="panel dashboard-panel forecast-panel historical-forecast-panel">
            <div class="panel-head">
                <div>
                    <span class="eyebrow">Datos históricos</span>
                    <h2>Selecciona un activo</h2>
                </div>
                <span id="forecastHistoryMeta" class="forecast-history-meta">Últimos 60 registros</span>
            </div>

            <div class="forecast-asset-buttons" role="group" aria-label="Activos históricos">
                <button class="forecast-asset-btn" type="button" data-par="SOL/USD">Sol</button>
                <button class="forecast-asset-btn" type="button" data-par="EUR/USD">Euro</button>
                <button class="forecast-asset-btn" type="button" data-par="USD/PEN">Dólar</button>
                <button class="forecast-asset-btn" type="button" data-par="JPY/USD">Yen</button>
                <button class="forecast-asset-btn" type="button" data-par="BTC/USD">Bitcoin</button>
                <button class="forecast-asset-btn" type="button" data-par="ETH/USD">Ethereum</button>
            </div>

            <div class="forecast-history-heading">
                <div>
                    <span class="eyebrow">Precio de cierre</span>
                    <h3 id="forecastHistoryTitle">SOL/USD</h3>
                </div>
                <strong id="forecastHistoryChange">--</strong>
            </div>

            <div class="forecast-chart-wrap">
                <canvas id="forecastHistoryChart" height="110"></canvas>
            </div>
            <div id="forecastHistoryStatus" class="empty-state">Cargando histórico...</div>
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

<div id="forecastToastRegion" class="toast-region" aria-live="polite" aria-atomic="true"></div>

<script>
    window.forecastConfig = {
        apiUrl: <?= json_encode(BASE_URL . 'pronosticos-data', JSON_UNESCAPED_SLASHES) ?>,
        historyUrl: <?= json_encode(BASE_URL . 'historial-data', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>assets/js/forecast.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/forecast.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
