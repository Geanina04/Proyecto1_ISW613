<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

$buscar    = getTexto('buscar');
$porPagina = registrosPorPagina('usuarios');
$pdo       = db();

$where  = 'u.eliminado_en IS NULL';
$params = [];
if ($buscar !== '') {
    $where .= ' AND (u.nombre LIKE :b1 OR u.correo LIKE :b2)';
    $params[':b1'] = $params[':b2'] = '%' . $buscar . '%';
}

$total = (int)ejecutar($pdo->prepare("SELECT COUNT(*) FROM usuarios u WHERE $where"), $params)->fetchColumn();
[$pagina, $totalPaginas, $offset] = paginar($total, $porPagina, getInt('pagina', 1));

$st = $pdo->prepare(
    "SELECT u.id, u.nombre, u.correo, u.activo, u.bloqueado_hasta, r.nombre AS rol
       FROM usuarios u JOIN roles r ON r.id = u.rol_id
      WHERE $where ORDER BY u.nombre LIMIT :lim OFFSET :off"
);
$usuarios = ejecutar($st, $params + [':lim' => $porPagina, ':off' => $offset])->fetchAll();

$titulo = 'Usuarios';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Usuarios</h1>
<div class="barra-herramientas">
    <form method="get" class="form-inline">
        <input type="search" name="buscar" maxlength="100" placeholder="Nombre o correo" value="<?= e($buscar) ?>">
        <button type="submit">Buscar</button>
        <?php if ($buscar !== ''): ?><a href="listar.php">Limpiar</a><?php endif; ?>
    </form>
    <?= selectorPorPagina($porPagina, ['buscar' => $buscar]) ?>
    <a class="btn" href="form.php">+ Nuevo usuario</a>
</div>

<div class="tabla-scroll">
<table>
    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= e($u['nombre']) ?></td>
            <td><?= e($u['correo']) ?></td>
            <td><?= e($u['rol']) ?></td>
            <td>
                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                <?php if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()): ?>
                    <span class="estado estado-expirado">Bloqueado</span>
                <?php endif; ?>
            </td>
            <td class="acciones">
                <a class="btn btn-chico" href="form.php?id=<?= (int)$u['id'] ?>">Editar</a>
                <?php if ((int)$u['id'] !== usuarioActual()['id']): ?>
                <form method="post" action="/actions/usuarios/eliminar.php" data-confirmar="¿Eliminar este usuario?">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn-chico btn-peligro">Eliminar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$usuarios): ?><tr><td colspan="5">No hay resultados.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<p><?= $total ?> registro(s)</p>
<?= enlacesPaginacion($pagina, $totalPaginas, ['buscar' => $buscar]) ?>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
