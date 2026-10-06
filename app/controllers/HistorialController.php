<?php
require_once __DIR__ . '/../models/Historial.php';

class HistorialController
{
    public function sincronizar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(['error' => 'Utiliza POST.']);
            return;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!isset($_SESSION['history_csrf']) || !hash_equals($_SESSION['history_csrf'], $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Recarga la página para actualizar el histórico.']);
            return;
        }
        $par = $_POST['par'] ?? '';
        if (!is_string($par) || !in_array($par, ['BTC/USD', 'ETH/USD', 'USDT/USD', 'BNB/USD'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Activo no permitido.']);
            return;
        }
        $syncKey = $par;
        if (time() - ($_SESSION['history_sync'][$syncKey] ?? 0) < 60) {
            http_response_code(429);
            echo json_encode(['error' => 'Espera un minuto antes de volver a sincronizar este activo.']);
            return;
        }
        $_SESSION['history_sync'][$syncKey] = time();
        session_write_close();
        try {
            echo json_encode((new Historial())->actualizarDiariamente($par, true), JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $error) {
            http_response_code(502);
            echo json_encode(['error' => $error instanceof PDOException ? 'No se pudo acceder a la base de datos.' : $error->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function index(): void
    {
        $historico = new Historial();
        $par = $_GET['par'] ?? 'USD/PEN';
        $desde = $_GET['desde'] ?? null;
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
        $hasta = $_GET['hasta'] ?? $today->format('Y-m-d');
        if (!is_string($par) || !in_array($par, $historico->listarSimbolos(), true)
            || !is_string($hasta) || ($desde !== null && !is_string($desde))) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Par o fechas inválidos.']);
            return;
        }
        $sync = $hasta >= $today->modify('-1 day')->format('Y-m-d')
            ? $historico->actualizarDiariamente($par) : null;

        $data = $historico->obtenerHistorico($par, $desde, $hasta, 10000);
        $data['actualizacion'] = $sync;
        $data['fecha_actual'] = $today->format('Y-m-d');

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

}
