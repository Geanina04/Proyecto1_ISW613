<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['operador']);
verificarCsrf();

$pdo   = db();
$id    = enteroEnRango($_POST['id'] ?? '', 1, PHP_INT_MAX) ?? 0;
$nuevo = $_POST['estado'] ?? '';
$volver = '/pages/prestamos/ver.php?id=' . $id;

if (!enLista($nuevo, array_keys(TRANSICIONES))) {
    flash('error', 'Estado no válido.');
    redirigir($volver);
}

// Regla 19: una solicitud vencida no se puede aprobar; primero se expira
expirarSolicitudes();

$pdo->beginTransaction();
try {
    // Se bloquea el préstamo para que dos operadores no cambien el estado a la vez
    $st = $pdo->prepare('SELECT id, estado FROM prestamos WHERE id = :id FOR UPDATE');
    $st->execute([':id' => $id]);
    $prestamo = $st->fetch();

    if (!$prestamo) {
        $pdo->rollBack();
        flash('error', 'El préstamo no existe.');
        redirigir('/pages/prestamos/listar.php');
    }
    if (!in_array($nuevo, TRANSICIONES[$prestamo['estado']], true)) {
        $pdo->rollBack();
        flash('error', "No se puede pasar de «{$prestamo['estado']}» a «{$nuevo}».");
        redirigir($volver);
    }

    $pdo->prepare('UPDATE prestamos SET estado = :e WHERE id = :id')->execute([':e' => $nuevo, ':id' => $id]);
    if ($nuevo === 'devuelto') {
        $pdo->prepare('UPDATE prestamo_detalle SET fecha_devolucion = NOW() WHERE prestamo_id = :id')->execute([':id' => $id]);
    }
    registrarBitacora('cambiar_estado', 'prestamos', $id, ['estado' => $prestamo['estado']], ['estado' => $nuevo]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $ex;
}

flash('ok', "Préstamo #$id actualizado a «{$nuevo}».");
redirigir($volver);
