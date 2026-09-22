<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/mercado.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<style>
    .flatpickr-day.date-has-data {
        background: rgba(63, 138, 255, 0.22) !important;
        border-color: rgba(63, 138, 255, 0.75) !important;
        color: #7db8ff !important;
    }

    .flatpickr-day.date-no-data {
        background: rgba(255, 82, 82, 0.18) !important;
        border-color: rgba(255, 82, 82, 0.7) !important;
        color: #ff8d8d !important;
    }

    .flatpickr-day.date-no-data:hover,
    .flatpickr-day.date-no-data:focus {
        background: rgba(255, 82, 82, 0.30) !important;
    }

    .flatpickr-day.date-has-data:hover,
    .flatpickr-day.date-has-data:focus {
        background: rgba(63, 138, 255, 0.35) !important;
    }
</style>

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
                    <small id="historialMeta">Últimos 30 días</small>
                </div>

                <div class="toolbar-controls">
                    <div class="field compact">
                        <label for="historialPar">Par</label>
                        <select id="historialPar">
                            <option value="USD/PEN" selected>USD/PEN</option>
                            <option value="EUR/USD">EUR/USD</option>
                            <option value="BTC/USD">BTC/USD</option>
                            <option value="ETH/USD">ETH/USD</option>
                        </select>
                    </div>
                    <div class="field compact">
                        <label for="historialDesde">Desde</label>
                        <input type="date" id="historialDesde" value="<?= date('Y-m-d', strtotime('-1 month')) ?>">
                    </div>
                    <div class="field compact">
                        <label for="historialHasta">Hasta</label>
                        <input type="date" id="historialHasta" value="<?= date('Y-m-d') ?>">
                    </div>
                    <button id="historialBuscar" class="btn primary" type="button">Buscar</button>
                    <a class="btn secondary" href="<?= BASE_URL ?>historial-exportar?par=USD%2FPEN&hasta=<?= date('Y-m-d') ?>">CSV Dólar</a>
                    <a class="btn secondary" href="<?= BASE_URL ?>historial-exportar?par=EUR%2FUSD&hasta=<?= date('Y-m-d') ?>">CSV Euro</a>
                    <a class="btn secondary" href="<?= BASE_URL ?>historial-exportar?par=BTC%2FUSD&hasta=<?= date('Y-m-d') ?>">CSV Bitcoin</a>
                    <a class="btn secondary" href="<?= BASE_URL ?>historial-exportar?par=ETH%2FUSD&hasta=<?= date('Y-m-d') ?>">CSV Ethereum</a>
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
                <div class="mt-3 text-center">
                    <button id="historialCargarMas" type="button" class="btn secondary" style="display:none;">Cargar más</button>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
    const historialConfig = {
        apiUrl: '<?= BASE_URL ?>historial-data'
    };
</script>
<script src="<?= BASE_URL ?>assets/js/historial.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
de 