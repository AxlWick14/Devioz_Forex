<?php
require_once __DIR__ . '/../models/Usuario.php';

class Auth
{
    private static ?array $user = null;
    public static function iniciar(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_name('FOREXSESSID');
            session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => BASE_URL]);
            session_start();
        }
        self::$user = null;
        if (!empty($_SESSION['user_id'])) {
            try { $user = (new Usuario())->buscar((int) $_SESSION['user_id']); }
            catch (PDOException $error) { $user = null; }
            if (!$user || !$user['is_active'] || !in_array($user['role'], ['admin','user'], true)
                || (int) $user['session_version'] !== (int) ($_SESSION['session_version'] ?? 0)
                || time() - (int) ($_SESSION['last_activity'] ?? 0) > 1800) {
                self::salir();
            } else { self::$user = $user; $_SESSION['last_activity'] = time(); }
        }
    }
    public static function usuario(): ?array { return self::$user; }
    public static function rol(): string { return self::$user['role'] ?? 'guest'; }
    public static function entrar(array $user): void {
        session_regenerate_id(true);
        $_SESSION = ['user_id' => (int) $user['id'], 'session_version' => (int) $user['session_version'], 'last_activity' => time()];
        self::$user = $user;
    }
    public static function salir(): void {
        $_SESSION = [];
        self::$user = null;
        session_regenerate_id(true);
    }
    public static function csrf(): string {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function validarCsrf(): bool {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}
