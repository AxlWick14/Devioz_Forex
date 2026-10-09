<?php require __DIR__ . '/../layouts/header.php'; require __DIR__ . '/../layouts/navbar.php'; ?>
<main class="page-shell"><section class="container panel"><h1>Roles y permisos</h1><p>Asigna el rol de cada cuenta en Administración de usuarios. Los permisos se aplican en el servidor.</p>
<div class="table-wrap"><table><thead><tr><th>Sección / acción</th><th>Administrador</th><th>Usuario</th><th>Invitado</th></tr></thead><tbody>
<?php foreach (Access::PERMISSIONS as $route => $permission): if ($route === '') continue; ?><tr><td><?= htmlspecialchars($route) ?></td><?php foreach (['admin','user','guest'] as $role): ?><td><?= Access::permite($route, $role) ? 'Sí' : 'No' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div><a class="btn primary" href="<?= BASE_URL ?>admin/usuarios">Gestionar cuentas y roles</a> <a class="btn secondary" href="<?= BASE_URL ?>admin">Volver a Administración</a></section></main>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
