<?php
require_once __DIR__ . '/Historial.php';

class Plataforma
{
    public function resumen(): array
    {
        $db = (new Database())->getConnection();
        $usuarios = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_active=1),0) AS activos,
            COALESCE(SUM(is_active=0),0) AS inactivos, COALESCE(SUM(role='admin' AND is_active=1),0) AS administradores
            FROM usuarios")->fetch();
        $rows = $db->query('SELECT par,COUNT(*) AS registros,MAX(fecha) AS ultima_fecha FROM historico_cotizaciones GROUP BY par')->fetchAll();
        $byPair = array_column($rows, null, 'par');
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
        $historico = [];
        foreach ((new Historial())->listarSimbolos() as $par) {
            $expected = $today->modify('-1 day');
            if (!in_array($par, ['BTC/USD','ETH/USD','USDT/USD','BNB/USD','SOL/USD'], true)) {
                while ((int) $expected->format('N') > 5) $expected = $expected->modify('-1 day');
            }
            $row = $byPair[$par] ?? ['registros' => 0, 'ultima_fecha' => null];
            $file = __DIR__ . '/../../storage/cache/history-sync-' . str_replace('/', '-', $par) . '.json';
            $sync = is_readable($file) ? json_decode(file_get_contents($file), true) : [];
            $attempt = (int) ($sync['intento'] ?? 0);
            $historico[] = [
                'par' => $par, 'registros' => (int) $row['registros'], 'ultima_fecha' => $row['ultima_fecha'],
                'pendiente' => !$row['ultima_fecha'] || $row['ultima_fecha'] < $expected->format('Y-m-d'),
                'ultimo_intento' => $attempt > 0 ? (new DateTimeImmutable('@' . $attempt))->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i') : 'Sin consultas',
                'mensaje' => is_string($sync['mensaje'] ?? null) ? $sync['mensaje'] : '',
                'error' => ($sync['estado'] ?? '') === 'error',
            ];
        }
        return ['usuarios' => $usuarios, 'historico' => $historico,
            'registros' => array_sum(array_column($historico, 'registros')),
            'pendientes' => count(array_filter($historico, static fn(array $row): bool => $row['pendiente']))];
    }
}
