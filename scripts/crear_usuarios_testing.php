<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/models/Usuario.php';

// Only creates the two explicitly named testing accounts; existing accounts are never reset.
$model = new Usuario();
$db = (new Database())->getConnection();
$actor = (int) $db->query("SELECT id FROM usuarios WHERE role='admin' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
if (!$actor) { fwrite(STDERR, "Primero crea el administrador inicial.\n"); exit(1); }
$path = __DIR__ . '/../storage/testing-accounts.json';
$credentials = is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
if (isset($credentials['superuser']) && !isset($credentials['user'])) {
    $credentials['user'] = $credentials['superuser'];
    unset($credentials['superuser']);
    file_put_contents($path, json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
foreach (['admin' => 'Administrador Testing', 'user' => 'Usuario Testing'] as $role => $nombre) {
    $email = $credentials[$role]['email'] ?? ($role . '.testing@forex.local');
    if ($role === 'user' && !$model->porEmail($email) && $model->porEmail('superuser.testing@forex.local')) $email = 'superuser.testing@forex.local';
    $existing = $model->porEmail($email);
    if ($existing) {
        echo $email . ": ya existe; se conserva sin cambios.\n";
        continue;
    }
    $password = 'Fx-' . bin2hex(random_bytes(8)) . '!';
    $model->guardar(['nombre' => $nombre, 'email' => $email, 'password' => $password, 'role' => $role, 'is_active' => '1'], null, $actor);
    $credentials[$role] = ['email' => $email, 'password' => $password];
    file_put_contents($path, json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo $email . ': cuenta activa creada.' . PHP_EOL;
}
echo "Credenciales: storage/testing-accounts.json (acceso HTTP bloqueado).\n";
