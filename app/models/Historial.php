<?php
require_once __DIR__ . '/../core/Database.php';

class Historial
{
    private Database $database;

    private array $symbols = [
        'EUR/USD',
        'USD/PEN',
        'JPY/USD',
        'BTC/USD',
        'ETH/USD',
        'USDT/USD',
        'BNB/USD',
        'SOL/USD',
    ];

    private array $minimumDates = [
        'EUR/USD' => '2000-01-01',
        'USD/PEN' => '2000-01-01',
        'JPY/USD' => '2000-01-01',
        'BTC/USD' => '2012-01-01',
        'ETH/USD' => '2015-01-01',
        'USDT/USD' => '2014-01-01',
        'BNB/USD' => '2017-01-01',
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
                20000
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

    public function obtenerHistorico(string $par = 'USD/PEN', ?string $desde = null, ?string $hasta = null, int $limit = 60): array
    {
        $par = in_array($par, $this->symbols, true) ? $par : 'USD/PEN';
        $desde = $desde ?: ($this->minimumDates[$par] ?? '2000-01-01');
        $hasta = $hasta ?: (new DateTimeImmutable('today', new DateTimeZone('America/Lima')))->format('Y-m-d');
        $minimumDate = $this->minimumDates[$par] ?? '2000-01-01';
        if ($desde < $minimumDate) {
            $desde = $minimumDate;
        }
        $limit = max(1, min(20000, $limit));

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
            'JPY/USD' => 0.0067,
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

    public function sincronizarPronostico(string $par): array
    {
        if (!in_array($par, $this->symbols, true)) {
            throw new InvalidArgumentException('Activo no permitido.');
        }
        // Only completed daily candles, never a live quote disguised as a close.
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
        $hasta = $today->modify('-1 day')->format('Y-m-d');
        $desde = $today->modify('-180 days')->format('Y-m-d');
        $datos = $this->obtenerHistoricoDesdeApi($par, $desde, $hasta);
        if ($datos === []) throw new RuntimeException('El proveedor no devolvió cierres válidos para el período solicitado.');
        $fechas = array_column($datos, 'fecha');
        return ['par' => $par, 'registros' => count($datos), 'ultima_fecha' => max($fechas), 'hasta_solicitado' => $hasta,
            'proveedor' => 'twelve'];
    }

    public function actualizarDiariamente(string $par, bool $force = false): array
    {
        if (!in_array($par, $this->symbols, true)) throw new InvalidArgumentException('Activo no permitido.');
        $directory = __DIR__ . '/../../storage/cache';
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $file = fopen($directory . '/history-sync-' . str_replace('/', '-', $par) . '.json', 'c+');
        if (!$file) return ['estado' => 'error', 'mensaje' => 'No se pudo abrir el control de actualización.'];
        if (!flock($file, LOCK_EX | LOCK_NB)) {
            fclose($file);
            return ['estado' => 'actualizando', 'mensaje' => 'Actualización en curso.'];
        }
        try {
            $today = (new DateTimeImmutable('today', new DateTimeZone('America/Lima')))->format('Y-m-d');
            $state = json_decode(stream_get_contents($file), true) ?: [];
            $expected = new DateTimeImmutable($today, new DateTimeZone('America/Lima'));
            $expected = $expected->modify('-1 day');
            if (!in_array($par, ['BTC/USD', 'ETH/USD', 'USDT/USD', 'BNB/USD', 'SOL/USD'], true)) {
                while ((int) $expected->format('N') > 5) $expected = $expected->modify('-1 day');
            }
            $sameProvider = ($state['proveedor'] ?? 'twelve') === 'twelve';
            if (!$force && $sameProvider && ($state['dia'] ?? '') === $today && ($state['estado'] ?? '') === 'ok'
                && ($state['ultima_fecha'] ?? '') >= $expected->format('Y-m-d')) return $state;
            $retrySeconds = (int) ($state['reintento_segundos'] ?? 3600);
            if (!$force && $sameProvider && time() - ($state['intento'] ?? 0) < $retrySeconds) return $state;
            try {
                $state = $this->sincronizarPronostico($par) + ['estado' => 'ok', 'dia' => $today, 'intento' => time()];
                if ($state['ultima_fecha'] < $expected->format('Y-m-d')) {
                    $state['estado'] = 'pendiente';
                    $state['mensaje'] = 'El proveedor todavía no publicó todos los cierres esperados. Se reintentará automáticamente.';
                }
            } catch (RuntimeException $error) {
                $state = ['estado' => 'error', 'dia' => $today, 'intento' => time(), 'proveedor' => 'twelve',
                    'reintento_segundos' => $error->getCode() === 429 ? 60 : 3600,
                    'mensaje' => $error instanceof PDOException ? 'No se pudo acceder a la base de datos.' : $error->getMessage()];
            }
            rewind($file);
            ftruncate($file, 0);
            fwrite($file, json_encode($state, JSON_UNESCAPED_UNICODE));
            return $state;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function obtenerHistoricoDesdeApi(string $par, string $desde, string $hasta): array
    {
            if (TWELVE_DATA_API_KEY === '' || TWELVE_DATA_API_KEY === 'PEGA_AQUI_TU_API_KEY') {
                throw new RuntimeException('Configura la clave de Twelve Data para descargar el histórico.');
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
            $curlCode = curl_errno($curl);
            curl_close($curl);

            if ($response === false || $httpCode !== 200) {
                throw new RuntimeException($httpCode === 429 ? 'Límite de Twelve Data alcanzado. Se reintentará en un minuto.' : 'No se pudo consultar Twelve Data (HTTP ' . $httpCode . ', cURL ' . $curlCode . '). Revisa la conexión y la clave API.', $httpCode);
            }

            $payload = json_decode($response, true);
            if (!is_array($payload) || !isset($payload['values']) || !is_array($payload['values'])) {
                $code = (int) ($payload['code'] ?? 0);
                throw new RuntimeException($code === 429 ? 'Límite de Twelve Data alcanzado. Se reintentará en un minuto.' : 'Twelve Data no devolvió datos. Revisa la clave, el plan y la disponibilidad del activo.', $code);
            }

        $datos = [];
        foreach ($payload['values'] as $value) {
            if (!isset($value['datetime'], $value['open'], $value['high'], $value['low'], $value['close'])) {
                continue;
            }

            $open = (float) $value['open'];
            $close = (float) $value['close'];
            $date = substr((string) $value['datetime'], 0, 10);
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $high = (float) $value['high'];
            $low = (float) $value['low'];
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < $desde || $date > $hasta
                || !is_finite($open) || !is_finite($close) || !is_finite($high) || !is_finite($low)
                || min($open, $close, $high, $low) <= 0 || $high < max($open, $close) || $low > min($open, $close)) {
                continue;
            }
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
            throw new RuntimeException('No se pudo guardar el histórico. No se aplicaron cambios para este activo.', 0, $exception);
        }
    }
}
