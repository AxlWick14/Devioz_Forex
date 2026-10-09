<?php
require_once __DIR__ . '/Auth.php';

class Access
{
    public const ROLES = ['admin' => 'Administrador', 'user' => 'Usuario', 'guest' => 'Invitado'];
    public const PERMISSIONS = [
        '' => 'public', 'home' => 'public', 'mercado' => 'public', 'calculadora' => 'public',
        'historial' => 'public', 'historial-data' => 'public', 'api/mercado' => 'public',
        'login' => 'public', 'registro' => 'public', 'logout' => 'public',
        'dashboard' => 'operational', 'pronosticos' => 'operational', 'pronosticos-data' => 'operational',
        'historial-sync' => 'operational', 'etl' => 'operational',
        'bitcoin-predictor' => 'operational', 'bitcoin-predictor-api' => 'operational',
        'admin/usuarios' => 'admin', 'admin/usuarios/guardar' => 'admin', 'admin/usuarios/eliminar' => 'admin',
        'admin/permisos' => 'admin', 'admin' => 'admin', 'admin/historico/actualizar' => 'admin',
    ];
    public static function permite(string $route, ?string $role = null): bool {
        $role = $role ?? Auth::rol();
        if (!isset(self::ROLES[$role])) return false;
        $permission = self::PERMISSIONS[$route] ?? null;
        return $permission === 'public' || ($permission === 'operational' && in_array($role, ['admin','user'], true))
            || ($permission === 'admin' && $role === 'admin');
    }
    public static function exigir(string $route, bool $json = false): bool {
        if (self::permite($route)) return true;
        if ($json) {
            http_response_code(Auth::rol() === 'guest' ? 401 : 403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Acceso restringido.', 'ok' => false]);
        } elseif (Auth::rol() === 'guest') {
            header('Location: ' . BASE_URL . 'login?restringido=1', true, 303);
        } else {
            http_response_code(403);
            $titulo = 'Acceso restringido';
            require __DIR__ . '/../views/auth/restringido.php';
        }
        return false;
    }
}
