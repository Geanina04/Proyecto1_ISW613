<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$pdo = db();
$id  = enteroEnRango($_POST['id'] ?? '', 1, PHP_INT_MAX) ?? 0;

$st = $pdo->prepare('SELECT id, equipo_id, archivo, mime FROM equipo_imagenes WHERE id = :id');
$st->execute([':id' => $id]);
$img = $st->fetch();
if (!$img) {
    flash('error', 'La imagen no existe.');
    redirigir('/pages/equipos/listar.php');
}

$pdo->prepare('DELETE FROM equipo_imagenes WHERE id = :id')->execute([':id' => $id]);
@unlink(DIR_IMAGENES . '/' . basename($img['archivo']));
registrarBitacora('eliminar_imagen', 'equipos', (int)$img['equipo_id'], $img, null);
flash('ok', 'Imagen eliminada.');
redirigir('/pages/equipos/form.php?id=' . (int)$img['equipo_id']);
