<?php require __DIR__ . '/../layouts/header.php'; require __DIR__ . '/../layouts/navbar.php'; ?>
<main class="page-shell"><section class="container panel account-panel">
    <h1>Crear cuenta</h1>
    <p>Regístrate con tu correo electrónico y una contraseña.</p>
    <?php if ($error): ?><p class="text-down" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="<?= BASE_URL ?>registro">
        <input type="hidden" name="csrf" value="<?= Auth::csrf() ?>">
        <div class="field"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" autocomplete="username" maxlength="190" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="password-help" required><small id="password-help">Usa al menos 12 caracteres.</small></div>
        <button class="btn primary" type="submit">Crear cuenta</button>
    </form>
    <p>¿Ya tienes cuenta? <a href="<?= BASE_URL ?>login">Iniciar sesión</a></p>
</section></main>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
