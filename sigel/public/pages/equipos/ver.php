<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin', 'operador']);

$id = getInt('id');
$st = db()->prepare(
    'SELECT e.id, e.codigo, e.nombre, e.estado, c.nombre AS categoria, c.dias_max_prestamo
       FROM equipos e JOIN categorias c ON c.id = e.categoria_id
      WHERE e.id = :id AND e.eliminado_en IS NULL'
);
$st->execute([':id' => $id]);
$equipo = $st->fetch();
if (!$equipo) {
    flash('error', 'El equipo no existe.');
    redirigir('listar.php');
}

// Cookie 8.3: se agrega a "equipos consultados recientemente"
agregarEquipoReciente((int)$equipo['id']);

$st = db()->prepare('SELECT id FROM equipo_imagenes WHERE equipo_id = :id ORDER BY id');
$st->execute([':id' => $id]);
$imagenes = $st->fetchAll();

// Próximos préstamos que bloquean este equipo
$st = db()->prepare(
    "SELECT p.id, p.estado, p.fecha_inicio, p.fecha_fin, u.nombre AS profesor
       FROM prestamo_detalle d JOIN prestamos p ON p.id = d.prestamo_id JOIN usuarios u ON u.id = p.usuario_id
      WHERE d.equipo_id = :id AND p.estado IN ('solicitado','aprobado','entregado') AND p.fecha_fin >= NOW()
      ORDER BY p.fecha_inicio"
);
$st->execute([':id' => $id]);
$reservas = $st->fetchAll();

$titulo = $equipo['codigo'];
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1><?= e($equipo['codigo']) ?> · <?= e($equipo['nombre']) ?></h1>
<div class="tarjeta">
    <p><strong>Categoría:</strong> <?= e($equipo['categoria']) ?> (máximo <?= (int)$equipo['dias_max_prestamo'] ?> días)</p>
    <p><strong>Estado:</strong> <span class="estado estado-<?= e($equipo['estado']) ?>"><?= e($equipo['estado']) ?></span></p>
</div>

<h2>Imágenes</h2>
<div class="galeria">
    <?php foreach ($imagenes as $img): ?>
        <figure><img src="/imagen.php?id=<?= (int)$img['id'] ?>" alt="Imagen de <?= e($equipo['nombre']) ?>"></figure>
    <?php endforeach; ?>
    <?php if (!$imagenes): ?><p>Este equipo no tiene imágenes.</p><?php endif; ?>
</div>

<h2>Reservas activas</h2>
<table>
    <thead><tr><th>#</th><th>Profesor</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($reservas as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td><td><?= e($r['profesor']) ?></td>
            <td><?= e(formatoFecha($r['fecha_inicio'])) ?></td><td><?= e(formatoFecha($r['fecha_fin'])) ?></td>
            <td><span class="estado estado-<?= e($r['estado']) ?>"><?= e($r['estado']) ?></span></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$reservas): ?><tr><td colspan="5">Sin reservas activas.</td></tr><?php endif; ?>
    </tbody>
</table>
<p>
    <a class="btn btn-sec" href="listar.php">Volver</a>
    <?php if (tieneRol(['operador'])): ?>
        <a class="btn" href="/pages/prestamos/form.php?equipo=<?= (int)$equipo['id'] ?>">Prestar este equipo</a>
    <?php endif; ?>
</p>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
