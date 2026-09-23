<?php
require_once __DIR__ . '/../models/Pronostico.php';

class PronosticoController
{
    public function index(): void
    {
        $model = new Pronostico();
        $data = $model->obtenerPronosticos();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
