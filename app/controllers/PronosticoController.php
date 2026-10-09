<?php
require_once __DIR__ . '/../models/Pronostico.php';
require_once __DIR__ . '/../core/Access.php';

class PronosticoController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $metodo = $_GET['metodo'] ?? (Auth::rol() === 'admin' ? 'lineal' : 'auto');
        if (!is_string($metodo) || !in_array($metodo, [...ForecastMethods::AVAILABLE_METHODS, 'auto'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Método de pronóstico inválido.']);
            return;
        }
        if (Auth::rol() !== 'admin') $metodo = 'auto';
        $model = new Pronostico();
        try {
            $data = $model->obtenerPronosticos($metodo);
        } catch (XgboostForecastException $error) {
            http_response_code(503);
            echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
