<?php
/**
 * bitacora.php - registra TODA acción de crear, editar, eliminar, cambiar estado, etc.
 * La tabla bitacora es de solo inserción: el sistema nunca hace UPDATE ni DELETE sobre ella.
 */
function registrarBitacora(string $accion, string $entidad, ?int $entidadId, ?array $antes = null, ?array $despues = null, ?int $usuarioId = null): void
{
    // Nunca guardar datos sensibles en la bitácora
    foreach (['password_hash', 'validator_hash'] as $sensible) {
        unset($antes[$sensible], $despues[$sensible]);
    }
    $st = db()->prepare(
        'INSERT INTO bitacora (usuario_id, accion, entidad, entidad_id, valores_antes, valores_despues, ip)
         VALUES (:u, :a, :en, :id, :antes, :despues, :ip)'
    );
    $st->execute([
        ':u'       => $usuarioId ?? ($_SESSION['usuario']['id'] ?? null),
        ':a'       => $accion,
        ':en'      => $entidad,
        ':id'      => $entidadId,
        ':antes'   => $antes   ? json_encode($antes, JSON_UNESCAPED_UNICODE)   : null,
        ':despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
        ':ip'      => ipCliente(),
    ]);
}
