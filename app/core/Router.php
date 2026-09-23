<?php
class Router
{
    public function dispatch(): void
    {
        $url = trim($_GET['url'] ?? '', '/');

        $routes = [
            '' => ['HomeController', 'index'],
            'home' => ['HomeController', 'index'],
            'mercado' => ['HomeController', 'mercado'],
            'calculadora' => ['HomeController', 'calculadora'],
            'historial' => ['HomeController', 'historial'],
            'historial-data' => ['HistorialController', 'index'],
            'historial-exportar' => ['HistorialController', 'exportar'],
            'dashboard' => ['HomeController', 'dashboard'],
            'pronosticos' => ['HomeController', 'pronosticos'],
            'pronosticos-data' => ['PronosticoController', 'index'],
            'alertas' => ['HomeController', 'alertas'],
            'api/mercado' => ['CotizacionController', 'mercado'],
        ];

        if (!isset($routes[$url])) {
            http_response_code(404);
            echo '<h1>404</h1><p>Página no encontrada.</p>';
            return;
        }

        [$controllerName, $method] = $routes[$url];
        require_once __DIR__ . '/../controllers/' . $controllerName . '.php';

        $controller = new $controllerName();
        $controller->$method();
    }
}
