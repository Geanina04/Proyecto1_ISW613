<?php
require __DIR__ . '/../includes/bootstrap.php';
redirigir(usuarioActual() ? '/pages/inicio.php' : '/pages/login.php');
