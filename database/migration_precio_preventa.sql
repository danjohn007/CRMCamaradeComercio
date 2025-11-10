-- Migration para agregar campos de precio de preventa a eventos
-- Fecha: 2025-11-10
-- Descripción: Agrega campos para precio_preventa y fecha_limite_preventa en la tabla eventos

USE crm_camara_comercio;

-- Variable con la base de datos activa
SET @db := DATABASE();

-- Agregar campo precio_preventa si no existe
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos' AND column_name = 'precio_preventa';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos ADD COLUMN precio_preventa DECIMAL(10,2) DEFAULT NULL AFTER costo',
  'ALTER TABLE eventos MODIFY COLUMN precio_preventa DECIMAL(10,2) DEFAULT NULL AFTER costo');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar campo fecha_limite_preventa si no existe
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos' AND column_name = 'fecha_limite_preventa';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos ADD COLUMN fecha_limite_preventa DATETIME DEFAULT NULL AFTER precio_preventa',
  'ALTER TABLE eventos MODIFY COLUMN fecha_limite_preventa DATETIME DEFAULT NULL AFTER precio_preventa');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para optimizar consultas de preventa
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'eventos' AND index_name = 'idx_fecha_limite_preventa';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos ADD INDEX idx_fecha_limite_preventa (fecha_limite_preventa)',
  'SELECT "index idx_fecha_limite_preventa ya existe"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
