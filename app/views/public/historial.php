<?php
$_SESSION['history_csrf'] ??= bin2hex(random_bytes(32));
$historyToday = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/mercado.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<style>
    #historialSyncStatus {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 18px;
    }

    .history-sync-highlight {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 12px 16px;
        border: 1px solid rgba(116, 167, 255, .35);
        border-left: 3px solid var(--accent2);
        border-radius: 10px;
        background: rgba(75, 140, 255, .08);
    }

    .history-sync-highlight > span {
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .04em;
    }

    .history-sync-highlight strong {
        color: var(--accent2);
        font-size: clamp(.95rem, 3vw, 1.15rem);
        font-variant-numeric: tabular-nums;
        overflow-wrap: anywhere;
    }

    .history-sync-latest {
        border-color: rgba(111, 209, 192, .4);
        border-left-color: #6fd1c0;
        background: rgba(111, 209, 192, .08);
    }

    .history-sync-latest strong { color: #8cf0df; }
    html[data-theme="light"] .history-sync-latest strong { color: #087363; }
    html[data-theme="sunset"] .history-sync-latest { border-color: rgba(255, 140, 112, .4); border-left-color: #ff8c70; background: rgba(255, 140, 112, .08); }
    html[data-theme="sunset"] .history-sync-latest strong { color: #ffb29c; }
    .history-sync-details { flex-basis: 100%; font-size: .85rem; }

    @media (max-width: 600px) {
        .history-sync-highlight { width: 100%; }
    }

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

    .flatpickr-day.date-no-data:focus {
        background: rgba(255, 82, 82, 0.30) !important;
    }

    .flatpickr-day.date-has-data:hover,
    .flatpickr-day.date-has-data:focus {
        background: rgba(63, 138, 255, 0.35) !important;
    }

    .history-gap-note {
        margin: 16px 0 0;
        color: #e9bf77;
        font-size: .8rem;
        line-height: 1.6;
    }

    .history-gap-note::before {
        content: '';
        display: inline-block;
        width: 14px;
        height: 14px;
        margin-right: 8px;
        border: 1px solid rgba(233, 191, 119, .4);
        border-radius: 3px;
        background: repeating-linear-gradient(45deg, transparent 0 4px, rgba(233, 191, 119, .15) 4px 5px), rgba(233, 191, 119, .07);
        vertical-align: -2px;
    }

    .history-gap-note[hidden] {
        display: none;
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
                    <small id="historialMeta">Últimos 7 días hasta hoy</small>
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
                        <input type="date" id="historialDesde" value="<?= $historyToday->modify('-7 days')->format('Y-m-d') ?>">
                    </div>
                    <div class="field compact">
                        <label for="historialHasta">Hasta</label>
                        <input type="date" id="historialHasta" value="<?= $historyToday->format('Y-m-d') ?>">
                    </div>
                    <button id="historialBuscar" class="btn primary" type="button">Buscar</button>
                </div>
            </div>
            <p id="historialSyncStatus" role="status" aria-live="polite"></p>
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
                <canvas id="historialChart" height="120" aria-describedby="historialGapNote"></canvas>
            </div>
            <p id="historialGapNote" class="history-gap-note" role="status" hidden></p>
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
<script src="<?= BASE_URL ?>assets/js/historial.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/historial.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
