<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$pdo = db();
$id  = enteroEnRango($_POST['id'] ?? '', 1, PHP_INT_MAX);

$st = $pdo->prepare('SELECT id, nombre, dias_max_prestamo FROM categorias WHERE id = :id AND eliminado_en IS NULL');
$st->execute([':id' => $id ?? 0]);
$antes = $st->fetch();

if (!$antes) {
    flash('error', 'La categoría no existe.');
    redirigir('/pages/categorias/listar.php');
}

// No se permite eliminar una categoría que todavía tiene equipos
$st = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE categoria_id = :id AND eliminado_en IS NULL');
$st->execute([':id' => $id]);
if ($st->fetchColumn() > 0) {
    flash('error', 'No se puede eliminar: la categoría tiene equipos asociados.');
    redirigir('/pages/categorias/listar.php');
}

// Eliminación lógica. Se cambia el nombre para liberar el UNIQUE y poder reutilizarlo.
$st = $pdo->prepare("UPDATE categorias SET eliminado_en = NOW(), nombre = CONCAT(LEFT(nombre, 55), ' [eliminada ', id, ']') WHERE id = :id");
$st->execute([':id' => $id]);
registrarBitacora('eliminar', 'categorias', $id, $antes, ['eliminado_en' => date('Y-m-d H:i:s')]);
flash('ok', 'Categoría eliminada.');
redirigir('/pages/categorias/listar.php');
