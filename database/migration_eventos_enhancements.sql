-- Migración: Mejoras para eventos públicos - QR codes y razón social
-- Fecha: 2025-11-01
-- Descripción: Agrega campos necesarios para códigos QR, razón social y tracking de boletos
USE `agenciae_canaco`;

-- IMPORTANTE:
-- 1) Haz una copia de seguridad de la base de datos / tabla antes de ejecutar este script.
-- 2) Algunas herramientas/usuarios pueden devolver error si la columna ya existe.
--    Revisa los "SHOW COLUMNS" más abajo antes de ejecutar los ALTER si no estás seguro.

-- Comprobaciones (ejecuta estas consultas primero y revisa si devuelven filas):
SHOW COLUMNS FROM `eventos_inscripciones` LIKE 'razon_social_invitado';
SHOW COLUMNS FROM `eventos_inscripciones` LIKE 'codigo_qr';
SHOW COLUMNS FROM `eventos_inscripciones` LIKE 'boleto_enviado';
SHOW COLUMNS FROM `eventos_inscripciones` LIKE 'fecha_envio_boleto';

-- 1) Agregar columnas adicionales a eventos_inscripciones
-- Ejecuta cada ALTER solo si la columna correspondiente NO existe (si ya existe, la sentencia fallará).
ALTER TABLE `eventos_inscripciones`
  ADD COLUMN `razon_social_invitado` VARCHAR(255) AFTER `nombre_invitado`;

ALTER TABLE `eventos_inscripciones`
  ADD COLUMN `codigo_qr` VARCHAR(100) AFTER `es_invitado`;

ALTER TABLE `eventos_inscripciones`
  ADD COLUMN `boleto_enviado` TINYINT(1) DEFAULT 0 AFTER `codigo_qr`;

ALTER TABLE `eventos_inscripciones`
  ADD COLUMN `fecha_envio_boleto` DATETIME AFTER `boleto_enviado`;

-- 2) Índice para búsqueda por codigo_qr
-- Antes de crear un índice UNIQUE asegúrate de que no haya valores duplicados:
SELECT `codigo_qr`, COUNT(*) AS c FROM `eventos_inscripciones` GROUP BY `codigo_qr` HAVING c > 1;

-- Crear un índice NO-UNIQUE (más seguro si no quieres que falle por duplicados):
CREATE INDEX `idx_codigo_qr` ON `eventos_inscripciones` (`codigo_qr`);

-- Si confirmas que NO HAY duplicados y quieres un índice UNIQUE, primero elimina el índice anterior (si lo creaste),
-- y luego crea el índice único con estos comandos (descomentar y ejecutar cuando estés listo):
-- DROP INDEX `idx_codigo_qr` ON `eventos_inscripciones`;
-- CREATE UNIQUE INDEX `idx_codigo_qr` ON `eventos_inscripciones` (`codigo_qr`);
