-- Migración para ajustes del sistema de registro y empresas
-- Fecha: 2025-11-05
-- Descripción: Ajustes al sistema de registro y empresas según requerimientos

-- 1. Agregar campo colonia_fiscal a la tabla empresas
-- Este campo diferenciará la colonia de dirección fiscal de la comercial
ALTER TABLE empresas
  ADD COLUMN colonia_fiscal VARCHAR(100)
    COMMENT 'Colonia de la dirección fiscal'
    AFTER colonia;

-- 2. Actualizar comentario del campo colonia existente para indicar que es para dirección comercial
ALTER TABLE empresas
  MODIFY COLUMN colonia VARCHAR(100) COMMENT 'Colonia de la dirección comercial';

-- 3. Modificar tipo de afiliación para normalizar valores
-- Nota: El campo ya existe como VARCHAR(100), lo usaremos con valores predefinidos: SIEM o MEMBRESÍA

-- 4. Crear índice en el campo colonia_fiscal para búsquedas más rápidas
CREATE INDEX idx_colonia_fiscal ON empresas(colonia_fiscal);

-- Nota: Los campos es_nueva y es_actualizacion ya existen como TINYINT(1)
-- Se mantendrán en la base de datos pero se usará lógica en el frontend
-- para convertir un select único a estos dos campos

-- Verificación de campos existentes
-- SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'empresas' 
-- AND TABLE_SCHEMA = 'crm_camara_comercio';
