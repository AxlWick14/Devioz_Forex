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
        try {
            const response = await fetch(`${window.forecastConfig.apiUrl}?t=${Date.now()}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            const data = await response.json();

            if (!response.ok || !Array.isArray(data)) {
                throw new Error('No se pudieron cargar los pronósticos.');
            }

            renderForecasts(data);
            announceNewForecasts(data);
        } catch (error) {
            if (forecastList) {
                forecastList.innerHTML = '<div class="empty-state">No hay pronósticos disponibles en este momento.</div>';
            }
            if (forecastSignals) {
                forecastSignals.innerHTML = '<div class="empty-state">Sin señales calculadas.</div>';
            }
        }
    }

    function renderForecasts(items) {
        if (!items.length) {
            if (forecastList) forecastList.innerHTML = '<div class="empty-state">No hay datos para forecast.</div>';
            return;
        }

        const top = items.reduce((best, item) => {
            return !best || item.confianza > best.confianza ? item : best;
        }, null);

        if (forecastTopConfianza) {
            forecastTopConfianza.textContent = top ? `${top.confianza.toFixed(1)}%` : '--';
        }

        const avgVol = items.reduce((sum, item) => sum + Number(item.volatilidad || 0), 0) / items.length;
        if (forecastVolatilityAvg) {
            forecastVolatilityAvg.textContent = `${avgVol.toFixed(2)}%`;
        }

        if (forecastList) {
            forecastList.innerHTML = items.map((item) => {
                const trendClass = item.tendencia === 'alcista' ? 'up' : 'down';
                const direction = item.cambio_estimado >= 0 ? '+' : '';
                return `
                    <article class="forecast-item ${trendClass}">
                        <div class="forecast-meta">
                            <span>${item.par}</span>
                            <strong>${direction}${item.cambio_estimado.toFixed(2)}%</strong>
                        </div>
                        <small>${item.mensaje}</small>
                    </article>
                `;
            }).join('');
        }

        if (forecastSignals) {
            forecastSignals.innerHTML = items.slice(0, 3).map((item) => {
                const type = item.tendencia === 'alcista' ? 'up' : 'down';
                return `
                    <div class="signal-item ${type}">
                        <div class="signal-indicator"></div>
                        <div>
                            <strong>${item.par} · ${item.tendencia}</strong>
                            <small>Confianza ${item.confianza.toFixed(1)}% · Cambio estimado ${item.cambio_estimado >= 0 ? '+' : ''}${item.cambio_estimado.toFixed(2)}%</small>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    async function loadHistory(par) {
        if (!historyCanvas || typeof Chart === 'undefined') return;

        historyButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.par === par);
        });
        historyTitle.textContent = par;
        historyStatus.textContent = 'Cargando histórico...';
        historyStatus.style.display = 'block';

        try {
            const today = new Date().toISOString().slice(0, 10);
            const url = `${window.forecastConfig.historyUrl}?par=${encodeURIComponent(par)}&hasta=${today}&limit=60`;
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            const rows = Array.isArray(data.datos) ? data.datos : [];

            if (!response.ok || rows.length === 0) throw new Error('Sin datos');

            if (historyChart) historyChart.destroy();
            const first = Number(rows[0].precio_cierre);
            const last = Number(rows[rows.length - 1].precio_cierre);
            const variation = first !== 0 ? ((last - first) / first) * 100 : 0;
            const color = variation >= 0 ? '#7df0b0' : '#ff9a9f';

            historyChange.textContent = `${variation >= 0 ? '+' : ''}${variation.toFixed(2)}%`;
            historyChange.className = variation >= 0 ? 'text-up' : 'text-down';
            historyMeta.textContent = `${rows.length} registros · ${rows[0].fecha} al ${rows[rows.length - 1].fecha}`;
            historyStatus.style.display = 'none';
            historyChart = new Chart(historyCanvas, {
                type: 'line',
                data: {
                    labels: rows.map((row) => row.fecha),
                    datasets: [{
                        label: par,
                        data: rows.map((row) => Number(row.precio_cierre)),
                        borderColor: color,
                        backgroundColor: 'rgba(116, 167, 255, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 1.5,
                        pointHoverRadius: 5,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#98a8bd', maxTicksLimit: 8 } },
                        y: { beginAtZero: false, grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#98a8bd' } }
                    }
                }
            });
        } catch (error) {
            historyStatus.textContent = 'No hay datos históricos disponibles para este activo.';
            historyStatus.style.display = 'block';
        }
    }

    loadForecasts();
    if (forecastSoundBtn) {
        forecastSoundBtn.addEventListener('click', () => {
            forecastState.soundEnabled = !forecastState.soundEnabled;
            forecastSoundBtn.classList.toggle('active', forecastState.soundEnabled);
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
    loadHistory('SOL/USD');
    window.setInterval(loadForecasts, 70000);
});
