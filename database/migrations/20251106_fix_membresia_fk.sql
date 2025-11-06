-- =====================================================================================
-- Migración: Fix Foreign Key Violation - Membresía ID
-- Fecha: 2025-11-06
-- Descripción: Esta migración crea triggers temporales para prevenir errores SQL 1452
--              cuando se intenta guardar empresas con membresia_id que no existe en la
--              tabla membresias. Los triggers establecen membresia_id = NULL si el valor
--              no es válido.
--
-- NOTA IMPORTANTE: Esta es una medida temporal de seguridad a nivel de base de datos.
--                  Se recomienda validar membresia_id en la capa de aplicación antes
--                  de insertar o actualizar registros en la tabla empresas.
--
-- ADVERTENCIA: Esta migración NO elimina ni modifica la foreign key existente.
--              Solo previene violaciones estableciendo NULL cuando el valor no es válido.
-- =====================================================================================

USE crm_camara_comercio;

-- Eliminar triggers si ya existen (para permitir re-ejecución de la migración)
DROP TRIGGER IF EXISTS before_empresas_insert_check_membresia;
DROP TRIGGER IF EXISTS before_empresas_update_check_membresia;

DELIMITER $$

-- =====================================================================================
-- Trigger: BEFORE INSERT en tabla empresas
-- Descripción: Verifica que membresia_id exista en la tabla membresias antes de insertar.
--              Si membresia_id no es NULL y no existe en membresias, lo establece a NULL.
-- =====================================================================================
CREATE TRIGGER before_empresas_insert_check_membresia
BEFORE INSERT ON empresas
FOR EACH ROW
BEGIN
    -- Solo validar si membresia_id no es NULL
    IF NEW.membresia_id IS NOT NULL THEN
        -- Verificar si el membresia_id existe en la tabla membresias
        IF NOT EXISTS (
            SELECT 1 FROM membresias WHERE id = NEW.membresia_id
        ) THEN
            -- Si no existe, establecer a NULL para evitar violación de FK
            SET NEW.membresia_id = NULL;
            
            -- NOTA: En un entorno de producción, considere registrar este evento
            -- en una tabla de logs para auditoría y seguimiento.
        END IF;
    END IF;
END$$

-- =====================================================================================
-- Trigger: BEFORE UPDATE en tabla empresas
-- Descripción: Verifica que membresia_id exista en la tabla membresias antes de actualizar.
--              Si membresia_id no es NULL y no existe en membresias, lo establece a NULL.
-- =====================================================================================
CREATE TRIGGER before_empresas_update_check_membresia
BEFORE UPDATE ON empresas
FOR EACH ROW
BEGIN
    -- Solo validar si membresia_id no es NULL
    IF NEW.membresia_id IS NOT NULL THEN
        -- Verificar si el membresia_id existe en la tabla membresias
        IF NOT EXISTS (
            SELECT 1 FROM membresias WHERE id = NEW.membresia_id
        ) THEN
            -- Si no existe, establecer a NULL para evitar violación de FK
            SET NEW.membresia_id = NULL;
            
            -- NOTA: En un entorno de producción, considere registrar este evento
            -- en una tabla de logs para auditoría y seguimiento.
        END IF;
    END IF;
END$$

DELIMITER ;

-- =====================================================================================
-- Verificación de la instalación de triggers
-- =====================================================================================
SELECT 
    'Triggers instalados correctamente:' as Status,
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    ACTION_TIMING
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'crm_camara_comercio'
  AND EVENT_OBJECT_TABLE = 'empresas'
  AND TRIGGER_NAME IN (
    'before_empresas_insert_check_membresia',
    'before_empresas_update_check_membresia'
  );

-- =====================================================================================
-- Instrucciones de uso
-- =====================================================================================
-- Para ejecutar esta migración en staging o producción:
-- 1. Hacer backup de la base de datos antes de ejecutar
-- 2. Ejecutar este archivo SQL: mysql -u usuario -p crm_camara_comercio < 20251106_fix_membresia_fk.sql
-- 3. Verificar que los triggers se crearon correctamente (ver query de verificación arriba)
-- 4. Probar crear/editar una empresa con membresia_id inválida
-- 5. Verificar que no se lanza Error 1452 y que membresia_id se guarda como NULL
--
-- Para revertir (eliminar triggers):
-- DROP TRIGGER IF EXISTS before_empresas_insert_check_membresia;
-- DROP TRIGGER IF EXISTS before_empresas_update_check_membresia;
-- =====================================================================================
