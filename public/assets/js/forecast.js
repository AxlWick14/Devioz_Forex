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
    let historyChart = null;

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
    historyButtons.forEach((button) => {
        button.addEventListener('click', () => loadHistory(button.dataset.par));
    });
    loadHistory('SOL/USD');
});
