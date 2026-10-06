<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/Historial.php';
$failed = false;
try {
    $history = new Historial();
    foreach ($history->listarSimbolos() as $pair) {
        $result = $history->actualizarDiariamente($pair, in_array('--force', $argv, true));
        if (($result['estado'] ?? '') === 'error' && ($result['reintento_segundos'] ?? 0) === 60) {
            echo $pair . ': límite de consultas; reintento en 60 segundos.' . PHP_EOL;
            sleep(60);
            $result = $history->actualizarDiariamente($pair);
        }
        echo $pair . ': ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $failed = $failed || ($result['estado'] ?? '') === 'error';
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo actualizar el histórico: ' . $error->getMessage() . PHP_EOL);
    $failed = true;
}
exit($failed ? 1 : 0);
