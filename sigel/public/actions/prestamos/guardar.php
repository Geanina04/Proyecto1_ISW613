<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['operador']);
verificarCsrf();

$pdo = db();
$volver = '/pages/prestamos/form.php';

$profesorId  = enteroEnRango($_POST['usuario_id'] ?? '', 1, PHP_INT_MAX);
$inicio      = fechaHoraValida($_POST['fecha_inicio'] ?? '');
$fin         = fechaHoraValida($_POST['fecha_fin'] ?? '');
$observacion = trim((string)($_POST['observacion'] ?? ''));
$equiposPost = $_POST['equipos'] ?? [];

// ---------- Validaciones básicas ----------
$errores = [];

$st = $pdo->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
                      WHERE u.id = :id AND r.nombre = 'profesor' AND u.activo = 1 AND u.eliminado_en IS NULL");
$st->execute([':id' => $profesorId ?? 0]);
if (!$st->fetch()) $errores[] = 'Seleccione un profesor válido.';

if (!$inicio || !$fin) {
    $errores[] = 'Indique fecha y hora de inicio y fin válidas.';
} else {
    if ($fin <= $inicio) $errores[] = 'La fecha de fin debe ser posterior a la de inicio.';
    if ($inicio < new DateTime('-10 minutes')) $errores[] = 'La fecha de inicio no puede estar en el pasado.';
}

$equipoIds = [];
if (!is_array($equiposPost) || count($equiposPost) === 0) {
    $errores[] = 'Seleccione al menos un equipo.';
} elseif (count($equiposPost) > 10) {
    $errores[] = 'Un préstamo puede tener como máximo 10 equipos.';
} else {
    foreach ($equiposPost as $v) {
        $idEq = enteroEnRango($v, 1, PHP_INT_MAX);
        if ($idEq === null) { $errores[] = 'Equipo no válido.'; break; }
        $equipoIds[$idEq] = $idEq;     // la llave evita repetidos
    }
}
if (mb_strlen($observacion) > 255) $errores[] = 'La observación admite máximo 255 caracteres.';

if ($errores) {
    guardarFormulario($_POST, $errores);
    redirigir($volver);
}

// Las solicitudes vencidas (regla 19) ya no deben bloquear equipos
expirarSolicitudes();

$fInicio = $inicio->format('Y-m-d H:i:s');
$fFin    = $fin->format('Y-m-d H:i:s');
$horasPrestamo = ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600;

// ---------- Transacción ----------
$pdo->beginTransaction();
try {
    // 1) Bloquear las filas de los equipos con FOR UPDATE.
    //    Si dos operadores intentan prestar el mismo equipo al mismo tiempo, el segundo
    //    espera aquí hasta que el primero termine (commit), y entonces sí ve el traslape.
    $marcadores = [];
    $params = [];
    foreach (array_values($equipoIds) as $i => $idEq) {
        $marcadores[] = ":e$i";
        $params[":e$i"] = $idEq;
    }
    $st = $pdo->prepare(
        'SELECT e.id, e.codigo, e.estado, c.dias_max_prestamo
           FROM equipos e JOIN categorias c ON c.id = e.categoria_id
          WHERE e.eliminado_en IS NULL AND e.id IN (' . implode(',', $marcadores) . ')
          FOR UPDATE'
    );
    $st->execute($params);
    $equipos = $st->fetchAll();

    if (count($equipos) !== count($equipoIds)) {
        $errores[] = 'Alguno de los equipos seleccionados ya no existe.';
    }

    // 2) Validar estado, días máximos y traslape de cada equipo
    $stTraslape = $pdo->prepare(
        "SELECT p.id FROM prestamo_detalle d JOIN prestamos p ON p.id = d.prestamo_id
          WHERE d.equipo_id = :equipo
            AND p.estado IN ('solicitado', 'aprobado', 'entregado')
            AND p.fecha_inicio < :nuevo_fin
            AND p.fecha_fin    > :nuevo_inicio
          LIMIT 1"
    );
    foreach ($equipos as $eq) {
        if ($eq['estado'] !== 'disponible') {
            $errores[] = "El equipo {$eq['codigo']} no está disponible (estado: {$eq['estado']}).";
        }
        if ($horasPrestamo > $eq['dias_max_prestamo'] * 24) {
            $errores[] = "El equipo {$eq['codigo']} solo se puede prestar por {$eq['dias_max_prestamo']} día(s).";
        }
        // existente.fecha_inicio < nuevo.fecha_fin AND existente.fecha_fin > nuevo.fecha_inicio
        $stTraslape->execute([':equipo' => $eq['id'], ':nuevo_fin' => $fFin, ':nuevo_inicio' => $fInicio]);
        $choque = $stTraslape->fetchColumn();
        if ($choque) {
            $errores[] = "El equipo {$eq['codigo']} ya está reservado en ese horario (préstamo #$choque).";
        }
    }

    if ($errores) {
        $pdo->rollBack();
        guardarFormulario($_POST, $errores);
        redirigir($volver);
    }

    // 3) Insertar encabezado y detalle
    $st = $pdo->prepare('INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin, observacion)
                         VALUES (:u, :o, \'solicitado\', :i, :f, :obs)');
    $st->execute([
        ':u' => $profesorId, ':o' => usuarioActual()['id'],
        ':i' => $fInicio, ':f' => $fFin, ':obs' => $observacion !== '' ? $observacion : null,
    ]);
    $prestamoId = (int)$pdo->lastInsertId();

    $stDet = $pdo->prepare('INSERT INTO prestamo_detalle (prestamo_id, equipo_id) VALUES (:p, :e)');
    foreach ($equipoIds as $idEq) {
        $stDet->execute([':p' => $prestamoId, ':e' => $idEq]);
    }

    registrarBitacora('crear', 'prestamos', $prestamoId, null, [
        'usuario_id' => $profesorId, 'estado' => 'solicitado',
        'fecha_inicio' => $fInicio, 'fecha_fin' => $fFin, 'equipos' => array_values($equipoIds),
    ]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $ex;
}

flash('ok', "Préstamo #$prestamoId registrado como «solicitado».");
redirigir('/pages/prestamos/ver.php?id=' . $prestamoId);
