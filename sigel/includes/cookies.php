<?php
/**
 * cookies.php - las dos cookies NO sensibles del sistema (sección 8.2 y 8.3).
 * Su contenido lo puede modificar el usuario desde el navegador, por eso se tratan
 * SIEMPRE como entrada no confiable y se validan antes de usarlas.
 */

const OPCIONES_POR_PAGINA = [5, 10, 20, 50];
const POR_PAGINA_DEFECTO  = 10;

function esHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/** Opciones comunes para cookies no sensibles (no necesitan HttpOnly porque no son secretas). */
function opcionesCookie(int $dias): array
{
    return [
        'expires'  => time() + 60 * 60 * 24 * $dias,
        'path'     => '/',
        'secure'   => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * 8.2 Registros por página por cada CRUD.
 * Si viene ?por_pagina= y es válido se guarda en la cookie sigel_pp_<modulo>.
 * Si no, se usa la cookie, validada contra la lista; cualquier valor raro => 10.
 */
function registrosPorPagina(string $modulo): int
{
    $nombreCookie = 'sigel_pp_' . $modulo;

    if (isset($_GET['por_pagina'])) {
        $pedido = enteroEnRango($_GET['por_pagina'], 1, 1000);
        if ($pedido !== null && in_array($pedido, OPCIONES_POR_PAGINA, true)) {
            setcookie($nombreCookie, (string)$pedido, opcionesCookie(365));
            return $pedido;
        }
    }

    $deCookie = enteroEnRango($_COOKIE[$nombreCookie] ?? '', 1, 1000);
    if ($deCookie !== null && in_array($deCookie, OPCIONES_POR_PAGINA, true)) {
        return $deCookie;
    }
    return POR_PAGINA_DEFECTO;
}

/**
 * 8.3 Equipos consultados recientemente: hasta 5 ids separados por coma.
 * Al leer se descartan valores que no sean enteros positivos.
 */
function equiposRecientes(): array
{
    $crudo = $_COOKIE['sigel_recientes'] ?? '';
    if (!is_string($crudo) || strlen($crudo) > 100) return [];
    $ids = [];
    foreach (explode(',', $crudo) as $parte) {
        $id = enteroEnRango($parte, 1, PHP_INT_MAX);
        if ($id !== null && !in_array($id, $ids, true)) $ids[] = $id;
    }
    return array_slice($ids, 0, 5);
}

/** Agrega un equipo al inicio de la lista de recientes (máximo 5). */
function agregarEquipoReciente(int $id): void
{
    $ids = array_filter(equiposRecientes(), fn($x) => $x !== $id);
    array_unshift($ids, $id);
    $ids = array_slice($ids, 0, 5);
    setcookie('sigel_recientes', implode(',', $ids), opcionesCookie(30));
}
