<?php

return [
    'db_host'    => getenv('SIGEL_DB_HOST') ?: '127.0.0.1',
    'db_name'    => getenv('SIGEL_DB_NAME') ?: 'sigel',
    'db_user'    => getenv('SIGEL_DB_USER') ?: 'root',
    'db_pass'    => getenv('SIGEL_DB_PASS') ?: '',

    'app_nombre'          => 'SIGEL',
    'minutos_inactividad' => 15,       // expiración de la sesión por inactividad
    'dias_recordarme'     => 14,       // duración de la cookie "Recordarme"
    'max_intentos_login'  => 3,        // intentos fallidos antes de bloquear
    'minutos_bloqueo'     => 15,       // tiempo de bloqueo de la cuenta
    'max_tamano_imagen'   => 2 * 1024 * 1024,  // 2 MB
];
