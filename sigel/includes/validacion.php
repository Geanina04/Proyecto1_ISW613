<?php
/**
 * validacion.php - validación del lado del servidor (tipo, longitud, rango, formato).
 * Cada función devuelve un mensaje de error o null si el valor es válido.
 */

function validarTexto(string $valor, string $campo, int $min, int $max): ?string
{
    $largo = mb_strlen($valor);
    if ($largo < $min) return "$campo es obligatorio" . ($min > 1 ? " (mínimo $min caracteres)" : '') . '.';
    if ($largo > $max) return "$campo admite como máximo $max caracteres.";
    return null;
}

function validarCorreo(string $valor): ?string
{
    if ($valor === '' || mb_strlen($valor) > 150 || !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        return 'El correo no tiene un formato válido.';
    }
    return null;
}

/** Devuelve el entero o null si no es un entero dentro del rango. */
function enteroEnRango($valor, int $min, int $max): ?int
{
    $v = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    return $v === false ? null : $v;
}

/** Valida un valor contra una lista blanca. */
function enLista($valor, array $permitidos): bool
{
    return is_string($valor) && in_array($valor, $permitidos, true);
}

/** Convierte 'Y-m-d\TH:i' (input datetime-local) a DateTime o null si es inválido. */
function fechaHoraValida($valor): ?DateTime
{
    if (!is_string($valor)) return null;
    $f = DateTime::createFromFormat('Y-m-d\TH:i', $valor);
    return ($f && $f->format('Y-m-d\TH:i') === $valor) ? $f : null;
}

/** Valida 'Y-m-d' (input date). */
function fechaValida($valor): ?DateTime
{
    if (!is_string($valor)) return null;
    $f = DateTime::createFromFormat('!Y-m-d', $valor);
    return ($f && $f->format('Y-m-d') === $valor) ? $f : null;
}

/** Contraseña: mínimo 8 caracteres, con letra y número. */
function validarPassword(string $valor): ?string
{
    if (mb_strlen($valor) < 8 || mb_strlen($valor) > 72) return 'La contraseña debe tener entre 8 y 72 caracteres.';
    if (!preg_match('/[A-Za-z]/', $valor) || !preg_match('/\d/', $valor)) {
        return 'La contraseña debe incluir letras y números.';
    }
    return null;
}
