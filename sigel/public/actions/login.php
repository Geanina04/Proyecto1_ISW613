<?php
require __DIR__ . '/../../includes/bootstrap.php';
exigirPost();
verificarCsrf();

$correo   = is_string($_POST['correo'] ?? null) ? mb_substr(trim($_POST['correo']), 0, 150) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

$usuario = null;
if (validarCorreo($correo) === null && $password !== '' && strlen($password) <= 72) {
    $usuario = intentarLogin($correo, $password);
}

if ($usuario === null) {
    // Mensaje genérico: no se dice si el correo existe, si la clave falló o si está bloqueado.
    guardarFormulario(['correo' => $correo], ['Credenciales incorrectas o cuenta bloqueada temporalmente.']);
    redirigir('/pages/login.php');
}

establecerSesion($usuario);
if (($_POST['recordarme'] ?? '') === '1') {
    crearTokenRecordarme((int)$usuario['id']);
}
registrarBitacora('login', 'usuarios', (int)$usuario['id']);
redirigir('/pages/inicio.php');
