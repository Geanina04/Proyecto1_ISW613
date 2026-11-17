<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

$id = getInt('id');
$equipo = ['codigo' => '', 'nombre' => '', 'categoria_id' => '', 'estado' => 'disponible', 'version' => 1];
$imagenes = [];
if ($id > 0) {
    $st = db()->prepare('SELECT id, codigo, nombre, categoria_id, estado, version FROM equipos WHERE id = :id AND eliminado_en IS NULL');
    $st->execute([':id' => $id]);
    $equipo = $st->fetch();
    if (!$equipo) {
        flash('error', 'El equipo no existe.');
        redirigir('listar.php');
    }
    $st = db()->prepare('SELECT id FROM equipo_imagenes WHERE equipo_id = :id ORDER BY id');
    $st->execute([':id' => $id]);
    $imagenes = $st->fetchAll();
}
[$datos, $errores] = recuperarFormulario();
unset($datos['version']);    // la versión siempre se toma de la BD
$equipo = array_merge($equipo, $datos);
$categorias = db()->query('SELECT id, nombre FROM categorias WHERE eliminado_en IS NULL ORDER BY nombre')->fetchAll();

$titulo = $id ? 'Editar equipo' : 'Nuevo equipo';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1><?= e($titulo) ?></h1>
<?php if ($errores): ?>
    <div class="alerta alerta-error"><ul class="errores"><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="/actions/equipos/guardar.php" enctype="multipart/form-data" class="tarjeta" novalidate>
    <?= campoCsrf() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="version" value="<?= (int)$equipo['version'] ?>">
    <div class="grid-2">
        <label>Código (ej. LAP-005)
            <input type="text" name="codigo" maxlength="30" required pattern="[A-Za-z0-9\-]+" value="<?= e($equipo['codigo']) ?>">
        </label>
        <label>Nombre <input type="text" name="nombre" maxlength="100" required value="<?= e($equipo['nombre']) ?>"></label>
        <label>Categoría
            <select name="categoria_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (string)$c['id'] === (string)$equipo['categoria_id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Estado
            <select name="estado">
                <?php foreach (['disponible', 'mantenimiento', 'baja'] as $op): ?>
                    <option value="<?= $op ?>" <?= $equipo['estado'] === $op ? 'selected' : '' ?>><?= ucfirst($op) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label>Agregar imágenes (JPG, PNG o WEBP, máximo 2 MB c/u, hasta 5)
        <input type="file" name="imagenes[]" accept=".jpg,.jpeg,.png,.webp" multiple>
    </label>
    <button type="submit">Guardar</button>
    <a class="btn btn-sec" href="listar.php">Cancelar</a>
</form>

<?php if ($imagenes): ?>
<h2>Imágenes actuales</h2>
<div class="galeria">
    <?php foreach ($imagenes as $img): ?>
        <figure>
            <img src="/imagen.php?id=<?= (int)$img['id'] ?>" alt="Imagen del equipo">
            <form method="post" action="/actions/equipos/imagen_eliminar.php" data-confirmar="¿Eliminar esta imagen?">
                <?= campoCsrf() ?>
                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                <button class="btn-chico btn-peligro">Quitar</button>
            </form>
        </figure>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
