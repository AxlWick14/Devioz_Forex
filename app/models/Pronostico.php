<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/ForecastMethods.php';
require_once __DIR__ . '/XgboostForecast.php';

class Pronostico
{
    private Database $database;
    private ?array $xgboost = null;
    private ?string $xgboostDate = null;

    private array $pares = [
        'USD/PEN',
        'EUR/USD',
        'BTC/USD',
        'ETH/USD',
    ];

    public function __construct()
    {
        $this->database = new Database();
    }

    public function obtenerPronosticos(string $metodo = 'lineal'): array
    {
        $resultados = [];

        foreach ($this->pares as $par) {
            $resultado = $this->generarPronostico($par, $metodo);
            if ($resultado !== []) {
                $resultados[] = $resultado;
            }
        }

        usort(
            $resultados,
            static fn(array $a, array $b): int => abs((float) $b['cambio_estimado']) <=> abs((float) $a['cambio_estimado'])
        );

        return $resultados;
    }

    public function generarPronostico(string $par, string $metodo = 'lineal'): array
    {
        if (!in_array($metodo, [...ForecastMethods::AVAILABLE_METHODS, 'auto'], true)) {
            throw new InvalidArgumentException('Método de pronóstico inválido.');
        }
        $hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
        $historico = $this->obtenerHistorico($par, 120, $hoy);

        if (count($historico) < 10) {
            return [];
        }
        $xgboost = null;
        if ($metodo === 'auto' || $metodo === 'boosting') {
            try {
                $this->prepararXgboost($hoy);
                $xgboost = $this->xgboost[$par] ?? null;
            } catch (XgboostForecastException $error) {
                if ($metodo === 'boosting') throw $error;
                error_log($error->getMessage());
            }
        }
        if ($metodo === 'auto') {
            $best = ForecastMethods::bestModel($historico, $hoy);
            if ($xgboost && (!$best || $xgboost['validacion']['error_mape'] < ($best['validacion']['error_mape'] ?? INF))) {
                $best = ['metodo' => 'boosting', 'validacion' => $xgboost['validacion'], 'proyeccion' => $xgboost['proyeccion']];
            }
            if (!$best) return [];
            $metodo = $best['metodo'];
            $validacion = $best['validacion'];
            $proyeccion = $best['proyeccion'];
        } elseif ($metodo === 'boosting') {
            if (!$xgboost) throw new XgboostForecastException('XGBoost necesita al menos 120 cierres recientes y datos suficientes para validar este activo.');
            $validacion = $xgboost['validacion'];
            $proyeccion = $xgboost['proyeccion'];
        } else {
            $validacion = ForecastMethods::calibrate($historico, $metodo);
            $proyeccion = ForecastMethods::project(array_slice($historico, -$validacion['ventana']), $metodo, 7, $hoy, true);
        }
        $ajuste = array_slice($historico, -$validacion['ventana']);
        if ($proyeccion === []) return [];
        $presente = array_shift($proyeccion);

        $precios = array_map(static fn(array $fila): float => (float) $fila['precio_cierre'], $historico);
        $retornos = [];
        for ($i = 1, $count = count($precios); $i < $count; $i++) {
            if ($precios[$i - 1] != 0.0) {
                $retornos[] = (($precios[$i] - $precios[$i - 1]) / $precios[$i - 1]) * 100;
            }
        }

        $precioActual = $precios[array_key_last($precios)];
        $mediaCorta = $this->promedio(array_slice($precios, -7));
        $mediaLarga = $this->promedio(array_slice($precios, -30));
        $volatilidad = $this->desviacionEstandar($retornos);
        $estimado = $proyeccion[array_key_last($proyeccion)]['precio'];
        $cambioEstimado = (($estimado - $precioActual) / max($precioActual, 0.00001)) * 100;
        $confianza = $this->calcularConfianza($cambioEstimado, $volatilidad);
        $fechaBase = substr($historico[array_key_last($historico)]['fecha'], 0, 10);
        $horizonteDesdeCierre = 7 + (int) round((strtotime($hoy . ' UTC') - strtotime($fechaBase . ' UTC')) / 86400);
        $probabilidades = $metodo === 'boosting' ? $xgboost['probabilidades']
            : ForecastMethods::directionProbabilities($historico, $metodo, $validacion['ventana'], $estimado, $precioActual, $horizonteDesdeCierre);

        return [
            'par' => $par,
            'metodo' => $metodo,
            'validacion' => $validacion,
            'observaciones' => $metodo === 'boosting' ? $xgboost['observaciones'] : count($ajuste),
            'fecha_base' => $historico[array_key_last($historico)]['fecha'],
            'fecha_presente' => $hoy,
            'precio_presente' => $presente['precio'],
            'horizonte_dias' => 7,
            'proyeccion' => $proyeccion,
            'precio_actual' => round($precioActual, 6),
            'precio_estimado' => round($estimado, 6),
            'cambio_estimado' => round($cambioEstimado, 2),
            'confianza' => round($confianza, 2),
            'probabilidades' => $probabilidades,
            'volatilidad' => round($volatilidad, 4),
            'tendencia' => $cambioEstimado >= 0 ? 'alcista' : 'bajista',
            'media_corta' => round($mediaCorta, 6),
            'media_larga' => round($mediaLarga, 6),
            'mensaje' => $this->mensajeTendencia($cambioEstimado, $volatilidad),
        ];
    }

