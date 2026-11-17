<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

[$desde, $hasta, $error] = rangoFechasReporte();
$filas = $error ? [] : equiposMasPrestados($desde, $hasta);

$titulo = 'Equipos más prestados';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Equipos más prestados</h1>
<?php if ($error): ?><div class="alerta alerta-error"><?= e($error) ?></div><?php endif; ?>
<div class="barra-herramientas">
    <form method="get" class="form-inline">
        <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>" required></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>" required></label>
        <button type="submit">Consultar</button>
    </form>
    <?php if (!$error): ?>
        <a class="btn" href="/actions/reportes/exportar_csv.php?<?= e(http_build_query(['desde' => $desde, 'hasta' => $hasta])) ?>">Exportar CSV</a>
    <?php endif; ?>
</div>
<p>Se cuentan los préstamos aprobados, entregados o devueltos cuya fecha de inicio está en el rango.</p>
<div class="tabla-scroll">
<table>
    <thead><tr><th>#</th><th>Código</th><th>Equipo</th><th>Categoría</th><th>Veces prestado</th><th>Horas totales</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $i => $f): ?>
        <tr>
            <td><?= $i + 1 ?></td><td><?= e($f['codigo']) ?></td><td><?= e($f['nombre']) ?></td>
            <td><?= e($f['categoria']) ?></td><td><?= (int)$f['veces'] ?></td><td><?= e($f['horas']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$filas): ?><tr><td colspan="6">Sin préstamos en el rango indicado.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
