<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

$id = getInt('id');
$categoria = ['nombre' => '', 'dias_max_prestamo' => 7];
if ($id > 0) {
    $st = db()->prepare('SELECT id, nombre, dias_max_prestamo FROM categorias WHERE id = :id AND eliminado_en IS NULL');
    $st->execute([':id' => $id]);
    $categoria = $st->fetch();
    if (!$categoria) {
        flash('error', 'La categoría no existe.');
        redirigir('listar.php');
    }
}
[$datos, $errores] = recuperarFormulario();
$categoria = array_merge($categoria, $datos);

$titulo = $id ? 'Editar categoría' : 'Nueva categoría';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1><?= e($titulo) ?></h1>
<?php if ($errores): ?>
    <div class="alerta alerta-error"><ul class="errores"><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" action="/actions/categorias/guardar.php" class="tarjeta" novalidate>
    <?= campoCsrf() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <label>Nombre <input type="text" name="nombre" maxlength="80" required value="<?= e($categoria['nombre']) ?>"></label>
    <label>Días máximos de préstamo (1 a 60)
        <input type="number" name="dias_max_prestamo" min="1" max="60" required value="<?= e($categoria['dias_max_prestamo']) ?>">
    </label>
    <button type="submit">Guardar</button>
    <a class="btn btn-sec" href="listar.php">Cancelar</a>
</form>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
