(() => {
    const root = document.getElementById('fxCalculator');
    if (!root) return;

    const apiUrl = root.dataset.apiUrl;
    const refreshMs = Number(root.dataset.refreshMs || 300000);
    const manualRefreshCooldownMs = Number(
        root.dataset.manualRefreshCooldownMs || 300000
    );
    const refreshCooldownKey = 'forex-market-refresh-until';
    const marketUpdatedKey = 'forex-market-updated-at';
    const marketDataKey = 'forex-market-data';

    const amountInput = document.getElementById('fxAmount');
    const fromSelect = document.getElementById('fxFrom');
    const toSelect = document.getElementById('fxTo');
    const swapButton = document.getElementById('fxSwap');
    const refreshButton = document.getElementById('refreshMarket');
    const refreshCountdown = document.getElementById('refreshCountdown');
    const autoRefreshToggle = document.getElementById('calculatorAutoRefresh');
    const copyResultButton = document.getElementById('copyResult');
    const roundResultButton = document.getElementById('roundResult');
    const quoteCards = [...document.querySelectorAll('.quote-card[data-from][data-to]')];
    const quickAmountButtons = [...document.querySelectorAll('[data-amount]')];

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
        BTC: 'Bitcoin',
        ETH: 'Ethereum',
        SOL: 'Solana',
        XRP: 'XRP'
    };

    const fiatAssets = ['USD', 'PEN', 'EUR', 'GBP'];
    const cryptoAssets = ['BTC', 'ETH', 'SOL', 'XRP'];

    let market = null;
    let refreshCooldownTimer = null;
    let autoRefreshTimer = null;
    let roundToTwoDecimals = false;

    async function loadMarket(forceRefresh = false, broadcastUpdate = false) {
        setLoading(true);
        hideMessage();

        try {
            const requestUrl = forceRefresh
                ? `${apiUrl}?refresh=1&t=${Date.now()}`
                : apiUrl;
            const response = await fetch(requestUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.message || 'No se pudieron obtener las cotizaciones.');
            }

            const unavailable = Array.isArray(data.unavailable) ? data.unavailable : [];
            const availableQuotes = Object.values(data.quotes || {})
                .filter(value => value !== null && value !== undefined).length;

            if (availableQuotes === 0) {
                throw new Error(
                    'La fuente de datos no respondió con cotizaciones. Intenta nuevamente más tarde.'
                );
            }

            market = data;
            updateQuoteCards();
            updateMetadata();
            calculateAll();

            if (forceRefresh) {
                startRefreshCooldown();
                showMessage('Cotizaciones actualizadas correctamente.', 'success');
                if (broadcastUpdate) {
                    localStorage.setItem(marketDataKey, JSON.stringify(data));
                    localStorage.setItem(marketUpdatedKey, String(Date.now()));
                }
            }

            if (unavailable.length && availableQuotes > 0) {
                showMessage(`Cotizaciones no disponibles: ${unavailable.join(', ')}`, 'warning');
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

        if (refreshCooldownTimer) {
            clearInterval(refreshCooldownTimer);
        }

        updateRefreshCooldown();
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

        if (!refreshCooldownTimer) {
            refreshCooldownTimer = setInterval(updateRefreshCooldown, 1000);
        }
    }

    function updateMetadata() {
        sourceElement.textContent = market.source || '-';
        const timestamp = Number(market.fetchedAt || market.updatedAt);
        updatedAtElement.textContent = Number.isFinite(timestamp)
            ? new Date(timestamp * 1000).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })
            : '-';
    }

    function updateQuoteCards() {
        setQuote('quote-USD-PEN', market.quotes['USD/PEN'], 5);
        setQuote('quote-EUR-USD', market.quotes['EUR/USD'], 5);
        setQuote('quote-GBP-USD', market.quotes['GBP/USD'], 5);
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

    function calculateAll(animateEquivalences = false) {
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
            `${formatValue(amount, from)} ${from} = ${formatConversionValue(result, to)} ${to}`;
        animateResult();

        rateElement.textContent =
            `1 ${from} = ${formatConversionValue(oneUnit, to)} ${to}`;

        updateSelectedQuote(from, to);

        equivalenceTitle.textContent =
            `${formatValue(amount, from)} ${from} en otros activos`;

        renderEquivalences(fiatAssets, fiatContainer, amount, from, animateEquivalences);
        renderEquivalences(cryptoAssets, cryptoContainer, amount, from, animateEquivalences);
    }

    function animateResult() {
        if (
            !resultElement.animate ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        resultElement.animate(
            [
                { opacity: 0.2, transform: 'translateY(8px) scale(.97)', filter: 'blur(2px)' },
                { opacity: 1, transform: 'translateY(0) scale(1)', filter: 'blur(0)' }
            ],
            { duration: 360, easing: 'cubic-bezier(.2,.75,.25,1)' }
        );
    }

    function animateSwap() {
        if (
            !swapButton.animate ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        swapButton.animate(
            [
                { transform: 'rotate(0) scale(1)' },
                { transform: 'rotate(180deg) scale(1.2)' },
                { transform: 'rotate(360deg) scale(1)' }
            ],
            { duration: 460, easing: 'cubic-bezier(.2,.75,.25,1)' }
        );
    }

    function animateRoundButton() {
        if (
            !roundResultButton.animate ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        roundResultButton.animate(
            [
                { transform: 'scale(1)', filter: 'brightness(1)' },
                { transform: 'scale(1.12)', filter: 'brightness(1.5)' },
                { transform: 'scale(1)', filter: 'brightness(1)' }
            ],
            { duration: 360, easing: 'cubic-bezier(.2,.75,.25,1)' }
        );
    }

    function renderEquivalences(assets, container, amount, from, animate = false) {
        container.innerHTML = '';

        assets.forEach((asset, index) => {
            if (asset === from) return;

            const value = convert(amount, from, asset);
            if (value === null) return;

            const card = document.createElement('article');
            card.className = 'equivalence-card';
            if (animate) {
                card.classList.add('is-entering');
                card.style.animationDelay = `${Math.min(index * 55, 165)}ms`;
                card.addEventListener('animationend', () => card.classList.remove('is-entering'), { once: true });
            }

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
            maximumFractionDigits: 4
        });
    }

    function formatConversionValue(value, asset) {
        if (!roundToTwoDecimals) return formatValue(value, asset);

        return Number(value).toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateSelectedQuote(from, to) {
        quoteCards.forEach(card => {
            const isSelected = card.dataset.from === from && card.dataset.to === to;
            card.classList.toggle('selected', isSelected);
            card.setAttribute('aria-pressed', String(isSelected));
        });
    }

    async function copyResult() {
        const result = resultElement.textContent.trim();
        if (!result || result.includes('Esperando') || result.includes('No se')) return;

        try {
            await navigator.clipboard.writeText(`${result} | ${rateElement.textContent}`);
            copyResultButton.textContent = 'Copiado';
            window.setTimeout(() => { copyResultButton.textContent = 'Copiar'; }, 1800);
        } catch (error) {
            showMessage('No se pudo copiar el resultado.', 'warning');
        }
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

    function updateAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }

        if (autoRefreshToggle?.checked && refreshMs >= 60000) {
            autoRefreshTimer = setInterval(() => loadMarket(false), refreshMs);
        }
    }

    amountInput.addEventListener('input', calculateAll);
    fromSelect.addEventListener('change', () => calculateAll(true));
    toSelect.addEventListener('change', () => calculateAll(true));

    quickAmountButtons.forEach(button => {
        button.addEventListener('click', () => {
            amountInput.value = button.dataset.amount;
            quickAmountButtons.forEach(item => item.classList.toggle('active', item === button));
            calculateAll(true);
        });
    });

    quoteCards.forEach(card => {
        const selectQuote = () => {
            fromSelect.value = card.dataset.from;
            toSelect.value = card.dataset.to;
            calculateAll();
        };

        card.addEventListener('click', selectQuote);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectQuote();
            }
        });
    });

    copyResultButton.addEventListener('click', copyResult);

    roundResultButton.addEventListener('click', () => {
        animateRoundButton();
        roundToTwoDecimals = !roundToTwoDecimals;
        roundResultButton.setAttribute('aria-pressed', String(roundToTwoDecimals));
        roundResultButton.setAttribute(
            'aria-label',
            roundToTwoDecimals ? 'Desactivar redondeo a 2 decimales' : 'Activar redondeo a 2 decimales'
        );
        roundResultButton.title = roundToTwoDecimals
            ? 'Mostrar el resultado con precisión completa'
            : 'Mostrar el resultado con 2 decimales';
        calculateAll(true);
    });

    swapButton.addEventListener('click', () => {
        animateSwap();
        const currentFrom = fromSelect.value;
        fromSelect.value = toSelect.value;
        toSelect.value = currentFrom;
        calculateAll(true);
    });

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
                    updateQuoteCards();
                    updateMetadata();
                    calculateAll();
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
