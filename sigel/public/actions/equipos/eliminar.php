<?php
require __DIR__ . '/../../../includes/bootstrap.php';
exigirPost();
requiereRol(['admin']);
verificarCsrf();

$pdo = db();
$id  = enteroEnRango($_POST['id'] ?? '', 1, PHP_INT_MAX) ?? 0;

$st = $pdo->prepare('SELECT id, codigo, nombre, categoria_id, estado FROM equipos WHERE id = :id AND eliminado_en IS NULL');
$st->execute([':id' => $id]);
$antes = $st->fetch();
if (!$antes) {
    flash('error', 'El equipo no existe.');
    redirigir('/pages/equipos/listar.php');
}

// No se elimina si tiene préstamos activos (solicitado, aprobado o entregado)
$st = $pdo->prepare("SELECT COUNT(*) FROM prestamo_detalle d JOIN prestamos p ON p.id = d.prestamo_id
                      WHERE d.equipo_id = :id AND p.estado IN ('solicitado','aprobado','entregado')");
$st->execute([':id' => $id]);
if ($st->fetchColumn() > 0) {
    flash('error', 'No se puede eliminar: el equipo tiene préstamos activos.');
    redirigir('/pages/equipos/listar.php');
}

// Eliminación lógica; el código se modifica para liberar el UNIQUE
$st = $pdo->prepare("UPDATE equipos SET eliminado_en = NOW(), version = version + 1,
                            codigo = CONCAT(LEFT(codigo, 18), '#DEL', id) WHERE id = :id");
$st->execute([':id' => $id]);
registrarBitacora('eliminar', 'equipos', $id, $antes, ['eliminado_en' => date('Y-m-d H:i:s')]);
flash('ok', 'Equipo eliminado.');
redirigir('/pages/equipos/listar.php');
