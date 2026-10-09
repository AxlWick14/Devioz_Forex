<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Access.php';

class UsuariosController extends Controller
{
    public function index(): void {
        if (!Access::exigir('admin/usuarios')) return;
        $model = new Usuario();
        $editing = null;
        $editId = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId) $editing = $model->buscar($editId);
        $flash = $_SESSION['users_flash'] ?? null;
        unset($_SESSION['users_flash']);
        $this->view('admin/usuarios', ['titulo' => 'Administración de usuarios', 'usuarios' => $model->listar(), 'editing' => $editing, 'flash' => $flash]);
    }
    public function guardar(): void { $this->mutar(false); }
    public function eliminar(): void { $this->mutar(true); }
    private function mutar(bool $delete): void {
        if (!Access::exigir($delete ? 'admin/usuarios/eliminar' : 'admin/usuarios/guardar')) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        if (!Auth::validarCsrf()) { http_response_code(403); echo 'Formulario inválido. Recarga la página.'; return; }
        try {
            foreach (['id','nombre','email','role','is_active','password'] as $key)
                if (isset($_POST[$key]) && !is_string($_POST[$key])) throw new InvalidArgumentException('Formulario inválido.');
            $rawId = $_POST['id'] ?? '';
            $id = $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || ($delete && $id === null)) throw new InvalidArgumentException('Usuario inválido.');
            $model = new Usuario();
            if ($delete) $model->eliminar($id, (int) Auth::usuario()['id']);
            else $model->guardar($_POST, $id, (int) Auth::usuario()['id']);
            $_SESSION['users_flash'] = ['ok' => true, 'message' => $delete ? 'Usuario eliminado.' : 'Usuario guardado.'];
        } catch (InvalidArgumentException $error) { $_SESSION['users_flash'] = ['ok' => false, 'message' => $error->getMessage()]; }
        catch (PDOException $error) { $_SESSION['users_flash'] = ['ok' => false, 'message' => $error->getCode() === '23000' ? 'El correo ya está registrado.' : 'No se pudo guardar el usuario.']; }
        catch (RuntimeException $error) { $_SESSION['users_flash'] = ['ok' => false, 'message' => $error->getMessage()]; }
        header('Location: ' . BASE_URL . 'admin/usuarios', true, 303);
    }
    public function permisos(): void {
        if (!Access::exigir('admin/permisos')) return;
        $this->view('admin/permisos', ['titulo' => 'Roles y permisos']);
    }
}
