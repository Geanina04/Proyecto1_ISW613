-- =========================================================
-- SIGEL - Sistema de Gestión de Préstamo de Equipos
-- schema.sql : crea la base de datos y todas las tablas
-- =========================================================
SET NAMES utf8mb4;
DROP DATABASE IF EXISTS sigel;
CREATE DATABASE sigel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigel;

-- Roles: admin, operador, profesor
CREATE TABLE roles (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Usuarios. Los profesores NO inician sesión, por eso password_hash puede ser NULL.
CREATE TABLE usuarios (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    rol_id            INT NOT NULL,
    nombre            VARCHAR(100) NOT NULL,
    correo            VARCHAR(150) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NULL,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta   DATETIME NULL,
    creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    eliminado_en      DATETIME NULL,           -- eliminación lógica
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- Tokens de "Recordarme" (esquema selector:validador)
CREATE TABLE auth_tokens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT NOT NULL,
    selector       CHAR(18) NOT NULL UNIQUE,
    validator_hash CHAR(64) NOT NULL,          -- hash SHA-256 del validador
    expira_en      DATETIME NOT NULL,
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(80) NOT NULL UNIQUE,
    dias_max_prestamo INT NOT NULL DEFAULT 7,
    eliminado_en      DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE equipos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    codigo       VARCHAR(30) NOT NULL UNIQUE,
    nombre       VARCHAR(100) NOT NULL,
    estado       ENUM('disponible','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
    version      INT NOT NULL DEFAULT 1,       -- control de concurrencia optimista
    eliminado_en DATETIME NULL,
    CONSTRAINT fk_equipos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

-- Un equipo puede tener una o más imágenes
CREATE TABLE equipo_imagenes (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    archivo   VARCHAR(100) NOT NULL,           -- nombre generado por el servidor
    mime      VARCHAR(30) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_imagenes_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB;

-- REGLA 19: se agrega el estado 'expirado'
CREATE TABLE prestamos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT NOT NULL,                 -- profesor al que se le presta
    operador_id  INT NOT NULL,                 -- operador que lo registró
    estado       ENUM('solicitado','aprobado','entregado','devuelto','cancelado','expirado')
                 NOT NULL DEFAULT 'solicitado',
    fecha_inicio DATETIME NOT NULL,
    fecha_fin    DATETIME NOT NULL,
    observacion  VARCHAR(255) NULL,
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- fecha de la solicitud
    CONSTRAINT fk_prestamos_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id),
    CONSTRAINT fk_prestamos_operador FOREIGN KEY (operador_id) REFERENCES usuarios(id),
    CONSTRAINT chk_fechas CHECK (fecha_fin > fecha_inicio)
) ENGINE=InnoDB;

CREATE TABLE prestamo_detalle (
    prestamo_id      INT NOT NULL,
    equipo_id        INT NOT NULL,
    fecha_devolucion DATETIME NULL,
    PRIMARY KEY (prestamo_id, equipo_id),
    CONSTRAINT fk_detalle_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id),
    CONSTRAINT fk_detalle_equipo   FOREIGN KEY (equipo_id)   REFERENCES equipos(id)
) ENGINE=InnoDB;

-- REGLA 19: tabla de configuración con la fila 'horas_expiracion'
CREATE TABLE configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Bitácora (solo inserción)
CREATE TABLE bitacora (
    id               BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NULL,
    accion           VARCHAR(30) NOT NULL,
    entidad          VARCHAR(30) NOT NULL,
    entidad_id       INT NULL,
    valores_antes    JSON NULL,
    valores_despues  JSON NULL,
    ip               VARCHAR(45) NULL,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE INDEX idx_prestamos_estado ON prestamos(estado, creado_en);
CREATE INDEX idx_prestamos_fechas ON prestamos(fecha_inicio, fecha_fin);
