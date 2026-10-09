<?php
$isAdmin = Auth::rol() === 'admin';
$_SESSION['history_csrf'] ??= bin2hex(random_bytes(32));
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forecast.css?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/css/forecast.css') ?>">

<main class="page-shell forecast-page <?= $isAdmin ? 'forecast-admin' : 'forecast-user' ?>">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Módulo Forex / Análisis</span>
            <h1>Pronósticos</h1>
            <p>Estimaciones basadas en tendencias históricas, volatilidad y comportamiento reciente del mercado.</p>
        </div>

        <div class="forecast-toolbar">
            <div><strong>Explora los escenarios del mercado</strong><small id="forecastUpdated" role="status">Cargando pronósticos...</small></div>
            <div class="forecast-toolbar-actions">
                <label class="refresh-toggle" for="forecastAutoRefresh" title="Actualizar pronósticos automáticamente cada 70 segundos">
                    <input id="forecastAutoRefresh" type="checkbox">
                    <span class="toggle-track" aria-hidden="true"></span>
                    <span>Auto</span>
                </label>
                <button id="forecastRefresh" class="btn primary" type="button">↻ Actualizar</button>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="dashboard-summary forecast-summary">
            <article class="summary-card dashboard-card forecast-card">
                <span>Modelo</span>
                <strong id="forecastModelName">Regresión lineal</strong>
                <small>Basado en histórico local</small>
            </article>
            <article class="summary-card dashboard-card forecast-card">
                <span>Mayor puntuación</span>
                <strong id="forecastTopConfianza">--</strong>
                <small id="forecastTopAsset">Señal principal</small>
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
        <?php endif; ?>

        <section class="dashboard-grid forecast-grid">
            <div class="panel dashboard-panel forecast-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Escenarios</span>
                        <h2>Pronósticos activos</h2>
                    </div>
                    <button id="forecastSoundBtn" class="forecast-control" type="button" aria-pressed="false" aria-label="Activar sonidos de pronóstico" title="Activar sonidos de pronóstico">Sonido: apagado</button>
                </div>

                <div class="forecast-filters" role="group" aria-label="Filtrar por tendencia">
                    <button class="forecast-control active" data-trend="all" aria-pressed="true" type="button">Todos</button>
                    <button class="forecast-control" data-trend="alcista" aria-pressed="false" type="button">↗ Alcistas</button>
                    <button class="forecast-control" data-trend="bajista" aria-pressed="false" type="button">↘ Bajistas</button>
                </div>
                <div id="forecastList" class="forecast-list" aria-busy="true"><div class="empty-state">Cargando escenarios...</div></div>
                <p class="forecast-probability-note">Probabilidades estimadas de subida y bajada a 7 días, respecto al último cierre. Se calculan con errores de pronósticos históricos; no garantizan el resultado futuro.</p>
            </div>

            <?php if ($isAdmin): ?>
            <div class="panel dashboard-panel forecast-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Indicadores</span>
                        <h2>Puntuación del modelo</h2>
                    </div>
                </div>

                <div id="forecastSignals" class="signal-list"></div>
            </div>
            <?php endif; ?>
        </section>

        <section id="forecastHistory" class="panel dashboard-panel forecast-panel historical-forecast-panel">
            <?php if ($isAdmin): ?>
            <div class="forecast-toolbar">
                <div><strong>Completar datos históricos</strong><small>Dólar, euro, Bitcoin y Ethereum · Últimos 180 días · Cierres publicados, sin incluir la vela de hoy.</small></div>
                <button id="forecastSyncHistory" class="btn primary" type="button">↻ Actualizar histórico</button>
            </div>
            <div id="forecastSyncStatus" role="status" aria-live="polite" class="forecast-projection-note" hidden></div>
            <?php else: ?>
            <p id="forecastDecision" class="forecast-decision" role="status">Comparando las predicciones a 7 días...</p>
            <?php endif; ?>
            <div class="panel-head">
                <div>
                    <span class="eyebrow">Pasado · Presente · Futuro</span>
                    <h2>Histórico y predicción</h2>
                </div>
                <span id="forecastHistoryMeta" class="forecast-history-meta">Proyección a 7 días</span>
            </div>

            <div class="forecast-asset-buttons" role="group" aria-label="Activos históricos">
                <button class="forecast-asset-btn" type="button" data-par="USD/PEN">Dólar (USD/PEN)</button>
                <button class="forecast-asset-btn" type="button" data-par="EUR/USD">Euro (EUR/USD)</button>
                <button class="forecast-asset-btn" type="button" data-par="BTC/USD">Bitcoin (BTC)</button>
                <button class="forecast-asset-btn" type="button" data-par="ETH/USD">Ethereum (ETH)</button>
            </div>

            <?php if ($isAdmin): ?>
            <div class="forecast-method-picker">
                <fieldset id="forecastMethod" class="forecast-model-buttons" aria-describedby="forecastMethodDescription">
                    <legend>Modelo de predicción</legend>
                    <?php foreach (['lineal' => 'Regresión lineal', 'logaritmica' => 'Regresión logarítmica', 'holt' => 'Holt amortiguado', 'arima' => 'ARIMA (1,1,0)', 'boosting' => 'XGBoost'] as $method => $label): ?>
                    <div class="forecast-model-option">
                        <button type="button" class="forecast-control <?= $method === 'lineal' ? 'active' : '' ?>" data-method="<?= $method ?>" aria-pressed="<?= $method === 'lineal' ? 'true' : 'false' ?>"><?= $label ?></button>
                        <button type="button" class="forecast-model-help" data-method-help="<?= $method ?>" aria-label="Acerca de <?= $label ?>" aria-haspopup="dialog">?</button>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                <p>5 modelos disponibles: regresión lineal, regresión logarítmica, Holt, ARIMA (1,1,0) y XGBoost. Selecciona uno para comparar sus predicciones.</p>
                <p id="forecastMethodDescription" role="status">Ajusta una recta a los cierres según su fecha: precio(t) = a + b × t.</p>
            </div>

            <div id="forecastModelDetails" class="forecast-model-details" aria-live="polite"></div>
            <p class="forecast-validation-note">Regresión lineal, regresión logarítmica, Holt y ARIMA eligen entre ventanas de 30, 60 y 120 cierres; XGBoost utiliza hasta 500 cierres reales. Los errores se evalúan sobre las mismas fechas de los últimos 21 orígenes, a hasta 7 días y sin usar datos futuros en cada ajuste. MAPE es el error porcentual absoluto medio: menor es mejor. Se compara con mantener el último cierre. Las comparaciones pueden compartir fechas; sirven para elegir el modelo y no garantizan precisión futura.</p>
            <?php endif; ?>

            <div class="forecast-history-heading">
                <div>
                    <span class="eyebrow" id="forecastPriceUnit">Cambio estimado a 7 días</span>
                    <h3 id="forecastHistoryTitle">BTC/USD</h3>
                </div>
                <strong id="forecastHistoryChange">--</strong>
            </div>

            <div class="forecast-chart-legend"><span><i class="history-key"></i>Pasado: cierres registrados</span><span><i class="bridge-key"></i>Conexión estimada: sin cierres confirmados</span><span><i class="future-key"></i>Futuro: predicción del modelo</span></div>
            <div class="forecast-chart-wrap">
                <canvas id="forecastHistoryChart" height="110" role="img" aria-label="Histórico y predicción a siete días" aria-describedby="forecastProjectionNote"></canvas>
            </div>
            <p id="forecastProjectionNote" class="forecast-projection-note"><?php if ($isAdmin): ?>La línea azul muestra cierres registrados. La conexión amarilla une el último cierre con la estimación de hoy y el primer pronóstico: es una guía visual, no cotizaciones observadas. La zona rayada indica datos faltantes. La predicción violeta parte de la estimación de hoy. El cambio porcentual compara el objetivo a 7 días con el último cierre. El sombreado no es un intervalo de confianza.<?php else: ?>Azul: precios registrados. Violeta: predicción a 7 días. Amarillo: conexión estimada donde faltan cierres. El porcentaje compara el último cierre con el precio previsto.<?php endif; ?></p>
            <div id="forecastHistoryStatus" class="empty-state" role="status">Cargando histórico...</div>
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
<?php if ($isAdmin): ?>
<dialog id="forecastModelDialog" class="forecast-model-dialog" aria-labelledby="forecastModelDialogTitle">
    <h2 id="forecastModelDialogTitle"></h2>
    <p id="forecastModelDialogText"></p>
    <p>Ningún método garantiza mayor precisión sin evaluar sus errores sobre datos nuevos.</p>
    <form method="dialog"><button class="btn primary" autofocus>Cerrar</button></form>
</dialog>
<?php endif; ?>

<script>
    window.forecastConfig = {
        isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
        apiUrl: <?= json_encode(BASE_URL . 'pronosticos-data', JSON_UNESCAPED_SLASHES) ?>,
        syncUrl: <?= json_encode(BASE_URL . 'historial-sync', JSON_UNESCAPED_SLASHES) ?>,
        csrfToken: <?= json_encode($_SESSION['history_csrf']) ?>,
        historyUrl: <?= json_encode(BASE_URL . 'historial-data', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>assets/js/forecast.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/forecast.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
