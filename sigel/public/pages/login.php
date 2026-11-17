<?php
require __DIR__ . '/../../includes/bootstrap.php';
if (usuarioActual()) redirigir('/pages/inicio.php');

[$datos, $errores] = recuperarFormulario();
$titulo = 'Iniciar sesión';
require RAIZ . '/includes/plantillas/encabezado.php';
?>
<section class="tarjeta login">
    <h1>Iniciar sesión</h1>
    <?php if ($errores): ?><div class="alerta alerta-error"><?= e($errores[0]) ?></div><?php endif; ?>
    <form method="post" action="/actions/login.php" novalidate>
        <?= campoCsrf() ?>
        <label>Correo
            <input type="email" name="correo" maxlength="150" required value="<?= e($datos['correo'] ?? '') ?>">
        </label>
        <label>Contraseña
            <input type="password" name="password" maxlength="72" required>
        </label>
        <label class="check"><input type="checkbox" name="recordarme" value="1"> Recordarme</label>
        <button type="submit" class="btn">Entrar</button>
    </form>
</section>
<?php require RAIZ . '/includes/plantillas/pie.php'; ?>
