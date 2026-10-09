<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/core/Access.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
check(Access::ROLES === ['admin' => 'Administrador', 'user' => 'Usuario', 'guest' => 'Invitado'], 'Deben existir exactamente los tres roles solicitados');
foreach (array_keys(Access::PERMISSIONS) as $route) check(!Access::permite($route, 'superuser'), 'El rol anterior no debe permitir acceso');
foreach (['','home','mercado','calculadora','historial','historial-data','api/mercado','login','registro','logout'] as $route)
    foreach (['guest','user','admin'] as $role) check(Access::permite($route, $role), "$route debe ser público");
foreach (['dashboard','pronosticos','pronosticos-data','historial-sync','etl','bitcoin-predictor','bitcoin-predictor-api'] as $route) {
    check(!Access::permite($route, 'guest'), "$route debe bloquear al invitado");
    foreach (['admin','user'] as $role) check(Access::permite($route, $role), "$route debe permitir $role");
}
foreach (['admin','admin/historico/actualizar','admin/usuarios','admin/usuarios/guardar','admin/usuarios/eliminar','admin/permisos'] as $route) {
    check(Access::permite($route, 'admin'), "$route debe permitir admin");
    foreach (['user','guest'] as $role) check(!Access::permite($route, $role), "$route debe bloquear $role");
}
foreach (['admin','user','guest','inventado'] as $role) check(!Access::permite('ruta-no-declarada', $role), 'Las rutas desconocidas se deniegan');
check(!Access::permite('home', 'inventado'), 'Roles desconocidos se deniegan');
echo "Matriz de acceso verificada.\n";
