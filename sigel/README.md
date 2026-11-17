# SIGEL · Sistema de Gestión de Préstamo de Equipos de Laboratorio

Proyecto 1 · ISW-613 Programación en Ambiente Web I · UTN

Aplicación web en **PHP 8 sin framework** + **MySQL/MariaDB (InnoDB)** con PDO. Gestiona usuarios,
categorías, equipos (con imágenes) y préstamos con ventana de tiempo, reportes con exportación CSV y bitácora.

## Requisitos

- XAMPP 8.2 o superior (Apache + PHP 8.x + MariaDB).
- Extensiones de PHP habilitadas: `pdo_mysql`, `fileinfo`, `mbstring` (en `php.ini` quitar el `;` de `extension=fileinfo` si hiciera falta).

## Instalación desde cero (XAMPP en Windows)

1. **Clonar el repositorio fuera de `htdocs`** (para que nada salvo `public/` quede expuesto):
   ```
   git clone <url-del-repo> C:/proyectos/sigel
   ```
2. **Crear la base de datos.** Desde `C:\xampp\mysql\bin`:
   ```
   mysql -u root < C:/proyectos/sigel/sql/schema.sql
   mysql -u root < C:/proyectos/sigel/sql/seed.sql
   ```
   Crear un usuario solo para la aplicación (no usar root):
   ```sql
   CREATE USER 'sigel_app'@'localhost' IDENTIFIED BY 'una_clave_segura';
   GRANT SELECT, INSERT, UPDATE, DELETE ON sigel.* TO 'sigel_app'@'localhost';
   ```
3. **Configuración.** Copiar `config/config.example.php` como `config/config.php` y poner el usuario y la clave reales.
   `config.php` está en `.gitignore`: nunca se sube al repositorio. También se pueden usar las variables de
   entorno `SIGEL_DB_HOST`, `SIGEL_DB_NAME`, `SIGEL_DB_USER` y `SIGEL_DB_PASS`.
4. **Virtual host.** En `C:\xampp\apache\conf\extra\httpd-vhosts.conf` agregar:
   ```apache
   <VirtualHost *:80>
       ServerName sigel.local
       DocumentRoot "C:/proyectos/sigel/public"
       <Directory "C:/proyectos/sigel/public">
           AllowOverride None
           Require all granted
           Options -Indexes
       </Directory>
       ErrorLog "logs/sigel-error.log"
   </VirtualHost>
   ```
   Verificar que en `httpd.conf` esté activa la línea `Include conf/extra/httpd-vhosts.conf`.
5. **Hosts.** Como administrador, agregar en `C:\Windows\System32\drivers\etc\hosts`:
   ```
   127.0.0.1   geanina.local.com
   ```
6. Reiniciar Apache desde el panel de XAMPP y abrir **http://geanina.local.com

> Las carpetas `storage/imagenes` y `storage/logs` deben tener permiso de escritura para Apache.

## Credenciales de prueba

| Rol      | Correo                | Contraseña     |
|----------|-----------------------|----------------|
| admin    | admin@sigel.test      | `Admin123*`    |
| operador | operador@sigel.test   | `Operador123*` |
| profesor | ana.rodriguez@utn.test (y otros) | *no inician sesión* |

## Roles

| Rol | Puede |
|---|---|
| **admin** | CRUD de usuarios, categorías y equipos; ver préstamos; reportes; bitácora. |
| **operador** | Registrar préstamos y cambiar su estado; consultar equipos. No administra usuarios. |
| **profesor** | No ingresa al sistema. Es el usuario al que se le asigna el préstamo. |

Los permisos se validan en el servidor con `requiereRol()` al inicio de **cada** página y acción.

## Estructura

```
sigel/
├── config/          config.example.php (en Git, valores falsos) y config.php (fuera de Git)
├── includes/        código reutilizable (no accesible desde la web)
│   ├── bootstrap.php   se incluye en todas las páginas: errores, sesión, conexión
│   ├── db.php          conexión PDO
│   ├── auth.php        sesión, login, bloqueo por intentos, "Recordarme", roles
│   ├── csrf.php        token CSRF
│   ├── validacion.php  validaciones del servidor
│   ├── cookies.php     cookies de registros por página y equipos recientes
│   ├── bitacora.php    registro de acciones
│   ├── prestamos.php   estados, transiciones y REGLA 19 (expiración)
│   ├── imagenes.php    subida segura de imágenes
│   ├── reportes.php    consulta del reporte y neutralización de fórmulas CSV
│   ├── helpers.php     escape, redirección, paginación, mensajes
│   └── plantillas/     encabezado y pie HTML
├── storage/         imagenes/ subidas y logs/error.log (fuera de public/)
├── sql/             schema.sql y seed.sql
└── public/          ÚNICO directorio expuesto (DocumentRoot)
    ├── index.php
    ├── imagen.php      sirve imágenes solo a usuarios autenticados
    ├── pages/          pantallas (GET)
    ├── actions/        procesan formularios (POST) y redirigen (PRG)
    └── assets/         css y js
```

