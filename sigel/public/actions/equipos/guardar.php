<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$pdo         = db();
$id          = enteroEnRango($_POST['id'] ?? 0, 0, PHP_INT_MAX) ?? 0;
$version     = enteroEnRango($_POST['version'] ?? '', 1, PHP_INT_MAX);
$codigo      = strtoupper(trim((string)($_POST['codigo'] ?? '')));
$nombre      = trim((string)($_POST['nombre'] ?? ''));
$categoriaId = enteroEnRango($_POST['categoria_id'] ?? '', 1, PHP_INT_MAX);
$estado      = $_POST['estado'] ?? '';
$volver      = '/pages/equipos/form.php' . ($id ? "?id=$id" : '');

// ---------- Validaciones ----------
$errores = [];
if ($e = validarTexto($codigo, 'El código', 3, 30)) {
    $errores[] = $e;
} elseif (!preg_match('/^[A-Z0-9\-]+$/', $codigo)) {
    $errores[] = 'El código solo admite letras, números y guiones.';
}
if ($e = validarTexto($nombre, 'El nombre', 3, 100)) $errores[] = $e;
if (!enLista($estado, ['disponible', 'mantenimiento', 'baja'])) $errores[] = 'Estado no válido.';

$st = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id = :id AND eliminado_en IS NULL');
$st->execute([':id' => $categoriaId ?? 0]);
if (!$st->fetchColumn()) $errores[] = 'Seleccione una categoría válida.';

$st = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE codigo = :c AND id <> :id');
$st->execute([':c' => $codigo, ':id' => $id]);
if ($st->fetchColumn() > 0) $errores[] = 'Ya existe un equipo con ese código.';

[$imagenes, $erroresImg] = validarImagenes(normalizarArchivos($_FILES['imagenes'] ?? null));
$errores = array_merge($errores, $erroresImg);

$antes = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT id, codigo, nombre, categoria_id, estado, version FROM equipos WHERE id = :id AND eliminado_en IS NULL');
    $st->execute([':id' => $id]);
    $antes = $st->fetch();
    if (!$antes) {
        flash('error', 'El equipo no existe.');
        redirigir('/pages/equipos/listar.php');
    }
    if ($version === null) $errores[] = 'Versión del registro no válida.';
}

if ($errores) {
    guardarFormulario($_POST, $errores);
    redirigir($volver);
}

// ---------- Guardar en una transacción (equipo + imágenes) ----------
$despues = ['codigo' => $codigo, 'nombre' => $nombre, 'categoria_id' => $categoriaId, 'estado' => $estado];
$archivosGuardados = [];
$pdo->beginTransaction();
try {
    if ($id === 0) {
        $st = $pdo->prepare('INSERT INTO equipos (categoria_id, codigo, nombre, estado) VALUES (:c, :co, :n, :e)');
        $st->execute([':c' => $categoriaId, ':co' => $codigo, ':n' => $nombre, ':e' => $estado]);
        $id = (int)$pdo->lastInsertId();
        $accion = 'crear';
    } else {
        // Control de concurrencia optimista: solo se actualiza si nadie cambió el equipo
        // desde que se abrió el formulario (la versión debe coincidir).
        $st = $pdo->prepare('UPDATE equipos SET categoria_id = :c, codigo = :co, nombre = :n, estado = :e,
                                    version = version + 1
                              WHERE id = :id AND version = :v AND eliminado_en IS NULL');
        $st->execute([':c' => $categoriaId, ':co' => $codigo, ':n' => $nombre, ':e' => $estado, ':id' => $id, ':v' => $version]);
        if ($st->rowCount() === 0) {
            $pdo->rollBack();
            flash('error', 'Otro usuario modificó este equipo mientras lo editaba. Revise los datos actuales e intente de nuevo.');
            redirigir($volver);
        }
        $accion = 'editar';
    }

    $archivosGuardados = guardarImagenes($id, $imagenes);
    if ($archivosGuardados) $despues['imagenes_nuevas'] = $archivosGuardados;
    registrarBitacora($accion, 'equipos', $id, $antes, $despues);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    foreach ($archivosGuardados as $a) @unlink(DIR_IMAGENES . '/' . $a);   // limpiar archivos huérfanos
    throw $ex;
}

flash('ok', $accion === 'crear' ? 'Equipo creado.' : 'Equipo actualizado.');
redirigir('/pages/equipos/listar.php');
