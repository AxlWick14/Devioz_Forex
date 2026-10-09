<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/XgboostForecast.php';
function checkXgboost(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$today = (new DateTimeImmutable('today', new DateTimeZone('America/Lima')))->format('Y-m-d');
$db = (new Database())->getConnection();
$histories = [];
foreach (['USD/PEN', 'EUR/USD', 'BTC/USD', 'ETH/USD'] as $pair) {
    $statement = $db->prepare('SELECT par,fecha,precio_cierre FROM historico_cotizaciones WHERE par=? AND fecha<? ORDER BY fecha DESC LIMIT 600');
    $statement->execute([$pair, $today]);
    $histories[$pair] = array_reverse($statement->fetchAll());
}
$results = XgboostForecast::prepare($histories, $today);
checkXgboost(count($results) === 4, 'Real engine must return four assets');
$snapshots = [];
foreach ($results as $pair => $result) {
    checkXgboost(count($result['proyeccion']) === 8, "$pair: missing present/future projection");
    checkXgboost($result['observaciones'] === 500, "$pair: real training window expected");
    $path = __DIR__ . '/../storage/ml/xgboost-forecast-' . str_replace('/', '-', $pair) . '.json';
    $snapshots[$pair] = ['path' => $path, 'content' => file_get_contents($path)];
}
$cached = XgboostForecast::prepare($histories, $today);
checkXgboost($cached === $results, 'Cache must reproduce genuine engine results');
foreach ($snapshots as $snapshot) checkXgboost(file_get_contents($snapshot['path']) === $snapshot['content'], 'Cache hit rewrote its artifact');

// A corrected close must invalidate the previous prediction, without writing to MySQL.
$pair = 'BTC/USD';
$changed = $histories[$pair];
$changed[array_key_last($changed)]['precio_cierre'] = (float) $changed[array_key_last($changed)]['precio_cierre'] * 1.01;
try {
    // A distinct as-of date keeps this artificial snapshot from matching live requests.
    $testDate = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
    $testBaseline = XgboostForecast::prepare([$pair => $histories[$pair]], $testDate);
    $testSnapshot = file_get_contents($snapshots[$pair]['path']);
    $corrected = XgboostForecast::prepare([$pair => $changed], $testDate);
    checkXgboost(isset($corrected[$pair]), 'Corrected history was rejected');
    checkXgboost(file_get_contents($snapshots[$pair]['path']) !== $testSnapshot, 'New close did not invalidate cache');
    checkXgboost($corrected[$pair]['proyeccion'] !== $testBaseline[$pair]['proyeccion'], 'Corrected price did not affect the prediction');
} finally {
    // Restore the real snapshot so the website retains its genuine database predictions.
    file_put_contents($snapshots[$pair]['path'], $snapshots[$pair]['content'], LOCK_EX);
}
$original = getenv('FOREX_ML_PYTHON');
try {
    putenv('FOREX_ML_PYTHON=' . __DIR__ . '/missing-xgboost-python.exe');
    try {
        XgboostForecast::prepare($histories, $today);
        throw new RuntimeException('Unavailable runtime was silently accepted');
    } catch (XgboostForecastException $expected) {
        checkXgboost(str_contains($expected->getMessage(), 'no está disponible'), 'Runtime failure message is unclear');
    }
} finally {
    if ($original === false) putenv('FOREX_ML_PYTHON');
    else putenv('FOREX_ML_PYTHON=' . $original);
}
try {
    XgboostForecast::prepare(['INVALID/PAIR' => []], $today);
    throw new RuntimeException('Unknown asset accepted');
} catch (XgboostForecastException $expected) {
    checkXgboost(str_contains($expected->getMessage(), 'inválido'), 'Unknown asset failure is unclear');
}
echo "XGBoost bridge checks passed: genuine batch, cache reuse, corrected closes, missing runtime and invalid asset.\n";
