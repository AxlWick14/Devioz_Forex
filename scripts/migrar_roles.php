<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';

$db = null;
$locked = false;
try {
    $db = (new Database())->getConnection();
    $locked = (int) $db->query("SELECT GET_LOCK('forex_usuarios_admin', 5)")->fetchColumn() === 1;
    if (!$locked) throw new RuntimeException('Otra operación de cuentas está en curso. Reintenta.');
    $column = $db->query("SHOW COLUMNS FROM usuarios WHERE Field='role'")->fetch();
    if (!$column || !in_array($column['Type'], ["enum('admin','superuser')", "enum('admin','superuser','user')", "enum('admin','user')"], true))
        throw new RuntimeException('Estructura de roles desconocida. No se modificó.');
    $before = $db->query('SELECT * FROM usuarios ORDER BY id')->fetchAll();
    foreach ($before as $row) {
        if (!in_array($row['role'], ['admin', 'superuser', 'user'], true))
            throw new RuntimeException('Existe un rol desconocido. No se modificó.');
    }
    if ($column['Type'] === "enum('admin','user')" && $column['Default'] === 'user') {
        echo "Migración de roles ya aplicada: Administrador, Usuario e Invitado (sin sesión).\n";
    } else {
        $directory = __DIR__ . '/../storage/backups';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('No se pudo crear el directorio de respaldo.');
        $snapshot = [
            'schema' => $db->query('SHOW CREATE TABLE usuarios')->fetch(PDO::FETCH_NUM)[1],
            'roles' => array_map(static fn(array $row): array => ['id' => $row['id'], 'role' => $row['role']], $before),
        ];
        $backup = $directory . '/roles-before-user-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
        if (file_put_contents($backup, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false)
            throw new RuntimeException('No se pudo guardar el respaldo. No se modificó.');
        $sql = file_get_contents(__DIR__ . '/../database/migrations/002_roles_usuario.sql');
        if ($sql === false) throw new RuntimeException('No se pudo leer la migración.');
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') $db->exec($statement);
        }
        $expected = array_map(static function (array $row): array {
            if ($row['role'] === 'superuser') $row['role'] = 'user';
            return $row;
        }, $before);
        $after = $db->query('SELECT * FROM usuarios ORDER BY id')->fetchAll();
        if ($expected !== $after) throw new RuntimeException('La verificación de las cuentas detectó diferencias adicionales. Revisa el respaldo de roles.');
        echo 'Migración aplicada. ' . count($after) . " cuentas conservadas; correos, contraseñas, estados y sesiones sin cambios.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($db && $locked) $db->query("SELECT RELEASE_LOCK('forex_usuarios_admin')");
}
