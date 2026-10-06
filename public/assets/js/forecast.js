document.addEventListener('DOMContentLoaded', () => {
    const forecastList = document.getElementById('forecastList');
    const forecastSignals = document.getElementById('forecastSignals');
    const forecastTopConfianza = document.getElementById('forecastTopConfianza');
    const forecastVolatilityAvg = document.getElementById('forecastVolatilityAvg');
    const historyButtons = document.querySelectorAll('.forecast-asset-btn');
    const historyTitle = document.getElementById('forecastHistoryTitle');
    const historyChange = document.getElementById('forecastHistoryChange');
    const historyMeta = document.getElementById('forecastHistoryMeta');
    const historyStatus = document.getElementById('forecastHistoryStatus');
    const historyCanvas = document.getElementById('forecastHistoryChart');
    const forecastSoundBtn = document.getElementById('forecastSoundBtn');
    const forecastToastRegion = document.getElementById('forecastToastRegion');
    let historyChart = null;
    let historyRequest = 0;
    let itemsCache = [];
    let trendFilter = 'all';
    let loading = false;
    let syncingHistory = false;
    const syncButton = document.getElementById('forecastSyncHistory');
    const syncStatus = document.getElementById('forecastSyncStatus');
    const allowedPairs = ['BTC/USD', 'ETH/USD', 'USDT/USD', 'BNB/USD'];
    let selectedPair = 'BTC/USD';
    const refreshButton = document.getElementById('forecastRefresh');
    const updated = document.getElementById('forecastUpdated');
    const methodSelect = document.getElementById('forecastMethod');
    let selectedMethod = 'lineal';
    const methodLabels = { lineal: 'Regresión lineal', logaritmica: 'Regresión logarítmica', holt: 'Holt amortiguado' };
    const methodDescriptions = {
        lineal: 'Ajusta una recta a los cierres según su fecha: precio(t) = a + b × t. Cada día futuro se evalúa sobre esa recta.',
        logaritmica: 'Ajusta una recta al logaritmo del precio: precio(t) = exp(a + b × t). Representa cambios proporcionales y produce precios positivos.',
        holt: 'Suaviza nivel y tendencia; reduce la pendiente futura con amortiguación. Parámetros fijos: α = 0,4; β = 0,2; φ = 0,9. Los días sin registros mantienen el cierre anterior para el cálculo.'
    };
    Object.assign(methodLabels, {
        boosting: 'LightGBM / XGBoost',
        cnn_lstm: 'CNN-LSTM',
        gru: 'GRU / CNN-GRU',
        ensemble: 'XGBoost + LSTM/GRU ensemble',
        tcn: 'TCN',
        transformers: 'Transformers',
        random_forest: 'Random Forest',
        arima: 'ARIMA / regresión tradicional'
    });
    Object.assign(methodDescriptions, {
        boosting: 'Árboles con boosting para combinar indicadores, volumen y otras variables. No modela secuencias de forma natural.',
        cnn_lstm: 'Combina patrones locales de una CNN con patrones temporales de una LSTM. Su complejidad exige controlar el sobreajuste.',
        gru: 'Redes recurrentes GRU, con una CNN opcional para patrones locales. Requiere suficientes datos para entrenar y validar.',
        ensemble: 'Combina las salidas de XGBoost y una LSTM o GRU. Requiere entrenar y validar tanto los modelos individuales como su combinación.',
        tcn: 'Red convolucional temporal para analizar secuencias. Su implementación requiere entrenamiento y validación específicos.',
        transformers: 'Modelos de atención para analizar secuencias. Requieren datasets amplios; su arquitectura no garantiza mejores resultados.',
        random_forest: 'Combina múltiples árboles de decisión como referencia para comparar modelos. Requiere entrenamiento y validación con datos históricos.',
        arima: 'ARIMA es un modelo estadístico de series temporales, pendiente de implementación. La regresión tradicional ya está disponible como regresión lineal y logarítmica.'
    });
    const pendingModelMessage = ' Pendiente de implementación: este modelo todavía no genera predicciones en la aplicación.';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const forecastState = {
        soundEnabled: false,
        audioContext: null,
        lastSignature: null
    };

    function dismissToast(toast) {
        if (!toast || toast.classList.contains('leaving')) return;
        toast.classList.add('leaving');
        window.setTimeout(() => toast.remove(), 260);
    }

    function showForecastToast(type, title, message) {
        if (!forecastToastRegion) return;
        const icon = type === 'success' ? '↑' : type === 'error' ? '↓' : 'i';
        const toast = document.createElement('article');
        toast.className = `dashboard-toast ${type}`;
        toast.innerHTML = `
            <span class="toast-icon" aria-hidden="true">${icon}</span>
            <div><strong>${title}</strong><p>${message}</p></div>
            <button class="toast-close" type="button" aria-label="Cerrar notificación">×</button>
        `;
        toast.querySelector('.toast-close').addEventListener('click', () => dismissToast(toast));
        forecastToastRegion.appendChild(toast);
        window.setTimeout(() => dismissToast(toast), 4200);
    }

    function playForecastSound(direction) {
        if (!forecastState.soundEnabled) return;
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;

        try {
            forecastState.audioContext ||= new AudioContextClass();
            const context = forecastState.audioContext;
            if (context.state === 'suspended') return;
            const now = context.currentTime;
            const gain = context.createGain();
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.07, now + 0.025);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + (direction === 'up' ? 0.58 : 0.32));
            gain.connect(context.destination);

            if (direction === 'up') {
                [660, 880, 1175].forEach((frequency, index) => {
                    const oscillator = context.createOscillator();
                    oscillator.type = 'sine';
                    oscillator.frequency.value = frequency;
                    oscillator.connect(gain);
                    oscillator.start(now + index * 0.08);
                    oscillator.stop(now + 0.62);
                });
                return;
            }

            const buffer = context.createBuffer(1, context.sampleRate * 0.28, context.sampleRate);
            const noise = buffer.getChannelData(0);
            for (let index = 0; index < noise.length; index += 1) {
                noise[index] = (Math.random() * 2 - 1) * (1 - index / noise.length);
            }
            const source = context.createBufferSource();
            const filter = context.createBiquadFilter();
            filter.type = 'highpass';
            filter.frequency.value = 1800;
            source.buffer = buffer;
            source.connect(filter).connect(gain);
            source.start(now);
            source.stop(now + 0.3);
        } catch (error) {
            // The forecast remains usable when audio is unavailable or blocked.
        }
    }

    function announceNewForecasts(items) {
        if (!items.length) { forecastState.lastSignature = null; return; }
        const signature = items.map((item) => `${item.par}:${item.tendencia}:${item.cambio_estimado}`).join('|');
        if (forecastState.lastSignature === null) {
            forecastState.lastSignature = signature;
            return;
        }
        if (signature === forecastState.lastSignature) return;

        const changed = items.find((item) => !forecastState.lastSignature.includes(`${item.par}:${item.tendencia}:${item.cambio_estimado}`)) || items[0];
        const isUp = changed.tendencia === 'alcista';
        showForecastToast(isUp ? 'success' : 'error', isUp ? 'Nueva señal alcista' : 'Nueva señal bajista', `${changed.par} · cambio estimado ${changed.cambio_estimado >= 0 ? '+' : ''}${Number(changed.cambio_estimado).toFixed(2)}%.`);
        playForecastSound(isUp ? 'up' : 'down');
        forecastState.lastSignature = signature;
    }

    async function loadForecasts() {
        if (loading || syncingHistory) return;
        loading = true;
        syncButton.disabled = true;
        methodSelect.disabled = true;
        refreshButton.disabled = true;
        refreshButton.textContent = 'Actualizando…';
        forecastList.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(`${window.forecastConfig.apiUrl}?metodo=${encodeURIComponent(selectedMethod)}&t=${Date.now()}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            const data = await response.json();

            if (!response.ok || !Array.isArray(data)) {
                throw new Error('No se pudieron cargar los pronósticos.');
            }

            itemsCache = data.filter((item) => allowedPairs.includes(item.par));
            renderForecasts(itemsCache);
            updated.textContent = `Última consulta: ${new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })} · Actualización cada 70 s`;
            announceNewForecasts(itemsCache);
        } catch (error) {
            updated.textContent = 'No se pudo actualizar. Pulsa Actualizar para reintentar.';
            itemsCache = [];
            forecastTopConfianza.textContent = '--';
            forecastVolatilityAvg.textContent = '--';
            document.getElementById('forecastTopAsset').textContent = 'Sin datos disponibles';
            if (forecastList) {
                forecastList.innerHTML = '<div class="empty-state">No hay pronósticos disponibles en este momento.</div>';
            }
            if (forecastSignals) {
                forecastSignals.innerHTML = '<div class="empty-state">Sin señales calculadas.</div>';
            }
        } finally {
            loading = false;
            syncButton.disabled = false;
            methodSelect.disabled = false;
            refreshButton.disabled = false;
            refreshButton.textContent = '↻ Actualizar';
            forecastList.setAttribute('aria-busy', 'false');
            loadHistory(selectedPair);
        }
    }

    function renderForecasts(items) {
        if (!items.length) {
            if (forecastList) forecastList.innerHTML = '<div class="empty-state">No hay pronósticos disponibles.</div>';
            forecastSignals.innerHTML = '<div class="empty-state">Sin señales calculadas.</div>';
            forecastTopConfianza.textContent = '--';
            forecastVolatilityAvg.textContent = '--';
            document.getElementById('forecastTopAsset').textContent = 'Sin datos disponibles';
            return;
        }

        const top = items.reduce((best, item) => {
            return !best || item.confianza > best.confianza ? item : best;
        }, null);

        if (forecastTopConfianza) {
            forecastTopConfianza.textContent = top ? `${top.confianza.toFixed(1)}/100` : '--';
        }
        document.getElementById('forecastTopAsset').textContent = top.par;

        const avgVol = items.reduce((sum, item) => sum + Number(item.volatilidad || 0), 0) / items.length;
        if (forecastVolatilityAvg) {
            forecastVolatilityAvg.textContent = `${avgVol.toFixed(2)}%`;
        }

        if (forecastList) {
            const visible = items.filter((item) => trendFilter === 'all' || item.tendencia === trendFilter);
            forecastList.innerHTML = visible.map((item, index) => {
                const trendClass = item.tendencia === 'alcista' ? 'up' : 'down';
                const direction = item.cambio_estimado >= 0 ? '+' : '';
                return `
                    <article class="forecast-item ${trendClass}">
                        <div class="forecast-meta">
                            <span>${escapeHtml(item.par)}</span>
                            <strong>${direction}${item.cambio_estimado.toFixed(2)}%</strong>
                        </div>
                        <small>${item.tendencia === 'alcista' ? '↗ Alcista' : '↘ Bajista'} · Escenario a 7 días</small>
                        <small>${escapeHtml(item.mensaje)}</small>
                        <div class="forecast-confidence"><label for="confidence-${index}">Puntuación orientativa ${Number(item.confianza).toFixed(1)}/100</label><progress id="confidence-${index}" max="100" value="${Math.max(0, Math.min(100, Number(item.confianza) || 0))}"></progress></div>
                        <button type="button" class="forecast-history-link" data-history="${escapeHtml(item.par)}">Ver predicción <span aria-hidden="true">→</span></button>
                    </article>
                `;
            }).join('') || '<div class="empty-state">No hay escenarios con esta tendencia.</div>';
        }

        if (forecastSignals) {
            forecastSignals.innerHTML = [...items].sort((a, b) => b.confianza - a.confianza).slice(0, 3).map((item) => {
                const type = item.tendencia === 'alcista' ? 'up' : 'down';
                return `
                    <div class="signal-item ${type}">
                        <div class="signal-indicator"></div>
                        <div>
                            <strong>${escapeHtml(item.par)} · ${escapeHtml(item.tendencia)}</strong>
                            <small>Puntuación ${item.confianza.toFixed(1)}/100 · Cambio estimado ${item.cambio_estimado >= 0 ? '+' : ''}${item.cambio_estimado.toFixed(2)}%</small>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    async function loadHistory(par) {
        if (!allowedPairs.includes(par)) return;
        selectedPair = par;
        const request = ++historyRequest;
        if (historyChart) { historyChart.destroy(); historyChart = null; }
        historyChange.textContent = '--';
        document.getElementById('forecastModelDetails').textContent = '';
        historyMeta.textContent = 'Proyección a 7 días';

        historyButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.par === par);
            button.setAttribute('aria-pressed', String(button.dataset.par === par));
        });
        historyTitle.textContent = par;
        historyStatus.textContent = 'Cargando histórico...';
        historyStatus.style.display = 'block';

        try {
            if (!historyCanvas || typeof Chart === 'undefined') throw new Error('Chart unavailable');
            const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
            const url = `${window.forecastConfig.historyUrl}?par=${encodeURIComponent(par)}&hasta=${today}&limit=60`;
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (request !== historyRequest) return;
            const rows = (Array.isArray(data.datos) ? data.datos : [])
                .filter((row) => Number.isFinite(Number(row.precio_cierre)) && Number(row.precio_cierre) > 0 && Number.isFinite(Date.parse(row.fecha)))
                .sort((a, b) => a.fecha.localeCompare(b.fecha));

            if (!response.ok || rows.length === 0) throw new Error('Sin datos');

            if (historyChart) historyChart.destroy();
            const last = Number(rows[rows.length - 1].precio_cierre);
            const day = 86400000;
            const forecast = itemsCache.find((item) => item.par === par);
            const presentDate = forecast?.fecha_presente || today;
            const present = Date.parse(presentDate + 'T00:00:00Z');
            const lastClose = Date.parse(rows[rows.length - 1].fecha.slice(0, 10) + 'T00:00:00Z');
            const stale = lastClose < present;
            const pastStart = present - 7 * day;
            // Do not join a forecast based on a different close to this history.
            const hasProjection = forecast && Number.isFinite(Number(forecast.precio_estimado))
                && forecast.metodo === selectedMethod
                && String(forecast.fecha_base).slice(0, 10) === rows[rows.length - 1].fecha.slice(0, 10)
                && Array.isArray(forecast.proyeccion) && forecast.proyeccion.length === 7
                && Number(forecast.precio_estimado) > 0
                && Math.abs(Number(forecast.precio_actual) - last) <= Math.max(0.000001, last * 0.000001);
            const target = hasProjection ? Number(forecast.precio_estimado) : null;
            const variation = hasProjection ? (target / last - 1) * 100 : null;
            const historicalPoints = rows.map((row) => ({ x: Date.parse(row.fecha.slice(0, 10) + 'T00:00:00Z'), y: Number(row.precio_cierre) }))
                .filter((point) => point.x >= pastStart);
            const projection = hasProjection ? forecast.proyeccion.map((point) => ({ x: Date.parse(point.fecha + 'T00:00:00Z'), y: Number(point.precio) })) : [];
            const presentPrice = Number(forecast?.precio_presente);
            const bridge = hasProjection && Number.isFinite(presentPrice) && presentPrice > 0
                ? [{ x: lastClose, y: last }, { x: present, y: presentPrice }, projection[0]] : [];
            const validation = forecast?.validacion;
            const details = document.getElementById('forecastModelDetails');
            const priceFormat = (value) => Number(value).toLocaleString('es-PE', { maximumFractionDigits: 6 });
            details.innerHTML = hasProjection ? `
                <div><span>Cierres utilizados</span><strong>${Number(forecast.observaciones)} / ventana ${Number(validation?.ventana || 120)}</strong></div>
                <div><span>Error histórico (MAPE)</span><strong>${validation?.error_mape != null ? Number(validation.error_mape).toFixed(2) + '%' : 'Sin evaluación suficiente'}</strong></div>
                <div><span>Error manteniendo el último cierre</span><strong>${validation?.error_base != null ? Number(validation.error_base).toFixed(2) + '%' : '--'}</strong></div>
                <div><span>Comparaciones de validación</span><strong>${Number(validation?.evaluaciones || 0)}</strong></div>
                <div><span>Último cierre / objetivo a 7 días</span><strong>${priceFormat(last)} / ${priceFormat(target)} USD</strong></div>
                <div><span>Medias de 7 / 30 cierres</span><strong>${priceFormat(forecast.media_corta)} / ${priceFormat(forecast.media_larga)} USD</strong></div>
            ` : '<p>Actualiza los pronósticos para consultar los detalles del modelo.</p>';
            const formatDate = (value) => new Date(value).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', timeZone: 'UTC' });
            historyChange.textContent = hasProjection ? `${variation >= 0 ? '+' : ''}${variation.toFixed(2)}% estimado` : 'Sin predicción';
            historyChange.className = hasProjection ? (variation >= 0 ? 'text-up' : 'text-down') : '';
            historyMeta.textContent = `Hoy: ${presentDate} · Último cierre: ${rows[rows.length - 1].fecha}`;
            historyStatus.textContent = hasProjection ? (stale ? `Último cierre confirmado: ${formatDate(lastClose)} (${last.toLocaleString('es-PE', { maximumFractionDigits: 6 })} USD). No hay cierres posteriores guardados; hoy aún no se incorpora como cierre definitivo. La predicción empieza el ${formatDate(projection[0].x)} y no completa el histórico.` : '') : 'No hay una predicción disponible para este cierre. Puedes actualizar para reintentar.';
            historyStatus.style.display = !hasProjection || stale ? 'block' : 'none';
            historyCanvas.setAttribute('aria-label', `${par}: histórico hasta ${rows[rows.length - 1].fecha}. ${hasProjection ? `Precio estimado a siete días: ${target} USD.` : 'Sin predicción disponible.'}`);
            historyChart = new Chart(historyCanvas, {
                type: 'line',
                plugins: [{
                    id: 'forecastPresent',
                    beforeDraw(chart) {
                        const { ctx, chartArea, scales } = chart;
                        if (!chartArea) return;
                        const x = scales.x.getPixelForValue(present);
                        ctx.save();
                        ctx.fillStyle = 'rgba(180, 145, 255, 0.10)';
                        ctx.fillRect(x, chartArea.top, chartArea.right - x, chartArea.bottom - chartArea.top);
                        if (stale) {
                            const start = Math.max(chartArea.left, scales.x.getPixelForValue(lastClose));
                            ctx.fillStyle = 'rgba(233,191,119,.07)';
                            ctx.fillRect(start, chartArea.top, x - start, chartArea.bottom - chartArea.top);
                            ctx.beginPath();
                            ctx.rect(start, chartArea.top, x - start, chartArea.bottom - chartArea.top);
                            ctx.clip();
                            ctx.strokeStyle = 'rgba(233,191,119,.15)';
                            for (let y = chartArea.top - (x - start); y < chartArea.bottom; y += 12) {
                                ctx.beginPath();
                                ctx.moveTo(start, y);
                                ctx.lineTo(x, y + x - start);
                                ctx.stroke();
                            }
                        }
                        ctx.restore();
                    },
                    afterDraw(chart) {
                        const { ctx, chartArea, scales } = chart;
                        if (!chartArea) return;
                        const x = scales.x.getPixelForValue(present);
                        ctx.save();
                        ctx.strokeStyle = '#dce8ff';
                        ctx.lineWidth = 1.5;
                        ctx.setLineDash([5, 5]);
                        ctx.beginPath();
                        ctx.moveTo(x, chartArea.top);
                        ctx.lineTo(x, chartArea.bottom);
                        ctx.stroke();
                        ctx.setLineDash([]);
                        ctx.fillStyle = '#dce8ff';
                        ctx.font = '600 12px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText(`Hoy · ${formatDate(present)}`, x, chartArea.top - 14);
                        ctx.fillStyle = '#98a8bd';
                        ctx.textAlign = 'left';
                        ctx.fillText('Pasado', chartArea.left, chartArea.top - 14);
                        ctx.textAlign = 'right';
                        ctx.fillText('Futuro', chartArea.right, chartArea.top - 14);
                        if (stale) {
                            const start = Math.max(chartArea.left, scales.x.getPixelForValue(lastClose));
                            ctx.translate((start + x) / 2, (chartArea.top + chartArea.bottom) / 2);
                            ctx.rotate(-Math.PI / 2);
                            ctx.textAlign = 'center';
                            ctx.fillStyle = '#e9bf77';
                            ctx.fillText('Sin cierres disponibles', 0, 0);
                        }
                        ctx.restore();
                    }
                }],
                data: {
                    datasets: [{
                        label: 'Histórico (USD)',
                        data: historicalPoints,
                        borderColor: '#74a7ff',
                        backgroundColor: 'rgba(116, 167, 255, 0.12)',
                        borderWidth: 2.5,
                        tension: 0,
                        pointRadius: 1.5,
                        pointHoverRadius: 5,
                        fill: true
                    }, {
                        label: `${methodLabels[selectedMethod]} (USD)`,
                        data: bridge.length ? [bridge[1], ...projection] : projection,
                        borderColor: '#c4a1ff',
                        borderDash: [7, 5],
                        borderWidth: 3,
                        tension: 0,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        fill: false
                    }, {
                        label: 'Conexión estimada (sin cierres confirmados)',
                        data: bridge,
                        borderColor: '#e9bf77',
                        borderDash: [3, 6],
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 32 } },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    plugins: { legend: { display: false }, tooltip: { callbacks: {
                        title: (points) => points.length ? formatDate(points[0].parsed.x) : '',
                        label: (context) => `${context.dataset.label}: ${context.parsed.y.toLocaleString('es-PE', { maximumFractionDigits: 6 })}`
                    } } },
                    scales: {
                        x: { type: 'linear', min: pastStart, max: present + 7 * day,
                            afterBuildTicks: (scale) => {
                                const count = Math.round((scale.max - scale.min) / day);
                                const step = Math.max(1, Math.ceil(count / 60));
                                scale.ticks = Array.from({ length: Math.floor(count / step) + 1 }, (_, i) => ({ value: scale.min + i * step * day }));
                            },
                            grid: { color: 'rgba(255,255,255,0.06)' },
                            ticks: { color: '#98a8bd', autoSkip: true, maxTicksLimit: 16, maxRotation: 45, callback: (value) => formatDate(value) } },
                        y: { beginAtZero: false, grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#98a8bd' } }
                    }
                }
            });
        } catch (error) {
            if (request !== historyRequest) return;
            historyStatus.textContent = typeof Chart === 'undefined' ? 'No se pudo cargar el gráfico. Recarga la página para reintentar.' : 'No hay datos históricos disponibles para este activo.';
            historyStatus.style.display = 'block';
        }
    }

    loadForecasts();
    methodSelect.addEventListener('click', (event) => {
        const help = event.target.closest('[data-method-help], [data-method-unavailable]');
        if (help) {
            const method = help.dataset.methodHelp || help.dataset.methodUnavailable;
            const pending = methodSelect.querySelector(`[data-method-unavailable="${method}"]`) !== null;
            document.getElementById('forecastModelDialogTitle').textContent = methodLabels[method];
            document.getElementById('forecastModelDialogText').textContent = methodDescriptions[method] + (pending ? pendingModelMessage : '');
            document.getElementById('forecastModelDialog').showModal();
            return;
        }
        const button = event.target.closest('[data-method]');
        if (!button || loading || syncingHistory || selectedMethod === button.dataset.method) return;
        selectedMethod = button.dataset.method;
        methodSelect.querySelectorAll('[data-method]').forEach((item) => {
            item.classList.toggle('active', item === button);
            item.setAttribute('aria-pressed', String(item === button));
        });
        forecastState.lastSignature = null;
        itemsCache = [];
        ++historyRequest;
        if (historyChart) { historyChart.destroy(); historyChart = null; }
        historyStatus.textContent = 'Calculando con el método seleccionado...';
        historyStatus.style.display = 'block';
        historyChange.textContent = '--';
        document.getElementById('forecastModelName').textContent = methodLabels[selectedMethod];
        document.getElementById('forecastMethodDescription').textContent = methodDescriptions[selectedMethod];
        renderForecasts([]);
        loadForecasts();
    });
    refreshButton.addEventListener('click', loadForecasts);
    syncButton.addEventListener('click', async () => {
        if (syncingHistory || loading) return;
        syncingHistory = true;
        syncButton.disabled = true;
        refreshButton.disabled = true;
        methodSelect.disabled = true;
        syncStatus.hidden = false;
        const messages = [];
        try {
            for (const [index, par] of allowedPairs.entries()) {
                syncButton.textContent = `Actualizando ${index + 1}/${allowedPairs.length}…`;
                syncStatus.textContent = [...messages, `Consultando ${par}…`].join(' · ');
                try {
                    const response = await fetch(window.forecastConfig.syncUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-Token': window.forecastConfig.csrfToken },
                        body: new URLSearchParams({ par })
                    });
                    const result = await response.json();
                    if (!response.ok || result.estado === 'error') throw new Error(result.error || result.mensaje || 'No se pudo actualizar.');
                    messages.push(`${par}: ${result.registros} cierres sincronizados con Twelve Data, último ${result.ultima_fecha}${result.ultima_fecha < result.hasta_solicitado ? ' (el proveedor no devolvió cierres más recientes)' : ''}.`);
                } catch (error) {
                    messages.push(`${par}: ${error.message}`);
                }
            }
        } finally {
            syncingHistory = false;
            syncButton.textContent = '↻ Actualizar histórico';
            syncButton.disabled = false;
            refreshButton.disabled = false;
            methodSelect.disabled = false;
            syncStatus.textContent = messages.join(' · ');
            await loadForecasts();
        }
    });
    document.querySelectorAll('[data-trend]').forEach((button) => {
        button.addEventListener('click', () => {
            trendFilter = button.dataset.trend;
            document.querySelectorAll('[data-trend]').forEach((filter) => {
                filter.classList.toggle('active', filter === button);
                filter.setAttribute('aria-pressed', String(filter === button));
            });
            renderForecasts(itemsCache);
        });
    });
    forecastList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-history]');
        if (!button) return;
        loadHistory(button.dataset.history);
        document.getElementById('forecastHistory').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    });
    if (forecastSoundBtn) {
        forecastSoundBtn.addEventListener('click', () => {
            forecastState.soundEnabled = !forecastState.soundEnabled;
            forecastSoundBtn.classList.toggle('active', forecastState.soundEnabled);
            forecastSoundBtn.setAttribute('aria-pressed', String(forecastState.soundEnabled));
            forecastSoundBtn.textContent = forecastState.soundEnabled ? 'Sonido: encendido' : 'Sonido: apagado';
            forecastSoundBtn.setAttribute('aria-label', forecastState.soundEnabled ? 'Desactivar sonidos de pronóstico' : 'Activar sonidos de pronóstico');
            forecastSoundBtn.title = forecastState.soundEnabled ? 'Desactivar sonidos de pronóstico' : 'Activar sonidos de pronóstico';
            if (forecastState.soundEnabled) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    forecastState.audioContext ||= new AudioContextClass();
                    forecastState.audioContext.resume().then(() => playForecastSound('up'));
                }
                showForecastToast('info', 'Sonidos activados', 'Se avisarán nuevas señales alcistas o bajistas.');
            } else {
                showForecastToast('info', 'Sonidos desactivados', 'Las señales seguirán visibles en pantalla.');
            }
        });
    }
    historyButtons.forEach((button) => {
        button.addEventListener('click', () => loadHistory(button.dataset.par));
    });
    window.setInterval(loadForecasts, 70000);
});
