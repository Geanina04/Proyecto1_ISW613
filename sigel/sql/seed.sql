-- =========================================================
-- SIGEL - seed.sql : datos de prueba
-- Contraseñas:  admin@sigel.test    -> Admin123*
--               operador@sigel.test -> Operador123*
-- Los profesores no tienen contraseña (no inician sesión)
-- =========================================================
SET NAMES utf8mb4;
USE sigel;
SET time_zone = '-06:00';   -- misma zona horaria que usa la aplicación (Costa Rica)

INSERT INTO roles (id, nombre) VALUES (1,'admin'), (2,'operador'), (3,'profesor');

INSERT INTO usuarios (id, rol_id, nombre, correo, password_hash) VALUES
(1, 1, 'Administrador General', 'admin@sigel.test',    '$2y$10$X3xeHUSZa5PIfkF8H7AzseRJSzMPTwmyhdZ5uiwQZRH145yayuZwu'),
(2, 2, 'Operador Laboratorio',  'operador@sigel.test', '$2y$10$xdyZAR03GLGMwTvXaz6Cy.ft4OH6Zp0aQ3QFsHsPG4BNBhdfHEV/i'),
(3, 3, 'Ana Rodríguez',         'ana.rodriguez@utn.test',   NULL),
(4, 3, 'Carlos Méndez',         'carlos.mendez@utn.test',   NULL),
(5, 3, 'Laura Jiménez',         'laura.jimenez@utn.test',   NULL);

INSERT INTO categorias (id, nombre, dias_max_prestamo) VALUES
(1, 'Computadoras portátiles', 7),
(2, 'Proyectores', 2),
(3, 'Microcontroladores', 14),
(4, 'Cámaras', 3);

INSERT INTO equipos (id, categoria_id, codigo, nombre, estado) VALUES
(1, 1, 'LAP-001', 'Laptop Dell Latitude 5420', 'disponible'),
(2, 1, 'LAP-002', 'Laptop Lenovo ThinkPad E14', 'disponible'),
(3, 1, 'LAP-003', 'Laptop HP ProBook 440', 'mantenimiento'),
(4, 2, 'PRO-001', 'Proyector Epson PowerLite', 'disponible'),
(5, 2, 'PRO-002', 'Proyector BenQ MS560', 'disponible'),
(6, 3, 'MCU-001', 'Arduino Uno R3', 'disponible'),
(7, 3, 'MCU-002', 'Raspberry Pi 4 Model B', 'disponible'),
(8, 3, 'MCU-003', 'ESP32 DevKit', 'disponible'),
(9, 4, 'CAM-001', 'Cámara Canon EOS Rebel T7', 'disponible'),
(10,1, 'LAP-004', 'Laptop Acer Aspire 5', 'disponible'),
(11,3, 'MCU-004', 'Arduino Mega 2560', 'disponible'),
(12,4, 'CAM-002', 'Cámara Logitech C920', 'disponible');

-- REGLA 19: fila de configuración
INSERT INTO configuracion (clave, valor) VALUES ('horas_expiracion', '24');

-- Préstamos de prueba
INSERT INTO prestamos (id, usuario_id, operador_id, estado, fecha_inicio, fecha_fin, observacion, creado_en) VALUES
-- Caso regla 19: solicitud con 25 horas sin aprobar -> debe aparecer EXPIRADA al listar
(1, 3, 2, 'solicitado', DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 8 HOUR,  DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 8 HOUR,  'Prueba regla 19: debe expirar',     NOW() - INTERVAL 25 HOUR),
-- Caso regla 19: aprobada creada hace 25 horas -> NO debe expirar
(2, 4, 2, 'aprobado',   DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 8 HOUR,  DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 8 HOUR,  'Prueba regla 19: no debe expirar', NOW() - INTERVAL 25 HOUR),
-- Solicitud reciente (aún vigente)
(3, 5, 2, 'solicitado', DATE_ADD(CURDATE(), INTERVAL 4 DAY) + INTERVAL 13 HOUR, DATE_ADD(CURDATE(), INTERVAL 5 DAY) + INTERVAL 13 HOUR, 'Solicitud reciente',               NOW() - INTERVAL 2 HOUR),
-- Históricos para el reporte
(4, 3, 2, 'devuelto',   '2026-09-01 08:00:00', '2026-09-03 17:00:00', NULL, '2026-08-30 10:00:00'),
(5, 4, 2, 'devuelto',   '2026-09-10 08:00:00', '2026-09-11 17:00:00', NULL, '2026-09-08 10:00:00'),
(6, 5, 2, 'devuelto',   '2026-10-05 08:00:00', '2026-10-06 17:00:00', NULL, '2026-10-02 10:00:00'),
(7, 3, 2, 'cancelado',  '2026-10-12 08:00:00', '2026-10-13 17:00:00', NULL, '2026-10-10 10:00:00');

INSERT INTO prestamo_detalle (prestamo_id, equipo_id, fecha_devolucion) VALUES
(1, 1, NULL), (1, 4, NULL),
(2, 6, NULL),
(3, 9, NULL),
(4, 1, '2026-09-03 16:00:00'), (4, 6, '2026-09-03 16:00:00'),
(5, 1, '2026-09-11 15:30:00'), (5, 4, '2026-09-11 15:30:00'),
(6, 6, '2026-10-06 16:10:00'), (6, 7, '2026-10-06 16:10:00'),
(7, 2, NULL);
