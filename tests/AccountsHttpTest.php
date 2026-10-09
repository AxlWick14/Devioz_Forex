<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/core/Database.php';
function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$base = 'http://localhost/forex_system_realtime/public/';
$db = (new Database())->getConnection();
$nonce = bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(12));
$ids = [];
$clients = [];
function client(): CurlHandle { global $clients; $c = curl_init(); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]); $clients[] = $c; return $c; }
function request(CurlHandle $c, string $route, ?array $post = null): array {
    global $base;
    curl_setopt_array($c, [CURLOPT_URL => $base . $route, CURLOPT_POST => $post !== null, CURLOPT_HEADER => true]);
    if ($post !== null) curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    else curl_setopt($c, CURLOPT_HTTPGET, true);
    $raw = curl_exec($c);
    if ($raw === false) throw new RuntimeException(curl_error($c));
    $size = curl_getinfo($c, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($c, CURLINFO_HTTP_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}
function csrf(array $response): string {
    expect((bool) preg_match('/name="csrf" value="([a-f0-9]+)"/', $response['body'], $m), 'Token CSRF ausente'); return $m[1];
}
function login(CurlHandle $c, string $email, string $password): void {
    $page = request($c, 'login');
    $result = request($c, 'login', ['csrf' => csrf($page), 'email' => $email, 'password' => $password]);
    expect($result['status'] === 303, 'Inicio de sesión fallido: ' . $result['status']);
}
try {
    foreach (['admin','user'] as $role) {
        $db->prepare('INSERT INTO usuarios (nombre,email,password_hash,role,is_active) VALUES (?,?,?,?,1)')->execute(['Prueba ' . $nonce, "$role-$nonce@example.test", password_hash($password, PASSWORD_DEFAULT), $role]);
        $ids[$role] = (int) $db->lastInsertId();
    }
    $guest = client();
    foreach (['','mercado','calculadora','historial'] as $route) expect(request($guest, $route)['status'] === 200, 'Página pública bloqueada: ' . $route);
    $home = request($guest, '');
    expect(!str_contains($home['body'], '>OVERVIEW<') && !str_contains($home['body'], 'class="admin-enchanted"'), 'Menú privado visible al invitado');
    foreach (['dashboard','pronosticos','etl','admin','admin/historico/actualizar','admin/usuarios','admin/permisos','bitcoin-predictor/'] as $route) expect(request($guest, $route)['status'] === 303, 'Invitado accede a ' . $route);
    foreach (['pronosticos-data','historial-sync','bitcoin-predictor/obtener_prediccion.php'] as $route) expect(request($guest, $route)['status'] === 401, 'API privada abierta: ' . $route);
    expect(request($guest, 'login', ['email' => 'x@example.test','password' => $password])['status'] === 403, 'Login permite CSRF');
    $loginPage = request($guest, 'login');
    expect(!str_contains($loginPage['body'], 'testing@forex.local') && !str_contains($loginPage['body'], 'Usuarios de prueba') && !str_contains($loginPage['body'], 'testing-account'), 'Credenciales de prueba visibles en el login');
    expect(str_contains($loginPage['body'], 'registro'), 'Enlace de registro ausente');
    $registration = request($guest, 'registro');
    expect($registration['status'] === 200, 'Registro público bloqueado');
    $registrationToken = csrf($registration);
    $registrationEmail = "registro-$nonce@example.test";
    expect(request($guest, 'registro', ['email'=>$registrationEmail,'password'=>$password])['status'] === 403, 'Registro permite CSRF');
    foreach ([['email'=>'invalido','password'=>$password], ['email'=>$registrationEmail,'password'=>'corta'], ['email'=>$registrationEmail,'password'=>str_repeat('x', 73)], ['email'=>['invalido'],'password'=>$password]] as $invalid) {
        expect(request($guest, 'registro', ['csrf'=>$registrationToken] + $invalid)['status'] === 400, 'Registro admite datos inválidos');
    }
    $registered = request($guest, 'registro', ['csrf'=>$registrationToken,'email'=>strtoupper($registrationEmail),'password'=>$password,'role'=>'admin','is_active'=>'0']);
    expect($registered['status'] === 303 && str_contains($registered['headers'], 'login?registrado=1'), 'Registro no redirige al login');
    $registrationFind = $db->prepare('SELECT * FROM usuarios WHERE email=?');
    $registrationFind->execute([$registrationEmail]); $registrationUser = $registrationFind->fetch();
    if ($registrationUser) $ids['registered'] = (int) $registrationUser['id'];
    expect((bool) $registrationUser && password_verify($password, $registrationUser['password_hash']) && $registrationUser['password_hash'] !== $password, 'Registro no guarda contraseña cifrada');
    expect($registrationUser['role'] === 'user' && (int) $registrationUser['is_active'] === 1, 'Registro permite modificar permisos');
    expect(request($guest, 'registro', ['csrf'=>$registrationToken,'email'=>$registrationEmail,'password'=>$password])['status'] === 409, 'Registro acepta correo duplicado');
    $registeredClient = client(); login($registeredClient, $registrationEmail, $password);
    expect(request($registeredClient, 'dashboard')['status'] === 200, 'Cuenta registrada no puede acceder');
    expect(request($registeredClient, 'admin/usuarios')['status'] === 403, 'Cuenta registrada accede a administración');
    foreach (['../storage/initial-admin.txt','../config/database.php','../app/controllers/UsuariosController.php','../scripts/crear_admin.php'] as $route) expect(request($guest, $route)['status'] === 403, 'Archivo interno público: ' . $route);
    $super = client(); login($super, "user-$nonce@example.test", $password);
    foreach (['dashboard','pronosticos','etl','bitcoin-predictor/'] as $route) expect(request($super, $route)['status'] === 200, 'Usuario bloqueado: ' . $route);
    foreach (['admin','admin/historico/actualizar','admin/usuarios','admin/permisos','admin/usuarios/guardar','admin/usuarios/eliminar'] as $route) expect(request($super, $route)['status'] === 403, 'Usuario accede a administración: ' . $route);
    $superHome = request($super, '');
    expect(str_contains($superHome['body'], '>OVERVIEW<') && !str_contains($superHome['body'], 'class="admin-enchanted"') && str_contains($superHome['body'], '>Usuario</span>'), 'Menú usuario incorrecto');
    $admin = client(); login($admin, "admin-$nonce@example.test", $password);
    $page = request($admin, 'admin/usuarios'); expect($page['status'] === 200, 'Admin sin acceso');
    expect(str_contains($page['body'], '>Administrador</td>') && str_contains($page['body'], '>Usuario</td>') && !str_contains($page['body'], '>Superusuario</td>') && !str_contains($page['body'], 'value="superuser"'), 'Nombres de roles incorrectos en cuentas');
    $permissions = request($admin, 'admin/permisos');
    expect($permissions['status'] === 200 && str_contains($permissions['body'], '<th>Administrador</th><th>Usuario</th><th>Invitado</th>') && !str_contains($permissions['body'], 'Superusuario'), 'Matriz de roles incorrecta');
    $token = csrf($page);
    $hub = request($admin, 'admin');
    expect($hub['status'] === 200 && str_contains($hub['body'], 'Estado del histórico'), 'Panel de administración no disponible');
    expect(request($admin, 'admin/historico/actualizar')['status'] === 405, 'Actualización del histórico permite GET');
    expect(request($admin, 'admin/historico/actualizar', ['par'=>'BTC/USD'])['status'] === 403, 'Actualización administrativa sin CSRF');
    expect(request($admin, 'admin/historico/actualizar', ['csrf'=>$token,'par'=>'INVALIDO'])['status'] === 400, 'Actualización de un activo inválido');
    expect(request($super, 'admin/historico/actualizar', ['csrf'=>csrf($superHome),'par'=>'BTC/USD'])['status'] === 403, 'Usuario ejecuta actualización administrativa');
    expect(request($admin, 'admin/usuarios/guardar', ['nombre' => 'x'])['status'] === 403, 'Mutación sin CSRF permitida');
    expect(request($admin, 'admin/usuarios/eliminar')['status'] === 405, 'Eliminación por GET permitida');
    $email = "nuevo-$nonce@example.test";
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'nombre'=>'Nueva cuenta','email'=>$email,'password'=>$password,'role'=>'user','is_active'=>'1']);
    $find = $db->prepare('SELECT * FROM usuarios WHERE email=?'); $find->execute([$email]); $created = $find->fetch();
    expect((bool) $created && password_verify($password, $created['password_hash']), 'Creación o hash incorrectos');
    $ids['created'] = (int) $created['id'];
    $account = client(); login($account, $email, $password);
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>'','role'=>'admin','is_active'=>'1']);
    expect(request($account, 'admin/usuarios')['status'] === 200, 'Cambio de rol no se aplica en la sesión');
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>'','role'=>'user','is_active'=>'1']);
    expect(request($account, 'admin/usuarios')['status'] === 403, 'Cambio de rol no revoca permiso');
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>'','role'=>'user','is_active'=>'0']);
    expect(request($account, 'dashboard')['status'] === 303, 'Desactivación no revoca sesión');
    $inactiveLogin = request($account, 'login');
    expect(request($account, 'login', ['csrf'=>csrf($inactiveLogin),'email'=>$email,'password'=>$password])['status'] === 401, 'Cuenta inactiva inicia sesión');
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>'','role'=>'guest','is_active'=>'1']);
    $find->execute([$email]); expect($find->fetch()['role'] === 'user', 'Rol guest almacenado');
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>'','role'=>'superuser','is_active'=>'1']);
    $find->execute([$email]); expect($find->fetch()['role'] === 'user', 'Rol anterior almacenado');
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>'','role'=>'user','is_active'=>'1']);
    login($account, $email, $password);
    request($admin, 'admin/usuarios/guardar', ['csrf'=>$token,'id'=>(string)$created['id'],'nombre'=>'Nueva cuenta','email'=>$email,'password'=>$password . 'x','role'=>'user','is_active'=>'1']);
    expect(request($account, 'dashboard')['status'] === 303, 'Contraseña nueva no revoca sesión');
    request($admin, 'admin/usuarios/eliminar', ['csrf'=>$token,'id'=>(string)$created['id']]);
    $find->execute([$email]); expect(!$find->fetch(), 'Usuario no eliminado');
    request($admin, 'admin/usuarios/eliminar', ['csrf'=>$token,'id'=>(string)$ids['admin']]);
    expect((bool) $db->query('SELECT id FROM usuarios WHERE id=' . $ids['admin'])->fetch(), 'Autoborrado permitido');
    expect(request($admin, 'logout', ['csrf'=>$token])['status'] === 303, 'Logout fallido');
    expect(request($admin, 'dashboard')['status'] === 303, 'Logout mantiene acceso');
    echo "Pruebas HTTP: permisos, URLs directas, login, CSRF, CRUD, roles y revocación de sesiones correctos.\n";
} finally {
    foreach ($ids as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
    foreach ($clients as $c) curl_close($c);
}
