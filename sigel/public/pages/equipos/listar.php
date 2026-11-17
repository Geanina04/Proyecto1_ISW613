<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin', 'operador']);   // el operador solo consulta

$esAdmin     = tieneRol(['admin']);
$buscar      = getTexto('buscar');
$categoriaId = getInt('categoria');
$estado      = getTexto('estado', 20);
$porPagina   = registrosPorPagina('equipos');
$pdo         = db();

$where  = 'e.eliminado_en IS NULL';
$params = [];
if ($buscar !== '') {
    $where .= ' AND (e.codigo LIKE :b1 OR e.nombre LIKE :b2)';
    $params[':b1'] = $params[':b2'] = '%' . $buscar . '%';
}
if ($categoriaId > 0) {
    $where .= ' AND e.categoria_id = :cat';
    $params[':cat'] = $categoriaId;
}
if (enLista($estado, ['disponible', 'mantenimiento', 'baja'])) {
    $where .= ' AND e.estado = :est';
    $params[':est'] = $estado;
} else {
    $estado = '';
}

$total = (int)ejecutar($pdo->prepare("SELECT COUNT(*) FROM equipos e WHERE $where"), $params)->fetchColumn();
[$pagina, $totalPaginas, $offset] = paginar($total, $porPagina, getInt('pagina', 1));

$st = $pdo->prepare(
    "SELECT e.id, e.codigo, e.nombre, e.estado, c.nombre AS categoria,
            (SELECT COUNT(*) FROM equipo_imagenes i WHERE i.equipo_id = e.id) AS imagenes
       FROM equipos e JOIN categorias c ON c.id = e.categoria_id
      WHERE $where ORDER BY e.codigo LIMIT :lim OFFSET :off"
);
$equipos    = ejecutar($st, $params + [':lim' => $porPagina, ':off' => $offset])->fetchAll();
$categorias = $pdo->query('SELECT id, nombre FROM categorias WHERE eliminado_en IS NULL ORDER BY nombre')->fetchAll();
$filtros    = ['buscar' => $buscar, 'categoria' => $categoriaId ?: '', 'estado' => $estado];

$titulo = 'Equipos';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Equipos</h1>
<div class="barra-herramientas">
    <form method="get" class="form-inline">
        <input type="search" name="buscar" maxlength="100" placeholder="Código o nombre" value="<?= e($buscar) ?>">
        <select name="categoria">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $categoriaId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estado">
            <option value="">Todos los estados</option>
            <?php foreach (['disponible', 'mantenimiento', 'baja'] as $op): ?>
                <option value="<?= $op ?>" <?= $estado === $op ? 'selected' : '' ?>><?= ucfirst($op) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Buscar</button>
        <a href="listar.php">Limpiar</a>
    </form>
    <?= selectorPorPagina($porPagina, $filtros) ?>
    <?php if ($esAdmin): ?><a class="btn" href="form.php">+ Nuevo equipo</a><?php endif; ?>
</div>
<div class="tabla-scroll">
<table>
    <thead><tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Estado</th><th>Imágenes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($equipos as $eq): ?>
        <tr>
            <td><?= e($eq['codigo']) ?></td>
            <td><?= e($eq['nombre']) ?></td>
            <td><?= e($eq['categoria']) ?></td>
            <td><span class="estado estado-<?= e($eq['estado']) ?>"><?= e($eq['estado']) ?></span></td>
            <td><?= (int)$eq['imagenes'] ?></td>
            <td class="acciones">
                <a class="btn btn-chico btn-sec" href="ver.php?id=<?= (int)$eq['id'] ?>">Ver</a>
                <?php if ($esAdmin): ?>
                <a class="btn btn-chico" href="form.php?id=<?= (int)$eq['id'] ?>">Editar</a>
                <form method="post" action="/actions/equipos/eliminar.php" data-confirmar="¿Eliminar este equipo?">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="<?= (int)$eq['id'] ?>">
                    <button class="btn-chico btn-peligro">Eliminar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$equipos): ?><tr><td colspan="6">No hay resultados.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<p><?= $total ?> registro(s)</p>
<?= enlacesPaginacion($pagina, $totalPaginas, $filtros) ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
