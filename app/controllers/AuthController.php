<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Access.php';

class AuthController extends Controller
{
    public function login(): void {
        if (Auth::usuario()) { header('Location: ' . BASE_URL . 'dashboard', true, 303); return; }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::validarCsrf()) { http_response_code(403); $error = 'La sesión del formulario venció. Recarga la página.'; }
            elseif (!is_string($_POST['email'] ?? null) || !is_string($_POST['password'] ?? null)) { http_response_code(400); $error = 'Credenciales inválidas.'; }
            else {
                // A shared IP counter survives new browser sessions; concurrent requests use a file lock.
                $directory = __DIR__ . '/../../storage/auth';
                if (!is_dir($directory)) mkdir($directory, 0700, true);
                $file = fopen($directory . '/' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local') . '.json', 'c+');
                if (!$file || !flock($file, LOCK_EX)) { http_response_code(503); $error = 'No se pudo iniciar sesión. Reintenta.'; }
                else {
                    try {
                        $state = json_decode(stream_get_contents($file), true) ?: ['start' => time(), 'attempts' => 0];
                        if (time() - $state['start'] >= 900) $state = ['start' => time(), 'attempts' => 0];
                        if ($state['attempts'] >= 10) { http_response_code(429); $error = 'Demasiados intentos. Espera 15 minutos.'; }
                        else {
                            $state['attempts']++;
                            $user = (new Usuario())->porEmail(strtolower(trim($_POST['email'])));
                            $valid = password_verify($_POST['password'], $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
                            if ($valid && $user && $user['is_active'] && in_array($user['role'], ['admin','user'], true)) {
                                Auth::entrar($user);
                                $state['attempts'] = 0;
                                header('Location: ' . BASE_URL . 'dashboard', true, 303);
                            } else { http_response_code(401); $error = 'Correo o contraseña incorrectos, o cuenta inactiva.'; }
                        }
                    } catch (PDOException $exception) { http_response_code(503); $error = 'El servicio de cuentas no está disponible.'; }
                    finally {
                        rewind($file); ftruncate($file, 0); fwrite($file, json_encode($state)); flock($file, LOCK_UN); fclose($file);
                    }
                    if (Auth::usuario()) return;
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); return; }
        $this->view('auth/login', ['titulo' => 'Iniciar sesión', 'error' => $error]);
    }
    public function registro(): void {
        if (Auth::usuario()) { header('Location: ' . BASE_URL . 'dashboard', true, 303); return; }
        $error = '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::validarCsrf()) { http_response_code(403); $error = 'La sesión del formulario venció. Recarga la página.'; }
            elseif (!is_string($_POST['email'] ?? null) || !is_string($_POST['password'] ?? null)) {
                http_response_code(400); $error = 'Introduce un correo y una contraseña válidos.';
            } else {
                try {
                    (new Usuario())->registrar($email, $_POST['password']);
                    header('Location: ' . BASE_URL . 'login?registrado=1', true, 303);
                    return;
                } catch (InvalidArgumentException $exception) {
                    http_response_code(400); $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        http_response_code(409); $error = 'Ya existe una cuenta con ese correo. Inicia sesión.';
                    } else { http_response_code(503); $error = 'El servicio de cuentas no está disponible. Reintenta.'; }
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); return; }
        $this->view('auth/registro', ['titulo' => 'Crear cuenta', 'error' => $error, 'email' => $email]);
    }
    public function logout(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        if (!Auth::validarCsrf()) { http_response_code(403); echo 'Formulario inválido.'; return; }
        Auth::salir();
        header('Location: ' . BASE_URL, true, 303);
    }
}
