<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin', 'operador']);

$id = getInt('id');
$st = db()->prepare(
    'SELECT p.*, u.nombre AS profesor, u.correo AS correo_profesor, o.nombre AS operador
       FROM prestamos p JOIN usuarios u ON u.id = p.usuario_id JOIN usuarios o ON o.id = p.operador_id
      WHERE p.id = :id'
);
$st->execute([':id' => $id]);
$prestamo = $st->fetch();
if (!$prestamo) {
    flash('error', 'El préstamo no existe.');
    redirigir('listar.php');
}

$st = db()->prepare('SELECT e.id, e.codigo, e.nombre, d.fecha_devolucion
                       FROM prestamo_detalle d JOIN equipos e ON e.id = d.equipo_id
                      WHERE d.prestamo_id = :id ORDER BY e.codigo');
$st->execute([':id' => $id]);
$detalle = $st->fetchAll();

$siguientes = TRANSICIONES[$prestamo['estado']] ?? [];
$etiquetas  = ['aprobado' => 'Aprobar', 'entregado' => 'Marcar entregado', 'devuelto' => 'Marcar devuelto', 'cancelado' => 'Cancelar préstamo'];

$titulo = 'Préstamo #' . $id;
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Préstamo #<?= (int)$id ?> <span class="estado estado-<?= e($prestamo['estado']) ?>"><?= e($prestamo['estado']) ?></span></h1>
<div class="tarjeta">
    <p><strong>Profesor:</strong> <?= e($prestamo['profesor']) ?> (<?= e($prestamo['correo_profesor']) ?>)</p>
    <p><strong>Registrado por:</strong> <?= e($prestamo['operador']) ?> el <?= e(formatoFecha($prestamo['creado_en'])) ?></p>
    <p><strong>Desde:</strong> <?= e(formatoFecha($prestamo['fecha_inicio'])) ?> &nbsp; <strong>Hasta:</strong> <?= e(formatoFecha($prestamo['fecha_fin'])) ?></p>
    <?php if ($prestamo['observacion']): ?><p><strong>Observación:</strong> <?= e($prestamo['observacion']) ?></p><?php endif; ?>
</div>

<h2>Equipos</h2>
<table>
    <thead><tr><th>Código</th><th>Nombre</th><th>Devuelto</th></tr></thead>
    <tbody>
    <?php foreach ($detalle as $d): ?>
        <tr><td><?= e($d['codigo']) ?></td><td><?= e($d['nombre']) ?></td><td><?= e(formatoFecha($d['fecha_devolucion'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($siguientes && tieneRol(['operador'])): ?>
<h2>Cambiar estado</h2>
<p>
<?php foreach ($siguientes as $nuevo): ?>
    <form method="post" action="/actions/prestamos/cambiar_estado.php" class="form-inline"
          data-confirmar="¿Confirmar cambio a «<?= e($nuevo) ?>»?">
        <?= campoCsrf() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="estado" value="<?= e($nuevo) ?>">
        <button class="<?= $nuevo === 'cancelado' ? 'btn-peligro' : '' ?>"><?= e($etiquetas[$nuevo]) ?></button>
    </form>
<?php endforeach; ?>
</p>
<?php endif; ?>
<p><a class="btn btn-sec" href="listar.php">Volver al listado</a></p>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
