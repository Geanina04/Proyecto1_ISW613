<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$pdo = db();
$id       = enteroEnRango($_POST['id'] ?? 0, 0, PHP_INT_MAX) ?? 0;
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$correo   = mb_strtolower(trim((string)($_POST['correo'] ?? '')));
$rolId    = enteroEnRango($_POST['rol_id'] ?? '', 1, PHP_INT_MAX);
$password = (string)($_POST['password'] ?? '');
$activo   = ($_POST['activo'] ?? '') === '1' ? 1 : 0;

$volver = '/pages/usuarios/form.php' . ($id ? "?id=$id" : '');

// ---------- Validaciones ----------
$errores = [];
if ($e = validarTexto($nombre, 'El nombre', 3, 100)) $errores[] = $e;
if ($e = validarCorreo($correo)) $errores[] = $e;

$rol = null;
if ($rolId !== null) {
    $st = $pdo->prepare('SELECT nombre FROM roles WHERE id = :id');
    $st->execute([':id' => $rolId]);
    $rol = $st->fetchColumn() ?: null;
}
if ($rol === null) $errores[] = 'Seleccione un rol válido.';

$antes = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT id, nombre, correo, rol_id, activo, password_hash FROM usuarios WHERE id = :id AND eliminado_en IS NULL');
    $st->execute([':id' => $id]);
    $antes = $st->fetch();
    if (!$antes) {
        flash('error', 'El usuario no existe.');
        redirigir('/pages/usuarios/listar.php');
    }
    if ($id === usuarioActual()['id'] && ($rol !== 'admin' || !$activo)) {
        $errores[] = 'No puede quitarse a sí mismo el rol de administrador ni desactivarse.';
    }
}

// Contraseña: obligatoria para admin/operador nuevos (o si antes era profesor y no tenía)
$necesitaPassword = in_array($rol, ROLES_CON_ACCESO, true) && ($id === 0 || empty($antes['password_hash']));
if ($rol !== 'profesor' && ($password !== '' || $necesitaPassword)) {
    if ($e = validarPassword($password)) $errores[] = $e;
}

// Correo único
$st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = :c AND id <> :id');
$st->execute([':c' => $correo, ':id' => $id]);
if ($st->fetchColumn() > 0) $errores[] = 'Ya existe un usuario con ese correo.';

if ($errores) {
    guardarFormulario(['activo' => $activo] + $_POST, $errores);
    redirigir($volver);
}

// ---------- Guardar ----------
if ($rol === 'profesor') {
    $hash = null;                                       // profesores no inician sesión
} elseif ($password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
} else {
    $hash = $antes['password_hash'] ?? null;            // se conserva la actual
}

$despues = ['nombre' => $nombre, 'correo' => $correo, 'rol_id' => $rolId, 'activo' => $activo];

if ($id === 0) {
    $st = $pdo->prepare('INSERT INTO usuarios (rol_id, nombre, correo, password_hash, activo)
                         VALUES (:r, :n, :c, :h, :a)');
    $st->execute([':r' => $rolId, ':n' => $nombre, ':c' => $correo, ':h' => $hash, ':a' => $activo]);
    $id = (int)$pdo->lastInsertId();
    registrarBitacora('crear', 'usuarios', $id, null, $despues);
    flash('ok', 'Usuario creado.');
} else {
    // Al editar también se desbloquea la cuenta (reinicia intentos fallidos)
    $st = $pdo->prepare('UPDATE usuarios SET rol_id = :r, nombre = :n, correo = :c, password_hash = :h,
                         activo = :a, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id');
    $st->execute([':r' => $rolId, ':n' => $nombre, ':c' => $correo, ':h' => $hash, ':a' => $activo, ':id' => $id]);
    if ($password !== '') $despues['password'] = '(cambiada)';
    registrarBitacora('editar', 'usuarios', $id, $antes, $despues);
    // Si se cambió la clave o se desactivó, se revocan sus tokens de "Recordarme"
    if ($password !== '' || !$activo || $rol === 'profesor') {
        $pdo->prepare('DELETE FROM auth_tokens WHERE usuario_id = :id')->execute([':id' => $id]);
    }
    flash('ok', 'Usuario actualizado.');
}
redirigir('/pages/usuarios/listar.php');   // Post/Redirect/Get
