<?php
require_once __DIR__ . '/../app/models/ForecastMethods.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function series(callable $price, int $step = 1): array
{
    $rows = [];
    for ($i = 0; $i < 30; $i++) {
        $rows[] = ['fecha' => gmdate('Y-m-d', strtotime('2026-01-01 UTC') + $i * $step * 86400), 'precio_cierre' => $price($i * $step)];
    }
    return $rows;
}

foreach (ForecastMethods::METHODS as $method) {
    $result = ForecastMethods::project(series(fn($i) => 100), $method);
    check(count($result) === 7, "$method: seven daily predictions");
    check($result[0]['fecha'] === '2026-01-31' && $result[6]['fecha'] === '2026-02-06', "$method: calendar horizon");
    foreach ($result as $point) check(abs($point['precio'] - 100) < 0.00001, "$method: constant series");
    check(ForecastMethods::project(array_slice(series(fn($i) => 100), 0, 9), $method) === [], "$method: insufficient data");
    check(ForecastMethods::project(series(fn($i) => $i === 10 ? 0 : 100), $method) === [], "$method: invalid price");
}
$result = ForecastMethods::project(series(fn($i) => 100 + 2 * $i), 'lineal');
check(abs($result[6]['precio'] - 172) < 0.00001, 'Linear trend extrapolation');
$result = ForecastMethods::project(series(fn($i) => 100 + 2 * $i, 2), 'lineal');
check(abs($result[6]['precio'] - 230) < 0.00001, 'Regression uses actual calendar gaps');
$result = ForecastMethods::project(series(fn($i) => 100 * exp(0.01 * $i)), 'logaritmica');
check(abs($result[6]['precio'] - 100 * exp(0.36)) < 0.00001, 'Logarithmic exponential trend');
$result = ForecastMethods::project(series(fn($i) => 100 + 2 * $i), 'holt');
$increment1 = $result[1]['precio'] - $result[0]['precio'];
$increment2 = $result[2]['precio'] - $result[1]['precio'];
check($increment1 > 0 && abs($increment2 / $increment1 - 0.9) < 0.00001, 'Holt dampens future increments');
check(ForecastMethods::project(series(fn($i) => 100 - 3 * $i), 'lineal') === [], 'Nonpositive forecasts are unavailable');
try {
    ForecastMethods::project(series(fn($i) => 100), 'invalid');
    throw new RuntimeException('Unknown method accepted');
} catch (InvalidArgumentException $expected) {
    // Expected input rejection.
}
$stale = [];
for ($i = 0; $i < 30; $i++) {
    $stale[] = ['fecha' => gmdate('Y-m-d', strtotime('2026-08-23 UTC') + $i * 86400), 'precio_cierre' => 100 + 2 * $i];
}
foreach (ForecastMethods::METHODS as $method) {
    $future = ForecastMethods::project($stale, $method, 7, '2026-10-01', true);
    check(count($future) === 8, "$method: present plus seven future days");
    check($future[0]['fecha'] === '2026-10-01', "$method: present is today");
    check($future[1]['fecha'] === '2026-10-02' && $future[7]['fecha'] === '2026-10-08', "$method: future dates despite stale history");
}
$future = ForecastMethods::project($stale, 'lineal', 7, '2026-10-01', true);
check(abs($future[0]['precio'] - 178) < 0.00001 && abs($future[7]['precio'] - 192) < 0.00001, 'Elapsed days affect prices, not just labels');
$validation = ForecastMethods::calibrate(series(fn($i) => 100 + 2 * $i), 'lineal');
check($validation['evaluaciones'] >= 5, 'Temporal validation produces comparisons');
check($validation['error_mape'] < 0.00001, 'Linear validation matches held-out trend');
check($validation['error_base'] > $validation['error_mape'], 'Validation compares against persistence');
$validation = ForecastMethods::calibrate(array_slice(series(fn($i) => 100), 0, 10), 'holt');
check($validation['error_mape'] === null && $validation['evaluaciones'] === 0, 'Insufficient validation is not reported as zero error');
$regime = [];
for ($i = 0; $i < 120; $i++) {
    $regime[] = ['fecha' => gmdate('Y-m-d', strtotime('2026-01-01 UTC') + $i * 86400), 'precio_cierre' => $i < 60 ? 200 - $i : 140 + 2 * ($i - 60)];
}
$validation = ForecastMethods::calibrate($regime, 'lineal');
check($validation['ventana'] === 30, 'Calibration favors recent window after trend reversal');
$automatic = ForecastMethods::bestModel($regime, '2026-04-30');
check(in_array($automatic['metodo'], ForecastMethods::METHODS, true), 'Automatic selection uses an available model');
check(count($automatic['proyeccion']) === 8, 'Automatic selection includes today and seven future days');
foreach (ForecastMethods::METHODS as $method) {
    $candidate = ForecastMethods::calibrate($regime, $method);
    check($automatic['validacion']['error_mape'] <= $candidate['error_mape'], 'Automatic selection minimizes temporal validation error');
}
$short = array_slice(series(fn($i) => 100), 0, 10);
$automatic = ForecastMethods::bestModel($short, '2026-01-10');
check($automatic['validacion']['error_mape'] === null, 'Automatic selection does not invent validation for short history');
check(ForecastMethods::bestModel(array_slice($short, 0, 9), '2026-01-10') === [], 'Automatic selection rejects insufficient history');
echo "Forecast method checks passed.\n";
$probability = ForecastMethods::directionProbabilities(series(fn($i) => 100 + 2 * $i), 'lineal', 30, 172, 158);
check($probability['evaluaciones'] >= 10 && $probability['subida'] > 50, 'Upward projections favor upward probability');
check(abs($probability['subida'] + $probability['bajada'] - 100) < .001, 'Direction probabilities sum to 100');
$probability = ForecastMethods::directionProbabilities(series(fn($i) => 200 - 2 * $i), 'lineal', 30, 128, 142);
check($probability['bajada'] > 50 && $probability['bajada'] < 100, 'Downward forecasts favor decline without claiming certainty');
$probability = ForecastMethods::directionProbabilities(series(fn($i) => 100), 'holt', 30, 100, 100);
check($probability['subida'] === 50.0 && $probability['bajada'] === 50.0, 'Flat series balances probabilities');
$probability = ForecastMethods::directionProbabilities($short, 'lineal', 30, 100, 100);
check($probability['subida'] === null && $probability['bajada'] === null, 'Insufficient backtests do not invent probabilities');
echo "Direction probability checks passed.\n";
