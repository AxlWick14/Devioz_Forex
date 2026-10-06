<?php

/** Pure forecasting calculations, independent of the database. */
final class ForecastMethods
{
    public const METHODS = ['lineal', 'logaritmica', 'holt'];

    public static function calibrate(array $rows, string $method): array
    {
        usort($rows, static fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        $best = null;
        // Each prediction sees only closes available before its validation date.
        foreach ([30, 60, 120] as $window) {
            $errors = $baseline = [];
            for ($index = max(10, count($rows) - 21); $index < count($rows); $index++) {
                $training = array_slice($rows, max(0, $index - $window), min($index, $window));
                $last = $training[array_key_last($training)];
                $steps = (int) round((strtotime($rows[$index]['fecha']) - strtotime($last['fecha'])) / 86400);
                if ($steps < 1 || $steps > 7) continue;
                $points = self::project($training, $method, 7);
                if (!$points) continue;
                // Evaluate all available closes up to seven calendar days ahead.
                for ($target = $index; $target < count($rows); $target++) {
                    $days = (int) round((strtotime($rows[$target]['fecha']) - strtotime($last['fecha'])) / 86400);
                    if ($days > 7) break;
                    if ($days < 1) continue;
                    $actual = (float) $rows[$target]['precio_cierre'];
                    if ($actual <= 0) continue;
                    $errors[] = abs($points[$days - 1]['precio'] / $actual - 1) * 100;
                    $baseline[] = abs((float) $last['precio_cierre'] / $actual - 1) * 100;
                }
            }
            if (count($errors) < 5) continue;
            $error = array_sum($errors) / count($errors);
            if ($best === null || $error < $best['error_mape']) {
                $best = ['ventana' => $window, 'error_mape' => $error,
                    'error_base' => array_sum($baseline) / count($baseline), 'evaluaciones' => count($errors)];
            }
        }
        return $best ?? ['ventana' => 120, 'error_mape' => null, 'error_base' => null, 'evaluaciones' => 0];
    }

    public static function project(array $rows, string $method, int $horizon = 7, ?string $asOf = null, bool $includePresent = false): array
    {
        if (!in_array($method, self::METHODS, true) || $horizon < 1 || $horizon > 30) {
            throw new InvalidArgumentException('Método u horizonte inválido.');
        }
        $observations = [];
        foreach ($rows as $row) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) ($row['fecha'] ?? ''), 0, 10), new DateTimeZone('UTC'));
            $price = (float) ($row['precio_cierre'] ?? 0);
            if (!$date || $date->format('Y-m-d') !== substr((string) $row['fecha'], 0, 10) || !is_finite($price) || $price <= 0) {
                return [];
            }
            $observations[$date->getTimestamp()] = $price;
        }
        ksort($observations);
        if (count($observations) < 10) return [];

        $times = array_keys($observations);
        $prices = array_values($observations);
        $origin = $times[0];
        $lastTime = $times[count($times) - 1];
        $startTime = $lastTime;
        if ($asOf !== null) {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $asOf, new DateTimeZone('UTC'));
            if (!$start || $start->format('Y-m-d') !== $asOf || $start->getTimestamp() < $lastTime) {
                throw new InvalidArgumentException('Fecha de proyección inválida.');
            }
            $startTime = $start->getTimestamp();
        }
        $gap = (int) (($startTime - $lastTime) / 86400);
        $x = array_map(static fn($time) => ($time - $origin) / 86400, $times);
        $lastX = $x[count($x) - 1];
        if ($lastX > 730) return []; // Avoid treating very sparse archives as a recent series.

        if ($method === 'holt') {
            // Daily grid: missing closes carry forward; these are not new observations.
            $level = $prices[0];
            $trend = ($prices[1] - $prices[0]) / ($x[1] - $x[0]);
            $previous = $level;
            for ($time = $origin + 86400; $time <= $lastTime; $time += 86400) {
                $price = $observations[$time] ?? $previous;
                $oldLevel = $level;
                $level = 0.4 * $price + 0.6 * ($level + 0.9 * $trend);
                $trend = 0.2 * ($level - $oldLevel) + 0.8 * 0.9 * $trend;
                $previous = $price;
            }
        } else {
            $values = $method === 'logaritmica' ? array_map('log', $prices) : $prices;
            $meanX = array_sum($x) / count($x);
            $meanY = array_sum($values) / count($values);
            $numerator = $denominator = 0.0;
            foreach ($x as $index => $value) {
                $numerator += ($value - $meanX) * ($values[$index] - $meanY);
                $denominator += ($value - $meanX) ** 2;
            }
            if ($denominator <= 0) return [];
            $slope = $numerator / $denominator;
            $intercept = $meanY - $slope * $meanX;
        }

        $result = [];
        for ($day = $includePresent ? 0 : 1; $day <= $horizon; $day++) {
            $steps = $gap + $day;
            if ($method === 'holt') {
                $damping = 0.9 * (1 - 0.9 ** $steps) / (1 - 0.9);
                $prediction = $level + $damping * $trend;
            } else {
                $prediction = $intercept + $slope * ($lastX + $steps);
                if ($method === 'logaritmica') $prediction = exp($prediction);
            }
            if ($steps === 0) $prediction = $prices[count($prices) - 1];
            if (!is_finite($prediction) || $prediction <= 0) return [];
            $result[] = ['fecha' => gmdate('Y-m-d', $startTime + $day * 86400), 'precio' => round($prediction, 6)];
        }
        return $result;
    }
}
