<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

$buscar    = getTexto('buscar');
$porPagina = registrosPorPagina('categorias');
$pdo       = db();

$where  = 'c.eliminado_en IS NULL';
$params = [];
if ($buscar !== '') {
    $where .= ' AND c.nombre LIKE :b';
    $params[':b'] = '%' . $buscar . '%';
}

$total = (int)ejecutar($pdo->prepare("SELECT COUNT(*) FROM categorias c WHERE $where"), $params)->fetchColumn();
[$pagina, $totalPaginas, $offset] = paginar($total, $porPagina, getInt('pagina', 1));

$st = $pdo->prepare(
    "SELECT c.id, c.nombre, c.dias_max_prestamo,
            (SELECT COUNT(*) FROM equipos e WHERE e.categoria_id = c.id AND e.eliminado_en IS NULL) AS equipos
       FROM categorias c WHERE $where ORDER BY c.nombre LIMIT :lim OFFSET :off"
);
$categorias = ejecutar($st, $params + [':lim' => $porPagina, ':off' => $offset])->fetchAll();

$titulo = 'Categorías';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Categorías</h1>
<div class="barra-herramientas">
    <form method="get" class="form-inline">
        <input type="search" name="buscar" maxlength="100" placeholder="Nombre" value="<?= e($buscar) ?>">
        <button type="submit">Buscar</button>
        <?php if ($buscar !== ''): ?><a href="listar.php">Limpiar</a><?php endif; ?>
    </form>
    <?= selectorPorPagina($porPagina, ['buscar' => $buscar]) ?>
    <a class="btn" href="form.php">+ Nueva categoría</a>
</div>
<div class="tabla-scroll">
<table>
    <thead><tr><th>Nombre</th><th>Días máx. de préstamo</th><th>Equipos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categorias as $c): ?>
        <tr>
            <td><?= e($c['nombre']) ?></td>
            <td><?= (int)$c['dias_max_prestamo'] ?></td>
            <td><?= (int)$c['equipos'] ?></td>
            <td class="acciones">
                <a class="btn btn-chico" href="form.php?id=<?= (int)$c['id'] ?>">Editar</a>
                <form method="post" action="/actions/categorias/eliminar.php" data-confirmar="¿Eliminar esta categoría?">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn-chico btn-peligro">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$categorias): ?><tr><td colspan="4">No hay resultados.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<p><?= $total ?> registro(s)</p>
<?= enlacesPaginacion($pagina, $totalPaginas, ['buscar' => $buscar]) ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
