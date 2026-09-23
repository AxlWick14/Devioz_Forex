document.addEventListener('DOMContentLoaded', () => {
    const forecastList = document.getElementById('forecastList');
    const forecastSignals = document.getElementById('forecastSignals');
    const forecastTopConfianza = document.getElementById('forecastTopConfianza');
    const forecastVolatilityAvg = document.getElementById('forecastVolatilityAvg');

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

    loadForecasts();
});
