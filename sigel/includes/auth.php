<?php
/**
 * auth.php - sesiones, inicio/cierre de sesión, "Recordarme" y control de roles.
 */

const NOMBRE_COOKIE_RECORDARME = 'sigel_remember';
const ROLES_CON_ACCESO = ['admin', 'operador'];   // los profesores NO ingresan al sistema

/** Configura la cookie de sesión (HttpOnly, SameSite, Secure) y arranca la sesión. */
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SIGELSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Cierra la sesión si pasaron más de X minutos sin actividad. */
function verificarInactividad(): void
{
    global $CONFIG;
    if (!isset($_SESSION['usuario'])) return;

    $limite = $CONFIG['minutos_inactividad'] * 60;
    if (isset($_SESSION['ultimo_acceso']) && time() - $_SESSION['ultimo_acceso'] > $limite) {
        destruirSesion();
        iniciarSesion();
        flash('aviso', 'La sesión expiró por inactividad.');
        return;
    }
    $_SESSION['ultimo_acceso'] = time();
}

/** Deja al usuario autenticado en la sesión. Siempre regenera el id (evita fijación de sesión). */
function establecerSesion(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int)$usuario['id'],
        'nombre' => $usuario['nombre'],
        'rol'    => $usuario['rol'],
    ];
    $_SESSION['ultimo_acceso'] = time();
    unset($_SESSION['csrf_token']);    // token nuevo después de autenticar
}

function usuarioActual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function tieneRol(array $roles): bool
{
    $u = usuarioActual();
    return $u !== null && in_array($u['rol'], $roles, true);
}

/**
 * Se llama al inicio de cada página/acción protegida.
 * La validación se hace en el servidor: aunque alguien escriba la URL directamente,
 * si no tiene el rol recibe 403.
 */
function requiereRol(array $roles): void
{
    if (usuarioActual() === null) {
        flash('aviso', 'Debe iniciar sesión.');
        redirigir('/pages/login.php');
    }
    if (!tieneRol($roles)) {
        http_response_code(403);
        require RAIZ . '/includes/plantillas/encabezado.php';
        echo '<h1>Acceso denegado</h1><p>No tiene permisos para esta opción.</p>';
        require RAIZ . '/includes/plantillas/pie.php';
        exit;
    }
}

/** Busca un usuario que puede iniciar sesión (no eliminado). */
function buscarUsuarioPorCorreo(string $correo): ?array
{
    $st = db()->prepare(
        'SELECT u.id, u.nombre, u.correo, u.password_hash, u.activo, u.intentos_fallidos,
                u.bloqueado_hasta, r.nombre AS rol
           FROM usuarios u JOIN roles r ON r.id = u.rol_id
          WHERE u.correo = :c AND u.eliminado_en IS NULL'
    );
    $st->execute([':c' => $correo]);
    return $st->fetch() ?: null;
}

/**
 * Intenta autenticar. Devuelve el usuario o null.
 * - 3 intentos fallidos bloquean la cuenta por 15 minutos.
 * - El mensaje que ve el usuario es SIEMPRE el mismo (genérico).
 */
