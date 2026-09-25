<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/mercado.css?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/css/mercado.css') ?>">

<main class="page-shell" id="marketWorkspace" data-refresh-ms="<?= (int) (defined('MARKET_REFRESH_MS') ? MARKET_REFRESH_MS : 300000) ?>">
    <section class="container">
        <div class="market-page-topline">
            <div class="page-heading market-page-heading">
                <span class="eyebrow">Módulo Forex / Trader</span>
                <h1>MERCADO</h1>
                <p>Consulta el precio actual, rango de la sesión, variación y tendencia de los principales pares.</p>
            </div>
            <div class="market-sync-card">
                <span>Última sincronización</span>
                <strong id="traderUpdatedAt">--:--</strong>
                <small><span id="traderSource">Conectando...</span></small>
            </div>
        </div>

        <div class="market-toolbar" id="marketToolbar">
            <div class="market-toolbar-actions">
                <label class="refresh-toggle" for="marketAutoRefresh">
                    <input id="marketAutoRefresh" type="checkbox">
                    <span class="toggle-track" aria-hidden="true"></span>
                    <span>Auto</span>
                </label>
                <button class="btn primary" id="traderRefresh" type="button"><span aria-hidden="true">↻</span> Actualizar</button>
                <small class="market-connected"><span aria-hidden="true"></span> Mercado conectado</small>
                <small class="refresh-countdown" id="traderRefreshCountdown" aria-live="polite"></small>
            </div>
        </div>

        <div class="api-message" id="traderMessage" hidden></div>

        <section class="trader-section">
            <div class="section-title">
                <span class="eyebrow">Principales pares</span>
                <h2>Forex</h2>
            </div>
            <div class="trader-grid" id="forexCards"></div>
        </section>

        <section class="trader-section">
            <div class="section-title">
                <span class="eyebrow">Activos digitales</span>
                <h2>Crypto</h2>
            </div>
            <div class="trader-grid" id="cryptoCards"></div>
        </section>

        <section class="trader-section">
            <div class="section-title">
                <span class="eyebrow">Comparativa</span>
                <h2>Detalle de mercado</h2>
            </div>
            <div class="table-wrapper">
                <table class="market-table">
                    <thead>
                        <tr>
                            <th>Par</th>
                            <th>Actual</th>
                            <th>Variación</th>
                            <th>Apertura</th>
                            <th>Máximo</th>
                            <th>Mínimo</th>
                            <th>Tendencia</th>
                        </tr>
                    </thead>
                    <tbody id="marketTableBody"></tbody>
                </table>
            </div>
        </section>
    </section>
</main>

<script>
    window.forexMarketConfig = {
        apiUrl: <?= json_encode(BASE_URL . 'api/mercado', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= BASE_URL ?>assets/js/mercado.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/mercado.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
