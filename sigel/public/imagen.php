<?php
/**
 * Sirve una imagen de equipo. Las imágenes están fuera de public/, por lo que no
 * tienen URL directa: solo usuarios autenticados las pueden ver a través de este script.
 */
require __DIR__ . '/../includes/bootstrap.php';
requiereRol(['admin', 'operador']);

$id = getInt('id');
$st = db()->prepare('SELECT archivo, mime FROM equipo_imagenes WHERE id = :id');
$st->execute([':id' => $id]);
$img = $st->fetch();

// basename() evita cualquier intento de salir del directorio (../)
$ruta = $img ? DIR_IMAGENES . '/' . basename($img['archivo']) : '';
if (!$img || !is_file($ruta)) {
    http_response_code(404);
    exit('Imagen no encontrada');
}
header('Content-Type: ' . $img['mime']);
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=3600');
readfile($ruta);
