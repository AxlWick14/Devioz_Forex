<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Pronostico.php';
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function verifyForecast(array $item, string $context): void {
    verify(in_array($item['metodo'], ForecastMethods::AVAILABLE_METHODS, true), "$context: unknown forecast method");
    verify(count($item['proyeccion']) === 7 && (int) $item['horizonte_dias'] === 7, "$context: invalid forecast horizon");
    $present = DateTimeImmutable::createFromFormat('!Y-m-d', $item['fecha_presente'], new DateTimeZone('UTC'));
    verify($present !== false, "$context: invalid present date");
    foreach ($item['proyeccion'] as $index => $point) {
        verify($point['fecha'] === $present->modify('+' . ($index + 1) . ' days')->format('Y-m-d'), "$context: nonconsecutive forecast dates");
        verify(is_numeric($point['precio']) && is_finite((float) $point['precio']) && $point['precio'] > 0, "$context: invalid forecast price");
    }
    verify($item['precio_estimado'] === $item['proyeccion'][6]['precio'], "$context: seven-day target differs from projection");
    $probability = $item['probabilidades'];
    if ($probability['evaluaciones'] >= 10) {
        verify($probability['subida'] > 0 && $probability['bajada'] > 0 && abs($probability['subida'] + $probability['bajada'] - 100) < .001, "$context: invalid probabilities");
    } else verify($probability['subida'] === null && $probability['bajada'] === null, "$context: invented probabilities");
}
$db = (new Database())->getConnection();
$nonce = bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(12));
$ids = $clients = $pages = [];
$pairs = ['USD/PEN', 'EUR/USD', 'BTC/USD', 'ETH/USD'];
function fetchPage(CurlHandle $client, string $route, ?array $post = null): array {
    curl_setopt_array($client, [CURLOPT_URL => 'http://localhost/forex_system_realtime/public/' . $route,
        CURLOPT_POST => $post !== null, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
    if ($post !== null) curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($post));
    else curl_setopt($client, CURLOPT_HTTPGET, true);
    $body = curl_exec($client);
    if ($body === false) throw new RuntimeException(curl_error($client));
    return ['body' => $body, 'status' => curl_getinfo($client, CURLINFO_HTTP_CODE)];
}
try {
    foreach (['user', 'admin'] as $role) {
        $email = "$role-forecast-$nonce@example.test";
        $db->prepare('INSERT INTO usuarios (nombre,email,password_hash,role,is_active) VALUES (?,?,?,?,1)')->execute(['Forecast test', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $ids[] = (int) $db->lastInsertId();
        $client = curl_init(); $clients[] = $client;
        curl_setopt_array($client, [CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false]);
        $login = fetchPage($client, 'login');
        verify((bool) preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $match), 'Missing login token');
        verify(fetchPage($client, 'login', ['csrf' => $match[1], 'email' => $email, 'password' => $password])['status'] === 303, 'Login failed');
        $page = fetchPage($client, 'pronosticos');
        verify($page['status'] === 200, "$role: forecast page inaccessible");
        $pages[$role] = $page['body'];
        foreach ($pairs as $par) verify(str_contains($page['body'], 'data-par="' . $par . '"'), "$role: missing asset $par");
        foreach (['id="forecastMethod"', 'id="forecastSyncHistory"', 'id="forecastModelDetails"', 'id="forecastModelDialog"', 'id="forecastSignals"'] as $control)
            verify(str_contains($page['body'], $control) === ($role === 'admin'), "$role: incorrect technical controls $control");
        if ($role === 'admin') {
            foreach (ForecastMethods::AVAILABLE_METHODS as $method)
                verify(str_contains($page['body'], 'data-method="' . $method . '"'), "admin: missing selectable model $method");
            verify(!str_contains($page['body'], 'data-method-unavailable'), 'Administrator still has unavailable models');
        }
        $automatic = fetchPage($client, 'pronosticos-data?metodo=auto');
        verify($automatic['status'] === 200, "$role: automatic API failed");
        $data = json_decode($automatic['body'], true, 512, JSON_THROW_ON_ERROR);
        verify(count($data) === 4, "$role: four forecasts expected with local history");
        foreach ($pairs as $par) {
            $item = array_values(array_filter($data, fn($item) => $item['par'] === $par))[0] ?? null;
            verify($item !== null, "$role: missing prediction $par");
            verifyForecast($item, "$role/$par/auto");
            $expected = (new Pronostico())->generarPronostico($par, 'auto');
            verify($item['metodo'] === $expected['metodo'], "$role: wrong automatic model $par");
        }
        foreach (ForecastMethods::AVAILABLE_METHODS as $method) {
            $response = fetchPage($client, 'pronosticos-data?metodo=' . $method);
            verify($response['status'] === 200, "$role: method request failed");
            $predictions = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            verify(count($predictions) === 4, "$role: missing forecasts for $method");
            if ($role === 'user') verify($predictions === $data, 'User must receive automatic selection even when specifying a method');
            foreach ($predictions as $item) {
                verifyForecast($item, "$role/{$item['par']}/$method");
                if ($role === 'admin') {
                    verify($item['metodo'] === $method, 'Administrator model choice not respected');
                    if ($method === 'boosting') {
                        verify($item['observaciones'] >= 120 && $item['observaciones'] <= 500, 'XGBoost did not use its training window');
                        verify($item['validacion']['evaluaciones'] >= 5 && is_numeric($item['validacion']['error_mape']), 'XGBoost lacks temporal validation');
                        verify($item['probabilidades']['evaluaciones'] >= 10, 'XGBoost lacks historical probability scenarios');
                    }
                }
            }
        }
        verify(fetchPage($client, 'pronosticos-data?metodo=invalid')['status'] === 400, 'Invalid model accepted');
    }
    if (in_array('--browser-fixture', $argv, true)) {
        require __DIR__ . '/fixtures/forecast-browser.php';
    }
    echo "Forecast roles HTTP checks passed: four assets, five available models including XGBoost, seven-day prices/probabilities, user automatic model and admin selector.\n";
} finally {
    foreach ($ids as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
    foreach ($clients as $client) curl_close($client);
}
