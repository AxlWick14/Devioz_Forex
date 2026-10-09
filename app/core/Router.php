<?php
require_once __DIR__ . '/Access.php';
class Router
{
    public function dispatch(): void
    {
        if (!is_string($_GET['url'] ?? '')) { http_response_code(400); return; }
        $url = trim($_GET['url'] ?? '', '/');

        $routes = [
            '' => ['HomeController', 'index'],
            'home' => ['HomeController', 'index'],
            'mercado' => ['HomeController', 'mercado'],
            'calculadora' => ['HomeController', 'calculadora'],
            'historial' => ['HomeController', 'historial'],
            'historial-data' => ['HistorialController', 'index'],
            'historial-sync' => ['HistorialController', 'sincronizar'],
            'dashboard' => ['HomeController', 'dashboard'],
            'pronosticos' => ['HomeController', 'pronosticos'],
            'pronosticos-data' => ['PronosticoController', 'index'],
            'api/mercado' => ['CotizacionController', 'mercado'],
            'etl' => ['HomeController', 'etl'],
            'login' => ['AuthController', 'login'],
            'registro' => ['AuthController', 'registro'],
            'logout' => ['AuthController', 'logout'],
            'admin/usuarios' => ['UsuariosController', 'index'],
            'admin/usuarios/guardar' => ['UsuariosController', 'guardar'],
            'admin/usuarios/eliminar' => ['UsuariosController', 'eliminar'],
            'admin/permisos' => ['UsuariosController', 'permisos'],
            'admin' => ['AdministracionController', 'index'],
            'admin/historico/actualizar' => ['AdministracionController', 'actualizarHistorico'],
        ];

        if (!isset($routes[$url])) {
            http_response_code(404);
            echo '<h1>404</h1><p>Página no encontrada.</p>';
            return;
        }

        $json = in_array($url, ['api/mercado','historial-data','historial-sync','pronosticos-data'], true);
        if (!Access::exigir($url, $json)) return;
        [$controllerName, $method] = $routes[$url];
        require_once __DIR__ . '/../controllers/' . $controllerName . '.php';

        $controller = new $controllerName();
        $controller->$method();
    }
}
