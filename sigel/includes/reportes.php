<?php
/**
 * reportes.php - consulta del reporte "equipos más prestados" (usada por la página y por el CSV).
 */

/** Lee y valida el rango de fechas de $_GET. Devuelve [desde, hasta, error]. */
function rangoFechasReporte(): array
{
    $desde = fechaValida($_GET['desde'] ?? '') ?? new DateTime('first day of this month');
    $hasta = fechaValida($_GET['hasta'] ?? '') ?? new DateTime('today');
    $error = null;
    if ($desde > $hasta) {
        $error = 'La fecha «desde» no puede ser mayor que «hasta».';
    } elseif ($desde->diff($hasta)->days > 366 * 2) {
        $error = 'El rango máximo es de dos años.';
    }
    return [$desde->format('Y-m-d'), $hasta->format('Y-m-d'), $error];
}

/** Equipos más prestados: cuenta préstamos aprobados, entregados o devueltos que inician en el rango. */
function equiposMasPrestados(string $desde, string $hasta): array
{
    $st = db()->prepare(
        "SELECT e.codigo, e.nombre, c.nombre AS categoria, COUNT(*) AS veces,
                ROUND(SUM(TIMESTAMPDIFF(MINUTE, p.fecha_inicio, p.fecha_fin)) / 60, 1) AS horas
           FROM prestamo_detalle d
           JOIN prestamos  p ON p.id = d.prestamo_id
           JOIN equipos    e ON e.id = d.equipo_id
           JOIN categorias c ON c.id = e.categoria_id
          WHERE p.estado IN ('aprobado', 'entregado', 'devuelto')
            AND p.fecha_inicio >= :desde
            AND p.fecha_inicio <  :hasta + INTERVAL 1 DAY
          GROUP BY e.id, e.codigo, e.nombre, c.nombre
          ORDER BY veces DESC, horas DESC, e.codigo"
    );
    $st->execute([':desde' => $desde, ':hasta' => $hasta]);
    return $st->fetchAll();
}

/**
 * Neutraliza fórmulas en CSV (CSV/Formula Injection).
 * Si una celda empieza con = + - @ tabulador o retorno, Excel la ejecutaría como fórmula;
 * se le antepone un apóstrofo para que se trate como texto.
 */
function celdaCsvSegura($valor): string
{
    $valor = (string)$valor;
    if ($valor !== '' && in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $valor;
    }
    return $valor;
}
