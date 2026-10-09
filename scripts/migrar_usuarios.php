<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';
try {
    $db = (new Database())->getConnection();
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $snapshot = [];
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $snapshot[$table] = ['rows' => (int) $db->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn(), 'schema' => $db->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM)[1]];
    }
    if (in_array('usuarios', $tables, true)) {
        $columns = $db->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['id','nombre','email','password_hash','role','is_active','session_version','created_at','updated_at'] as $column)
            if (!in_array($column, $columns, true)) throw new RuntimeException('La tabla usuarios existente requiere una migración específica. No se modificó.');
        echo "La migración de usuarios ya está aplicada.\n";
        exit(0);
    }
    // Refuse to create a competing identity table on a different installation.
    foreach ($tables as $table) if (preg_match('/user|usuario|cuenta|auth/i', $table)) throw new RuntimeException('Existe una tabla de cuentas que debe adaptarse: ' . $table);
    $directory = __DIR__ . '/../storage/backups';
    if (!is_dir($directory)) mkdir($directory, 0700, true);
    file_put_contents($directory . '/schema-before-users-' . date('Ymd-His') . '.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $db->exec(file_get_contents(__DIR__ . '/../database/migrations/001_usuarios.sql'));
    foreach ($snapshot as $table => $before) {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        if ((int) $db->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn() !== $before['rows'])
            throw new RuntimeException('El conteo de filas cambió durante la migración: ' . $table);
    }
    echo "Migración aplicada. Tablas y registros anteriores conservados.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); }
