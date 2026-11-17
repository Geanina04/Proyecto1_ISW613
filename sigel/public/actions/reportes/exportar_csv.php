<?php
/**
 * Exporta el reporte a CSV con fputcsv(). Es GET porque solo lee datos (no cambia estado).
 */
require __DIR__ . '/../../../includes/bootstrap.php';
requiereRol(['admin']);

[$desde, $hasta, $error] = rangoFechasReporte();
if ($error) {
    flash('error', $error);
    redirigir('/pages/reportes/mas_prestados.php');
}
$filas = equiposMasPrestados($desde, $hasta);
registrarBitacora('exportar_csv', 'reportes', null, null, ['desde' => $desde, 'hasta' => $hasta, 'filas' => count($filas)]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="equipos_mas_prestados_' . $desde . '_a_' . $hasta . '.csv"');
header('Cache-Control: no-store');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");    // BOM para que Excel muestre bien las tildes
fputcsv($salida, ['Posición', 'Código', 'Equipo', 'Categoría', 'Veces prestado', 'Horas totales'], ',', '"', '');
foreach ($filas as $i => $f) {
    fputcsv($salida, array_map('celdaCsvSegura', [
        $i + 1, $f['codigo'], $f['nombre'], $f['categoria'], $f['veces'], $f['horas'],
    ]), ',', '"', '');
}
fclose($salida);
