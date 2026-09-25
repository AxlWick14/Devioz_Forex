(() => {
    const config = window.forexMarketConfig;
    if (!config) return;

    const workspace = document.getElementById('marketWorkspace');
    const sourceElement = document.getElementById('traderSource');
    const updatedAtElement = document.getElementById('traderUpdatedAt');
    const refreshButton = document.getElementById('traderRefresh');
    const refreshCountdown = document.getElementById('traderRefreshCountdown');
    const autoRefreshToggle = document.getElementById('marketAutoRefresh');
    const messageElement = document.getElementById('traderMessage');
    const forexCards = document.getElementById('forexCards');
    const cryptoCards = document.getElementById('cryptoCards');
    const tableBody = document.getElementById('marketTableBody');
    const refreshCooldownKey = 'forex-market-refresh-until';
    const marketUpdatedKey = 'forex-market-updated-at';
    const marketDataKey = 'forex-market-data';
    const manualRefreshCooldownMs = 300000;
    const refreshMs = Number(workspace?.dataset.refreshMs || 300000);
    let refreshCooldownTimer = null;
    let autoRefreshTimer = null;

    const forexSymbols = ['USD/PEN', 'EUR/USD', 'GBP/USD', 'EUR/GBP'];
    const cryptoSymbols = ['BTC/USD', 'ETH/USD', 'SOL/USD', 'XRP/USD'];
    const marketNames = {
        'USD/PEN': 'Dólar / Sol peruano',
        'EUR/USD': 'Euro / Dólar',
        'GBP/USD': 'Libra / Dólar',
        'EUR/GBP': 'Euro / Libra',
        'BTC/USD': 'Bitcoin / Dólar',
        'ETH/USD': 'Ethereum / Dólar',
        'SOL/USD': 'Solana / Dólar',
        'XRP/USD': 'XRP / Dólar'
    };

    let market = null;

    async function loadMarket(forceRefresh = false, broadcastUpdate = false) {
        setLoading(true);
        hideMessage();

        try {
            const requestUrl = forceRefresh
                ? `${config.apiUrl}?refresh=1&t=${Date.now()}`
                : config.apiUrl;
            const response = await fetch(requestUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.message || 'No se pudieron obtener los datos del mercado.');
            }

            const availableQuotes = Object.values(data.quotes || {})
                .filter(value => value !== null && value !== undefined).length;

            if (availableQuotes === 0) {
                throw new Error(
                    'La fuente no devolvió cotizaciones. Intenta nuevamente más tarde.'
                );
            }

            market = data;
            renderMarket();

            if (forceRefresh && broadcastUpdate) {
                startRefreshCooldown();
                showMessage(
                    data.stale
                        ? data.message
                        : 'Cotizaciones actualizadas correctamente.',
                    data.stale ? 'warning' : 'success'
                );
                localStorage.setItem(marketDataKey, JSON.stringify(data));
                localStorage.setItem(marketUpdatedKey, String(Date.now()));
            }
        } catch (error) {
            showMessage(error.message || 'Error conectando con la API.', 'error');
        } finally {
            setLoading(false);
        }
    }

    function renderMarket() {
        sourceElement.textContent = market.source || '-';
        const timestamp = Number(market.fetchedAt || market.updatedAt);
        updatedAtElement.textContent = Number.isFinite(timestamp)
            ? new Date(timestamp * 1000).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })
            : '-';

        renderCards(forexSymbols, forexCards);
        renderCards(cryptoSymbols, cryptoCards);
        renderTable();
    }

    function renderCards(symbols, container) {
        container.innerHTML = '';

        symbols.forEach(symbol => {
            const detail = market.marketDetails?.[symbol];
            const card = document.createElement('article');
            card.className = 'trader-card';

            if (!detail) {
                card.innerHTML = `<strong>${symbol}</strong><span class="unavailable">No disponible</span>`;
                container.appendChild(card);
                return;
            }

            const trend = trendInfo(detail);
            card.innerHTML = `
                <div class="trader-card-heading">
                    <div>
                        <strong>${symbol}</strong>
                        <small>${marketNames[symbol]}</small>
                    </div>
                    <span class="trend-badge ${trend.className}">${trend.label}</span>
                </div>
                <div class="trader-price">${formatPrice(detail.close ?? market.quotes[symbol], symbol)}</div>
                <div class="trader-change ${trend.className}">
                    ${formatSigned(detail.percentChange, 2)}% · ${formatSigned(detail.change, priceDecimals(symbol))}
                </div>
                <div class="range-track" aria-label="Rango entre mínimo y máximo">
                    <span style="width:${rangePosition(detail)}%"></span>
                </div>
                <div class="trader-range">
                    <span>Mín ${formatPrice(detail.low, symbol)}</span>
                    <span>Máx ${formatPrice(detail.high, symbol)}</span>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function renderTable() {
        tableBody.innerHTML = '';

        [...forexSymbols, ...cryptoSymbols].forEach(symbol => {
            const detail = market.marketDetails?.[symbol];
            if (!detail) return;

            const trend = trendInfo(detail);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${symbol}</strong><small>${marketNames[symbol]}</small></td>
                <td>${formatPrice(detail.close ?? market.quotes[symbol], symbol)}</td>
                <td class="${trend.className}">${formatSigned(detail.percentChange, 2)}%</td>
                <td>${formatPrice(detail.open, symbol)}</td>
                <td>${formatPrice(detail.high, symbol)}</td>
                <td>${formatPrice(detail.low, symbol)}</td>
                <td><span class="trend-label ${trend.className}">${trend.label}</span></td>
            `;
            tableBody.appendChild(row);
        });
    }

    function trendInfo(detail) {
        const percentChange = Number(detail.percentChange);
        if (!Number.isFinite(percentChange) || percentChange === 0) {
            return { label: 'Estable', className: 'stable' };
        }
        return percentChange > 0
            ? { label: 'Alcista ↑', className: 'up' }
            : { label: 'Bajista ↓', className: 'down' };
    }

    function rangePosition(detail) {
        const low = Number(detail.low);
        const high = Number(detail.high);
        const close = Number(detail.close);
        if (![low, high, close].every(Number.isFinite) || high <= low) return 50;
        return Math.max(0, Math.min(100, ((close - low) / (high - low)) * 100));
    }

    function formatPrice(value, symbol) {
        if (value === null || value === undefined || !Number.isFinite(Number(value))) return '-';
        return Number(value).toLocaleString('en-US', {
            minimumFractionDigits: priceDecimals(symbol),
            maximumFractionDigits: priceDecimals(symbol)
        });
    }

    function priceDecimals(symbol) {
        if (symbol.startsWith('BTC/') || symbol.startsWith('ETH/')) return 2;
        if (symbol.startsWith('SOL/') || symbol.startsWith('XRP/')) return 4;
        return 5;
    }

    function formatSigned(value, decimals) {
        if (value === null || value === undefined || !Number.isFinite(Number(value))) return '-';
        const number = Number(value);
        return `${number > 0 ? '+' : ''}${number.toFixed(decimals)}`;
    }

    function setLoading(loading) {
        refreshButton.disabled = loading || isRefreshOnCooldown();
        refreshButton.textContent = loading ? 'Actualizando...' : 'Actualizar';
    }

    function isRefreshOnCooldown() {
        return getRefreshCooldownUntil() > Date.now();
    }

    function getRefreshCooldownUntil() {
        const cooldownUntil = Number(localStorage.getItem(refreshCooldownKey));
        return Number.isFinite(cooldownUntil) ? cooldownUntil : 0;
    }

    function startRefreshCooldown() {
        localStorage.setItem(
            refreshCooldownKey,
            String(Date.now() + manualRefreshCooldownMs)
        );

        if (refreshCooldownTimer) clearInterval(refreshCooldownTimer);
        updateRefreshCooldown();
        refreshCooldownTimer = setInterval(updateRefreshCooldown, 1000);
    }

    function updateRefreshCooldown() {
        const remainingMs = getRefreshCooldownUntil() - Date.now();

        if (remainingMs <= 0) {
            localStorage.removeItem(refreshCooldownKey);
            if (refreshCooldownTimer) clearInterval(refreshCooldownTimer);
            refreshCooldownTimer = null;
            refreshButton.disabled = false;
            refreshButton.title = 'Actualizar cotizaciones';
            refreshCountdown.textContent = '';
            return;
        }

        const remainingSeconds = Math.ceil(remainingMs / 1000);
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = String(remainingSeconds % 60).padStart(2, '0');
        refreshButton.disabled = true;
        refreshButton.title = 'Debes esperar antes de actualizar nuevamente';
        refreshCountdown.textContent = `Podrás actualizar nuevamente en ${minutes}:${seconds}`;

        if (!refreshCooldownTimer) {
            refreshCooldownTimer = setInterval(updateRefreshCooldown, 1000);
        }
    }

    function showMessage(text, type) {
        messageElement.hidden = false;
        messageElement.className = `api-message ${type}`;
        messageElement.textContent = text;
    }

    function hideMessage() {
        messageElement.hidden = true;
        messageElement.className = 'api-message';
        messageElement.textContent = '';
    }

    function updateAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }

        if (autoRefreshToggle?.checked && refreshMs >= 60000) {
            autoRefreshTimer = setInterval(() => loadMarket(false), refreshMs);
        }
    }

    refreshButton.addEventListener('click', () => {
        if (isRefreshOnCooldown()) {
            updateRefreshCooldown();
            return;
        }
        loadMarket(true, true);
    });

    autoRefreshToggle?.addEventListener('change', updateAutoRefresh);

    window.addEventListener('storage', event => {
        if (event.key === refreshCooldownKey || event.key === marketUpdatedKey) {
            updateRefreshCooldown();
        }

        if (event.key === marketDataKey && event.newValue) {
            try {
                const sharedMarket = JSON.parse(event.newValue);
                if (Object.values(sharedMarket.quotes || {}).some(value => value !== null)) {
                    market = sharedMarket;
                    renderMarket();
                }
            } catch (error) {
                showMessage('No se pudo sincronizar la cotización compartida.', 'warning');
            }
        }
    });

    document.addEventListener('visibilitychange', updateRefreshCooldown);
    window.addEventListener('focus', updateRefreshCooldown);
    window.addEventListener('pageshow', updateRefreshCooldown);
    updateRefreshCooldown();
    updateAutoRefresh();
    loadMarket();
})();
