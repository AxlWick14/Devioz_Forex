(() => {
    const root = document.getElementById('fxCalculator');
    if (!root) return;

    const apiUrl = root.dataset.apiUrl;
    const refreshMs = Number(root.dataset.refreshMs || 300000);
    const manualRefreshCooldownMs = Number(
        root.dataset.manualRefreshCooldownMs || 300000
    );
    const refreshCooldownKey = 'forex-market-refresh-until';

    const amountInput = document.getElementById('fxAmount');
    const fromSelect = document.getElementById('fxFrom');
    const toSelect = document.getElementById('fxTo');
    const swapButton = document.getElementById('fxSwap');
    const refreshButton = document.getElementById('refreshMarket');
    const refreshCountdown = document.getElementById('refreshCountdown');

    const resultElement = document.getElementById('fxResult');
    const rateElement = document.getElementById('fxRate');
    const sourceElement = document.getElementById('marketSource');
    const updatedAtElement = document.getElementById('marketUpdatedAt');
    const messageElement = document.getElementById('apiMessage');

    const fiatContainer = document.getElementById('fiatEquivalences');
    const cryptoContainer = document.getElementById('cryptoEquivalences');
    const equivalenceTitle = document.getElementById('equivalenceTitle');

    const assetNames = {
        USD: 'Dólar estadounidense',
        PEN: 'Sol peruano',
        EUR: 'Euro',
        GBP: 'Libra esterlina',
        JPY: 'Yen japonés',
        BTC: 'Bitcoin',
        ETH: 'Ethereum',
        SOL: 'Solana',
        XRP: 'XRP'
    };

    const fiatAssets = ['USD', 'PEN', 'EUR', 'GBP', 'JPY'];
    const cryptoAssets = ['BTC', 'ETH', 'SOL', 'XRP'];

    let market = null;
    let refreshCooldownTimer = null;

    async function loadMarket(forceRefresh = false) {
        setLoading(true);
        hideMessage();

        try {
            const requestUrl = forceRefresh ? `${apiUrl}?refresh=1` : apiUrl;
            const response = await fetch(requestUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.message || 'No se pudieron obtener las cotizaciones.');
            }

            market = data;
            updateQuoteCards();
            updateMetadata();
            calculateAll();

            if (forceRefresh) {
                startRefreshCooldown();
            }

            if (Array.isArray(data.unavailable) && data.unavailable.length) {
                showMessage(`No disponibles: ${data.unavailable.join(', ')}`, 'warning');
            }
        } catch (error) {
            showMessage(error.message || 'Error conectando con la API.', 'error');
            resultElement.textContent = 'No se pudieron obtener las cotizaciones.';
        } finally {
            setLoading(false);
        }
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
        const cooldownUntil = Date.now() + manualRefreshCooldownMs;
        localStorage.setItem(refreshCooldownKey, String(cooldownUntil));
        updateRefreshCooldown();

        if (refreshCooldownTimer) {
            clearInterval(refreshCooldownTimer);
        }

        refreshCooldownTimer = setInterval(updateRefreshCooldown, 1000);
    }

    function updateRefreshCooldown() {
        const remainingMs = getRefreshCooldownUntil() - Date.now();

        if (remainingMs <= 0) {
            localStorage.removeItem(refreshCooldownKey);
            clearInterval(refreshCooldownTimer);
            refreshCooldownTimer = null;
            refreshButton.disabled = false;
            refreshButton.textContent = 'Actualizar';
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
    }

    function updateMetadata() {
        sourceElement.textContent = market.source || '-';
        const timestamp = Number(market.updatedAt);
        updatedAtElement.textContent = Number.isFinite(timestamp)
            ? new Date(timestamp * 1000).toLocaleString('es-PE')
            : '-';
    }

    function updateQuoteCards() {
        setQuote('quote-USD-PEN', market.quotes['USD/PEN'], 5);
        setQuote('quote-EUR-USD', market.quotes['EUR/USD'], 5);
        setQuote('quote-GBP-USD', market.quotes['GBP/USD'], 5);
        setQuote('quote-USD-JPY', market.quotes['USD/JPY'], 3);

        setCryptoQuote('quote-BTC-USD', market.quotes['BTC/USD']);
        setCryptoQuote('quote-ETH-USD', market.quotes['ETH/USD']);
        setCryptoQuote('quote-SOL-USD', market.quotes['SOL/USD']);
        setCryptoQuote('quote-XRP-USD', market.quotes['XRP/USD']);
    }

    function setQuote(id, value, decimals) {
        const element = document.getElementById(id);
        if (!element) return;

        if (value === null || value === undefined) {
            element.textContent = 'No disponible';
            return;
        }

        element.textContent = Number(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function setCryptoQuote(id, value) {
        const element = document.getElementById(id);
        if (!element) return;

        if (value === null || value === undefined) {
            element.textContent = 'No disponible';
            return;
        }

        element.textContent = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: value < 1 ? 6 : 2
        }).format(value);
    }

    function convert(amount, from, to) {
        if (!market || !market.usdValue) return null;

        const sourceUsdValue = Number(market.usdValue[from]);
        const targetUsdValue = Number(market.usdValue[to]);

        if (
            !Number.isFinite(sourceUsdValue) ||
            !Number.isFinite(targetUsdValue) ||
            sourceUsdValue <= 0 ||
            targetUsdValue <= 0
        ) {
            return null;
        }

        return (amount * sourceUsdValue) / targetUsdValue;
    }

    function calculateAll() {
        if (!market) return;

        const amount = Number(amountInput.value);
        const from = fromSelect.value;
        const to = toSelect.value;

        if (!Number.isFinite(amount) || amount < 0) {
            resultElement.textContent = 'Ingresa un monto válido.';
            rateElement.textContent = '';
            return;
        }

        const result = convert(amount, from, to);
        const oneUnit = convert(1, from, to);

        if (result === null || oneUnit === null) {
            resultElement.textContent = 'No hay cotización disponible para esa conversión.';
            rateElement.textContent = '';
            return;
        }

        resultElement.textContent =
            `${formatValue(amount, from)} ${from} = ${formatValue(result, to)} ${to}`;

        rateElement.textContent =
            `1 ${from} = ${formatValue(oneUnit, to)} ${to}`;

        equivalenceTitle.textContent =
            `${formatValue(amount, from)} ${from} en otros activos`;

        renderEquivalences(fiatAssets, fiatContainer, amount, from);
        renderEquivalences(cryptoAssets, cryptoContainer, amount, from);
    }

    function renderEquivalences(assets, container, amount, from) {
        container.innerHTML = '';

        assets.forEach(asset => {
            if (asset === from) return;

            const value = convert(amount, from, asset);
            if (value === null) return;

            const card = document.createElement('article');
            card.className = 'equivalence-card';

            const info = document.createElement('div');
            const code = document.createElement('strong');
            const name = document.createElement('small');
            const number = document.createElement('div');

            code.textContent = asset;
            name.textContent = assetNames[asset];
            number.className = 'equivalence-value';
            number.textContent = formatValue(value, asset);

            info.appendChild(code);
            info.appendChild(name);
            card.appendChild(info);
            card.appendChild(number);
            container.appendChild(card);
        });
    }

    function formatValue(value, asset) {
        if (cryptoAssets.includes(asset)) {
            return Math.abs(value) < 1
                ? Number(value).toFixed(8)
                : Number(value).toLocaleString('es-PE', { maximumFractionDigits: 6 });
        }

        return Number(value).toLocaleString('es-PE', {
            maximumFractionDigits: asset === 'JPY' ? 2 : 4
        });
    }

    function showMessage(text, type) {
        messageElement.hidden = false;
        messageElement.className = `api-message ${type}`;
        messageElement.textContent = text;
    }

    function hideMessage() {
        messageElement.hidden = true;
        messageElement.textContent = '';
        messageElement.className = 'api-message';
    }

    amountInput.addEventListener('input', calculateAll);
    fromSelect.addEventListener('change', calculateAll);
    toSelect.addEventListener('change', calculateAll);

    swapButton.addEventListener('click', () => {
        const currentFrom = fromSelect.value;
        fromSelect.value = toSelect.value;
        toSelect.value = currentFrom;
        calculateAll();
    });

    refreshButton.addEventListener('click', () => {
        if (isRefreshOnCooldown()) return;

        loadMarket(true);
    });

    updateRefreshCooldown();
    loadMarket();

    if (Number.isFinite(refreshMs) && refreshMs >= 60000) {
        setInterval(() => loadMarket(false), refreshMs);
    }
})();
