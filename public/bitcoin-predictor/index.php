<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba Bitcoin · ExtraTrees</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="page-shell"><section class="container panel">
    <h1>Prueba de predicción Bitcoin</h1>
    <p>ExtraTrees · BTC/USD · Dirección del cierre del día siguiente al último cierre del histórico.</p>
    <p>Esta prueba usa el CSV exportado. No calcula un precio objetivo a siete días.</p>
    <button type="button" id="predict" class="btn primary">Obtener predicción</button>
    <p id="status" role="status" aria-live="polite">Entrena el modelo e inicia Python para consultar.</p>
    <pre id="result" style="white-space:pre-wrap" hidden></pre>
</section></main>
<script>
const button = document.getElementById('predict');
const status = document.getElementById('status');
const result = document.getElementById('result');
button.addEventListener('click', async () => {
    button.disabled = true;
    result.hidden = true;
    status.textContent = 'Consultando el modelo…';
    try {
        const response = await fetch('obtener_prediccion.php', {cache: 'no-store'});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo obtener la predicción.');
        status.textContent = data.datos_desactualizados ? 'Histórico desactualizado: la fecha predicha ya pasó. Exporta datos recientes y reentrena.' : 'Predicción calculada.';
        result.textContent = `Modelo: ${data.modelo}\nÚltimo cierre (${data.fecha_base}): ${data.precio_cierre} USD\nFecha predicha: ${data.fecha_prediccion}\nSeñal: ${data.prediccion}\nProbabilidad SUBE: ${data.probabilidad_sube}%\nProbabilidad BAJA o IGUAL: ${data.probabilidad_baja_o_igual}%\n\nEvaluación temporal (${data.validacion.prueba} observaciones):\nExactitud: ${data.validacion.accuracy}%\nReferencia (clase mayoritaria): ${data.validacion.baseline_accuracy}%\nExactitud balanceada: ${data.validacion.balanced_accuracy}%\n\n${data.nota}`;
        result.hidden = false;
    } catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
});
</script>
</body></html>
