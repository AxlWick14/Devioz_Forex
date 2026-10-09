document.addEventListener('DOMContentLoaded', () => {
    const parSelect = document.getElementById('historialPar');
    const requestedPair = new URLSearchParams(window.location.search).get('par');
    if (requestedPair && Array.from(parSelect.options).some(option => option.value === requestedPair)) {
        parSelect.value = requestedPair;
    }
    const desdeInput = document.getElementById('historialDesde');
    const hastaInput = document.getElementById('historialHasta');
    const tableBody = document.getElementById('historialTableBody');
    const buscarBtn = document.getElementById('historialBuscar');
    const chartCanvas = document.getElementById('historialChart');
    const historialGapNote = document.getElementById('historialGapNote');
    const historialParTitle = document.getElementById('historialParTitle');
    const historialMeta = document.getElementById('historialMeta');
    const historialChartTitle = document.getElementById('historialChartTitle');
    const historialTrendBadge = document.getElementById('historialTrendBadge');
    const historialPrecioInicial = document.getElementById('historialPrecioInicial');
    const historialPrecioFinal = document.getElementById('historialPrecioFinal');
    const historialCambioResumen = document.getElementById('historialCambioResumen');
    const historialRowsLabel = document.getElementById('historialRowsLabel');
    const historialCargarMas = document.getElementById('historialCargarMas');

    let chartInstance = null;
    let availableDates = new Set();
    let datePickers = [];
    let allRows = [];
    let visibleRowsCount = 15;
    let requestId = 0;
    const todayInLima = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const shiftDate = (date, days) => {
        const value = new Date(date + 'T12:00:00Z');
        value.setUTCDate(value.getUTCDate() + days);
        return value.toISOString().slice(0, 10);
    };
    let currentDay = todayInLima();
    desdeInput.value = shiftDate(currentDay, -7);
    hastaInput.value = currentDay;

    const formatValue = (value, digits = 5) => Number(value ?? 0).toFixed(digits);
    const formatPercent = (value) => `${Number(value ?? 0).toFixed(2)}%`;

    function initializeDatePickers() {
        if (typeof flatpickr === 'undefined') return;

        datePickers.forEach((picker) => {
            if (picker && typeof picker.destroy === 'function') {
                picker.destroy();
            }
        });

        datePickers = [desdeInput, hastaInput].map((input) => flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: false,
            locale: 'es',
            allowInput: false,
            maxDate: todayInLima(),
            onChange: () => {
                if (desdeInput.value && hastaInput.value) {
                    cargarHistorial();
                }
            }
        }));
    }

    function updateAvailableDates(rows) {
        availableDates = new Set(rows.map((row) => row.fecha));

        datePickers.forEach((picker) => picker.redraw());
    }

    function actualizarResumenVacio() {
        historialParTitle.textContent = parSelect.value;
        historialMeta.textContent = 'Sin registros';
        historialChartTitle.textContent = parSelect.value;
        historialTrendBadge.textContent = 'Sin datos';
        historialTrendBadge.className = 'trend-badge stable';
        historialPrecioInicial.textContent = '--';
        historialPrecioFinal.textContent = '--';
        historialCambioResumen.textContent = '--';
        historialRowsLabel.textContent = '0 registros';
        if (historialCargarMas) {
            historialCargarMas.style.display = 'none';
        }
    }

    function actualizarResumen(rows, par) {
        const firstClose = Number(rows[0].precio_cierre ?? 0);
        const lastClose = Number(rows[rows.length - 1].precio_cierre ?? 0);
        const delta = lastClose - firstClose;
        const variationPct = firstClose !== 0 ? (delta / firstClose) * 100 : 0;
        const trend = variationPct >= 0 ? 'up' : 'down';

        historialParTitle.textContent = par;
        historialChartTitle.textContent = par;
        historialMeta.textContent = `${rows[0].fecha} → ${rows[rows.length - 1].fecha}`;
        historialPrecioInicial.textContent = formatValue(firstClose);
        historialPrecioFinal.textContent = formatValue(lastClose);
        historialCambioResumen.textContent = `${variationPct >= 0 ? '+' : ''}${variationPct.toFixed(2)}%`;
        historialRowsLabel.textContent = `${rows.length} registros`;
        historialTrendBadge.textContent = `${variationPct >= 0 ? '+' : ''}${variationPct.toFixed(2)}%`;
        historialTrendBadge.className = `trend-badge ${trend}`;
    }

    function renderHistorialRows() {
        tableBody.innerHTML = '';

        if (!allRows.length) {
            tableBody.innerHTML = '<tr><td colspan="6">No hay datos para este rango.</td></tr>';
            if (historialCargarMas) {
                historialCargarMas.style.display = 'none';
            }
            return;
        }

        const rowsToShow = allRows.slice(-visibleRowsCount);
        rowsToShow.forEach((row) => {
            const tr = document.createElement('tr');
            const cambio = Number(row.cambio_porcentual ?? 0);
            const cambioClass = cambio >= 0 ? 'text-up' : 'text-down';

            tr.innerHTML = `
                <td><strong>${row.fecha}</strong></td>
                <td>${formatValue(row.precio_apertura)}</td>
                <td>${formatValue(row.precio_maximo)}</td>
                <td>${formatValue(row.precio_minimo)}</td>
                <td>${formatValue(row.precio_cierre)}</td>
                <td class="${cambioClass}">${formatPercent(row.cambio_porcentual)}</td>
            `;
            tableBody.appendChild(tr);
        });

        if (historialCargarMas) {
            historialCargarMas.style.display = visibleRowsCount >= allRows.length ? 'none' : 'inline-block';
        }
    }

    function cargarMasRegistros() {
        if (!allRows.length) return;
        visibleRowsCount = Math.min(visibleRowsCount + 15, allRows.length);
        renderHistorialRows();
    }

    async function cargarHistorial() {
        const request = ++requestId;
        const par = parSelect.value;
        const desde = desdeInput.value;
        const hasta = hastaInput.value;

        if (!par || !desde || !hasta) {
            actualizarResumenVacio();
            return;
        }
        if (desde > hasta || hasta > todayInLima()) {
            document.getElementById('historialSyncStatus').textContent = 'Selecciona un rango válido hasta la fecha de hoy.';
            return;
        }

        visibleRowsCount = 15;
        const url = `${historialConfig.apiUrl}?par=${encodeURIComponent(par)}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;

        try {
            historialGapNote.hidden = true;
            document.getElementById('historialSyncStatus').textContent = 'Consultando y actualizando los cierres disponibles…';
            const response = await fetch(url, { cache: 'no-store' });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            if (request !== requestId) return;
            if (!data || !Array.isArray(data.datos)) {
                throw new Error('Respuesta inválida del historial');
            }

            allRows = data.datos;
            updateAvailableDates(allRows);
            const sync = data.actualizacion;
            const latest = allRows.length ? allRows[allRows.length - 1].fecha : 'sin registros en este rango';
            const syncStatus = document.getElementById('historialSyncStatus');
            const highlights = [
                { label: 'Período seleccionado', value: `${desde} → ${hasta}`, className: 'history-sync-period' },
                { label: 'Último cierre disponible', value: latest, className: 'history-sync-latest' }
            ].map(({ label, value, className }) => {
                const item = document.createElement('span');
                item.className = `history-sync-highlight ${className}`;
                const caption = document.createElement('span');
                caption.textContent = label;
                const date = document.createElement('strong');
                date.textContent = value;
                item.append(caption, date);
                return item;
            });
            syncStatus.replaceChildren(...highlights);
            if (sync?.estado === 'error' || sync?.estado === 'pendiente') {
                const details = document.createElement('span');
                details.className = 'history-sync-details';
                details.textContent = `${sync.mensaje} Se reintentará automáticamente.`;
                syncStatus.append(details);
            }

            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }

            if (!allRows.length) {
                actualizarResumenVacio();
                renderHistorialRows();
            } else {
                renderHistorialRows();
                actualizarResumen(allRows, par);
            }

            const byDate = new Map(allRows.map((row) => [row.fecha, Number(row.precio_cierre)]));
            const labels = [];
            for (let date = desde; date <= hasta; date = shiftDate(date, 1)) labels.push(date);
            const values = labels.map((date) => byDate.get(date) ?? null);
            const missingCount = values.filter((value) => value === null).length;
            historialGapNote.textContent = `Sin cierres disponibles: ${missingCount} ${missingCount === 1 ? 'día sin cotización de cierre publicada' : 'días sin cotización de cierre publicada'} en el período seleccionado. Las zonas rayadas indican datos faltantes; no representan precios estimados.`;
            historialGapNote.hidden = missingCount === 0;

            if (!chartCanvas || typeof Chart === 'undefined') return;
            const firstValue = Number(allRows[0]?.precio_cierre ?? 0);
            const lastValue = Number(allRows[allRows.length - 1]?.precio_cierre ?? 0);
            const trendColor = lastValue >= firstValue ? '#7df0b0' : '#ff9a9f';

            chartInstance = new Chart(chartCanvas, {
                type: 'line',
                plugins: [{
                    id: 'historyMissingCloses',
                    beforeDraw(chart) {
                        const { ctx, chartArea, scales } = chart;
                        if (!chartArea) return;
                        ctx.save();
                        ctx.fillStyle = 'rgba(233, 191, 119, 0.07)';
                        for (let index = 0; index < values.length; index++) {
                            if (values[index] !== null) continue;
                            const start = index;
                            while (index + 1 < values.length && values[index + 1] === null) index++;
                            const left = start === 0 ? chartArea.left :
                                scales.x.getPixelForValue(start - 1);
                            const right = index === values.length - 1 ? chartArea.right :
                                scales.x.getPixelForValue(index + 1);
                            ctx.fillRect(left, chartArea.top, right - left, chartArea.bottom - chartArea.top);
                            ctx.save();
                            ctx.beginPath();
                            ctx.rect(left, chartArea.top, right - left, chartArea.bottom - chartArea.top);
                            ctx.clip();
                            ctx.strokeStyle = 'rgba(233, 191, 119, 0.15)';
                            ctx.lineWidth = 1;
                            for (let y = chartArea.top - (right - left); y < chartArea.bottom; y += 12) {
                                ctx.beginPath();
                                ctx.moveTo(left, y);
                                ctx.lineTo(right, y + right - left);
                                ctx.stroke();
                            }
                            ctx.restore();
                        }
                        ctx.restore();
                    },
                    afterDraw(chart) {
                        const { ctx, chartArea, scales } = chart;
                        if (!chartArea) return;
                        ctx.save();
                        ctx.beginPath();
                        ctx.rect(chartArea.left, chartArea.top, chartArea.right - chartArea.left, chartArea.bottom - chartArea.top);
                        ctx.clip();
                        ctx.font = '600 12px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#e9bf77';
                        const message = 'Sin cierres disponibles';
                        for (let index = 0; index < values.length; index++) {
                            if (values[index] !== null) continue;
                            const start = index;
                            while (index + 1 < values.length && values[index + 1] === null) index++;
                            const left = start === 0 ? chartArea.left :
                                scales.x.getPixelForValue(start - 1);
                            const right = index === values.length - 1 ? chartArea.right :
                                scales.x.getPixelForValue(index + 1);
                            ctx.save();
                            ctx.translate((left + right) / 2, (chartArea.top + chartArea.bottom) / 2);
                            if (right - left >= 18) {
                                ctx.rotate(-Math.PI / 2);
                                ctx.fillText(message, 0, 0, chartArea.bottom - chartArea.top - 16);
                            }
                            ctx.restore();
                        }
                        ctx.restore();
                    }
                }],
                data: {
                    labels,
                    datasets: [{
                        label: par,
                        data: values,
                        borderColor: trendColor,
                        backgroundColor: 'rgba(116, 167, 255, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        spanGaps: false,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(255,255,255,0.06)'
                            },
                            ticks: {
                                color: '#98a8bd',
                                maxRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: false,
                            grid: {
                                color: 'rgba(255,255,255,0.06)'
                            },
                            ticks: {
                                color: '#98a8bd'
                            }
                        }
                    }
                }
            });
        } catch (error) {
            if (request !== requestId) return;
            historialGapNote.hidden = true;
            document.getElementById('historialSyncStatus').textContent = 'No se pudo consultar el histórico. Se reintentará automáticamente.';
            if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
            actualizarResumenVacio();
            allRows = [];
            renderHistorialRows();
            console.error(error);
        }
    }

    buscarBtn.addEventListener('click', cargarHistorial);
    parSelect.addEventListener('change', cargarHistorial);
    desdeInput.addEventListener('change', cargarHistorial);
    hastaInput.addEventListener('change', cargarHistorial);

    if (historialCargarMas) {
        historialCargarMas.addEventListener('click', cargarMasRegistros);
    }

    initializeDatePickers();
    cargarHistorial();
    function refreshDay() {
        const nextDay = todayInLima();
        if (nextDay !== currentDay) {
            const followsToday = hastaInput.value === currentDay;
            const followsWeek = followsToday && desdeInput.value === shiftDate(currentDay, -7);
            currentDay = nextDay;
            datePickers.forEach((picker) => picker.set('maxDate', currentDay));
            if (followsToday) {
                if (followsWeek) desdeInput.value = shiftDate(currentDay, -7);
                hastaInput.value = currentDay;
                datePickers.forEach((picker) => picker.setDate(picker.input.value, false));
            }
        }
        cargarHistorial();
    }
    window.setInterval(refreshDay, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshDay(); });
});
