-- =====================================================
-- Migración: Corregir Foreign Key de vendedor_id
-- Fecha: 18 de Noviembre 2025
-- Descripción: Cambiar FK de empresas.vendedor_id de 
--              vendedores(id) a usuarios(id) ya que el
--              sistema ahora usa usuarios con rol AFILADOR
-- =====================================================

-- Paso 1: Eliminar la foreign key existente
ALTER TABLE empresas DROP FOREIGN KEY empresas_ibfk_1;

-- Paso 2: Limpiar datos inconsistentes (vendedor_id que no existen en usuarios)
-- Esto establece NULL para vendedor_id que no corresponden a usuarios válidos
UPDATE empresas e
LEFT JOIN usuarios u ON e.vendedor_id = u.id
SET e.vendedor_id = NULL
WHERE e.vendedor_id IS NOT NULL AND u.id IS NULL;

-- Paso 3: Agregar la nueva foreign key apuntando a usuarios
ALTER TABLE empresas 
ADD CONSTRAINT fk_empresas_vendedor_usuario 
FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Nota: La tabla vendedores se mantiene por compatibilidad con datos históricos
-- pero ya no se utiliza activamente en el sistema