    private function prepararXgboost(string $hoy): void
    {
        if ($this->xgboostDate === $hoy) return;
        $this->xgboostDate = $hoy;
        $this->xgboost = [];
        $histories = [];
        foreach ($this->pares as $par) $histories[$par] = $this->obtenerHistorico($par, 600, $hoy);
        // Windows counts wall time for PHP's limit; the cold batch needs time to validate four assets.
        if (function_exists('set_time_limit')) set_time_limit(120);
        $this->xgboost = XgboostForecast::prepare($histories, $hoy);
    }

    private function obtenerHistorico(string $par, int $dias, string $hasta): array
    {
        $sql = '
            SELECT par, fecha, precio_cierre
            FROM historico_cotizaciones
            WHERE par = :par
              AND fecha < :hasta
            ORDER BY fecha DESC
            LIMIT :dias
        ';

        $stmt = $this->database->getConnection()->prepare($sql);
        $stmt->bindValue(':par', $par, PDO::PARAM_STR);
        $stmt->bindValue(':hasta', $hasta, PDO::PARAM_STR);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        usort($datos, static fn(array $a, array $b): int => strcmp($a['fecha'], $b['fecha']));

        return $datos;
    }

    private function promedio(array $valores): float
    {
        if ($valores === []) {
            return 0.0;
        }

        return array_sum($valores) / count($valores);
    }

    private function desviacionEstandar(array $valores): float
    {
        if (count($valores) < 2) {
            return 0.0;
        }

        $media = $this->promedio($valores);
        $variacion = array_map(static fn(float $valor): float => ($valor - $media) ** 2, $valores);
        return sqrt(array_sum($variacion) / count($variacion));
    }

    private function calcularPendiente(array $precios): float
    {
        $n = count($precios);
        if ($n < 2) {
            return 0.0;
        }

        $x = range(1, $n);
        $y = $precios;
        $mediaX = $this->promedio($x);
        $mediaY = $this->promedio($y);

        $numerador = 0.0;
        $denominador = 0.0;

        foreach ($x as $index => $valorX) {
            $numerador += ($valorX - $mediaX) * ($y[$index] - $mediaY);
            $denominador += ($valorX - $mediaX) ** 2;
        }

        if ($denominador == 0.0) {
            return 0.0;
        }

        return $numerador / $denominador;
    }

    private function calcularConfianza(float $cambioEstimado, float $volatilidad): float
    {
        $base = 60.0;
        $factorCambio = min(25.0, abs($cambioEstimado) * 10.0);
        $factorVolatilidad = max(0.0, 20.0 - ($volatilidad * 1000.0));
        $confianza = $base + $factorCambio + $factorVolatilidad;

        return max(55.0, min(92.0, $confianza));
    }

    private function mensajeTendencia(float $cambioEstimado, float $volatilidad): string
    {
        if ($cambioEstimado > 0.75 && $volatilidad < 0.8) {
            return 'Tendencia sólida con volatilidad controlada.';
        }

        if ($cambioEstimado > 0) {
            return 'Momento alcista moderado.';
        }

        if ($cambioEstimado < -0.75 && $volatilidad < 0.8) {
            return 'Presión bajista clara pero aún gestionable.';
        }

        return 'Mercado en rango con señales mixtas.';
    }
}
