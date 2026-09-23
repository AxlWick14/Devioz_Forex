<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">

<main class="page-shell dashboard-page">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Panel principal</span>
            <h1>Dashboard Forex</h1>
            <p>Resumen ejecutivo del mercado en tiempo real, con tendencia, variación, historial reciente y alertas operativas.</p>
        </div>

        <div class="dashboard-toolbar">
            <div class="dashboard-filter-group" aria-label="Filtros del dashboard">
                <button class="filter-btn active" type="button" data-filter="all">Todo</button>
                <button class="filter-btn" type="button" data-filter="forex">Forex</button>
                <button class="filter-btn" type="button" data-filter="crypto">Crypto</button>
            </div>
            <button id="dashboardRefreshBtn" class="btn secondary" type="button">Actualizar</button>
        </div>

        <div class="dashboard-summary" id="dashboardSummary">
            <article class="summary-card dashboard-card">
                <span>Mercado</span>
                <strong id="summaryMarketState">--</strong>
                <small id="summaryMarketLabel">Sin datos</small>
            </article>
            <article class="summary-card dashboard-card">
                <span>Última actualización</span>
                <strong id="summaryUpdatedAt">--</strong>
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

        <section class="dashboard-grid">
            <div class="panel dashboard-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Tendencia</span>
                        <h2>Principales cotizaciones</h2>
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

        <section class="panel dashboard-panel full-width">
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
    </section>
</main>

<script>
    window.forexDashboardConfig = {
        apiUrl: <?= json_encode(BASE_URL . 'api/mercado', JSON_UNESCAPED_SLASHES) ?>,
        historyApiUrl: <?= json_encode(BASE_URL . 'historial-data', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= BASE_URL ?>assets/js/dashboard.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/dashboard.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
