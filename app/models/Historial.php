<?php
require_once __DIR__ . '/../core/Database.php';

class Historial
{
    private Database $database;

    private array $symbols = [
        'EUR/USD',
        'GBP/USD',
        'USD/PEN',
        'BTC/USD',
        'ETH/USD',
        'SOL/USD',
        'XRP/USD',
    ];

    private array $minimumDates = [
        'EUR/USD' => '2000-01-01',
        'GBP/USD' => '2000-01-01',
        'USD/PEN' => '2000-01-01',
        'BTC/USD' => '2012-01-01',
        'XRP/USD' => '2013-01-01',
        'ETH/USD' => '2015-01-01',
        'SOL/USD' => '2020-01-01',
    ];

    public function __construct()
    {
        $this->database = new Database();
    }

    public function listarSimbolos(): array
    {
        return $this->symbols;
    }

    public function obtenerHistoricoCompleto(string $hasta): array
    {
        $datos = [];

        foreach ($this->symbols as $par) {
            $historico = $this->obtenerHistorico(
                $par,
                $this->minimumDates[$par] ?? '2000-01-01',
                $hasta,
                10000
            );
            $datos = array_merge($datos, $historico['datos']);
        }

        usort($datos, static function (array $a, array $b): int {
            $dateComparison = strcmp($a['fecha'], $b['fecha']);
            return $dateComparison !== 0
                ? $dateComparison
                : strcmp($a['par'], $b['par']);
        });

        return $datos;
    }

    public function obtenerHistorico(string $par = 'EUR/USD', ?string $desde = null, ?string $hasta = null, int $limit = 60): array
    {
        $par = in_array($par, $this->symbols, true) ? $par : 'EUR/USD';
        $desde = $desde ?: ($this->minimumDates[$par] ?? '2000-01-01');
        $hasta = $hasta ?: date('Y-m-d');
        $minimumDate = $this->minimumDates[$par] ?? '2000-01-01';
        if ($desde < $minimumDate) {
            $desde = $minimumDate;
        }
        $limit = max(1, min(10000, $limit));

        $datosApi = $this->obtenerHistoricoDesdeApi($par, $desde, $hasta);
        if ($datosApi !== []) {
            return [
                'par' => $par,
                'desde' => $desde,
                'hasta' => $hasta,
                'datos' => $datosApi,
            ];
        }

        $sql = "
            SELECT par, fecha, precio_apertura, precio_maximo, precio_minimo, precio_cierre, cambio_porcentual, fuente
            FROM historico_cotizaciones
            WHERE par = :par
              AND fecha BETWEEN :desde AND :hasta
            ORDER BY fecha ASC
            LIMIT :limit
        ";

        $stmt = $this->database->getConnection()->prepare($sql);
        $stmt->bindValue(':par', $par, PDO::PARAM_STR);
        $stmt->bindValue(':desde', $desde, PDO::PARAM_STR);
        $stmt->bindValue(':hasta', $hasta, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($datos === []) {
            $datos = $this->generarDatosDemo($par, $desde, $hasta);
        }

        return [
            'par' => $par,
            'desde' => $desde,
            'hasta' => $hasta,
            'datos' => $datos,
        ];
    }

    private function generarDatosDemo(string $par, string $desde, string $hasta): array
    {
        $basePrices = [
            'EUR/USD' => 1.0850,
            'GBP/USD' => 1.2700,
            'USD/PEN' => 3.7400,
            'BTC/USD' => 42000.00,
            'ETH/USD' => 2350.00,
            'SOL/USD' => 92.50,
            'XRP/USD' => 0.62,
        ];

        $start = new DateTimeImmutable($desde);
        $end = new DateTimeImmutable($hasta);
        $current = $start;
        $base = $basePrices[$par] ?? 1.0;
        $datos = [];
        $index = 0;

        while ($current <= $end && $index < 180) {
            $fecha = $current->format('Y-m-d');
            $mod = $index / 7.0;
            $drift = sin($mod) * 0.008;
            $volatility = cos($mod * 1.7) * 0.003;
            $open = $base;
            $close = $base * (1 + $drift + $volatility);
            $high = max($open, $close) * (1 + 0.004);
            $low = min($open, $close) * (1 - 0.004);
            $change = $open != 0.0 ? (($close - $open) / $open) * 100 : 0.0;

            $datos[] = [
                'par' => $par,
                'fecha' => $fecha,
                'precio_apertura' => round($open, 6),
                'precio_maximo' => round($high, 6),
                'precio_minimo' => round($low, 6),
                'precio_cierre' => round($close, 6),
                'cambio_porcentual' => round($change, 4),
                'fuente' => 'Demo',
            ];

            $base = $close;
            $current = $current->modify('+1 day');
            $index++;
        }

        return $datos;
    }

    private function obtenerHistoricoDesdeApi(string $par, string $desde, string $hasta): array
    {
        if (TWELVE_DATA_API_KEY === '' || TWELVE_DATA_API_KEY === 'PEGA_AQUI_TU_API_KEY') {
            return [];
        }

        $query = http_build_query([
            'symbol' => $par,
            'interval' => '1day',
            'start_date' => $desde,
            'end_date' => $hasta,
            'order' => 'ASC',
            'outputsize' => 5000,
            'apikey' => TWELVE_DATA_API_KEY,
        ]);
        $curl = curl_init(TWELVE_DATA_BASE_URL . '/time_series?' . $query);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $httpCode !== 200) {
            return [];
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || !isset($payload['values']) || !is_array($payload['values'])) {
            return [];
        }

        $datos = [];
        foreach ($payload['values'] as $value) {
            if (!isset($value['datetime'], $value['open'], $value['high'], $value['low'], $value['close'])) {
                continue;
            }

            $open = (float) $value['open'];
            $close = (float) $value['close'];
            $datos[] = [
                'par' => $par,
                'fecha' => substr((string) $value['datetime'], 0, 10),
                'precio_apertura' => $open,
                'precio_maximo' => (float) $value['high'],
                'precio_minimo' => (float) $value['low'],
                'precio_cierre' => $close,
                'cambio_porcentual' => $open !== 0.0 ? (($close - $open) / $open) * 100 : null,
                'fuente' => 'Twelve Data',
            ];
        }

        if ($datos !== []) {
            $this->guardarDatosApi($datos);
        }

        return $datos;
    }

