<?php
require_once __DIR__ . '/../models/Historial.php';

class HistorialController
{
    public function index(): void
    {
        $historico = new Historial();
        $par = $_GET['par'] ?? 'USD/PEN';
        $desde = $_GET['desde'] ?? null;
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        $data = $historico->obtenerHistorico($par, $desde, $hasta, 10000);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function exportar(): void
    {
        $par = $_GET['par'] ?? null;
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $historico = new Historial();

        if ($par !== null && in_array($par, ['EUR/USD', 'USD/PEN', 'BTC/USD', 'ETH/USD'], true)) {
            $datos = $historico->obtenerHistorico($par, null, $hasta, 10000)['datos'];
            $fileName = 'historico_' . str_replace('/', '_', $par) . '_hasta_' . $hasta . '.csv';
        } else {
            $datos = $historico->obtenerHistoricoCompleto($hasta);
            $fileName = 'historial_completo_hasta_' . $hasta . '.csv';
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'wb');
        fputcsv($output, [
            'par',
            'fecha',
            'precio_apertura',
            'precio_maximo',
            'precio_minimo',
            'precio_cierre',
            'cambio_porcentual',
        ], ';');

        foreach ($datos as $dato) {
            fputcsv($output, [
                $dato['par'],
                $dato['fecha'],
                $dato['precio_apertura'],
                $dato['precio_maximo'],
                $dato['precio_minimo'],
                $dato['precio_cierre'],
                $dato['cambio_porcentual'],
            ], ';');
        }

        fclose($output);
    }
}
