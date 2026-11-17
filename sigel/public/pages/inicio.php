<?php
require __DIR__ . '/../../includes/bootstrap.php';
requiereRol(['admin', 'operador']);

$pdo = db();
$resumen = [
    'Equipos'               => $pdo->query('SELECT COUNT(*) FROM equipos WHERE eliminado_en IS NULL')->fetchColumn(),
    'Préstamos solicitados' => $pdo->query("SELECT COUNT(*) FROM prestamos WHERE estado = 'solicitado'")->fetchColumn(),
    'Préstamos entregados'  => $pdo->query("SELECT COUNT(*) FROM prestamos WHERE estado = 'entregado'")->fetchColumn(),
    'Profesores'            => $pdo->query("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id
                                            WHERE r.nombre='profesor' AND u.eliminado_en IS NULL")->fetchColumn(),
];
$titulo = 'Inicio';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Bienvenido, <?= e(usuarioActual()['nombre']) ?></h1>
<div class="resumen">
    <?php foreach ($resumen as $nombre => $valor): ?>
        <div class="tarjeta"><strong><?= e($valor) ?></strong><span><?= e($nombre) ?></span></div>
    <?php endforeach; ?>
</div>
<?php if (tieneRol(['operador'])): ?>
    <p><a class="btn" href="/pages/prestamos/form.php">Registrar préstamo</a></p>
<?php endif; ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
