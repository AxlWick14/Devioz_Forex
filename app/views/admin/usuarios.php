<?php require __DIR__ . '/../layouts/header.php'; require __DIR__ . '/../layouts/navbar.php';
$esc = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<main class="page-shell"><div class="container">
    <section class="panel"><h1>Administración de usuarios</h1><p>Crea cuentas y gestiona su rol y estado. Invitado corresponde a visitantes sin sesión.</p>
    <a class="btn secondary" href="<?= BASE_URL ?>admin">Volver a Administración</a>
    <a class="btn secondary" href="<?= BASE_URL ?>admin/permisos">Roles y permisos</a>
    <?php if ($flash): ?><p role="status" class="<?= $flash['ok'] ? 'text-up' : 'text-down' ?>"><?= $esc($flash['message']) ?></p><?php endif; ?>
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>Nombre / correo</th><th>Rol</th><th>Estado</th><th>Creación / actualización</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($usuarios as $usuario): ?><tr>
        <td><?= (int) $usuario['id'] ?></td><td><?= $esc($usuario['nombre']) ?><br><?= $esc($usuario['email']) ?></td>
        <td><?= $esc(Access::ROLES[$usuario['role']] ?? 'Rol desconocido') ?></td><td><?= $usuario['is_active'] ? 'Activo' : 'Inactivo' ?></td>
        <td><?= $esc($usuario['created_at']) ?><br><?= $esc($usuario['updated_at']) ?></td>
        <td><a class="btn secondary" href="<?= BASE_URL ?>admin/usuarios?editar=<?= (int) $usuario['id'] ?>#cuenta">Editar</a>
        <?php if ((int) $usuario['id'] !== (int) Auth::usuario()['id']): ?>
        <form method="post" action="<?= BASE_URL ?>admin/usuarios/eliminar" class="account-inline" data-confirm-delete>
            <input type="hidden" name="csrf" value="<?= Auth::csrf() ?>"><input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
            <button class="btn secondary" type="submit">Eliminar</button>
        </form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div></section>
    <section class="panel account-editor" id="cuenta"><h2><?= $editing ? 'Editar usuario' : 'Crear usuario' ?></h2>
    <form method="post" action="<?= BASE_URL ?>admin/usuarios/guardar">
        <input type="hidden" name="csrf" value="<?= Auth::csrf() ?>"><input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">
        <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="<?= $esc($editing['nombre'] ?? '') ?>" maxlength="120" required></div>
        <div class="field"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" value="<?= $esc($editing['email'] ?? '') ?>" maxlength="190" required></div>
        <div class="field"><label for="password">Contraseña <?= $editing ? '(deja vacío para conservarla)' : '' ?></label><input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" <?= $editing ? '' : 'required' ?>><small>Entre 12 y 72 bytes.</small></div>
        <div class="field"><label for="role">Rol</label><select name="role" id="role"><?php foreach (['user' => 'Usuario', 'admin' => 'Administrador'] as $role => $label): ?><option value="<?= $role ?>" <?= ($editing['role'] ?? 'user') === $role ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="is_active">Estado</label><select name="is_active" id="is_active"><option value="1" <?= ($editing['is_active'] ?? 1) ? 'selected' : '' ?>>Activo</option><option value="0" <?= isset($editing) && !$editing['is_active'] ? 'selected' : '' ?>>Inactivo</option></select></div>
        <button class="btn primary" type="submit">Guardar usuario</button><a class="btn secondary" href="<?= BASE_URL ?>admin/usuarios">Nuevo / cancelar</a>
    </form></section>
</div></main>
<script>document.querySelectorAll('[data-confirm-delete]').forEach(form => form.addEventListener('submit', event => { if (!confirm('¿Eliminar esta cuenta? Esta acción no se puede deshacer.')) event.preventDefault(); }));</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
