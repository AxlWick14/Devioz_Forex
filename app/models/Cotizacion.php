<?php
require_once __DIR__ . '/../../config/config.php';

class Cotizacion
{
    private array $symbols = [
        'USD/PEN',
        'EUR/USD',
        'GBP/USD',
        'USD/JPY',
        'BTC/USD',
        'ETH/USD',
        'SOL/USD',
        'XRP/USD',
    ];

    public function obtenerMercado(bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $cached = $this->leerCache();
            if ($cached !== null) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        if (TWELVE_DATA_API_KEY === '' || TWELVE_DATA_API_KEY === 'PEGA_AQUI_TU_API_KEY') {
            throw new RuntimeException('Configura tu API Key de Twelve Data en config/config.php.');
        }

        $rawQuotes = $this->consultarSimbolosEnParalelo($this->symbols);
        $quotes = [];
        $timestamps = [];
        $unavailable = [];

        foreach ($this->symbols as $symbol) {
            $quote = $rawQuotes[$symbol] ?? null;

            if ($quote === null) {
                $quotes[$symbol] = null;
                $unavailable[] = $symbol;
                continue;
            }

            $quotes[$symbol] = $quote['rate'];
            $timestamps[] = $quote['timestamp'];
        }

        $usdValue = [
            'USD' => 1.0,
            'PEN' => $this->invertir($quotes['USD/PEN'] ?? null),
            'EUR' => $quotes['EUR/USD'] ?? null,
            'GBP' => $quotes['GBP/USD'] ?? null,
            'JPY' => $this->invertir($quotes['USD/JPY'] ?? null),
            'BTC' => $quotes['BTC/USD'] ?? null,
            'ETH' => $quotes['ETH/USD'] ?? null,
            'SOL' => $quotes['SOL/USD'] ?? null,
            'XRP' => $quotes['XRP/USD'] ?? null,
        ];

        $data = [
            'error' => false,
            'updatedAt' => $timestamps ? max($timestamps) : time(),
            'source' => 'Twelve Data',
            'cached' => false,
            'quotes' => $quotes,
            'usdValue' => $usdValue,
            'unavailable' => $unavailable,
        ];

        if (count(array_filter($quotes, fn($v) => $v !== null)) > 0) {
            $this->guardarCache($data);
        }

        return $data;
    }

    private function consultarSimbolosEnParalelo(array $symbols): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($symbols as $symbol) {
            $url = TWELVE_DATA_BASE_URL
                . '/exchange_rate?symbol=' . urlencode($symbol)
                . '&apikey=' . urlencode(TWELVE_DATA_API_KEY);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);

            curl_multi_add_handle($multi, $ch);
            $handles[$symbol] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $active);
            if ($active) {
                curl_multi_select($multi, 1.0);
            }
        } while ($active && $status === CURLM_OK);

        $results = [];

        foreach ($handles as $symbol => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data = json_decode($response, true);

            if ($httpCode === 200 && is_array($data) && isset($data['rate'])) {
                $results[$symbol] = [
                    'rate' => (float) $data['rate'],
                    'timestamp' => (int) ($data['timestamp'] ?? time()),
                ];
            } else {
                $results[$symbol] = null;
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);
        return $results;
    }

    private function invertir(?float $value): ?float
    {
        if ($value === null || $value == 0.0) {
            return null;
        }
        return 1 / $value;
    }

    private function cachePath(): string
    {
        return __DIR__ . '/../../storage/cache/market.json';
    }

    private function leerCache(): ?array
    {
        $path = $this->cachePath();

        if (!file_exists($path) || (time() - filemtime($path)) >= MARKET_CACHE_SECONDS) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (!is_array($data) || !isset($data['quotes'], $data['usdValue'])) {
            return null;
        }

        return $data;
    }

    private function guardarCache(array $data): void
    {
        $path = $this->cachePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
