<?php
require_once __DIR__ . '/../models/Pronostico.php';

class PronosticoController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $metodo = $_GET['metodo'] ?? 'lineal';
        if (!is_string($metodo) || !in_array($metodo, ForecastMethods::METHODS, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Método de pronóstico inválido.']);
            return;
        }
        $model = new Pronostico();
        $data = $model->obtenerPronosticos($metodo);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
