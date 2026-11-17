<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

$id = getInt('id');
$usuario = ['nombre' => '', 'correo' => '', 'rol_id' => '', 'activo' => 1];
if ($id > 0) {
    $st = db()->prepare('SELECT id, nombre, correo, rol_id, activo, bloqueado_hasta FROM usuarios
                          WHERE id = :id AND eliminado_en IS NULL');
    $st->execute([':id' => $id]);
    $usuario = $st->fetch();
    if (!$usuario) {
        flash('error', 'El usuario no existe.');
        redirigir('listar.php');
    }
}
[$datos, $errores] = recuperarFormulario();
$usuario = array_merge($usuario, $datos);    // si hubo error, se muestran los datos que escribió
$roles = db()->query('SELECT id, nombre FROM roles ORDER BY id')->fetchAll();

$titulo = $id ? 'Editar usuario' : 'Nuevo usuario';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1><?= e($titulo) ?></h1>
<?php if ($errores): ?>
    <div class="alerta alerta-error"><ul class="errores"><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="/actions/usuarios/guardar.php" class="tarjeta" novalidate>
    <?= campoCsrf() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <label>Nombre <input type="text" name="nombre" maxlength="100" required value="<?= e($usuario['nombre']) ?>"></label>
    <label>Correo <input type="email" name="correo" maxlength="150" required value="<?= e($usuario['correo']) ?>"></label>
    <label>Rol
        <select name="rol_id" id="rol_id" required>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['id'] ?>" data-rol="<?= e($r['nombre']) ?>"
                    <?= (string)$r['id'] === (string)$usuario['rol_id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div id="bloque-password">
        <label>Contraseña <?= $id ? '(dejar en blanco para no cambiarla)' : '' ?>
            <input type="password" name="password" maxlength="72" autocomplete="new-password">
        </label>
        <small>Solo administradores y operadores inician sesión. Los profesores no usan contraseña.</small>
    </div>
    <label class="check"><input type="checkbox" name="activo" value="1" <?= !empty($usuario['activo']) ? 'checked' : '' ?>> Activo</label>
    <?php if ($id && !empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()): ?>
        <div class="alerta alerta-aviso">Cuenta bloqueada por intentos fallidos. Al guardar se desbloquea.</div>
    <?php endif; ?>
    <button type="submit">Guardar</button>
    <a class="btn btn-sec" href="listar.php">Cancelar</a>
</form>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
