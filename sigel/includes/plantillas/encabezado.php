<?php
/** Encabezado HTML común. Variable opcional: $titulo */
$u = usuarioActual();
$tituloPagina = isset($titulo) ? $titulo . ' · SIGEL' : 'SIGEL';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?></title>
    <link rel="stylesheet" href="/assets/css/estilos.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<header class="barra">
    <a class="marca" href="/index.php">SIGEL</a>
    <?php if ($u): ?>
    <nav>
        <a href="/pages/inicio.php">Inicio</a>
        <?php if ($u['rol'] === 'admin'): ?>
            <a href="/pages/usuarios/listar.php">Usuarios</a>
            <a href="/pages/categorias/listar.php">Categorías</a>
        <?php endif; ?>
        <a href="/pages/equipos/listar.php">Equipos</a>
        <a href="/pages/prestamos/listar.php">Préstamos</a>
        <?php if ($u['rol'] === 'admin'): ?>
            <a href="/pages/reportes/mas_prestados.php">Reportes</a>
            <a href="/pages/bitacora/listar.php">Bitácora</a>
        <?php endif; ?>
    </nav>
    <div class="usuario">
        <?= e($u['nombre']) ?> <span class="etiqueta"><?= e($u['rol']) ?></span>
        <form method="post" action="/actions/logout.php" class="form-inline">
            <?= campoCsrf() ?>
            <button type="submit" class="btn-link">Salir</button>
        </form>
    </div>
    <?php endif; ?>
</header>
<main class="contenedor">
<?php foreach (obtenerFlash() as $f): ?>
    <div class="alerta alerta-<?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div>
<?php endforeach; ?>
