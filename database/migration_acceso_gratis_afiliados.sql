-- Migration para agregar campo de control de acceso gratis a afiliados en eventos
-- Fecha: 2025-11-13
-- Descripción: Agrega campo para controlar si los afiliados activos obtienen acceso gratis o deben pagar

USE crm_camara_comercio;

-- Variable con la base de datos activa
SET @db := DATABASE();

-- Agregar campo acceso_gratis_afiliados si no existe
-- 1 = Los afiliados activos obtienen 1 boleto gratis (comportamiento predeterminado)
-- 0 = Todos pagan, incluyendo afiliados activos
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos' AND column_name = 'acceso_gratis_afiliados';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos ADD COLUMN acceso_gratis_afiliados TINYINT(1) DEFAULT 1 AFTER requiere_inscripcion',
  'ALTER TABLE eventos MODIFY COLUMN acceso_gratis_afiliados TINYINT(1) DEFAULT 1 AFTER requiere_inscripcion');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar comentario a la columna para documentación
SET @sql = 'ALTER TABLE eventos MODIFY COLUMN acceso_gratis_afiliados TINYINT(1) DEFAULT 1 COMMENT ''1=Acceso gratis para afiliados activos, 0=Todos pagan''';

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
