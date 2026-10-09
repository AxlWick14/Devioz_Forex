<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Access.php';
require_once __DIR__ . '/../models/Plataforma.php';

class AdministracionController extends Controller
{
    public function index(): void
    {
        if (!Access::exigir('admin')) return;
        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);
        try { $resumen = (new Plataforma())->resumen(); }
        catch (PDOException $error) {
            http_response_code(503);
            $resumen = null;
            $flash = ['ok' => false, 'mensaje' => 'No se pudo consultar el estado de la plataforma. Reintenta cuando MySQL esté disponible.'];
        }
        $this->view('admin/index', ['titulo' => 'Administración | Forex System', 'resumen' => $resumen, 'flash' => $flash]);
    }
    public function actualizarHistorico(): void
    {
        if (!Access::exigir('admin/historico/actualizar')) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        if (!Auth::validarCsrf()) { http_response_code(403); echo 'Formulario inválido. Recarga la página.'; return; }
        $par = $_POST['par'] ?? null;
        if (!is_string($par)) { http_response_code(400); echo 'Activo inválido.'; return; }
        try {
            $historial = new Historial();
            if (!in_array($par, $historial->listarSimbolos(), true)) { http_response_code(400); echo 'Activo inválido.'; return; }
            // Reuse the shared lock and retry window; clicks cannot bypass provider limits.
            $result = $historial->actualizarDiariamente($par);
            $ok = ($result['estado'] ?? '') === 'ok';
            $_SESSION['admin_flash'] = ['ok' => $ok, 'mensaje' => $par . ': ' . ($result['mensaje'] ?? ($ok ? 'Último cierre disponible: ' . $result['ultima_fecha'] : 'Actualización en curso.'))];
        } catch (RuntimeException $error) {
            $_SESSION['admin_flash'] = ['ok' => false, 'mensaje' => $error instanceof PDOException ? 'No se pudo acceder a MySQL.' : $error->getMessage()];
        }
        header('Location: ' . BASE_URL . 'admin#historicos', true, 303);
    }
}
