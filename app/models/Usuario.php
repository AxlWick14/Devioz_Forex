<?php
require_once __DIR__ . '/../core/Database.php';

class Usuario
{
    private PDO $db;
    public function __construct() { $this->db = (new Database())->getConnection(); }
    public function buscar(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function porEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    public function registrar(string $email, string $password): int {
        $email = strtolower(trim($email));
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new InvalidArgumentException('Introduce un correo electrónico válido.');
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0"))
            throw new InvalidArgumentException('La contraseña debe tener entre 12 y 72 bytes.');
        $nombre = mb_substr(explode('@', $email, 2)[0], 0, 120);
        $stmt = $this->db->prepare("INSERT INTO usuarios (nombre,email,password_hash,role,is_active) VALUES (?,?,?,'user',1)");
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) $this->db->lastInsertId();
    }
    public function listar(): array {
        return $this->db->query('SELECT id,nombre,email,role,is_active,created_at,updated_at FROM usuarios ORDER BY id')->fetchAll();
    }
    public function guardar(array $data, ?int $id, int $actor): int {
        $nombre = trim($data['nombre'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $role = $data['role'] ?? '';
        if (!in_array($data['is_active'] ?? null, ['0','1'], true)) throw new InvalidArgumentException('Estado de cuenta inválido.');
        $active = $data['is_active'] === '1' ? 1 : 0;
        $password = $data['password'] ?? '';
        if ($nombre === '' || mb_strlen($nombre) > 120 || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !in_array($role, ['admin', 'user'], true)) throw new InvalidArgumentException('Nombre, correo o rol inválidos.');
        if (($id === null || $password !== '') && (strlen($password) < 12 || strlen($password) > 72))
            throw new InvalidArgumentException('La contraseña debe tener entre 12 y 72 bytes.');
        return $this->modificar(function () use ($id, $actor, $nombre, $email, $role, $active, $password) {
            $old = $id === null ? null : $this->buscar($id);
            if ($id !== null && !$old) throw new InvalidArgumentException('Usuario inexistente.');
            if ($old && $old['role'] === 'admin' && $old['is_active'] && ($role !== 'admin' || !$active)) $this->protegerUltimoAdmin();
            if ($id === $actor && ($role !== 'admin' || !$active)) throw new InvalidArgumentException('No puedes quitarte el acceso administrativo.');
            if ($id === null) {
                $stmt = $this->db->prepare('INSERT INTO usuarios (nombre,email,password_hash,role,is_active) VALUES (?,?,?,?,?)');
                $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
                return (int) $this->db->lastInsertId();
            }
            $params = [$nombre, $email, $role, $active];
            $sql = 'UPDATE usuarios SET nombre=?,email=?,role=?,is_active=?';
            if ($password !== '') { $sql .= ',password_hash=?'; $params[] = password_hash($password, PASSWORD_DEFAULT); }
            if ($password !== '' || (int) $old['is_active'] !== $active) $sql .= ',session_version=session_version+1';
            $sql .= ' WHERE id=?'; $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        }, $actor);
    }
    public function eliminar(int $id, int $actor): void {
        $this->modificar(function () use ($id, $actor) {
            if ($id === $actor) throw new InvalidArgumentException('No puedes eliminar tu propia cuenta.');
            $old = $this->buscar($id);
            if (!$old) throw new InvalidArgumentException('Usuario inexistente.');
            if ($old['role'] === 'admin' && $old['is_active']) $this->protegerUltimoAdmin();
            $this->db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
            return $id;
        }, $actor);
    }
    private function protegerUltimoAdmin(): void {
        if ((int) $this->db->query("SELECT COUNT(*) FROM usuarios WHERE role='admin' AND is_active=1")->fetchColumn() <= 1)
            throw new InvalidArgumentException('Debe existir al menos un administrador activo.');
    }
    private function modificar(callable $operation, int $actor): int {
        if ((int) $this->db->query("SELECT GET_LOCK('forex_usuarios_admin', 5)")->fetchColumn() !== 1)
            throw new RuntimeException('Otra operación está en curso. Reintenta.');
        try {
            $this->db->beginTransaction();
            $current = $this->buscar($actor);
            if (!$current || !$current['is_active'] || $current['role'] !== 'admin') throw new RuntimeException('Acceso administrativo revocado.');
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        } finally { $this->db->query("SELECT RELEASE_LOCK('forex_usuarios_admin')"); }
    }
}
