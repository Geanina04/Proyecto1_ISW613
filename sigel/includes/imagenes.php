<?php
/**
 * imagenes.php - subida segura de imágenes de equipos.
 * Controles: límite de tamaño, lista blanca de extensiones, tipo MIME REAL con finfo
 * (no se confía en el tipo que manda el navegador) y nombre de archivo generado por el servidor.
 * Las imágenes se guardan en storage/imagenes (fuera de public/) y se sirven con public/imagen.php.
 */

const MIME_PERMITIDOS = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp'],
];
const MAX_IMAGENES_POR_ENVIO = 5;

/** Convierte $_FILES['campo'] (múltiple) en una lista de archivos individuales. */
function normalizarArchivos(?array $archivos): array
{
    if (!$archivos || !is_array($archivos['name'] ?? null)) return [];
    $lista = [];
    foreach ($archivos['name'] as $i => $nombre) {
        if ($archivos['error'][$i] === UPLOAD_ERR_NO_FILE) continue;   // campo vacío
        $lista[] = [
            'name'     => (string)$nombre,
            'tmp_name' => $archivos['tmp_name'][$i],
            'error'    => $archivos['error'][$i],
            'size'     => (int)$archivos['size'][$i],
        ];
    }
    return $lista;
}

/**
 * Valida las imágenes. Devuelve [lista de imágenes válidas, lista de errores].
 * Cada imagen válida trae: tmp_name, mime, extension.
 */
function validarImagenes(array $archivos): array
{
    global $CONFIG;
    $validas = [];
    $errores = [];

    if (count($archivos) > MAX_IMAGENES_POR_ENVIO) {
        return [[], ['Puede subir como máximo ' . MAX_IMAGENES_POR_ENVIO . ' imágenes a la vez.']];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($archivos as $a) {
        $nombre = mb_substr($a['name'], 0, 60);   // se escapa al mostrarse
        if ($a['error'] !== UPLOAD_ERR_OK) {
            $errores[] = "No se pudo subir \"$nombre\".";
            continue;
        }
        if ($a['size'] <= 0 || $a['size'] > $CONFIG['max_tamano_imagen']) {
            $errores[] = "\"$nombre\" supera el tamaño máximo de 2 MB.";
            continue;
        }
        $ext = strtolower(pathinfo($a['name'], PATHINFO_EXTENSION));
        $todasLasExt = array_merge(...array_values(MIME_PERMITIDOS));
        if (!in_array($ext, $todasLasExt, true)) {
            $errores[] = "\"$nombre\": solo se permiten imágenes JPG, PNG o WEBP.";
            continue;
        }
        if (!is_uploaded_file($a['tmp_name'])) {
            $errores[] = "\"$nombre\" no es un archivo subido válido.";
            continue;
        }
        $mime = $finfo->file($a['tmp_name']);
        if (!isset(MIME_PERMITIDOS[$mime]) || !in_array($ext, MIME_PERMITIDOS[$mime], true)) {
            $errores[] = "\"$nombre\": el contenido real del archivo no es una imagen permitida.";
            continue;
        }
        $validas[] = ['tmp_name' => $a['tmp_name'], 'mime' => $mime, 'extension' => MIME_PERMITIDOS[$mime][0]];
    }
    return [$validas, $errores];
}

/**
 * Mueve las imágenes a storage/ con un nombre aleatorio y las registra en la BD.
 * Devuelve la lista de archivos guardados (por si hay que borrarlos tras un rollback).
 */
function guardarImagenes(int $equipoId, array $imagenes): array
{
    $guardados = [];
    $st = db()->prepare('INSERT INTO equipo_imagenes (equipo_id, archivo, mime) VALUES (:e, :a, :m)');
    foreach ($imagenes as $img) {
        $archivo = bin2hex(random_bytes(16)) . '.' . $img['extension'];   // nombre generado por el servidor
        if (!move_uploaded_file($img['tmp_name'], DIR_IMAGENES . '/' . $archivo)) {
            throw new RuntimeException('No se pudo mover la imagen subida.');
        }
        $guardados[] = $archivo;
        $st->execute([':e' => $equipoId, ':a' => $archivo, ':m' => $img['mime']]);
    }
    return $guardados;
}
