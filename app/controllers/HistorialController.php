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

}
