-- Migración: Mejoras para eventos públicos - QR codes y razón social
-- Fecha: 2025-11-01
-- Descripción: Agrega campos necesarios para códigos QR, razón social y tracking de boletos

USE `agenciae_canaco`;

-- 1) Agregar campos adicionales a eventos_inscripciones
ALTER TABLE `eventos_inscripciones`
  ADD COLUMN IF NOT EXISTS `razon_social_invitado` VARCHAR(255) AFTER `nombre_invitado`,
  ADD COLUMN IF NOT EXISTS `codigo_qr` VARCHAR(100) UNIQUE AFTER `es_invitado`,
  ADD COLUMN IF NOT EXISTS `boleto_enviado` TINYINT(1) DEFAULT 0 AFTER `codigo_qr`,
  ADD COLUMN IF NOT EXISTS `fecha_envio_boleto` DATETIME AFTER `boleto_enviado`;

-- 2) Agregar índice para búsqueda por código QR
ALTER TABLE `eventos_inscripciones`
  ADD INDEX IF NOT EXISTS `idx_codigo_qr` (`codigo_qr`);