Flujo típico: `pages/x/form.php` (GET) → `actions/x/guardar.php` (POST: valida, guarda, bitácora) → redirección.

## Dónde está cada requisito de seguridad

| # | Control | Dónde |
|---|---|---|
| 1 | SQL Injection | `includes/db.php` (ERRMODE_EXCEPTION, EMULATE_PREPARES=false); todas las consultas con `prepare()` |
| 2 | Contraseñas | `password_hash()` en `actions/usuarios/guardar.php`, `password_verify()` en `includes/auth.php` |
| 3 | Sesiones | `iniciarSesion()`, `establecerSesion()` (regenera id), `verificarInactividad()` (15 min), `cerrarSesion()` |
| 4 | CSRF | `includes/csrf.php`, `hash_equals()`; campo `campoCsrf()` en todo formulario POST |
| 5 | XSS | función `e()` en `includes/helpers.php` en toda salida |
| 6 | Validación | `includes/validacion.php` y cada `actions/*/guardar.php` |
| 7 | Archivos | `includes/imagenes.php`: 2 MB, lista blanca, `finfo`, nombre aleatorio, guardado fuera de `public/` |
| 8 | Autorización | `requiereRol()` en cada página/acción |
| 9 | Autenticación | mensaje genérico; 3 intentos fallidos ⇒ bloqueo de 15 min (`intentarLogin()`) |
| 10 | PRG | todas las acciones terminan en `redirigir()` |
| 11 | Errores | `bootstrap.php`: `display_errors=0`, excepciones a `storage/logs/error.log`, página genérica |
| 12 | CSV | `celdaCsvSegura()` antepone `'` a celdas que empiezan con `= + - @` |
| 13 | Cookies | ver sección siguiente |

## Cookies

| Cookie | Uso | Detalle |
|---|---|---|
| `SIGELSESSID` | sesión | HttpOnly, SameSite=Lax, Secure si hay HTTPS |
| `sigel_remember` | "Recordarme" | `selector:validador`; en BD solo el hash SHA-256 del validador; rotación en cada uso; se revoca al salir |
| `sigel_pp_<modulo>` | registros por página de cada CRUD | se valida contra `[5, 10, 20, 50]`; cualquier otro valor ⇒ 10 |
| `sigel_recientes` | últimos 5 equipos consultados | ids separados por coma; se descarta lo que no sea entero; aparecen arriba al registrar un préstamo |

## Préstamos

- Estados: `solicitado → aprobado → entregado → devuelto`; `solicitado/aprobado → cancelado`; `solicitado → expirado` (regla 19).
- **Un préstamo bloquea el equipo desde que se solicita** (estados `solicitado`, `aprobado`, `entregado`).
- Traslape: `existente.fecha_inicio < nuevo.fecha_fin AND existente.fecha_fin > nuevo.fecha_inicio`.
- La creación es una **transacción**: `SELECT ... FOR UPDATE` sobre los equipos para que dos operadores no
  presten el mismo equipo al mismo tiempo; después se valida estado, días máximos de la categoría y traslape.
- Edición de equipos con **control optimista** (columna `version`).

## Regla 19 · Expiración de solicitudes

- Cambio de esquema: estado `expirado` en `prestamos.estado` y fila `horas_expiracion = 24` en la tabla `configuracion`.
- `expirarSolicitudes()` (`includes/prestamos.php`) se ejecuta al abrir el listado de préstamos.
  Solo cambia préstamos en `solicitado` con `creado_en` de hace más de 24 h, registra cada cambio en la bitácora
  (acción `expirar`) y, como el `UPDATE` exige `estado = 'solicitado'`, no se repite en consultas posteriores.

**Cómo comprobarlo con el seed:**
1. Entrar como operador y abrir *Préstamos*: el #1 (creado hace 25 h) aparece **expirado** y el #2 (aprobado, también de hace 25 h) sigue **aprobado**.
2. Entrar como admin → *Bitácora* → acción `expirar`: hay un registro para el préstamo #1.
3. Recargar el listado: no aparece de nuevo el aviso ni se agregan registros a la bitácora.

## Bitácora

Toda acción de crear, editar, eliminar, cambiar estado, expirar, subir/eliminar imagen, exportar, login,
login fallido, bloqueo y logout se guarda en `bitacora` con valores antes/después en JSON e IP. La tabla es de solo inserción.
