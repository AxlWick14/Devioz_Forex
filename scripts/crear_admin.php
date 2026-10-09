<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';
$db = (new Database())->getConnection();
if ((int) $db->query("SELECT COUNT(*) FROM usuarios WHERE role='admin' AND is_active=1")->fetchColumn() > 0) {
    echo "Ya existe un administrador activo. Gestiona las cuentas desde la aplicación.\n"; exit(0);
}
$email = $argv[1] ?? 'admin@forex.local';
$nombre = $argv[2] ?? 'Administrador';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Correo inválido.\n"); exit(1); }
$password = bin2hex(random_bytes(16));
$stmt = $db->prepare("INSERT INTO usuarios (nombre,email,password_hash,role,is_active) VALUES (?,?,?,'admin',1)");
$stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
$path = __DIR__ . '/../storage/initial-admin.txt';
file_put_contents($path, "Correo: $email\nContraseña: $password\n\nInicio de sesión: http://localhost/forex_system_realtime/public/login\nCambia la contraseña desde Administración > Usuarios y elimina este archivo después de usarlo.\n");
echo "Administrador creado. Credenciales iniciales guardadas en storage/initial-admin.txt (bloqueado por HTTP).\n";
