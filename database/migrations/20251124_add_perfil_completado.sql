-- Migration: Add profile completion percentage column
-- Date: 2025-11-24
-- Description: Adds perfil_completado_porcentaje column to empresas table to store and cache profile completion percentage

USE crm_camara_comercio;

-- Add column for profile completion percentage
ALTER TABLE empresas 
ADD COLUMN IF NOT EXISTS perfil_completado_porcentaje DECIMAL(5,2) DEFAULT 0 COMMENT 'Porcentaje de completitud del perfil (0-100)';

-- Update existing records with their current completion percentage
-- This will calculate and set the initial percentage for all existing companies
UPDATE empresas e
SET perfil_completado_porcentaje = (
    SELECT ROUND(
        (
            (CASE WHEN e.razon_social IS NOT NULL AND e.razon_social != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.rfc IS NOT NULL AND e.rfc != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.email IS NOT NULL AND e.email != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.telefono IS NOT NULL AND e.telefono != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.whatsapp IS NOT NULL AND e.whatsapp != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.representante IS NOT NULL AND e.representante != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.direccion_comercial IS NOT NULL AND e.direccion_comercial != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.direccion_fiscal IS NOT NULL AND e.direccion_fiscal != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.colonia IS NOT NULL AND e.colonia != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.ciudad IS NOT NULL AND e.ciudad != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.codigo_postal IS NOT NULL AND e.codigo_postal != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.estado IS NOT NULL AND e.estado != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.sector_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN e.categoria_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN e.membresia_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN e.descripcion IS NOT NULL AND e.descripcion != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.servicios_productos IS NOT NULL AND e.servicios_productos != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.palabras_clave IS NOT NULL AND e.palabras_clave != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.sitio_web IS NOT NULL AND e.sitio_web != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.facebook IS NOT NULL AND e.facebook != '' THEN 1 ELSE 0 END) +
            (CASE WHEN e.instagram IS NOT NULL AND e.instagram != '' THEN 1 ELSE 0 END)
        ) * 100 / 21, 2)
);

-- Add index for better query performance
CREATE INDEX IF NOT EXISTS idx_perfil_completado ON empresas(perfil_completado_porcentaje);
