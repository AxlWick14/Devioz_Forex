<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/calculadora.css?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/css/calculadora.css') ?>">

<main
    class="page-shell"
    id="fxCalculator"
    data-api-url="<?= BASE_URL ?>api/mercado"
    data-refresh-ms="<?= (int) ($marketRefreshMs ?? 300000) ?>"
    data-manual-refresh-cooldown-ms="<?= (int) (defined('MANUAL_REFRESH_COOLDOWN_MS') ? MANUAL_REFRESH_COOLDOWN_MS : 300000) ?>"
>
    <section class="container">
        <div class="calculator-topline">
            <div class="calculator-heading page-heading">
                <span class="eyebrow">Divisas & cripto / Calculadora</span>
                <h1>Convierte sin <span>límites.</span></h1>
                <p>Un monto. Múltiples posibilidades. Explora el valor de tus activos.</p>
            </div>
            <div class="calculator-sync-card">
                <span>Última sincronización</span>
                <strong id="marketUpdatedAt">--:--</strong>
                <small>Fuente: <span id="marketSource">Conectando...</span></small>
            </div>
        </div>

        <div class="api-message" id="apiMessage" hidden></div>

        <div class="market-toolbar">
            <span class="calculator-toolbar-label"><span aria-hidden="true">◈</span> Centro de conversión</span>
            <div class="calculator-toolbar-actions">
                <label class="refresh-toggle" for="calculatorAutoRefresh">
                    <input id="calculatorAutoRefresh" type="checkbox">
                    <span class="toggle-track" aria-hidden="true"></span>
                    <span>Autoactualizar</span>
                </label>
                <button type="button" class="btn primary" id="refreshMarket"><span aria-hidden="true">↻</span> Actualizar</button>
                <small class="refresh-countdown" id="refreshCountdown" aria-live="polite"></small>
            </div>
        </div>

        <div class="calculator-workspace">
        <section class="calculator-section conversion-primary">
            <div class="section-title">
                <span class="eyebrow">01 / Conversión</span>
                <h2>Convierte tus activos</h2>
            </div>

            <div class="calculator-panel">
                <div class="field amount-field">
                    <label for="fxAmount">Monto a convertir</label>
                    <input id="fxAmount" type="number" min="0" step="0.01" value="100">
                    <div class="quick-amounts" aria-label="Montos rápidos">
                        <button type="button" data-amount="100">100</button>
                        <button type="button" data-amount="500">500</button>
                        <button type="button" data-amount="1000">1.000</button>
                        <button type="button" data-amount="5000">5.000</button>
                    </div>
                </div>

                <div class="currency-row">
                    <div class="field">
                        <label for="fxFrom">De</label>
                        <select id="fxFrom">
                            <option value="PEN">PEN - Sol peruano</option>
                            <option value="USD">USD - Dólar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - Libra</option>
                            <option value="BTC">BTC - Bitcoin</option>
                            <option value="ETH">ETH - Ethereum</option>
                            <option value="SOL">SOL - Solana</option>
                            <option value="XRP">XRP</option>
                        </select>
                    </div>

                    <button class="swap-button" type="button" id="fxSwap" title="Intercambiar monedas" aria-label="Intercambiar monedas">⇄</button>

                    <div class="field">
                        <label for="fxTo">A</label>
                        <select id="fxTo">
                            <option value="USD">USD - Dólar</option>
                            <option value="PEN">PEN - Sol peruano</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - Libra</option>
                            <option value="BTC">BTC - Bitcoin</option>
                            <option value="ETH">ETH - Ethereum</option>
                            <option value="SOL">SOL - Solana</option>
                            <option value="XRP">XRP</option>
                        </select>
                    </div>
                </div>

                <div class="conversion-result">
                    <div class="result-heading">
                        <span>Recibes el equivalente a</span>
                        <div class="result-actions">
                            <button type="button" id="roundResult" class="copy-result round-result" aria-label="Activar redondeo a 2 decimales" aria-pressed="false" title="Mostrar el resultado con 2 decimales">2 decimales</button>
                            <button type="button" id="copyResult" class="copy-result" aria-label="Copiar resultado" title="Copiar resultado">Copiar</button>
                        </div>
                    </div>
                    <strong id="fxResult" aria-live="polite">Esperando cotizaciones...</strong>
                    <small id="fxRate"></small>
                </div>
            </div>
        </section>

        <section class="calculator-section equivalences-panel">
            <div class="section-title">
                <span class="eyebrow">02 / Equivalencias</span>
                <h2 id="equivalenceTitle">El monto en otros activos</h2>
            </div>

            <h3 class="subheading">Divisas</h3>
            <div class="equivalence-grid" id="fiatEquivalences"></div>

            <h3 class="subheading crypto-heading">Criptomonedas</h3>
            <div class="equivalence-grid" id="cryptoEquivalences"></div>
        </section>
        </div>
    </section>
</main>

<script src="<?= BASE_URL ?>assets/js/calculadora.js?v=<?= (int) filemtime(__DIR__ . '/../../../public/assets/js/calculadora.js') ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
