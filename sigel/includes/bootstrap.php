<?php
/**
 * bootstrap.php
 * Se incluye al inicio de TODAS las páginas y acciones.
 * Carga configuración, manejo de errores, sesión, conexión y funciones comunes.
 */
declare(strict_types=1);

define('RAIZ', dirname(__DIR__));                 // carpeta raíz del proyecto (fuera de public/)
define('DIR_IMAGENES', RAIZ . '/storage/imagenes');
define('DIR_LOGS', RAIZ . '/storage/logs');

$archivoConfig = RAIZ . '/config/config.php';
if (!is_file($archivoConfig)) {
    http_response_code(500);
    exit('Falta config/config.php (copie config.example.php).');
}
$CONFIG = require $archivoConfig;

date_default_timezone_set('America/Costa_Rica');

// ---------- Errores: nunca se muestran al usuario, se registran en archivo ----------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DIR_LOGS . '/error.log');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
       . '<p style="font-family:sans-serif">Ocurrió un error inesperado. Intente de nuevo más tarde.</p>'
       . '<p><a href="/index.php">Volver al inicio</a></p>';
    exit;
});

// ---------- Cabeceras de seguridad básicas ----------
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; object-src 'none'; frame-ancestors 'none'");
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/validacion.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prestamos.php';
require_once __DIR__ . '/imagenes.php';
require_once __DIR__ . '/reportes.php';

iniciarSesion();
verificarInactividad();
loginDesdeCookieRecordarme();
