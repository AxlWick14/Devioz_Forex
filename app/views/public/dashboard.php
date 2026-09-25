<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">

<main class="page-shell dashboard-page">
    <div class="dashboard-layout container">
        <div class="dashboard-content">
            <div class="dashboard-topline">
                <div class="page-heading">
                    <h1>OVERVIEW</h1>
                    <p>Una plataforma operativa diseñada para monitorear el pulso de tus activos en tiempo real, identificar tendencias clave y tomar decisiones informadas con contexto estratégico.</p>
                </div>
                <div class="dashboard-clock">
                    <span>Última sincronización</span>
                    <strong id="dashboardLiveClock">--:--</strong>
                    <small id="dashboardSyncState">Conectando...</small>
                </div>
            </div>

            <div class="dashboard-toolbar" id="overview">
                <div class="toolbar-actions">
                    <button id="dashboardSoundBtn" class="icon-btn" type="button" aria-label="Activar sonidos" title="Activar sonidos"><span aria-hidden="true">◖</span></button>
                    <label class="refresh-toggle" for="dashboardAutoRefresh">
                        <input id="dashboardAutoRefresh" type="checkbox">
                        <span class="toggle-track" aria-hidden="true"></span>
                        <span>Auto</span>
                    </label>
                    <button id="dashboardRefreshBtn" class="btn primary" type="button"><span class="button-icon" aria-hidden="true">↻</span> Actualizar</button>
                </div>
            </div>

        <div class="dashboard-summary" id="dashboardSummary">
            <article class="summary-card dashboard-card">
                <span>Mercado</span>
                <strong id="summaryMarketState">--</strong>
                <small id="summaryMarketLabel">Sin datos</small>
            </article>
            <article class="summary-card dashboard-card">
                <span>Última actualización</span>
                <strong id="summaryUpdatedAt">--</strong>-
                <small id="summarySource">Fuente: --</small>
            </article>
            <article class="summary-card dashboard-card">
                <span>Mayor movimiento</span>
                <strong id="summaryTopMover">--</strong>
                <small id="summaryTopMoverMeta">--</small>
            </article>
            <article class="summary-card dashboard-card">
                <span>Rendimiento medio</span>
                <strong id="summaryAverageChange">--</strong>
                <small>Promedio del mercado</small>
            </article>
        </div>

        <section class="dashboard-grid" id="assets">
            <div class="panel dashboard-panel" id="signals">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Tendencia</span>
                        <h2>Principales cotizaciones</h2>
                    </div>
                    <div class="dashboard-filter-group" aria-label="Filtrar cotizaciones">
                        <button class="filter-btn active" type="button" data-filter="forex">FOREX</button>
                        <button class="filter-btn" type="button" data-filter="crypto">CRYPTO</button>
                    </div>
                </div>
                <div id="dashboardMarketCards" class="dashboard-cards"></div>
            </div>

            <div class="panel dashboard-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Monitoreo automático</span>
                        <h2>Alertas del mercado</h2>
                    </div>
                    <span id="dashboardAlertCount" class="alert-count">--</span>
                </div>
                <div id="dashboardFocusCard" class="focus-card">
                    <span>Activo seleccionado</span>
                    <strong id="focusSymbol">--</strong>
                    <small id="focusMeta">Selecciona un activo</small>
                    <div id="focusTrend" class="focus-trend">--</div>
                </div>
                <div id="dashboardSignals" class="signal-list"></div>
            </div>
        </section>

        <section class="panel dashboard-panel full-width" id="details">
            <div class="panel-head">
                <div>
                    <span class="eyebrow">Comparativa</span>
                    <h2>Detalle del mercado</h2>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="market-table">
                    <thead>
                        <tr>
                            <th>Par</th>
                            <th>Precio</th>
                            <th>Variación</th>
                            <th>Máximo</th>
                            <th>Mínimo</th>
                            <th>Tendencia</th>
                        </tr>
                    </thead>
                    <tbody id="dashboardTableBody">
                        <tr>
                            <td colspan="6">Cargando cotizaciones...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<div id="dashboardToastRegion" class="toast-region" aria-live="polite" aria-atomic="true"></div>

<script>
    window.forexDashboardConfig = {
        apiUrl: <?= json_encode(BASE_URL . 'api/mercado', JSON_UNESCAPED_SLASHES) ?>,
        historyApiUrl: <?= json_encode(BASE_URL . 'historial-data', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= BASE_URL ?>assets/js/dashboard.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/dashboard.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
