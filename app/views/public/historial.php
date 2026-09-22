<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/mercado.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

<main class="page-shell">
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Historial</span>
            <h1>Histórico de cotizaciones</h1>
            <p>Consulta el comportamiento de un par a lo largo del tiempo y revisa su evolución por fechas.</p>
        </div>

        <div class="panel">
            <div class="trader-toolbar history-toolbar">
                <div class="toolbar-meta">
                    <strong id="historialParTitle">EUR/USD</strong>
   p|                 <small id="historialMeta">Últimos 30 días</small>
                </div>

                <div class="toolbar-controls">
                    <div class="field compact">
                        <label for="historialPar">Par</label>
                        <select id="historialPar">
                            <option value="EUR/USD">EUR/USD</option>
                            <option value="GBP/USD">GBP/USD</option>
                            <option value="USD/PEN">USD/PEN</option>
                            <option value="BTC/USD">BTC/USD</option>
                            <option value="ETH/USD">ETH/USD</option>
                            <option value="SOL/USD">SOL/USD</option>
                            <option value="XRP/USD">XRP/USD</option>
                        </select>
                    </div>
                    <div class="field compact">
                        <label for="historialDesde">Desde</label>
                        <input type="date" id="historialDesde" value="2000-01-01">
                    </div>
                    <div class="field compact">
                        <label for="historialHasta">Hasta</label>
                        <input type="date" id="historialHasta" value="<?= date('Y-m-d') ?>">
                    </div>
                    <button id="historialBuscar" class="btn primary" type="button">Buscar</button>
                </div>
            </div>
        </div>

        <div class="history-summary">
            <div class="summary-card">
                <span>Precio inicial</span>
                <strong id="historialPrecioInicial">--</strong>
            </div>
            <div class="summary-card">
                <span>Precio final</span>
                <strong id="historialPrecioFinal">--</strong>
            </div>
            <div class="summary-card">
                <span>Variación</span>
                <strong id="historialCambioResumen">--</strong>
            </div>
        </div>

        <div class="panel chart-panel">
            <div class="chart-header">
                <div>
                    <span class="eyebrow">Series</span>
                    <h2 id="historialChartTitle">EUR/USD</h2>
                </div>
                <span id="historialTrendBadge" class="trend-badge stable">Sin datos</span>
            </div>
            <div class="chart-wrap">
                <canvas id="historialChart" height="120"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="table-heading">
                <h3>Detalle del período</h3>
                <span id="historialRowsLabel">0 registros</span>
            </div>
            <div class="table-wrapper">
                <table class="market-table historial-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Apertura</th>
                            <th>Máximo</th>
                            <th>Mínimo</th>
                            <th>Cierre</th>
                            <th>Cambio %</th>
                        </tr>
                    </thead>
                    <tbody id="historialTableBody">
                        <tr>
                            <td colspan="6">Cargando historial...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js?v=4.4.3" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr?v=4.1.4" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js?v=4.1.4" defer></script>
<script>
    const historialConfig = {
        apiUrl: '<?= BASE_URL ?>historial-data'
    };
</script>
<script src="<?= BASE_URL ?>assets/js/historial.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/historial.js') ?>" defer></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
de 