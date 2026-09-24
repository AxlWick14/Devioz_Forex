document.addEventListener('DOMContentLoaded', () => {
    const marketCards = document.getElementById('dashboardMarketCards');
    const signals = document.getElementById('dashboardSignals');
    const tableBody = document.getElementById('dashboardTableBody');
    const refreshButton = document.getElementById('dashboardRefreshBtn');
    const filterButtons = Array.from(document.querySelectorAll('.filter-btn'));
    const focusSymbol = document.getElementById('focusSymbol');
    const focusMeta = document.getElementById('focusMeta');
    const focusTrend = document.getElementById('focusTrend');
    const alertCount = document.getElementById('dashboardAlertCount');

    const summaryMarketState = document.getElementById('summaryMarketState');
    const summaryMarketLabel = document.getElementById('summaryMarketLabel');
    const summaryUpdatedAt = document.getElementById('summaryUpdatedAt');
    const summarySource = document.getElementById('summarySource');
    const summaryTopMover = document.getElementById('summaryTopMover');
    const summaryTopMoverMeta = document.getElementById('summaryTopMoverMeta');
    const summaryAverageChange = document.getElementById('summaryAverageChange');
    const dashboardSyncState = document.getElementById('dashboardSyncState');
    const dashboardLiveClock = document.getElementById('dashboardLiveClock');
    const dashboardSoundBtn = document.getElementById('dashboardSoundBtn');
    const dashboardToastRegion = document.getElementById('dashboardToastRegion');
    const dashboardAutoRefresh = document.getElementById('dashboardAutoRefresh');

    const symbols = ['USD/PEN', 'EUR/USD', 'GBP/USD', 'EUR/GBP', 'BTC/USD', 'ETH/USD', 'SOL/USD', 'XRP/USD'];
    const marketNames = {
        'USD/PEN': 'Dólar / Sol',
        'EUR/USD': 'Euro / Dólar',
        'GBP/USD': 'Libra / Dólar',
        'EUR/GBP': 'Euro / Libra',
        'BTC/USD': 'Bitcoin / Dólar',
        'ETH/USD': 'Ethereum / Dólar',
        'SOL/USD': 'Solana / Dólar',
        'XRP/USD': 'XRP / Dólar'
    };

    const state = {
        filter: 'forex',
        selectedSymbol: null,
        currentData: null,
        soundEnabled: false,
        audioContext: null,
        lastAlertSignature: null,
        autoRefreshTimer: null,
    };

    function showToast(type, title, message) {
        if (!dashboardToastRegion) return;
        const icons = { success: '✓', error: '!', warning: '!', info: 'i' };
        const toast = document.createElement('article');
        toast.className = `dashboard-toast ${type}`;
        toast.innerHTML = `
            <span class="toast-icon" aria-hidden="true">${icons[type] || icons.info}</span>
            <div><strong>${title}</strong><p>${message}</p></div>
            <button class="toast-close" type="button" aria-label="Cerrar notificación">×</button>
        `;
        toast.querySelector('.toast-close').addEventListener('click', () => dismissToast(toast));
        dashboardToastRegion.appendChild(toast);
        playAlertSound(type);
        window.setTimeout(() => dismissToast(toast), 4800);
    }

    function dismissToast(toast) {
        if (!toast || toast.classList.contains('leaving')) return;
        toast.classList.add('leaving');
        window.setTimeout(() => toast.remove(), 260);
    }

    function playAlertSound(type) {
        if (!state.soundEnabled || !window.AudioContext) return;
        try {
            state.audioContext ||= new AudioContext();
            if (state.audioContext.state === 'suspended') return;
            const oscillator = state.audioContext.createOscillator();
            const gain = state.audioContext.createGain();
            const frequencies = { success: 660, error: 180, warning: 330, info: 520 };
            oscillator.frequency.value = frequencies[type] || frequencies.info;
            oscillator.type = 'sine';
            gain.gain.setValueAtTime(0.0001, state.audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.045, state.audioContext.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, state.audioContext.currentTime + 0.18);
            oscillator.connect(gain).connect(state.audioContext.destination);
            oscillator.start();
            oscillator.stop(state.audioContext.currentTime + 0.2);
        } catch (error) {
            // Audio is an enhancement; data loading must continue if it is unavailable.
        }
    }

    function setLoadingState(isLoading) {
        if (!marketCards || !signals || !tableBody) return;
        if (!isLoading) return;
        marketCards.innerHTML = '<div class="dashboard-skeleton"></div><div class="dashboard-skeleton"></div><div class="dashboard-skeleton"></div><div class="dashboard-skeleton"></div>';
        signals.innerHTML = '<div class="dashboard-skeleton"></div><div class="dashboard-skeleton"></div>';
        tableBody.innerHTML = '<tr><td colspan="6"><div class="dashboard-skeleton"></div></td></tr>';
    }

    function updateClock() {
        if (!dashboardLiveClock) return;
        dashboardLiveClock.textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
    }

    async function loadDashboard(forceRefresh = false) {
        setRefreshState(true);
        setLoadingState(true);
        if (dashboardSyncState) dashboardSyncState.textContent = 'Sincronizando...';

        try {
            const response = await fetch(`${window.forexDashboardConfig.apiUrl}?refresh=${forceRefresh ? 1 : 0}&t=${Date.now()}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            const data = await response.json();

            if (!response.ok || data.error || !data.marketDetails) {
                throw new Error(data.message || 'No se pudieron cargar los datos del mercado.');
            }

            state.currentData = data;
            renderDashboard(data);
            if (dashboardSyncState) dashboardSyncState.textContent = data.cached ? 'Última copia disponible' : 'Datos actualizados';
            updateClock();
            if (forceRefresh) showToast('success', 'Mercado actualizado', 'Las cotizaciones más recientes ya están disponibles.');
        } catch (error) {
            renderEmptyState(error.message || 'Error cargando el dashboard.');
            if (dashboardSyncState) dashboardSyncState.textContent = 'Sin conexión';
            showToast('error', 'No se pudo actualizar', error.message || 'Revisa la conexión e inténtalo nuevamente.');
        } finally {
            setRefreshState(false);
        }
    }

    function renderDashboard(data) {
        const marketDetails = data.marketDetails || {};
        const validQuotes = symbols
            .map((symbol) => ({ symbol, detail: marketDetails[symbol], quote: data.quotes?.[symbol] }))
            .filter((entry) => entry.detail && Number.isFinite(Number(entry.detail.close ?? entry.quote)));

        const filteredQuotes = applyFilter(validQuotes);
        const averageChange = validQuotes.reduce((acc, entry) => acc + Number(entry.detail.percentChange ?? 0), 0) / (validQuotes.length || 1);

        const topMover = validQuotes.reduce((best, entry) => {
            const change = Number(entry.detail.percentChange ?? 0);
            if (!best || Math.abs(change) > Math.abs(Number(best.detail.percentChange ?? 0))) {
                return entry;
            }
            return best;
        }, null);

        const positive = validQuotes.filter((entry) => Number(entry.detail.percentChange ?? 0) > 0).length;
        const negative = validQuotes.filter((entry) => Number(entry.detail.percentChange ?? 0) < 0).length;
        const stable = validQuotes.length - positive - negative;

        summaryMarketState.textContent = positive >= negative ? 'Alcista' : 'Bajista';
        summaryMarketLabel.textContent = `${positive} alza · ${negative} baja · ${stable} estable`;
        summarySource.textContent = `Fuente: ${data.source || 'N/D'}`;
        summaryUpdatedAt.textContent = formatTimestamp(data.fetchedAt || data.updatedAt);

        if (topMover) {
            const topChange = Number(topMover.detail.percentChange ?? 0);
            summaryTopMover.textContent = topMover.symbol;
            summaryTopMoverMeta.textContent = `${formatSigned(topChange, 2)}% · ${marketNames[topMover.symbol]}`;
        } else {
            summaryTopMover.textContent = '--';
            summaryTopMoverMeta.textContent = 'Sin movimiento';
        }

        summaryAverageChange.textContent = `${formatSigned(averageChange, 2)}%`;

        if (!state.selectedSymbol || !filteredQuotes.some((entry) => entry.symbol === state.selectedSymbol)) {
            state.selectedSymbol = filteredQuotes[0]?.symbol || null;
        }

        renderMarketCards(filteredQuotes);
        renderSignals(validQuotes);
        renderTable(filteredQuotes);
        renderFocusCard(filteredQuotes.find((entry) => entry.symbol === state.selectedSymbol) || filteredQuotes[0] || null);
    }

    function renderEmptyState(message) {
        if (marketCards) marketCards.innerHTML = `<div class="empty-state">${message}</div>`;
        if (signals) signals.innerHTML = '<div class="empty-state">Sin señales disponibles ahora.</div>';
        if (tableBody) tableBody.innerHTML = '<tr><td colspan="6">No hay datos disponibles.</td></tr>';
        if (focusSymbol) focusSymbol.textContent = '--';
        if (focusMeta) focusMeta.textContent = 'Sin datos';
        if (focusTrend) {
            focusTrend.textContent = '--';
            focusTrend.className = 'focus-trend';
        }
        if (alertCount) alertCount.textContent = '-- alertas';

        summaryMarketState.textContent = '--';
        summaryMarketLabel.textContent = 'Sin datos';
        summaryUpdatedAt.textContent = '--';
        summarySource.textContent = 'Fuente: --';
        summaryTopMover.textContent = '--';
        summaryTopMoverMeta.textContent = '--';
        summaryAverageChange.textContent = '--';
    }

    function applyFilter(entries) {
        if (state.filter === 'forex') {
            return entries.filter((entry) => !['BTC/USD', 'ETH/USD', 'SOL/USD', 'XRP/USD'].includes(entry.symbol));
        }

        if (state.filter === 'crypto') {
            return entries.filter((entry) => ['BTC/USD', 'ETH/USD', 'SOL/USD', 'XRP/USD'].includes(entry.symbol));
        }

        return entries;
    }

    function renderMarketCards(entries) {
        if (!marketCards) return;
        marketCards.innerHTML = entries.map((entry) => {
            const detail = entry.detail;
            const change = Number(detail.percentChange ?? 0);
            const trend = trendInfo(change);
            const isSelected = state.selectedSymbol === entry.symbol;

            return `
                <article class="mini-market-card ${trend.className} ${isSelected ? 'selected' : ''}" data-symbol="${entry.symbol}" tabindex="0">
                    <div class="mini-card-head">
                        <div>
                            <strong>${entry.symbol}</strong>
                            <small>${marketNames[entry.symbol]}</small>
                        </div>
                        <span class="trend-badge ${trend.className}">${trend.label}</span>
                    </div>
                    <div class="mini-card-price">${formatPrice(detail.close ?? entry.quote, entry.symbol)}</div>
                    <div class="mini-card-change ${trend.className}">${formatSigned(change, 2)}%</div>
                </article>
            `;
        }).join('');

        marketCards.querySelectorAll('[data-symbol]').forEach((card) => {
            card.addEventListener('click', () => {
                state.selectedSymbol = card.dataset.symbol;
                renderDashboard(state.currentData);
            });
            card.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    state.selectedSymbol = card.dataset.symbol;
                    renderDashboard(state.currentData);
                }
            });
        });
    }

    function renderSignals(entries) {
        if (!signals) return;

        const list = entries
            .map((entry) => {
                const detail = entry.detail;
                const change = Number(detail.percentChange ?? 0);
                const trend = change > 0 ? 'alcista' : change < 0 ? 'bajista' : 'estable';
                const title = `${entry.symbol} · ${trend}`;
                const message = `${formatSigned(change, 2)}% respecto al cierre anterior`;
                return {
                    title,
                    message,
                    type: change >= 0 ? 'up' : 'down',
                    changeValue: Math.abs(change)
                };
            })
            .sort((a, b) => b.changeValue - a.changeValue)
            .slice(0, 5);

        if (!list.length) {
            signals.innerHTML = '<div class="empty-state">No hay señales cálculables.</div>';
            if (alertCount) alertCount.textContent = '0 alertas';
            return;
        }

        if (alertCount) {
            const activeAlerts = list.filter((item) => item.changeValue >= 1).length;
            alertCount.textContent = `${activeAlerts} alerta${activeAlerts === 1 ? '' : 's'}`;
            const alertSignature = list
                .filter((item) => item.changeValue >= 1)
                .map((item) => item.title)
                .join('|');
            if (activeAlerts > 0 && alertSignature !== state.lastAlertSignature) {
                showToast('warning', 'Movimiento detectado', `${activeAlerts} activo${activeAlerts === 1 ? '' : 's'} superan el 1% de variación.`);
            }
            state.lastAlertSignature = alertSignature;
        }

        signals.innerHTML = list.map((item) => `
            <div class="signal-item ${item.type}">
                <div class="signal-indicator"></div>
                <div>
                    <strong>${item.title}</strong>
                    <small>${item.message}</small>
                </div>
            </div>
        `).join('');
    }

    function renderTable(entries) {
        if (!tableBody) return;

        tableBody.innerHTML = entries.map((entry) => {
            const detail = entry.detail;
            const change = Number(detail.percentChange ?? 0);
            const trend = trendInfo(change);
            const isSelected = state.selectedSymbol === entry.symbol;
            return `
                <tr class="${isSelected ? 'selected-row' : ''}" data-symbol="${entry.symbol}" tabindex="0">
                    <td><strong>${entry.symbol}</strong><small>${marketNames[entry.symbol]}</small></td>
                    <td>${formatPrice(detail.close ?? entry.quote, entry.symbol)}</td>
                    <td class="${trend.className}">${formatSigned(change, 2)}%</td>
                    <td>${formatPrice(detail.high, entry.symbol)}</td>
                    <td>${formatPrice(detail.low, entry.symbol)}</td>
                    <td><span class="trend-label ${trend.className}">${trend.label}</span></td>
                </tr>
            `;
        }).join('');

        tableBody.querySelectorAll('[data-symbol]').forEach((row) => {
            const selectRow = () => {
                state.selectedSymbol = row.dataset.symbol;
                renderDashboard(state.currentData);
            };
            row.addEventListener('click', selectRow);
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectRow();
                }
            });
        });
    }

    function renderFocusCard(entry) {
        if (!entry) {
            focusSymbol.textContent = '--';
            focusMeta.textContent = 'Selecciona un activo';
            focusTrend.textContent = '--';
            focusTrend.className = 'focus-trend';
            return;
        }

        const change = Number(entry.detail.percentChange ?? 0);
        const trend = trendInfo(change);
        focusSymbol.textContent = entry.symbol;
        focusMeta.textContent = `${marketNames[entry.symbol]} · ${formatPrice(entry.detail.close ?? entry.quote, entry.symbol)}`;
        focusTrend.textContent = `${formatSigned(change, 2)}% · ${trend.label}`;
        focusTrend.className = `focus-trend ${trend.className}`;
    }

    function trendInfo(value) {
        if (!Number.isFinite(value) || value === 0) {
            return { label: 'Estable', className: 'stable' };
        }
        return value > 0 ? { label: 'Alcista', className: 'up' } : { label: 'Bajista', className: 'down' };
    }

    function formatTimestamp(value) {
        const timestamp = Number(value);
        if (!Number.isFinite(timestamp)) return '--';
        return new Date(timestamp * 1000).toLocaleString('es-PE', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });
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
        if (!Number.isFinite(Number(value))) return '-';
        const number = Number(value);
        return `${number > 0 ? '+' : ''}${number.toFixed(decimals)}`;
    }

    function setRefreshState(isRefreshing) {
        if (!refreshButton) return;
        refreshButton.disabled = isRefreshing;
        refreshButton.innerHTML = isRefreshing ? '<span class="button-icon" aria-hidden="true">◌</span> Actualizando...' : '<span class="button-icon" aria-hidden="true">↻</span> Actualizar';
    }

    if (refreshButton) refreshButton.addEventListener('click', () => loadDashboard(true));

    if (dashboardSoundBtn) {
        dashboardSoundBtn.addEventListener('click', () => {
            state.soundEnabled = !state.soundEnabled;
            dashboardSoundBtn.classList.toggle('active', state.soundEnabled);
            dashboardSoundBtn.setAttribute('aria-label', state.soundEnabled ? 'Desactivar sonidos' : 'Activar sonidos');
            dashboardSoundBtn.title = state.soundEnabled ? 'Desactivar sonidos' : 'Activar sonidos';
            if (state.soundEnabled && window.AudioContext) {
                state.audioContext ||= new AudioContext();
                state.audioContext.resume().then(() => playAlertSound('info'));
            }
            showToast('info', state.soundEnabled ? 'Sonidos activados' : 'Sonidos desactivados', state.soundEnabled ? 'Escucharás avisos importantes una sola vez.' : 'Las alertas continuarán visibles sin audio.');
        });
    }

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filterButtons.forEach((btn) => btn.classList.toggle('active', btn === button));
            state.filter = button.dataset.filter || 'all';
            renderDashboard(state.currentData || { marketDetails: {} });
        });
    });

    function configureAutoRefresh() {
        if (state.autoRefreshTimer) {
            window.clearInterval(state.autoRefreshTimer);
            state.autoRefreshTimer = null;
        }
        if (dashboardAutoRefresh?.checked) {
            state.autoRefreshTimer = window.setInterval(() => loadDashboard(false), 70000);
        }
    }

    if (dashboardAutoRefresh) {
        dashboardAutoRefresh.addEventListener('change', () => {
            configureAutoRefresh();
            showToast('info', dashboardAutoRefresh.checked ? 'Actualización automática activa' : 'Actualización automática pausada', dashboardAutoRefresh.checked ? 'El mercado se actualizará cada 1 minuto y 10 segundos.' : 'Puedes actualizar manualmente cuando lo necesites.');
        });
    }

    updateClock();
    window.setInterval(updateClock, 30000);

    loadDashboard();
});
