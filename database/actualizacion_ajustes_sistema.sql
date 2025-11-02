-- ========================================================================
-- Actualizaciones del Sistema CRM - Ajustes y Mejoras
-- Fecha: Noviembre 2025
-- ========================================================================

-- ========================================================================
-- 1. Verificar y asegurar columna 'activo' en finanzas_categorias
-- ========================================================================
-- La columna ya existe, pero verificamos su presencia
SELECT 'Verificando columna activo en finanzas_categorias...' AS paso;

-- Si por alguna razón no existe, la creamos (safe check)
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

-- Eliminar trigger si existe
DROP TRIGGER IF EXISTS after_pago_insert;

DELIMITER $$

CREATE TRIGGER after_pago_insert
AFTER INSERT ON pagos
FOR EACH ROW
BEGIN
    DECLARE v_categoria_id INT;
    
    -- Obtener ID de categoría "Pago de Membresías"
    SELECT id INTO v_categoria_id 
    FROM finanzas_categorias 
    WHERE nombre = 'Pago de Membresías' AND tipo = 'INGRESO' 
    LIMIT 1;
    
    -- Si la categoría existe y el pago está completado, crear movimiento financiero
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
            CONCAT('Pago ID: ', NEW.id, IFNULL(CONCAT(' - ', NEW.notas), '')),
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- ========================================================================
-- 4. Sincronizar pagos existentes con movimientos financieros
-- ========================================================================
SELECT 'Sincronizando pagos existentes con movimientos financieros...' AS paso;

-- Insertar movimientos financieros para pagos completados que no tienen movimiento asociado
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
    CONCAT('Pago ID: ', p.id, IFNULL(CONCAT(' - ', p.notas), '')) AS notas,
    p.created_at
FROM pagos p
WHERE p.estado = 'COMPLETADO'
AND NOT EXISTS (
    SELECT 1 FROM finanzas_movimientos fm 
    WHERE fm.notas LIKE CONCAT('%Pago ID: ', p.id, '%')
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
-- 6. Índices adicionales para optimizar consultas
-- ========================================================================
SELECT 'Creando índices adicionales para optimización...' AS paso;

-- Índice en finanzas_movimientos para búsquedas por empresa
CREATE INDEX IF NOT EXISTS idx_empresa_id ON finanzas_movimientos(empresa_id);

-- Índice en finanzas_movimientos para búsquedas por fecha
CREATE INDEX IF NOT EXISTS idx_fecha_movimiento ON finanzas_movimientos(fecha_movimiento);

-- Índice en pagos para evidencia
CREATE INDEX IF NOT EXISTS idx_evidencia_pago ON pagos(evidencia_pago);

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
