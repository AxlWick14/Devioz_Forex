<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';
try {
    $today = (new DateTimeImmutable('today', new DateTimeZone('America/Lima')))->format('Y-m-d');
    $statement = (new Database())->getConnection()->prepare('
        SELECT par, fecha, precio_apertura, precio_maximo, precio_minimo, precio_cierre, cambio_porcentual, fuente
        FROM historico_cotizaciones WHERE par = :par AND fecha < :hoy ORDER BY fecha ASC');
    $statement->execute([':par' => 'BTC/USD', ':hoy' => $today]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < 250) throw new RuntimeException('Esta prueba requiere al menos 250 cierres BTC/USD; disponibles: ' . count($rows));
    $directory = __DIR__ . '/../storage/ml';
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) throw new RuntimeException('No se pudo crear storage/ml.');
    $temporary = $directory . '/btc.csv.tmp';
    $file = fopen($temporary, 'wb');
    if (!$file) throw new RuntimeException('No se pudo abrir el CSV.');
    try {
        fputcsv($file, array_keys($rows[0]), ';', '"', '');
        foreach ($rows as $row) fputcsv($file, array_values($row), ';', '"', '');
    } finally { fclose($file); }
    if (!rename($temporary, $directory . '/btc.csv')) throw new RuntimeException('No se pudo guardar el CSV.');
    echo 'Exportados ' . count($rows) . ' cierres BTC/USD a storage/ml/btc.csv' . PHP_EOL;
    echo 'Fuentes: ' . json_encode(array_count_values(array_map(static fn($row) => (string) $row['fuente'], $rows)), JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo exportar BTC: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
