<?php
declare(strict_types=1);

/** Conditional least-squares ARIMA(1,1,0) with drift on a daily price grid. */
final class Arima
{
    public static function fit(array $prices): array
    {
        if (count($prices) < 10) return [];
        foreach ($prices as $price) if (!is_numeric($price) || !is_finite((float) $price) || $price <= 0) return [];
        $differences = [];
        for ($i = 1; $i < count($prices); $i++) $differences[] = (float) $prices[$i] - (float) $prices[$i - 1];
        $lagged = array_slice($differences, 0, -1);
        $current = array_slice($differences, 1);
        $meanLagged = array_sum($lagged) / count($lagged);
        $meanCurrent = array_sum($current) / count($current);
        $numerator = $denominator = 0.0;
        foreach ($lagged as $i => $lag) {
            $numerator += ($lag - $meanLagged) * ($current[$i] - $meanCurrent);
            $denominator += ($lag - $meanLagged) ** 2;
        }
        // Bound the coefficient to keep the differenced process stationary.
        $phi = $denominator > 1e-18 ? max(-0.98, min(0.98, $numerator / $denominator)) : 0.0;
        $constant = $meanCurrent - $phi * $meanLagged;
        if (!is_finite($phi) || !is_finite($constant)) return [];
        return ['orden' => [1, 1, 0], 'phi' => $phi, 'constante' => $constant,
            'deriva' => $constant / (1 - $phi), 'ultimo_cambio' => $differences[array_key_last($differences)],
            'ultimo_precio' => (float) $prices[array_key_last($prices)]];
    }

    public static function forecast(array $prices, int $steps): array
    {
        if ($steps < 0 || $steps > 730) return [];
        $fit = self::fit($prices);
        if (!$fit) return [];
        $level = $fit['ultimo_precio'];
        $difference = $fit['ultimo_cambio'];
        $result = [$level];
        for ($day = 1; $day <= $steps; $day++) {
            $difference = $fit['constante'] + $fit['phi'] * $difference;
            $level += $difference;
            if (!is_finite($level) || $level <= 0) return [];
            $result[] = $level;
        }
        return $result;
    }
}