function intentarLogin(string $correo, string $password): ?array
{
    global $CONFIG;
    $usuario = buscarUsuarioPorCorreo($correo);

    if ($usuario === null) {
        // Se ejecuta password_verify igual para que el tiempo de respuesta sea parecido
        password_verify($password, '$2y$10$C7vknxmEyl18O8AHowTequ1YEuyHQqEkv9.RLrwTFQoPUypJTN.3W');
        return null;
    }

    // ¿Cuenta bloqueada temporalmente?
    if ($usuario['bloqueado_hasta'] !== null && strtotime($usuario['bloqueado_hasta']) > time()) {
        return null;
    }

    $ok = $usuario['password_hash'] !== null
        && password_verify($password, $usuario['password_hash'])
        && (int)$usuario['activo'] === 1
        && in_array($usuario['rol'], ROLES_CON_ACCESO, true);

    if (!$ok) {
        $intentos = (int)$usuario['intentos_fallidos'] + 1;
        if ($intentos >= $CONFIG['max_intentos_login']) {
            $st = db()->prepare('UPDATE usuarios SET intentos_fallidos = 0,
                                 bloqueado_hasta = NOW() + INTERVAL :m MINUTE WHERE id = :id');
            $st->execute([':m' => $CONFIG['minutos_bloqueo'], ':id' => $usuario['id']]);
            registrarBitacora('bloqueo', 'usuarios', (int)$usuario['id'], null,
                ['motivo' => 'intentos fallidos', 'minutos' => $CONFIG['minutos_bloqueo']], (int)$usuario['id']);
        } else {
            $st = db()->prepare('UPDATE usuarios SET intentos_fallidos = :i WHERE id = :id');
            $st->execute([':i' => $intentos, ':id' => $usuario['id']]);
        }
        registrarBitacora('login_fallido', 'usuarios', (int)$usuario['id'], null, ['intento' => $intentos], (int)$usuario['id']);
        return null;
    }

    // Éxito: reiniciar contador
    $st = db()->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id');
    $st->execute([':id' => $usuario['id']]);
    return $usuario;
}

// ===================== "Recordarme" (selector:validador) =====================

/** Crea un token nuevo en auth_tokens y envía la cookie selector:validador. */
function crearTokenRecordarme(int $usuarioId): void
{
    global $CONFIG;
    $selector  = bin2hex(random_bytes(9));     // 18 caracteres hex
    $validador = bin2hex(random_bytes(32));    // 64 caracteres hex
    $expira    = time() + 60 * 60 * 24 * $CONFIG['dias_recordarme'];

    $st = db()->prepare('INSERT INTO auth_tokens (usuario_id, selector, validator_hash, expira_en)
                         VALUES (:u, :s, :h, :e)');
    $st->execute([
        ':u' => $usuarioId,
        ':s' => $selector,
        ':h' => hash('sha256', $validador),    // en la BD solo va el HASH del validador
        ':e' => date('Y-m-d H:i:s', $expira),
    ]);

    setcookie(NOMBRE_COOKIE_RECORDARME, "$selector:$validador", [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function borrarCookieRecordarme(): void
{
    setcookie(NOMBRE_COOKIE_RECORDARME, '', [
        'expires' => time() - 3600, 'path' => '/', 'secure' => esHttps(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    unset($_COOKIE[NOMBRE_COOKIE_RECORDARME]);
}

/** Separa y valida el formato de la cookie. Devuelve [selector, validador] o null. */
function leerCookieRecordarme(): ?array
{
    $valor = $_COOKIE[NOMBRE_COOKIE_RECORDARME] ?? '';
    if (!is_string($valor) || !preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/', $valor, $m)) {
        return null;
    }
    return [$m[1], $m[2]];
}

/**
 * Si no hay sesión pero existe la cookie "Recordarme": busca por selector, compara el hash
 * del validador con hash_equals(), revisa expiración y, si todo está bien, ROTA el token
 * (borra el viejo y crea uno nuevo) e inicia una sesión nueva.
 */
function loginDesdeCookieRecordarme(): void
{
    if (usuarioActual() !== null || !isset($_COOKIE[NOMBRE_COOKIE_RECORDARME])) return;

    $partes = leerCookieRecordarme();
    if ($partes === null) {
        borrarCookieRecordarme();
        return;
    }
    [$selector, $validador] = $partes;

    $st = db()->prepare(
        'SELECT t.id AS token_id, t.validator_hash, t.expira_en, u.id, u.nombre, u.activo, r.nombre AS rol
           FROM auth_tokens t
           JOIN usuarios u ON u.id = t.usuario_id
           JOIN roles r    ON r.id = u.rol_id
          WHERE t.selector = :s AND u.eliminado_en IS NULL'
    );
    $st->execute([':s' => $selector]);
    $fila = $st->fetch();

    $valido = $fila
        && hash_equals($fila['validator_hash'], hash('sha256', $validador))
        && strtotime($fila['expira_en']) > time()
        && (int)$fila['activo'] === 1
        && in_array($fila['rol'], ROLES_CON_ACCESO, true);

    if ($fila) {
        // Se borra siempre el token usado: si era válido se rota, si no, se invalida.
        db()->prepare('DELETE FROM auth_tokens WHERE id = :id')->execute([':id' => $fila['token_id']]);
    }

    if (!$valido) {
        borrarCookieRecordarme();
        return;
    }

    establecerSesion($fila);
    crearTokenRecordarme((int)$fila['id']);    // rotación del token
    registrarBitacora('login_recordarme', 'usuarios', (int)$fila['id']);
}

/** Cierra sesión: revoca el token en BD, borra cookie "Recordarme", destruye sesión y su cookie. */
function cerrarSesion(): void
{
    $partes = leerCookieRecordarme();
    if ($partes !== null) {
        db()->prepare('DELETE FROM auth_tokens WHERE selector = :s')->execute([':s' => $partes[0]]);
    }
    borrarCookieRecordarme();
    destruirSesion();
}

function destruirSesion(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
        'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
    ]);
    session_destroy();
}
