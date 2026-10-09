<?php
// Included by ForecastRolesHttpTest.php only when --browser-fixture is requested.
if (PHP_SAPI !== 'cli' || !isset($pages, $db, $data)) { http_response_code(404); exit; }
$fixturePath = __DIR__ . '/../../public/theme-animation-check.html';
if (is_file($fixturePath)) throw new RuntimeException('The temporary browser page already exists.');
$history = [];
foreach ($pairs as $par) {
    $stmt = $db->prepare('SELECT fecha,precio_cierre FROM historico_cotizaciones WHERE par=? ORDER BY fecha DESC LIMIT 60');
    $stmt->execute([$par]);
    $history[$par] = ['datos' => array_reverse($stmt->fetchAll())];
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
// UI-only fixture: fetch and Chart are replaced. Genuine model forecasts are checked by the HTTP test.
$mock = '<script>window.fixtureErrors=[]; window.fixtureForecastMethods=[]; window.addEventListener("error", e=>window.fixtureErrors.push(e.message));
window.addEventListener("unhandledrejection", e=>window.fixtureErrors.push(String(e.reason)));
window.Chart=class {constructor(canvas,options){window.fixtureChart=options;} destroy(){window.fixtureChart=null;}};
window.fetch=async url=>{const u=new URL(url,parent.location.href); const requested=u.searchParams.get("metodo");
const forecasts=' . json_encode($data, $jsonFlags) . '; const history=' . json_encode($history, $jsonFlags) . ';
if(u.pathname.endsWith("pronosticos-data")){window.fixtureForecastMethods.push(requested);
if(window.fixtureApiError)return {ok:false,json:async()=>({error:"No se pudo calcular XGBoost. Vuelve a intentarlo."})};
for(const item of forecasts){if(requested!=="auto")item.metodo=requested;
if(window.fixtureDown){item.cambio_estimado=-2;item.tendencia="bajista";item.precio_estimado=item.precio_actual*.98;
item.proyeccion.forEach((point,i)=>point.precio=item.precio_actual*(1-.02*(i+1)/7));}}
return {ok:true,json:async()=>forecasts};}
if(u.pathname.endsWith("historial-data"))return {ok:true,json:async()=>history[u.searchParams.get("par")]};
return {ok:true,json:async()=>({estado:"ok",registros:180,ultima_fecha:"2026-10-08",hasta_solicitado:"2026-10-08"})};};</script>';
$documents = [];
foreach ($pages as $role => $html) {
    $html = str_replace('<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>', $mock, $html);
    $script = file_get_contents(__DIR__ . '/../../public/assets/js/forecast.js');
    $script = str_replace("} catch (error) {\n            if (request !== historyRequest)", "} catch (error) {\n            window.fixtureErrors.push(error.stack);\n            if (request !== historyRequest)", str_replace("\r\n", "\n", $script));
    $html = preg_replace_callback('~<script src="[^"]*assets/js/forecast\.js[^\"]*"></script>~', static fn() => '<script>' . $script . '</script>', $html);
    $documents[$role] = $html;
}
$harness = <<<'HTML'
<!doctype html><html lang="es"><meta charset="utf-8"><pre id="result">RUNNING</pre>
<script>
const documents=DOCUMENTS;
const results=[];
const assert=(condition,message)=>{if(!condition)throw Error(message);results.push(message);};
const tick=()=>new Promise(resolve=>setTimeout(resolve,30));
async function ready(frame){for(let i=0;i<50;i++){if(frame.contentWindow.fixtureChart)return;await tick();}throw Error('Chart initialization timeout: '+JSON.stringify(frame.contentWindow.fixtureErrors));}
(async()=>{try{
for(const role of ['user','admin']){
const frame=document.createElement('iframe');frame.style='width:1100px;height:900px';frame.srcdoc=documents[role];document.body.append(frame);
await ready(frame);const w=frame.contentWindow;const d=w.document;
assert(w.fixtureErrors.length===0,role+': initialization without JavaScript errors');
assert(w.fixtureChart.data.datasets[1].data.length===8,role+': seven-day projection present');
assert(d.querySelectorAll('.probability-up').length===4&&d.querySelectorAll('.probability-down').length===4,role+': highlighted probabilities for four assets');
if(role==='user')assert(!d.getElementById('forecastMethod')&&!d.getElementById('forecastSyncHistory')&&!d.getElementById('forecastModelDetails'),'user: technical blocks omitted');
for(const par of ['USD/PEN','EUR/USD','BTC/USD','ETH/USD']){
d.querySelector(`[data-par="${par}"]`).click();await ready(frame);
assert(d.getElementById('forecastHistoryTitle').textContent.includes(par),role+': chart switches to '+par);
assert(w.fixtureChart.data.datasets[0].label.includes(par.split('/')[1]),role+': correct currency '+par);
assert(w.fixtureChart.data.datasets[1].data.length===8,role+': projection displayed '+par);
const tooltip=w.fixtureChart.options.plugins.tooltip;
const point=w.fixtureChart.data.datasets[1].data[1];
assert(tooltip.callbacks.label({datasetIndex:1,parsed:point}).includes('Precio pronosticado')&&tooltip.callbacks.label({datasetIndex:1,parsed:point}).includes(par.split('/')[1]),role+': tooltip price and currency '+par);
const details=tooltip.callbacks.afterBody().join(' ');
assert(details.includes('Subida:')&&details.includes('Bajada:')&&details.includes('objetivo de 7 días'),role+': tooltip probabilities specify seven-day horizon '+par);
assert(tooltip.bodyFont.size>=15&&tooltip.padding>=18,role+': larger tooltip '+par);
}
if(role==='admin'){
assert(d.querySelectorAll('[data-method]').length===5&&!d.querySelector('[data-method-unavailable]'),'admin: five available model buttons');
d.querySelector('[data-method="holt"]').click();await ready(frame);
assert(d.querySelector('[data-method="holt"]').getAttribute('aria-pressed')==='true','admin: model selection updates');
assert(w.fixtureChart.data.datasets[1].label.includes('Holt'),'admin: selected model reaches chart');
d.querySelector('[data-method="arima"]').click();await ready(frame);
assert(d.querySelector('[data-method="arima"]').getAttribute('aria-pressed')==='true'&&w.fixtureChart.data.datasets[1].label.includes('ARIMA (1,1,0)'),'admin: ARIMA selected and projected');
d.querySelector('[data-method="boosting"]').click();await ready(frame);
assert(d.querySelector('[data-method="boosting"]').getAttribute('aria-pressed')==='true'&&w.fixtureForecastMethods.at(-1)==='boosting','admin: XGBoost selection requests correct API method');
assert(w.fixtureChart.data.datasets[1].label.includes('XGBoost'),'admin: XGBoost label reaches chart');
d.querySelector('[data-method-help="boosting"]').click();
assert(d.getElementById('forecastModelDialog').open&&d.getElementById('forecastModelDialogText').textContent.includes('500 cierres reales')&&!d.getElementById('forecastModelDialogText').textContent.includes('Pendiente'),'admin: XGBoost help describes working model');
d.getElementById('forecastModelDialog').close();
d.getElementById('forecastSyncHistory').click();await tick();await ready(frame);
assert(d.getElementById('forecastSyncStatus').textContent.includes('USD/PEN')&&d.getElementById('forecastSyncStatus').textContent.includes('EUR/USD'),'admin: synchronization covers dollar and euro');
w.fixtureApiError=true;d.getElementById('forecastRefresh').click();await tick();await ready(frame);
assert(d.getElementById('forecastUpdated').textContent.includes('No se pudo calcular XGBoost'),'admin: API calculation errors remain readable');
assert(w.fixtureChart.data.datasets[1].data.length===0,'admin: failed request does not leave an obsolete projection');
}else{
assert(d.querySelectorAll('.forecast-item').length===4,'user: four clear prediction cards');
w.fixtureDown=true;d.getElementById('forecastRefresh').click();await tick();await ready(frame);
assert(d.getElementById('forecastDecision').textContent.includes('Ningún activo'),'user: no false upward suggestion when all decline');
}
assert(w.fixtureErrors.length===0,role+': interactions without JavaScript errors');
}
document.getElementById('result').textContent='PASS: '+results.join(' | ');
}catch(error){document.getElementById('result').textContent='FAIL: '+error.message;}})();
</script></html>
HTML;
$harness = str_replace('DOCUMENTS', json_encode($documents, $jsonFlags), $harness);
if (file_put_contents($fixturePath, $harness) === false) throw new RuntimeException('Cannot write browser fixture.');
