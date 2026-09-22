document.addEventListener('DOMContentLoaded', () => {
    const parSelect = document.getElementById('historialPar');
    const desdeInput = document.getElementById('historialDesde');
    const hastaInput = document.getElementById('historialHasta');
    const tableBody = document.getElementById('historialTableBody');
    const buscarBtn = document.getElementById('historialBuscar');
    const chartCanvas = document.getElementById('historialChart');
    const historialParTitle = document.getElementById('historialParTitle');
    const historialMeta = document.getElementById('historialMeta');
    const historialChartTitle = document.getElementById('historialChartTitle');
    const historialTrendBadge = document.getElementById('historialTrendBadge');
    const historialPrecioInicial = document.getElementById('historialPrecioInicial');
    const historialPrecioFinal = document.getElementById('historialPrecioFinal');
    const historialCambioResumen = document.getElementById('historialCambioResumen');
    const historialRowsLabel = document.getElementById('historialRowsLabel');
    let chartInstance = null;
    let availableDates = new Set();
    let datePickers = [];

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
            onChange: () => {
                if (desdeInput.value && hastaInput.value) cargarHistorial();
            }
        }));
    }

    function updateAvailableDates(rows) {
        availableDates = new Set(rows.map((row) => row.fecha));
        if (availableDates.size) {
            const dates = [...availableDates].sort();
            if (!availableDates.has(desdeInput.value)) desdeInput.value = dates[0];
            if (!availableDates.has(hastaInput.value)) hastaInput.value = dates[dates.length - 1];
        }
        initializeDatePickers();
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

    async function cargarHistorial() {
        const par = parSelect.value;
        const desde = desdeInput.value;
        const hasta = hastaInput.value;

        if (!par || !desde || !hasta) {
            actualizarResumenVacio();
            return;
        }

        if (!chartCanvas || typeof Chart === 'undefined') {
            return;
        }

        const url = `${historialConfig.apiUrl}?par=${encodeURIComponent(par)}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            if (!data || !Array.isArray(data.datos)) {
                throw new Error('Respuesta inválida del historial');
            }

            const rows = data.datos;
            updateAvailableDates(rows);

            tableBody.innerHTML = '';

            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }

            if (!rows.length) {
                actualizarResumenVacio();
                tableBody.innerHTML = '<tr><td colspan="6">No hay datos para este rango.</td></tr>';
                return;
            }

            rows.forEach((row) => {
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

            actualizarResumen(rows, par);

            const labels = rows.map((row) => row.fecha);
            const values = rows.map((row) => Number(row.precio_cierre ?? 0));
            const firstValue = Number(rows[0].precio_cierre ?? 0);
            const lastValue = Number(rows[rows.length - 1].precio_cierre ?? 0);
            const trendColor = lastValue >= firstValue ? '#7df0b0' : '#ff9a9f';

            chartInstance = new Chart(chartCanvas, {
                type: 'line',
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
            actualizarResumenVacio();
            tableBody.innerHTML = '<tr><td colspan="6">Error al cargar el historial.</td></tr>';
            console.error(error);
        }
    }

    buscarBtn.addEventListener('click', cargarHistorial);
    parSelect.addEventListener('change', cargarHistorial);
    desdeInput.addEventListener('change', cargarHistorial);
    hastaInput.addEventListener('change', cargarHistorial);
    initializeDatePickers();
    cargarHistorial();
});
