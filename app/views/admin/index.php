<?php require __DIR__ . '/../layouts/header.php'; require __DIR__ . '/../layouts/navbar.php';
$esc = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<main class="page-shell admin-page"><div class="container">
    <div class="page-heading"><span class="eyebrow">Gestión de la plataforma</span><h1>Administración</h1><p>Administra las cuentas, revisa sus permisos y supervisa la actualización de los datos.</p></div>
    <?php if ($flash): ?><p class="panel <?= $flash['ok'] ? 'text-up' : 'text-down' ?>" role="status"><?= $esc($flash['mensaje']) ?></p><?php endif; ?>
    <?php if ($resumen): ?>
    <div class="admin-summary">
        <article class="panel"><span>Usuarios registrados</span><strong><?= (int) $resumen['usuarios']['total'] ?></strong><small><?= (int) $resumen['usuarios']['activos'] ?> activos · <?= (int) $resumen['usuarios']['inactivos'] ?> inactivos</small></article>
        <article class="panel"><span>Administradores activos</span><strong><?= (int) $resumen['usuarios']['administradores'] ?></strong><small>Con acceso a la gestión de la plataforma</small></article>
        <article class="panel"><span>Cierres almacenados</span><strong><?= number_format($resumen['registros'], 0, ',', '.') ?></strong><small><?= (int) $resumen['pendientes'] ?> activos con cierres pendientes</small></article>
    </div>
    <?php endif; ?>
    <section class="admin-tools" aria-label="Herramientas de gestión">
        <article class="panel"><span class="eyebrow">Cuentas</span><h2>Usuarios</h2><p>Crea y edita cuentas, activa o desactiva usuarios y asigna su rol.</p><div class="admin-tool-actions"><a class="btn primary" href="<?= BASE_URL ?>admin/usuarios">Gestionar usuarios</a><a class="btn secondary" href="<?= BASE_URL ?>admin/usuarios#cuenta">Crear usuario</a></div></article>
        <article class="panel"><span class="eyebrow">Acceso</span><h2>Roles y permisos</h2><p>Consulta qué secciones y acciones permite cada rol de la plataforma.</p><a class="btn primary" href="<?= BASE_URL ?>admin/permisos">Consultar permisos</a></article>
        <article class="panel"><span class="eyebrow">Datos</span><h2>Históricos</h2><p>Comprueba los últimos cierres guardados y solicita la actualización de cada activo.</p><a class="btn primary" href="#historicos">Gestionar históricos</a></article>
    </section>
    <?php if ($resumen): ?>
    <section class="panel admin-history" id="historicos"><h2>Estado del histórico</h2><p>Los cierres de hoy se incorporan al finalizar el día. Las consultas respetan los tiempos de reintento y los límites del proveedor. Horarios en America/Lima.</p>
    <div class="table-wrap"><table><thead><tr><th>Activo</th><th>Registros</th><th>Último cierre</th><th>Estado</th><th>Última consulta</th><th>Gestión</th></tr></thead><tbody>
    <?php foreach ($resumen['historico'] as $row): ?><tr>
        <td><strong><?= $esc($row['par']) ?></strong></td><td><?= number_format($row['registros'], 0, ',', '.') ?></td><td><?= $esc($row['ultima_fecha'] ?? 'Sin datos') ?></td>
        <td><span class="admin-state <?= $row['error'] ? 'text-down' : ($row['pendiente'] ? '' : 'text-up') ?>"><?= $row['error'] ? 'Error de actualización' : ($row['pendiente'] ? 'Cierres pendientes' : 'Al día') ?></span><?php if ($row['mensaje']): ?><small class="admin-history-message"><?= $esc($row['mensaje']) ?></small><?php endif; ?></td>
        <td><?= $esc($row['ultimo_intento']) ?></td>
        <td><form method="post" action="<?= BASE_URL ?>admin/historico/actualizar"><input type="hidden" name="csrf" value="<?= Auth::csrf() ?>"><input type="hidden" name="par" value="<?= $esc($row['par']) ?>"><button type="submit" class="btn secondary">Actualizar cierres</button></form><a class="admin-history-link" href="<?= BASE_URL ?>historial?par=<?= rawurlencode($row['par']) ?>">Ver histórico</a></td>
    </tr><?php endforeach; ?></tbody></table></div></section>
    <?php endif; ?>
</div></main>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
