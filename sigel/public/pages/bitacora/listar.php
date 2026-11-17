<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

$entidades = ['usuarios', 'categorias', 'equipos', 'prestamos', 'reportes'];
$entidad   = getTexto('entidad', 20);
$accion    = getTexto('accion', 30);
$porPagina = registrosPorPagina('bitacora');
$pdo       = db();

$where  = '1 = 1';
$params = [];
if (enLista($entidad, $entidades)) {
    $where .= ' AND b.entidad = :en';
    $params[':en'] = $entidad;
} else {
    $entidad = '';
}
if ($accion !== '') {
    $where .= ' AND b.accion = :ac';
    $params[':ac'] = $accion;
}

$total = (int)ejecutar($pdo->prepare("SELECT COUNT(*) FROM bitacora b WHERE $where"), $params)->fetchColumn();
[$pagina, $totalPaginas, $offset] = paginar($total, $porPagina, getInt('pagina', 1));
$st = $pdo->prepare(
    "SELECT b.*, u.nombre AS usuario FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id
      WHERE $where ORDER BY b.id DESC LIMIT :lim OFFSET :off"
);
$registros = ejecutar($st, $params + [':lim' => $porPagina, ':off' => $offset])->fetchAll();
$acciones  = $pdo->query('SELECT DISTINCT accion FROM bitacora ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);
$filtros   = ['entidad' => $entidad, 'accion' => $accion];

/** Muestra el JSON de forma legible (y escapado). */
function jsonLegible(?string $json): string
{
    if (!$json) return '';
    $datos = json_decode($json, true);
    return '<pre class="json">' . e(json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
}

$titulo = 'Bitácora';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Bitácora</h1>
<div class="barra-herramientas">
    <form method="get" class="form-inline">
        <select name="entidad">
            <option value="">Todas las entidades</option>
            <?php foreach ($entidades as $op): ?>
                <option value="<?= e($op) ?>" <?= $entidad === $op ? 'selected' : '' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="accion">
            <option value="">Todas las acciones</option>
            <?php foreach ($acciones as $op): ?>
                <option value="<?= e($op) ?>" <?= $accion === $op ? 'selected' : '' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
        <a href="listar.php">Limpiar</a>
    </form>
    <?= selectorPorPagina($porPagina, $filtros) ?>
</div>
<div class="tabla-scroll">
<table>
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>ID</th><th>Antes</th><th>Después</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($registros as $r): ?>
        <tr>
            <td><?= e(formatoFecha($r['creado_en'])) ?></td>
            <td><?= e($r['usuario'] ?? '—') ?></td>
            <td><?= e($r['accion']) ?></td>
            <td><?= e($r['entidad']) ?></td>
            <td><?= e($r['entidad_id']) ?></td>
            <td><?= jsonLegible($r['valores_antes']) ?></td>
            <td><?= jsonLegible($r['valores_despues']) ?></td>
            <td><?= e($r['ip']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$registros): ?><tr><td colspan="8">Sin registros.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<p><?= $total ?> registro(s)</p>
<?= enlacesPaginacion($pagina, $totalPaginas, $filtros) ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
