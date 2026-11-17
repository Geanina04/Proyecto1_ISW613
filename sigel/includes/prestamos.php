<?php
/**
 * prestamos.php - lógica de préstamos compartida por páginas y acciones.
 */

const ESTADOS_ACTIVOS = ['solicitado', 'aprobado', 'entregado'];   // bloquean el equipo

/** Transiciones permitidas: estado actual => estados a los que puede pasar. */
const TRANSICIONES = [
    'solicitado' => ['aprobado', 'cancelado'],
    'aprobado'   => ['entregado', 'cancelado'],
    'entregado'  => ['devuelto'],
    'devuelto'   => [],
    'cancelado'  => [],
    'expirado'   => [],
];

function horasExpiracion(): int
{
    $st = db()->prepare("SELECT valor FROM configuracion WHERE clave = 'horas_expiracion'");
    $st->execute();
    $horas = enteroEnRango($st->fetchColumn(), 1, 720);
    return $horas ?? 24;
}

/**
 * REGLA 19 - Expiración de solicitudes.
 * Las solicitudes en estado 'solicitado' con más de N horas (fila horas_expiracion)
 * pasan a 'expirado'. Se ejecuta al consultar el listado.
 *
 * - Solo toca préstamos en 'solicitado', así una aprobada nunca expira.
 * - Como el UPDATE tiene "AND estado = 'solicitado'", si la solicitud ya fue expirada
 *   en una consulta anterior no se vuelve a cambiar ni a registrar en bitácora.
 * - Todo va en una transacción con FOR UPDATE para que dos consultas simultáneas
 *   no registren la misma expiración dos veces.
 *
 * Devuelve cuántas solicitudes expiró.
 */
function expirarSolicitudes(): int
{
    $pdo   = db();
    $horas = horasExpiracion();

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            "SELECT id, estado, creado_en FROM prestamos
              WHERE estado = 'solicitado' AND creado_en <= NOW() - INTERVAL :h HOUR
              FOR UPDATE"
        );
        $st->execute([':h' => $horas]);
        $vencidas = $st->fetchAll();

        $upd = $pdo->prepare("UPDATE prestamos SET estado = 'expirado' WHERE id = :id AND estado = 'solicitado'");
        foreach ($vencidas as $p) {
            $upd->execute([':id' => $p['id']]);
            if ($upd->rowCount() === 1) {
                registrarBitacora('expirar', 'prestamos', (int)$p['id'],
                    ['estado' => 'solicitado', 'creado_en' => $p['creado_en']],
                    ['estado' => 'expirado', 'horas_expiracion' => $horas]);
            }
        }
        $pdo->commit();
        return count($vencidas);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Profesores activos (a quienes se les asignan los préstamos). */
function listarProfesores(): array
{
    return db()->query(
        "SELECT u.id, u.nombre, u.correo FROM usuarios u JOIN roles r ON r.id = u.rol_id
          WHERE r.nombre = 'profesor' AND u.activo = 1 AND u.eliminado_en IS NULL
          ORDER BY u.nombre"
    )->fetchAll();
}

/** Equipos que se pueden prestar (no eliminados y en estado disponible). */
function listarEquiposPrestables(): array
{
    return db()->query(
        "SELECT e.id, e.codigo, e.nombre, c.nombre AS categoria, c.dias_max_prestamo
           FROM equipos e JOIN categorias c ON c.id = e.categoria_id
          WHERE e.eliminado_en IS NULL AND e.estado = 'disponible'
          ORDER BY e.codigo"
    )->fetchAll();
}
