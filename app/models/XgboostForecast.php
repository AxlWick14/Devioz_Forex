<?php
declare(strict_types=1);

final class XgboostForecastException extends RuntimeException {}

/** One private Python batch per changed historical snapshot, never per chart point. */
final class XgboostForecast
{
    private const PAIRS = ['USD/PEN', 'EUR/USD', 'BTC/USD', 'ETH/USD'];
    private const TIMEOUT = 75;
    private const LOCK_TIMEOUT = 15;

    public static function prepare(array $histories, string $asOf): array
    {
        $root = dirname(__DIR__, 2);
        $engine = $root . '/scripts/ml/xgboost_forecast.py';
        $configured = getenv('FOREX_ML_PYTHON');
        $python = $configured ?: $root . '/scripts/ml/.python/python.exe';
        if (!$configured && !is_file($python)) $python = $root . '/scripts/ml/.venv/Scripts/python.exe';
        if (!$configured && !is_file($python)) $python = $root . '/scripts/ml/.venv/bin/python';
        if (!is_file($python) || !is_file($engine) || !function_exists('proc_open')) {
            throw new XgboostForecastException('XGBoost no está disponible en el servidor.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $asOf, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $asOf) {
            throw new XgboostForecastException('La fecha del pronóstico XGBoost es inválida.');
        }
        $directory = $root . '/storage/ml';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new XgboostForecastException('No se pudo preparar el almacenamiento de XGBoost.');
        }
        $fingerprints = $paths = $assets = [];
        foreach ($histories as $pair => $rows) {
            if (!in_array($pair, self::PAIRS, true)) {
                throw new XgboostForecastException('Activo XGBoost inválido.');
            }
            $assets[] = ['par' => $pair, 'rows' => array_values($rows)];
            $fingerprints[$pair] = hash('sha256', json_encode([$asOf, $python,
                hash_file('sha256', $engine), hash_file('sha256', $root . '/scripts/ml/requirements.txt'),
                $rows], JSON_THROW_ON_ERROR));
            $paths[$pair] = $directory . '/xgboost-forecast-' . str_replace('/', '-', $pair) . '.json';
        }
        $read = static function () use ($paths, $fingerprints, $asOf): array {
            $cached = [];
            foreach ($paths as $pair => $path) {
                if (!is_file($path)) continue;
                $entry = json_decode((string) @file_get_contents($path), true);
                if (is_array($entry) && ($entry['fingerprint'] ?? null) === $fingerprints[$pair]
                    && self::validResult($entry['data'] ?? null, $asOf)) $cached[$pair] = $entry['data'];
            }
            return $cached;
        };
        $results = $read();
        if (count($results) === count($assets)) return $results;

        // Serialize retraining across requests and recheck the cache after acquiring the lock.
        $lock = @fopen($directory . '/xgboost-forecast.lock', 'c');
        if (!$lock) throw new XgboostForecastException('No se pudo iniciar XGBoost.');
        $started = microtime(true);
        try {
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) - $started > self::LOCK_TIMEOUT) {
                    throw new XgboostForecastException('XGBoost está actualizando sus resultados. Inténtalo de nuevo.');
                }
                usleep(100000);
            }
            $results = $read();
            $pending = array_values(array_filter($assets, static fn($asset) => !isset($results[$asset['par']])));
            if (!$pending) return $results;
            $output = self::run($python, $engine, $root, $directory, ['as_of' => $asOf, 'assets' => $pending]);
            foreach ($pending as $asset) {
                $pair = $asset['par'];
                $result = $output['results'][$pair] ?? null;
                if (!self::validResult($result, $asOf)) {
                    $message = $output['errors'][$pair] ?? 'Resultado de XGBoost inválido.';
                    error_log('XGBoost ' . $pair . ': ' . substr((string) $message, 0, 500));
                    continue;
                }
                $results[$pair] = $result;
                $temporary = $paths[$pair] . '.' . bin2hex(random_bytes(6)) . '.tmp';
                try {
                    $encoded = json_encode(['fingerprint' => $fingerprints[$pair], 'data' => $result], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                    if (@file_put_contents($temporary, $encoded, LOCK_EX) === false || !@rename($temporary, $paths[$pair])) {
                        throw new XgboostForecastException('No se pudo guardar el resultado de XGBoost.');
                    }
                } finally {
                    if (is_file($temporary)) unlink($temporary);
                }
            }
            return $results;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function run(string $python, string $engine, string $root, string $directory, array $request): array
    {
        $prefix = $directory . '/xgboost-job-' . bin2hex(random_bytes(10));
        $input = $prefix . '.input';
        $output = $prefix . '.output';
        $errors = $prefix . '.errors';
        $process = null;
        try {
            if (@file_put_contents($input, json_encode($request, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new XgboostForecastException('No se pudo preparar el histórico para XGBoost.');
            }
            // File descriptors avoid Windows anonymous-pipe blocking; argv avoids shell interpolation.
            $process = @proc_open([$python, $engine], [0 => ['file', $input, 'r'],
                1 => ['file', $output, 'w'], 2 => ['file', $errors, 'w']], $pipes, $root, null, ['bypass_shell' => true]);
            if (!is_resource($process)) throw new XgboostForecastException('No se pudo iniciar el motor XGBoost.');
            $started = microtime(true);
            do {
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true) - $started > self::TIMEOUT) {
                    proc_terminate($process);
                    throw new XgboostForecastException('XGBoost tardó demasiado en responder. Inténtalo de nuevo.');
                }
                usleep(100000);
            } while (true);
            $exit = $status['exitcode'];
            proc_close($process);
            $process = null;
            if ($exit !== 0) {
                error_log('XGBoost: ' . substr((string) @file_get_contents($errors), 0, 2000));
                throw new XgboostForecastException('No se pudo calcular XGBoost. Comprueba las dependencias del servidor.');
            }
            $data = json_decode((string) @file_get_contents($output), true);
            if (!is_array($data) || !is_array($data['results'] ?? null) || !is_array($data['errors'] ?? null)) {
                throw new XgboostForecastException('El motor XGBoost devolvió una respuesta inválida.');
            }
            return $data;
        } finally {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            foreach ([$input, $output, $errors] as $path) if (is_file($path)) unlink($path);
        }
    }

    private static function validResult(mixed $data, string $asOf): bool
    {
        if (!is_array($data) || !is_array($data['proyeccion'] ?? null)
            || !array_is_list($data['proyeccion']) || count($data['proyeccion']) !== 8) return false;
        $start = strtotime($asOf . ' UTC');
        foreach ($data['proyeccion'] as $index => $point) {
            if (!is_array($point) || !is_int($index)) return false;
            $price = $point['precio'] ?? null;
            if (!is_numeric($price) || !is_finite((float) $price) || $price <= 0
                || ($point['fecha'] ?? '') !== gmdate('Y-m-d', $start + $index * 86400)) return false;
        }
        $validation = $data['validacion'] ?? [];
        if (!is_array($validation) || !is_int($validation['evaluaciones'] ?? null)
            || !is_int($data['observaciones'] ?? null)) return false;
        foreach (['error_mape', 'error_base'] as $key) {
            if (!is_numeric($validation[$key] ?? null) || !is_finite((float) $validation[$key]) || $validation[$key] < 0) return false;
        }
        if (($validation['ventana'] ?? 0) !== 500 || ($validation['evaluaciones'] ?? 0) < 5
            || ($data['observaciones'] ?? 0) < 120 || $data['observaciones'] > 500) return false;
        $probabilities = $data['probabilidades'] ?? [];
        if (!is_array($probabilities) || !array_key_exists('subida', $probabilities) || !array_key_exists('bajada', $probabilities)
            || !is_int($probabilities['evaluaciones'] ?? null) || $probabilities['evaluaciones'] < 0) return false;
        if ($probabilities['subida'] === null && $probabilities['bajada'] === null) return $probabilities['evaluaciones'] < 10;
        foreach (['subida', 'bajada'] as $key) {
            if (!is_numeric($probabilities[$key]) || !is_finite((float) $probabilities[$key])
                || $probabilities[$key] < 0 || $probabilities[$key] > 100) return false;
        }
        return $probabilities['evaluaciones'] >= 10 && abs($probabilities['subida'] + $probabilities['bajada'] - 100) < 0.01;
    }
}
