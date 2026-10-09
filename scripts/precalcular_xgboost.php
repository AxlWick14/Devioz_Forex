<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/models/Pronostico.php';
try {
    $started = microtime(true);
    $results = (new Pronostico())->obtenerPronosticos('boosting');
    if (count($results) !== 4) throw new RuntimeException('No se obtuvieron los cuatro pronósticos XGBoost.');
    foreach ($results as $result) {
        echo json_encode(['par' => $result['par'], 'modelo' => 'XGBoost',
            'observaciones' => $result['observaciones'], 'fecha_base' => $result['fecha_base'],
            'fecha_objetivo' => $result['proyeccion'][6]['fecha'], 'precio_estimado' => $result['precio_estimado'],
            'mape' => $result['validacion']['error_mape'], 'mape_base' => $result['validacion']['error_base'],
            'evaluaciones' => $result['validacion']['evaluaciones'], 'probabilidades' => $result['probabilidades']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }
    echo 'XGBoost listo. Tiempo: ' . round(microtime(true) - $started, 2) . ' s.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo preparar XGBoost: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
