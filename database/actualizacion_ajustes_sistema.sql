-- ========================================================================
-- Actualizaciones del Sistema CRM - Ajustes y Mejoras
-- Fecha: Noviembre 2025
-- ========================================================================

-- ========================================================================
-- 1. Verificar y asegurar columna 'activo' en finanzas_categorias
-- ========================================================================
SELECT 'Verificando columna activo en finanzas_categorias...' AS paso;

SET @dbname = DATABASE();
SET @tablename = 'finanzas_categorias';
SET @columnname = 'activo';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT ''Column already exists'' AS result',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TINYINT(1) DEFAULT 1 AFTER color')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ========================================================================
-- 2. Crear categoría por defecto para pagos de membresías
-- ========================================================================
SELECT 'Creando categoría por defecto para Pagos de Membresías...' AS paso;

INSERT IGNORE INTO finanzas_categorias (nombre, tipo, descripcion, color, activo)
VALUES ('Pago de Membresías', 'INGRESO', 'Pagos de membresías de empresas afiliadas', '#10B981', 1);

-- ========================================================================
-- 3. Crear trigger para sincronizar pagos con movimientos financieros
-- ========================================================================
SELECT 'Creando trigger para sincronizar pagos con movimientos financieros...' AS paso;

DROP TRIGGER IF EXISTS after_pago_insert;

DELIMITER $$

CREATE TRIGGER after_pago_insert
AFTER INSERT ON pagos
FOR EACH ROW
BEGIN
    DECLARE v_categoria_id INT;
    SELECT id INTO v_categoria_id 
    FROM finanzas_categorias 
    WHERE nombre = 'Pago de Membresías' AND tipo = 'INGRESO' 
    LIMIT 1;
    IF v_categoria_id IS NOT NULL AND NEW.estado = 'COMPLETADO' THEN
        INSERT INTO finanzas_movimientos 
        (categoria_id, tipo, concepto, descripcion, monto, fecha_movimiento, metodo_pago, 
         referencia, empresa_id, usuario_id, notas, created_at)
        VALUES (
            v_categoria_id,
            'INGRESO',
            NEW.concepto,
            'Generado automáticamente desde Registrar Pago (trigger)',
            NEW.monto,
            NEW.fecha_pago,
            NEW.metodo_pago,
            NEW.referencia,
            NEW.empresa_id,
            NEW.usuario_id,
            CONCAT('PAGO_ID:', NEW.id, IFNULL(CONCAT(' - ', NEW.notas), '')),
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- ========================================================================
-- 4. Sincronizar pagos existentes con movimientos financieros
-- ========================================================================
SELECT 'Sincronizando pagos existentes con movimientos financieros...' AS paso;

INSERT INTO finanzas_movimientos 
(categoria_id, tipo, concepto, descripcion, monto, fecha_movimiento, metodo_pago, 
 referencia, empresa_id, usuario_id, notas, created_at)
SELECT 
    (SELECT id FROM finanzas_categorias WHERE nombre = 'Pago de Membresías' AND tipo = 'INGRESO' LIMIT 1) AS categoria_id,
    'INGRESO' AS tipo,
    p.concepto,
    'Sincronizado automáticamente desde pagos existentes' AS descripcion,
    p.monto,
    p.fecha_pago AS fecha_movimiento,
    p.metodo_pago,
    p.referencia,
    p.empresa_id,
    p.usuario_id,
    CONCAT('PAGO_ID:', p.id, IFNULL(CONCAT(' - ', p.notas), '')) AS notas,
    p.created_at
FROM pagos p
WHERE p.estado = 'COMPLETADO'
AND NOT EXISTS (
    SELECT 1 FROM finanzas_movimientos fm 
    WHERE fm.empresa_id = p.empresa_id
    AND fm.monto = p.monto
    AND fm.fecha_movimiento = p.fecha_pago
    AND fm.notas LIKE CONCAT('PAGO_ID:', p.id, '%')
);

-- ========================================================================
-- 5. Verificar que el campo evidencia_pago existe en la tabla pagos
-- ========================================================================
SELECT 'Verificando campo evidencia_pago en tabla pagos...' AS paso;

SET @tablename = 'pagos';
SET @columnname = 'evidencia_pago';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT ''Column already exists'' AS result',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) NULL AFTER notas')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ========================================================================
-- 6. Índices adicionales para optimizar consultas (compatible MySQL)
-- ========================================================================
SELECT 'Creando índices adicionales para optimización (compatible MySQL)...' AS paso;

-- Índice en finanzas_movimientos para búsquedas por empresa
SET @dbname = DATABASE();
SET @tablename = 'finanzas_movimientos';
SET @indexname = 'idx_empresa_id';
SET @preparedStatement = (
    SELECT IF(
        (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = @dbname
            AND table_name = @tablename
            AND index_name = @indexname
        ) > 0,
        'SELECT ''Index already exists'' AS result',
        CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(empresa_id)')
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice en finanzas_movimientos para búsquedas por fecha
SET @indexname = 'idx_fecha_movimiento';
SET @preparedStatement = (
    SELECT IF(
        (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = @dbname
            AND table_name = @tablename
            AND index_name = @indexname
        ) > 0,
        'SELECT ''Index already exists'' AS result',
        CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(fecha_movimiento)')
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice en pagos para evidencia
SET @tablename = 'pagos';
SET @indexname = 'idx_evidencia_pago';
SET @preparedStatement = (
    SELECT IF(
        (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = @dbname
            AND table_name = @tablename
            AND index_name = @indexname
        ) > 0,
        'SELECT ''Index already exists'' AS result',
        CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(evidencia_pago)')
    )
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================================================
-- 7. Actualizar permisos y privilegios (si es necesario)
-- ========================================================================
SELECT 'Actualizaciones completadas exitosamente!' AS resultado;

-- ========================================================================
-- NOTAS DE IMPLEMENTACIÓN:
-- ========================================================================
-- 1. Error en enlace del boleto digital: Corregido en eventos.php
--    - Se eliminó el uso de e() para escapar HTML del enlace
--    - El mensaje ahora muestra correctamente el link clickeable
--
-- 2. Módulo Financiero:
--    - Las categorías ahora pueden ser desactivadas (soft delete) por usuarios con permiso DIRECCION
--    - Vista de categorías inactivas agregada
--    - Los pagos registrados se reflejan automáticamente en el Dashboard Financiero
--    - Trigger automático para sincronizar pagos con movimientos financieros
--
-- 3. Botones de limpiar filtros:
--    - Dashboard Financiero: Agregado
--    - Reportes y Estadísticas: Agregado
--    - Calendario de Eventos: Agregado con función JavaScript
--    - Requerimientos Comerciales: Agregado
--    - Gestión de Empresas: Agregado
--    - Gestión de Usuarios: Agregado
--
-- 4. Registrar Pago:
--    - Evidencia de pago ahora es obligatoria (campo required en HTML y validación en API)
--    - Concepto precarga automáticamente el nombre de la membresía
--    - Monto precarga automáticamente el costo de la membresía
--    - Ambos campos son editables
--
-- 5. Funcionalidad actual preservada:
--    - Todas las modificaciones son compatibles con la funcionalidad existente
--    - No se eliminaron ni modificaron características en uso
--    - Se agregaron validaciones adicionales sin romper flujos existentes
-- ========================================================================
