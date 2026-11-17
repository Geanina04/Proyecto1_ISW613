<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$id = enteroEnRango($_POST['id'] ?? '', 1, PHP_INT_MAX);
if ($id === null || $id === usuarioActual()['id']) {
    flash('error', 'No se puede eliminar ese usuario.');
    redirigir('/pages/usuarios/listar.php');
}

$pdo = db();
$st = $pdo->prepare('SELECT id, nombre, correo, rol_id, activo FROM usuarios WHERE id = :id AND eliminado_en IS NULL');
$st->execute([':id' => $id]);
$antes = $st->fetch();

if ($antes) {
    // Eliminación lógica: se marca la fecha, el registro sigue en la BD.
    // Se modifica el correo para liberar el UNIQUE (el original queda en la bitácora).
    $pdo->prepare("UPDATE usuarios SET eliminado_en = NOW(), activo = 0,
                   correo = CONCAT(LEFT(correo, 120), '#eliminado', id) WHERE id = :id")->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM auth_tokens WHERE usuario_id = :id')->execute([':id' => $id]);
    registrarBitacora('eliminar', 'usuarios', $id, $antes, ['eliminado_en' => date('Y-m-d H:i:s')]);
    flash('ok', 'Usuario eliminado.');
}
redirigir('/pages/usuarios/listar.php');
