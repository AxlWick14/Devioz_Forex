<?php
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/calculadora.css">

<main
    class="page-shell"
    id="fxCalculator"
    data-api-url="<?= BASE_URL ?>api/mercado"
    data-refresh-ms="<?= (int) ($marketRefreshMs ?? 300000) ?>"
    data-manual-refresh-cooldown-ms="<?= (int) (defined('MANUAL_REFRESH_COOLDOWN_MS') ? MANUAL_REFRESH_COOLDOWN_MS : 300000) ?>"
>
    <section class="container">
        <div class="page-heading">
            <span class="eyebrow">Herramienta</span>
            <h1>Calculadora de divisas y criptomonedas</h1>
            <p>Cotizaciones obtenidas desde Internet mediante el backend PHP de la plataforma.</p>
        </div>

        <div class="api-message" id="apiMessage" hidden></div>

        <div class="market-toolbar">
            <div>
                <strong>Cotizaciones actuales</strong>
                <small>
                    Fuente: <span id="marketSource">-</span>
                    · Actualizado: <span id="marketUpdatedAt">-</span>
                </small>
            </div>

            <div class="refresh-control">
                <button type="button" class="btn secondary" id="refreshMarket">Actualizar</button>
                <small class="refresh-countdown" id="refreshCountdown" aria-live="polite"></small>
            </div>
        </div>
 
        <section class="calculator-section">
            <div class="section-title">
                <span class="eyebrow">Forex</span>
                <h2>Divisas principales</h2>
            </div>

            <div class="quote-grid">
                <article class="quote-card">
                    <span>USD / PEN</span>
                    <strong id="quote-USD-PEN">Cargando...</strong>
                    <small>Dólar / Sol</small>
                </article>

                <article class="quote-card">
                    <span>EUR / USD</span>
                    <strong id="quote-EUR-USD">Cargando...</strong>
                    <small>Euro / Dólar</small>
                </article>

                <article class="quote-card">
                    <span>GBP / USD</span>
                    <strong id="quote-GBP-USD">Cargando...</strong>
                    <small>Libra / Dólar</small>
                </article>

                <article class="quote-card">
                    <span>USD / JPY</span>
                    <strong id="quote-USD-JPY">Cargando...</strong>
                    <small>Dólar / Yen</small>
                </article>
            </div>
        </section>

        <section class="calculator-section">
            <div class="section-title">
                <span class="eyebrow">Crypto</span>
                <h2>Criptomonedas</h2>
            </div>

            <div class="quote-grid">
                <article class="quote-card crypto">
                    <span>BTC / USD</span>
                    <strong id="quote-BTC-USD">Cargando...</strong>
                    <small>Bitcoin</small>
                </article>

                <article class="quote-card crypto">
                    <span>ETH / USD</span>
                    <strong id="quote-ETH-USD">Cargando...</strong>
                    <small>Ethereum</small>
                </article>

                <article class="quote-card crypto">
                    <span>SOL / USD</span>
                    <strong id="quote-SOL-USD">Cargando...</strong>
                    <small>Solana</small>
                </article>

                <article class="quote-card crypto">
                    <span>XRP / USD</span>
                    <strong id="quote-XRP-USD">Cargando...</strong>
                    <small>XRP</small>
                </article>
            </div>
        </section>

        <section class="calculator-section">
            <div class="section-title">
                <span class="eyebrow">Conversión</span>
                <h2>Convertir</h2>
            </div>

            <div class="calculator-panel">
                <div class="field">
                    <label for="fxAmount">Monto</label>
                    <input id="fxAmount" type="number" min="0" step="0.01" value="100">
                </div>

                <div class="currency-row">
                    <div class="field">
                        <label for="fxFrom">De</label>
                        <select id="fxFrom">
                            <option value="PEN">PEN - Sol peruano</option>
                            <option value="USD">USD - Dólar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - Libra</option>
                            <option value="JPY">JPY - Yen</option>
                            <option value="BTC">BTC - Bitcoin</option>
                            <option value="ETH">ETH - Ethereum</option>
                            <option value="SOL">SOL - Solana</option>
                            <option value="XRP">XRP</option>
                        </select>
                    </div>

                    <button class="swap-button" type="button" id="fxSwap" title="Intercambiar">⇄</button>

                    <div class="field">
                        <label for="fxTo">A</label>
                        <select id="fxTo">
                            <option value="USD">USD - Dólar</option>
                            <option value="PEN">PEN - Sol peruano</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - Libra</option>
                            <option value="JPY">JPY - Yen</option>
                            <option value="BTC">BTC - Bitcoin</option>
                            <option value="ETH">ETH - Ethereum</option>
                            <option value="SOL">SOL - Solana</option>
                            <option value="XRP">XRP</option>
                        </select>
                    </div>
                </div>

                <div class="conversion-result">
                    <span>Resultado</span>
                    <strong id="fxResult">Esperando cotizaciones...</strong>
                    <small id="fxRate"></small>
                </div>
            </div>
        </section>

        <section class="calculator-section">
            <div class="section-title">
                <span class="eyebrow">Equivalencias</span>
                <h2 id="equivalenceTitle">El monto en otros activos</h2>
            </div>

            <h3 class="subheading">Divisas</h3>
            <div class="equivalence-grid" id="fiatEquivalences"></div>

            <h3 class="subheading crypto-heading">Criptomonedas</h3>
            <div class="equivalence-grid" id="cryptoEquivalences"></div>
        </section>
    </section>
</main>

<script src="<?= BASE_URL ?>assets/js/calculadora.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
