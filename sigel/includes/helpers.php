<?php
/**
 * helpers.php - funciones pequeñas reutilizadas en todo el sistema.
 */

/** Escapa cualquier texto antes de imprimirlo en HTML (anti XSS). */
function e($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirige y termina el script (se usa para el patrón Post/Redirect/Get). */
function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Mensajes flash: se guardan en sesión y se muestran una sola vez. */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtenerFlash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

/** Guarda los datos del formulario y errores para volver a mostrarlos después de redirigir. */
function guardarFormulario(array $datos, array $errores): void
{
    unset($datos['csrf_token'], $datos['password']);
    $_SESSION['form_datos']   = $datos;
    $_SESSION['form_errores'] = $errores;
}

/** Recupera (y borra) los datos guardados del formulario. */
function recuperarFormulario(): array
{
    $datos   = $_SESSION['form_datos'] ?? [];
    $errores = $_SESSION['form_errores'] ?? [];
    unset($_SESSION['form_datos'], $_SESSION['form_errores']);
    return [$datos, $errores];
}

/** Solo permite POST en las acciones que modifican datos. */
function exigirPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }
}

function ipCliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

/** Lee un entero de $_GET de forma segura. */
function getInt(string $clave, int $defecto = 0): int
{
    $v = filter_input(INPUT_GET, $clave, FILTER_VALIDATE_INT);
    return ($v === false || $v === null) ? $defecto : $v;
}

/** Lee un texto de $_GET, recortado y con largo máximo. */
function getTexto(string $clave, int $max = 100): string
{
    $v = $_GET[$clave] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

/**
 * Calcula los datos de paginación.
 * Devuelve [página actual, total de páginas, offset].
 */
function paginar(int $total, int $porPagina, int $pagina): array
{
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min(max(1, $pagina), $totalPaginas);
    return [$pagina, $totalPaginas, ($pagina - 1) * $porPagina];
}

/** Dibuja los enlaces de paginación conservando los filtros actuales. */
function enlacesPaginacion(int $pagina, int $totalPaginas, array $filtros = []): string
{
    if ($totalPaginas <= 1) return '';
    $html = '<nav class="paginacion">';
    for ($i = 1; $i <= $totalPaginas; $i++) {
        $query = http_build_query(array_merge($filtros, ['pagina' => $i]));
        $clase = $i === $pagina ? ' class="actual"' : '';
        $html .= "<a$clase href=\"?" . e($query) . "\">$i</a>";
    }
    return $html . '</nav>';
}

/** Selector de "registros por página" (el valor se guarda en cookie). */
function selectorPorPagina(int $actual, array $filtros = []): string
{
    $html = '<form method="get" class="form-inline">';
    foreach ($filtros as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $html .= '<label>Registros por página <select name="por_pagina" class="auto-submit">';
    foreach (OPCIONES_POR_PAGINA as $op) {
        $sel = $op === $actual ? ' selected' : '';
        $html .= "<option value=\"$op\"$sel>$op</option>";
    }
    return $html . '</select></label> <noscript><button>Aplicar</button></noscript></form>';
}

function formatoFecha(?string $fecha): string
{
    return $fecha ? date('d/m/Y H:i', strtotime($fecha)) : '';
}

/**
 * Ejecuta una sentencia preparada enlazando los enteros como PARAM_INT
 * (necesario para LIMIT/OFFSET con sentencias preparadas reales).
 */
function ejecutar(PDOStatement $st, array $parametros): PDOStatement
{
    foreach ($parametros as $clave => $valor) {
        $st->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : (is_null($valor) ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    $st->execute();
    return $st;
}
