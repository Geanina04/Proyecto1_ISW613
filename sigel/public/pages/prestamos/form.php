<?php
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['operador']);    // solo el operador registra préstamos

[$datos, $errores] = recuperarFormulario();
$profesores = listarProfesores();
$equipos    = listarEquiposPrestables();

// Equipos marcados: los del intento anterior, o el que viene por ?equipo= desde "Prestar este equipo"
$marcados = array_map('intval', (array)($datos['equipos'] ?? []));
if (!$marcados && getInt('equipo') > 0) $marcados = [getInt('equipo')];

// Cookie 8.3: equipos consultados recientemente (ya validados como enteros)
$idsRecientes = equiposRecientes();
$recientes = [];
$resto     = [];
foreach ($equipos as $eq) {
    if (in_array((int)$eq['id'], $idsRecientes, true)) $recientes[(int)$eq['id']] = $eq;
    else $resto[] = $eq;
}
// mantener el orden de la cookie (más reciente primero)
$recientes = array_values(array_filter(array_map(fn($id) => $recientes[$id] ?? null, $idsRecientes)));

function casillaEquipo(array $eq, array $marcados): string
{
    $chk = in_array((int)$eq['id'], $marcados, true) ? ' checked' : '';
    return '<label data-texto="' . e(mb_strtolower($eq['codigo'] . ' ' . $eq['nombre'])) . '">'
         . '<input type="checkbox" name="equipos[]" value="' . (int)$eq['id'] . '"' . $chk . '> '
         . '<strong>' . e($eq['codigo']) . '</strong> ' . e($eq['nombre'])
         . ' <small>(' . e($eq['categoria']) . ', máx. ' . (int)$eq['dias_max_prestamo'] . ' días)</small></label>';
}

$titulo = 'Registrar préstamo';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<h1>Registrar préstamo</h1>
<?php if ($errores): ?>
    <div class="alerta alerta-error"><ul class="errores"><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="/actions/prestamos/guardar.php" class="tarjeta" novalidate>
    <?= campoCsrf() ?>
    <label>Profesor
        <select name="usuario_id" required>
            <option value="">Seleccione…</option>
            <?php foreach ($profesores as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)$p['id'] === (string)($datos['usuario_id'] ?? '') ? 'selected' : '' ?>>
                    <?= e($p['nombre']) ?> (<?= e($p['correo']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="grid-2">
        <label>Fecha y hora de inicio
            <input type="datetime-local" name="fecha_inicio" required value="<?= e($datos['fecha_inicio'] ?? '') ?>">
        </label>
        <label>Fecha y hora de fin prevista
            <input type="datetime-local" name="fecha_fin" required value="<?= e($datos['fecha_fin'] ?? '') ?>">
        </label>
    </div>

    <p><strong>Equipos</strong> (solo se listan los que están en estado «disponible»)</p>
    <?php if ($recientes): ?>
        <div class="recientes">
            <p><strong>Consultados recientemente</strong></p>
            <?php foreach ($recientes as $eq) echo casillaEquipo($eq, $marcados); ?>
        </div>
    <?php endif; ?>
    <label>Buscar equipo <input type="search" id="filtro-equipos" placeholder="Escriba código o nombre"></label>
    <div class="lista-equipos" id="lista-equipos">
        <?php foreach ($resto as $eq) echo casillaEquipo($eq, $marcados); ?>
    </div>

    <label>Observación (opcional)
        <textarea name="observacion" maxlength="255" rows="2"><?= e($datos['observacion'] ?? '') ?></textarea>
    </label>
    <button type="submit">Registrar solicitud</button>
    <a class="btn btn-sec" href="listar.php">Cancelar</a>
</form>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
