-- Migración: permitir inscripciones de invitados en eventos_inscripciones
-- Fecha: 2025-11-01
-- Nota: Hacer backup antes de ejecutar. Esta migración evita dropear el índice existing unique_inscripcion
-- (evita error #1553). Si deseas quitar el índice antiguo, primero hay que dropear cualquier FK que lo use.

USE `agenciae_canaco`;

-- 1) Permitir usuario_id NULL (para inscripciones de invitados)
ALTER TABLE `eventos_inscripciones`
  MODIFY COLUMN `usuario_id` INT NULL;

-- 2) Agregar columnas para invitados (si alguna ya existe la sentencia fallará; en tu volcado actual no existen)
ALTER TABLE `eventos_inscripciones`
  ADD COLUMN `nombre_invitado` VARCHAR(150) AFTER `empresa_id`,
  ADD COLUMN `email_invitado` VARCHAR(100) AFTER `nombre_invitado`,
  ADD COLUMN `telefono_invitado` VARCHAR(20) AFTER `email_invitado`,
  ADD COLUMN `whatsapp_invitado` VARCHAR(20) AFTER `telefono_invitado`,
  ADD COLUMN `rfc_invitado` VARCHAR(13) AFTER `whatsapp_invitado`,
  ADD COLUMN `boletos_solicitados` INT DEFAULT 1 AFTER `rfc_invitado`,
  ADD COLUMN `es_invitado` TINYINT(1) DEFAULT 0 AFTER `boletos_solicitados`;

-- 3) Agregar índice único para invitados (evento_id + email_invitado)
-- Esto evita duplicados por email entre invitados del mismo evento.
ALTER TABLE `eventos_inscripciones`
  ADD UNIQUE KEY `unique_inscripcion_invitado` (`evento_id`,`email_invitado`);

-- 4) Índices para búsqueda por WhatsApp y RFC (invitados)
ALTER TABLE `eventos_inscripciones`
  ADD INDEX `idx_whatsapp_invitado` (`whatsapp_invitado`),
  ADD INDEX `idx_rfc_invitado` (`rfc_invitado`);

-- 5) Crear tabla para tokens de enlace público (si no existe)
CREATE TABLE IF NOT EXISTS `eventos_enlaces_publicos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `evento_id` INT NOT NULL,
    `token` VARCHAR(64) UNIQUE NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eventos_enlaces_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_evento` (`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
