<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Database.php';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/importar_historico.php archivo.csv EUR/USD [fuente] [intervalo]\n");
    exit(1);
}

$filePath = $argv[1];
$par = strtoupper(trim($argv[2]));
$source = $argv[3] ?? 'CSV';
$interval = $argv[4] ?? '1day';

if (!is_file($filePath) || !is_readable($filePath)) {
    fwrite(STDERR, "No se puede leer el archivo: {$filePath}\n");
    exit(1);
}

$handle = fopen($filePath, 'rb');
$headers = fgetcsv($handle);

if ($headers === false) {
    fclose($handle);
    fwrite(STDERR, "El CSV está vacío.\n");
    exit(1);
}

$headers = array_map(static fn($header): string => strtolower(trim((string) $header)), $headers);
$aliases = [
    'datetime' => ['datetime', 'date', 'timestamp', 'time'],
    'open' => ['open', 'price_open', 'precio_apertura'],
    'high' => ['high', 'price_high', 'precio_maximo'],
    'low' => ['low', 'price_low', 'precio_minimo'],
    'close' => ['close', 'price_close', 'precio_cierre'],
    'volume' => ['volume', 'volumen'],
];

$indexes = [];
foreach ($aliases as $name => $possibleHeaders) {
    foreach ($possibleHeaders as $possibleHeader) {
        $index = array_search($possibleHeader, $headers, true);
        if ($index !== false) {
            $indexes[$name] = $index;
            break;
        }
    }
}

foreach (['datetime', 'open', 'high', 'low', 'close'] as $required) {
    if (!array_key_exists($required, $indexes)) {
        fclose($handle);
        fwrite(STDERR, "Falta la columna requerida: {$required}\n");
        exit(1);
    }
}

$database = new Database();
$connection = $database->getConnection();
$sql = '
    INSERT INTO velas_historicas
        (par, intervalo, fecha_hora, precio_apertura, precio_maximo,
         precio_minimo, precio_cierre, volumen, fuente)
    VALUES
        (:par, :intervalo, :fecha_hora, :open, :high, :low, :close, :volume, :fuente)
    ON DUPLICATE KEY UPDATE
        precio_apertura = VALUES(precio_apertura),
        precio_maximo = VALUES(precio_maximo),
        precio_minimo = VALUES(precio_minimo),
        precio_cierre = VALUES(precio_cierre),
        volumen = VALUES(volumen),
        fuente = VALUES(fuente)';
$statement = $connection->prepare($sql);
$historySql = '
    INSERT INTO historico_cotizaciones
        (par, fecha, precio_apertura, precio_maximo, precio_minimo,
         precio_cierre, cambio_porcentual, fuente)
    VALUES
        (:par, :fecha, :open, :high, :low, :close, :change, :fuente)
    ON DUPLICATE KEY UPDATE
        precio_apertura = VALUES(precio_apertura),
        precio_maximo = VALUES(precio_maximo),
        precio_minimo = VALUES(precio_minimo),
        precio_cierre = VALUES(precio_cierre),
        cambio_porcentual = VALUES(cambio_porcentual),
        fuente = VALUES(fuente)';
$historyStatement = $connection->prepare($historySql);

$inserted = 0;
$skipped = 0;
$connection->beginTransaction();

try {
    while (($row = fgetcsv($handle)) !== false) {
        $dateValue = trim((string) ($row[$indexes['datetime']] ?? ''));
        $dateTime = date_create($dateValue);
        $open = $row[$indexes['open']] ?? null;
        $high = $row[$indexes['high']] ?? null;
        $low = $row[$indexes['low']] ?? null;
        $close = $row[$indexes['close']] ?? null;

        if (!$dateTime || !is_numeric($open) || !is_numeric($high) || !is_numeric($low) || !is_numeric($close)) {
            $skipped++;
            continue;
        }

        $volume = array_key_exists('volume', $indexes) ? ($row[$indexes['volume']] ?? null) : null;
        $change = ((float) $open) !== 0.0
            ? (((float) $close - (float) $open) / (float) $open) * 100
            : null;
        $statement->execute([
            ':par' => $par,
            ':intervalo' => $interval,
            ':fecha_hora' => $dateTime->format('Y-m-d H:i:s'),
            ':open' => (float) $open,
            ':high' => (float) $high,
            ':low' => (float) $low,
            ':close' => (float) $close,
            ':volume' => is_numeric($volume) ? (float) $volume : null,
            ':fuente' => $source,
        ]);
        $historyStatement->execute([
            ':par' => $par,
            ':fecha' => $dateTime->format('Y-m-d'),
            ':open' => (float) $open,
            ':high' => (float) $high,
            ':low' => (float) $low,
            ':close' => (float) $close,
            ':change' => $change,
            ':fuente' => $source,
        ]);
        $inserted++;
    }

    $connection->commit();
    fclose($handle);
    echo "Filas procesadas: {$inserted}; filas omitidas: {$skipped}\n";
} catch (Throwable $exception) {
    $connection->rollBack();
    fclose($handle);
    fwrite(STDERR, "Error durante la importación: {$exception->getMessage()}\n");
    exit(1);
}