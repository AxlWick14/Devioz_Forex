<?php
require_once __DIR__ . '/../models/Cotizacion.php';

class CotizacionController
{
    public function mercado(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        try {
            $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
            $model = new Cotizacion();
            $data = $model->obtenerMercado($forceRefresh);

            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(502);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
