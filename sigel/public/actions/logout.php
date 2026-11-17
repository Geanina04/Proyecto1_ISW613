<?php
require __DIR__ . '/../../includes/bootstrap.php';
exigirPost();
verificarCsrf();

if (usuarioActual()) {
    registrarBitacora('logout', 'usuarios', usuarioActual()['id']);
}
cerrarSesion();
iniciarSesion();
flash('ok', 'Sesión cerrada correctamente.');
redirigir('/pages/login.php');
