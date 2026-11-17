<?php
/**
 * csrf.php - protección contra CSRF.
 * Se genera un token aleatorio por sesión; todo formulario que modifica datos lo envía
 * en un campo oculto y el servidor lo compara con hash_equals().
 */

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto para pegar dentro de cada <form method="post">. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(tokenCsrf()) . '">';
}

/** Detiene la petición si el token no coincide. */
function verificarCsrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        http_response_code(403);
        error_log('[' . date('Y-m-d H:i:s') . '] Token CSRF inválido desde ' . ipCliente());
        exit('Solicitud no válida (token CSRF). <a href="/index.php">Volver</a>');
    }
}
