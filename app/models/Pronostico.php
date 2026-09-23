<?php
require_once __DIR__ . '/../core/Database.php';

class Pronostico
{
    private Database $database;

    private array $pares = [
        'EUR/USD',
        'USD/PEN',
        'JPY/USD',
        'BTC/USD',
        'ETH/USD',
        'SOL/USD',
    ];

    public function __construct()
    {
        $this->database = new Database();
    }

    public function obtenerPronosticos(): array
    {
        $resultados = [];

        foreach ($this->pares as $par) {
            $resultado = $this->generarPronostico($par);
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

    public function generarPronostico(string $par): array
    {
        $historico = $this->obtenerHistorico($par, 120);

        if (count($historico) < 10) {
            return [];
        }

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
        $pendiente = $this->calcularPendiente($precios);
        $estimado = $precioActual * (1 + ($pendiente * 0.18) + (($mediaCorta - $mediaLarga) / max($mediaLarga, 0.00001)) * 0.35);
        $cambioEstimado = (($estimado - $precioActual) / max($precioActual, 0.00001)) * 100;
        $confianza = $this->calcularConfianza($cambioEstimado, $volatilidad);

        return [
            'par' => $par,
            'precio_actual' => round($precioActual, 6),
            'precio_estimado' => round($estimado, 6),
            'cambio_estimado' => round($cambioEstimado, 2),
            'confianza' => round($confianza, 2),
            'volatilidad' => round($volatilidad, 4),
            'tendencia' => $cambioEstimado >= 0 ? 'alcista' : 'bajista',
            'media_corta' => round($mediaCorta, 6),
            'media_larga' => round($mediaLarga, 6),
            'mensaje' => $this->mensajeTendencia($cambioEstimado, $volatilidad),
        ];
    }

    private function obtenerHistorico(string $par, int $dias): array
    {
        $sql = '
            SELECT par, fecha, precio_cierre
            FROM historico_cotizaciones
            WHERE par = :par
            ORDER BY fecha DESC
            LIMIT :dias
        ';

        $stmt = $this->database->getConnection()->prepare($sql);
        $stmt->bindValue(':par', $par, PDO::PARAM_STR);
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
