<?php
require_once __DIR__ . '/../app/models/ForecastMethods.php';
function verifyArima(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

// Known ARIMA(1,1,0): delta(t) = 0.4 + 0.6 * delta(t-1).
$prices = [100.0];
$difference = 3.0;
for ($i = 1; $i < 60; $i++) {
    $prices[] = $prices[$i - 1] + $difference;
    $difference = 0.4 + 0.6 * $difference;
}
$fit = Arima::fit($prices);
verifyArima(abs($fit['phi'] - 0.6) < 1e-8 && abs($fit['constante'] - 0.4) < 1e-8, 'Recover known AR coefficient and constant');
verifyArima(abs($fit['deriva'] - 1) < 1e-8, 'Recover long-run drift');
$forecast = Arima::forecast($prices, 7);
$expected = $prices[array_key_last($prices)];
$lastDifference = $prices[count($prices) - 1] - $prices[count($prices) - 2];
for ($day = 1; $day <= 7; $day++) {
    $lastDifference = 0.4 + 0.6 * $lastDifference;
    $expected += $lastDifference;
    verifyArima(abs($forecast[$day] - $expected) < 1e-8, 'Recursive integrated forecast matches known process');
}
verifyArima(Arima::forecast(array_fill(0, 9, 100), 7) === [], 'Reject insufficient observations');
verifyArima(Arima::fit([100,100,100,100,100,100,100,100,INF,100]) === [], 'Reject invalid prices');
verifyArima(Arima::forecast(array_map(fn($i) => 100 - 9 * $i, range(0, 10)), 7) === [], 'Reject forecasts crossing zero');
$explosive = [100.0]; $delta = 1.0;
for ($i = 1; $i < 30; $i++) { $explosive[] = end($explosive) + $delta; $delta *= 1.1; }
verifyArima(abs(Arima::fit($explosive)['phi']) < 1, 'Differenced AR coefficient remains stationary');

$rows = [];
foreach ($prices as $index => $price) $rows[] = ['fecha' => gmdate('Y-m-d', strtotime('2026-01-01 UTC') + $index * 86400), 'precio_cierre' => $price];
$present = gmdate('Y-m-d', strtotime(end($rows)['fecha'] . ' UTC') + 3 * 86400);
$projection = ForecastMethods::project($rows, 'arima', 7, $present, true);
$extended = Arima::forecast($prices, 10);
verifyArima(count($projection) === 8 && $projection[0]['fecha'] === $present, 'Calendar dates include present despite stale history');
verifyArima(abs($projection[0]['precio'] - $extended[3]) < 1e-6 && abs($projection[7]['precio'] - $extended[10]) < 1e-6, 'Elapsed days change ARIMA prices as well as dates');
$probabilities = ForecastMethods::directionProbabilities($rows, 'arima', 30, $extended[7], end($prices));
verifyArima($probabilities['evaluaciones'] >= 10 && abs($probabilities['subida'] + $probabilities['bajada'] - 100) < .001, 'ARIMA integrates with directional probability calculation');
echo "ARIMA checks passed: fit, recursion, drift, validity, calendar horizon and probabilities.\n";
