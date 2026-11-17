<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin', 'operador']);

// REGLA 19: la expiración se evalúa cada vez que se consulta el listado
$expiradas = expirarSolicitudes();

$estados   = array_keys(TRANSICIONES);
$estado    = getTexto('estado', 20);
$buscar    = getTexto('buscar');
$porPagina = registrosPorPagina('prestamos');
$pdo       = db();

$where  = '1 = 1';
$params = [];
if (enLista($estado, $estados)) {
    $where .= ' AND p.estado = :est';
    $params[':est'] = $estado;
} else {
    $estado = '';
}
if ($buscar !== '') {
    $where .= ' AND u.nombre LIKE :b';
    $params[':b'] = '%' . $buscar . '%';
}

$total = (int)ejecutar($pdo->prepare(
    "SELECT COUNT(*) FROM prestamos p JOIN usuarios u ON u.id = p.usuario_id WHERE $where"), $params)->fetchColumn();
[$pagina, $totalPaginas, $offset] = paginar($total, $porPagina, getInt('pagina', 1));

$st = $pdo->prepare(
    "SELECT p.id, p.estado, p.fecha_inicio, p.fecha_fin, p.creado_en,
            u.nombre AS profesor, o.nombre AS operador,
            (SELECT GROUP_CONCAT(e.codigo ORDER BY e.codigo SEPARATOR ', ')
               FROM prestamo_detalle d JOIN equipos e ON e.id = d.equipo_id WHERE d.prestamo_id = p.id) AS equipos
       FROM prestamos p
       JOIN usuarios u ON u.id = p.usuario_id
       JOIN usuarios o ON o.id = p.operador_id
      WHERE $where ORDER BY p.creado_en DESC LIMIT :lim OFFSET :off"
);
$prestamos = ejecutar($st, $params + [':lim' => $porPagina, ':off' => $offset])->fetchAll();
$filtros = ['estado' => $estado, 'buscar' => $buscar];

$titulo = 'Préstamos';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Préstamos</h1>
<?php if ($expiradas > 0): ?>
    <div class="alerta alerta-aviso"><?= $expiradas ?> solicitud(es) pasaron a «expirado» por no aprobarse en <?= horasExpiracion() ?> horas.</div>
<?php endif; ?>
<div class="barra-herramientas">
    <form method="get" class="form-inline">
        <input type="search" name="buscar" maxlength="100" placeholder="Profesor" value="<?= e($buscar) ?>">
        <select name="estado">
            <option value="">Todos los estados</option>
            <?php foreach ($estados as $op): ?>
                <option value="<?= e($op) ?>" <?= $estado === $op ? 'selected' : '' ?>><?= e(ucfirst($op)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
        <a href="listar.php">Limpiar</a>
    </form>
    <?= selectorPorPagina($porPagina, $filtros) ?>
    <?php if (tieneRol(['operador'])): ?><a class="btn" href="form.php">+ Registrar préstamo</a><?php endif; ?>
</div>
<div class="tabla-scroll">
<table>
    <thead><tr><th>#</th><th>Profesor</th><th>Equipos</th><th>Inicio</th><th>Fin</th><th>Solicitado</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($prestamos as $p): ?>
        <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= e($p['profesor']) ?></td>
            <td><?= e($p['equipos']) ?></td>
            <td><?= e(formatoFecha($p['fecha_inicio'])) ?></td>
            <td><?= e(formatoFecha($p['fecha_fin'])) ?></td>
            <td><?= e(formatoFecha($p['creado_en'])) ?></td>
            <td><span class="estado estado-<?= e($p['estado']) ?>"><?= e($p['estado']) ?></span></td>
            <td><a class="btn btn-chico btn-sec" href="ver.php?id=<?= (int)$p['id'] ?>">Ver</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$prestamos): ?><tr><td colspan="8">No hay préstamos.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<p><?= $total ?> registro(s)</p>
<?= enlacesPaginacion($pagina, $totalPaginas, $filtros) ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
