<?php
/**
 * db.php - Conexión única con PDO.
 * ERRMODE_EXCEPTION: los errores lanzan excepciones.
 * EMULATE_PREPARES = false: las sentencias preparadas las hace realmente MySQL,
 * así la consulta (estructura) y los datos viajan por separado (anti SQL Injection).
 */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '-06:00'");   // misma zona horaria que PHP
    }
    return $pdo;
}
