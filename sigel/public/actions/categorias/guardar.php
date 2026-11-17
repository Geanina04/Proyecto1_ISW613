<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$pdo    = db();
$id     = enteroEnRango($_POST['id'] ?? 0, 0, PHP_INT_MAX) ?? 0;
$nombre = trim((string)($_POST['nombre'] ?? ''));
$dias   = enteroEnRango($_POST['dias_max_prestamo'] ?? '', 1, 60);

$errores = [];
if ($e = validarTexto($nombre, 'El nombre', 2, 80)) $errores[] = $e;
if ($dias === null) $errores[] = 'Los días máximos deben ser un número entero entre 1 y 60.';

$st = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE nombre = :n AND id <> :id');
$st->execute([':n' => $nombre, ':id' => $id]);
if ($st->fetchColumn() > 0) $errores[] = 'Ya existe una categoría con ese nombre.';

$antes = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT id, nombre, dias_max_prestamo FROM categorias WHERE id = :id AND eliminado_en IS NULL');
    $st->execute([':id' => $id]);
    $antes = $st->fetch();
    if (!$antes) {
        flash('error', 'La categoría no existe.');
        redirigir('/pages/categorias/listar.php');
    }
}

if ($errores) {
    guardarFormulario($_POST, $errores);
    redirigir('/pages/categorias/form.php' . ($id ? "?id=$id" : ''));
}

$despues = ['nombre' => $nombre, 'dias_max_prestamo' => $dias];
if ($id === 0) {
    $st = $pdo->prepare('INSERT INTO categorias (nombre, dias_max_prestamo) VALUES (:n, :d)');
    $st->execute([':n' => $nombre, ':d' => $dias]);
    registrarBitacora('crear', 'categorias', (int)$pdo->lastInsertId(), null, $despues);
    flash('ok', 'Categoría creada.');
} else {
    $st = $pdo->prepare('UPDATE categorias SET nombre = :n, dias_max_prestamo = :d WHERE id = :id');
    $st->execute([':n' => $nombre, ':d' => $dias, ':id' => $id]);
    registrarBitacora('editar', 'categorias', $id, $antes, $despues);
    flash('ok', 'Categoría actualizada.');
}
redirigir('/pages/categorias/listar.php');