    private function guardarDatosApi(array $datos): void
    {
        $connection = $this->database->getConnection();
        $historyStatement = $connection->prepare('
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
                fuente = VALUES(fuente)');
        $candleStatement = $connection->prepare('
            INSERT INTO velas_historicas
                (par, intervalo, fecha_hora, precio_apertura, precio_maximo,
                 precio_minimo, precio_cierre, fuente)
            VALUES
                (:par, "1day", :fecha_hora, :open, :high, :low, :close, :fuente)
            ON DUPLICATE KEY UPDATE
                precio_apertura = VALUES(precio_apertura),
                precio_maximo = VALUES(precio_maximo),
                precio_minimo = VALUES(precio_minimo),
                precio_cierre = VALUES(precio_cierre),
                fuente = VALUES(fuente)');

        $connection->beginTransaction();
        try {
            foreach ($datos as $dato) {
                $historyStatement->execute([
                    ':par' => $dato['par'],
                    ':fecha' => $dato['fecha'],
                    ':open' => $dato['precio_apertura'],
                    ':high' => $dato['precio_maximo'],
                    ':low' => $dato['precio_minimo'],
                    ':close' => $dato['precio_cierre'],
                    ':change' => $dato['cambio_porcentual'],
                    ':fuente' => $dato['fuente'],
                ]);
                $candleStatement->execute([
                    ':par' => $dato['par'],
                    ':fecha_hora' => $dato['fecha'] . ' 00:00:00',
                    ':open' => $dato['precio_apertura'],
                    ':high' => $dato['precio_maximo'],
                    ':low' => $dato['precio_minimo'],
                    ':close' => $dato['precio_cierre'],
                    ':fuente' => $dato['fuente'],
                ]);
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
        }
    }
}
