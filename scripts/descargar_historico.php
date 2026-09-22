<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$assets = [
    'EUR/USD' => '2000-01-01',
    'GBP/USD' => '2000-01-01',
    'USD/PEN' => '2000-01-01',
    'BTC/USD' => '2012-01-01',
    'XRP/USD' => '2013-01-01',
    'ETH/USD' => '2015-01-01',
    'SOL/USD' => '2020-01-01',
];

$endDate = date('Y-m-d');
$database = new Database();
$connection = $database->getConnection();

$candleStatement = $connection->prepare('
    INSERT INTO velas_historicas
        (par, intervalo, fecha_hora, precio_apertura, precio_maximo,
         precio_minimo, precio_cierre, volumen, fuente)
    VALUES
        (:par, "1day", :fecha_hora, :open, :high, :low, :close, :volume, "Twelve Data")
    ON DUPLICATE KEY UPDATE
        precio_apertura = VALUES(precio_apertura),
        precio_maximo = VALUES(precio_maximo),
        precio_minimo = VALUES(precio_minimo),
        precio_cierre = VALUES(precio_cierre),
        volumen = VALUES(volumen)');

$historyStatement = $connection->prepare('
    INSERT INTO historico_cotizaciones
        (par, fecha, precio_apertura, precio_maximo, precio_minimo,
         precio_cierre, cambio_porcentual, fuente)
    VALUES
        (:par, :fecha, :open, :high, :low, :close, :change, "Twelve Data")
    ON DUPLICATE KEY UPDATE
        precio_apertura = VALUES(precio_apertura),
        precio_maximo = VALUES(precio_maximo),
        precio_minimo = VALUES(precio_minimo),
        precio_cierre = VALUES(precio_cierre),
        cambio_porcentual = VALUES(cambio_porcentual)');

foreach ($assets as $symbol => $firstDate) {
    $from = new DateTimeImmutable($firstDate);
    $last = new DateTimeImmutable($endDate);
    echo "Descargando {$symbol} desde {$firstDate}...\n";

    while ($from <= $last) {
        $to = $from->modify('+1 year -1 day');
        if ($to > $last) {
            $to = $last;
        }

        $query = http_build_query([
            'symbol' => $symbol,
            'interval' => '1day',
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $to->format('Y-m-d'),
            'order' => 'ASC',
            'outputsize' => 5000,
            'apikey' => TWELVE_DATA_API_KEY,
        ]);
        $curl = curl_init(TWELVE_DATA_BASE_URL . '/time_series?' . $query);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $httpCode !== 200) {
            $errorPayload = json_decode((string) $response, true);
            $apiMessage = is_array($errorPayload) ? ($errorPayload['message'] ?? '') : '';
            $detail = $apiMessage !== '' ? $apiMessage : ($curlError !== '' ? $curlError : 'respuesta vacía');
            fwrite(STDERR, "Error HTTP {$httpCode} en {$symbol} ({$from->format('Y')}): {$detail}\n");
            break;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || isset($payload['code']) || !isset($payload['values'])) {
            $message = $payload['message'] ?? 'Respuesta sin datos';
            fwrite(STDERR, "{$symbol} ({$from->format('Y')}): {$message}\n");
            break;
        }

        $connection->beginTransaction();
        try {
            foreach ($payload['values'] as $value) {
                if (!isset($value['datetime'], $value['open'], $value['high'], $value['low'], $value['close'])) {
                    continue;
                }

                $open = (float) $value['open'];
                $close = (float) $value['close'];
                $change = $open !== 0.0 ? (($close - $open) / $open) * 100 : null;
                $dateTime = date_create((string) $value['datetime']);
                if (!$dateTime) {
                    continue;
                }

                $params = [
                    ':par' => $symbol,
                    ':fecha_hora' => $dateTime->format('Y-m-d H:i:s'),
                    ':open' => $open,
                    ':high' => (float) $value['high'],
                    ':low' => (float) $value['low'],
                    ':close' => $close,
                    ':volume' => isset($value['volume']) && is_numeric($value['volume'])
                        ? (float) $value['volume']
                        : null,
                ];
                $candleStatement->execute($params);
                $historyStatement->execute([
                    ':par' => $symbol,
                    ':fecha' => $dateTime->format('Y-m-d'),
                    ':open' => $open,
                    ':high' => (float) $value['high'],
                    ':low' => (float) $value['low'],
                    ':close' => $close,
                    ':change' => $change,
                ]);
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        echo "  {$from->format('Y-m-d')} a {$to->format('Y-m-d')}: " . count($payload['values']) . " velas\n";
        $from = $to->modify('+1 day');
    }
}

echo "Descarga finalizada.\n";