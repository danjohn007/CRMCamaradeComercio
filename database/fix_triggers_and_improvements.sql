-- Script de actualización del sistema
-- Fecha: 2025-11-02
-- Descripción: Correcciones y mejoras al sistema CRM

USE crm_camara_comercio;

-- ====================================================================
-- 1. SOLUCIONAR ERROR DE TRIGGER EN TABLA EMPRESAS
-- ====================================================================
-- El error "Can't update table 'empresas' in stored function/trigger" 
-- ocurre cuando un trigger intenta actualizar la misma tabla que lo invocó.
-- Solución: Eliminar triggers problemáticos que causan recursión.

-- Eliminar triggers problemáticos que actualizan la tabla empresas desde sí misma
DROP TRIGGER IF EXISTS actualizar_porcentaje_perfil_insert;
DROP TRIGGER IF EXISTS actualizar_porcentaje_perfil_update;

-- Reemplazar con triggers que NO actualizan la tabla empresas directamente
-- En su lugar, estos se pueden manejar en la lógica de aplicación

-- ====================================================================
-- 2. AGREGAR COLUMNA imagen A EVENTOS SI NO EXISTE
-- ====================================================================
-- Para mostrar imágenes en el calendario de eventos
ALTER TABLE eventos 
ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT NULL 
AFTER descripcion;

-- ====================================================================
-- 3. AGREGAR COLUMNA boletos_solicitados SI NO EXISTE
-- ====================================================================
-- Para registrar el número de boletos por inscripción
ALTER TABLE eventos_inscripciones 
ADD COLUMN IF NOT EXISTS boletos_solicitados INT DEFAULT 1 
AFTER es_invitado;

-- ====================================================================
-- 4. AGREGAR ÍNDICES PARA MEJORAR BÚSQUEDAS
-- ====================================================================
-- Índice para búsqueda de usuarios por WhatsApp y email
ALTER TABLE usuarios
ADD INDEX IF NOT EXISTS idx_whatsapp (whatsapp),
ADD INDEX IF NOT EXISTS idx_email (email);

-- Índice para búsqueda de empresas por WhatsApp
ALTER TABLE empresas
ADD INDEX IF NOT EXISTS idx_whatsapp (whatsapp);

-- Índice para búsqueda en inscripciones por campos de invitado
ALTER TABLE eventos_inscripciones
ADD INDEX IF NOT EXISTS idx_whatsapp_invitado (whatsapp_invitado),
ADD INDEX IF NOT EXISTS idx_email_invitado (email_invitado);

-- ====================================================================
-- 5. AGREGAR NUEVAS CONFIGURACIONES DEL SISTEMA
-- ====================================================================
-- Configuración para API de códigos QR
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES 
    ('qr_api_provider', 'google', 'Proveedor de API para generación de códigos QR (google, qrserver, quickchart)')
ON DUPLICATE KEY UPDATE 
    descripcion = 'Proveedor de API para generación de códigos QR (google, qrserver, quickchart)';

INSERT INTO configuracion (clave, valor, descripcion) 
VALUES 
    ('qr_size', '400', 'Tamaño en píxeles del código QR para impresión')
ON DUPLICATE KEY UPDATE 
    descripcion = 'Tamaño en píxeles del código QR para impresión';

-- ====================================================================
-- 6. CREAR TRIGGER MEJORADO PARA NOTIFICACIONES (SIN ACTUALIZAR EMPRESAS)
-- ====================================================================
-- Este trigger solo crea notificaciones, no actualiza la tabla empresas
DROP TRIGGER IF EXISTS notificar_renovacion_proxima;

DELIMITER //

CREATE TRIGGER notificar_renovacion_proxima
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    DECLARE dias_restantes INT;
    
    -- Solo procesar si la fecha de renovación cambió
    IF NEW.fecha_renovacion IS NOT NULL AND (OLD.fecha_renovacion IS NULL OR OLD.fecha_renovacion != NEW.fecha_renovacion) THEN
        SET dias_restantes = DATEDIFF(NEW.fecha_renovacion, CURDATE());
        
        -- Si faltan 30, 15 o 5 días, crear notificación
        IF dias_restantes IN (30, 15, 5) THEN
            -- Verificar si ya existe notificación para este día específico
            IF NOT EXISTS (
                SELECT 1 FROM notificaciones 
                WHERE empresa_id = NEW.id 
                AND tipo = 'RENOVACION_PROXIMA'
                AND DATE(fecha_evento) = NEW.fecha_renovacion
                AND DATEDIFF(fecha_evento, created_at) = dias_restantes
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ) THEN
                INSERT INTO notificaciones (
                    empresa_id,
                    tipo,
                    titulo,
                    mensaje,
                    fecha_evento,
                    prioridad
                ) VALUES (
                    NEW.id,
                    'RENOVACION_PROXIMA',
                    CONCAT('Renovación en ', dias_restantes, ' días'),
                    CONCAT('La membresía de ', NEW.razon_social, ' vence el ', DATE_FORMAT(NEW.fecha_renovacion, '%d/%m/%Y')),
                    NEW.fecha_renovacion,
                    CASE 
                        WHEN dias_restantes = 5 THEN 'ALTA'
                        WHEN dias_restantes = 15 THEN 'MEDIA'
                        ELSE 'NORMAL'
                    END
                );
            END IF;
        END IF;
    END IF;
END//

DELIMITER ;

-- ====================================================================
-- 7. VERIFICAR Y CREAR COLUMNAS FALTANTES EN EVENTOS_INSCRIPCIONES
-- ====================================================================
-- Asegurar que existan todas las columnas necesarias

-- Columnas para datos de invitados (registro público)
ALTER TABLE eventos_inscripciones
ADD COLUMN IF NOT EXISTS nombre_invitado VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS email_invitado VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS whatsapp_invitado VARCHAR(20) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS rfc_invitado VARCHAR(13) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS razon_social_invitado VARCHAR(255) DEFAULT NULL;

-- Columnas para estado de inscripción
ALTER TABLE eventos_inscripciones
ADD COLUMN IF NOT EXISTS estado ENUM('PENDIENTE', 'CONFIRMADO', 'CANCELADO', 'ASISTIO') DEFAULT 'CONFIRMADO',
ADD COLUMN IF NOT EXISTS codigo_qr VARCHAR(100) UNIQUE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS boleto_enviado TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS fecha_envio_boleto DATETIME DEFAULT NULL;

-- ====================================================================
-- 8. CREAR ÍNDICES ADICIONALES PARA RENDIMIENTO
-- ====================================================================
ALTER TABLE eventos
ADD INDEX IF NOT EXISTS idx_tipo_fecha (tipo, fecha_inicio),
ADD INDEX IF NOT EXISTS idx_activo_fecha (activo, fecha_inicio);

ALTER TABLE eventos_inscripciones
ADD INDEX IF NOT EXISTS idx_codigo_qr (codigo_qr),
ADD INDEX IF NOT EXISTS idx_evento_usuario (evento_id, usuario_id);

-- ====================================================================
-- 9. VERIFICAR INTEGRIDAD DE DATOS
-- ====================================================================
-- Actualizar contador de inscritos en eventos basado en inscripciones reales
UPDATE eventos e 
SET inscritos = (
    SELECT COALESCE(SUM(boletos_solicitados), 0) 
    FROM eventos_inscripciones ei 
    WHERE ei.evento_id = e.id 
    AND ei.estado IN ('CONFIRMADO', 'ASISTIO')
)
WHERE e.activo = 1;

-- ====================================================================
-- 10. LIMPIAR DATOS INCONSISTENTES
-- ====================================================================
-- Asegurar que los boletos solicitados no sean nulos
UPDATE eventos_inscripciones 
SET boletos_solicitados = 1 
WHERE boletos_solicitados IS NULL OR boletos_solicitados < 1;

-- ====================================================================
-- RESUMEN DE CAMBIOS
-- ====================================================================
-- 1. ✓ Eliminados triggers problemáticos que causaban el error de recursión
-- 2. ✓ Agregada columna imagen a eventos para el calendario
-- 3. ✓ Agregada columna boletos_solicitados a inscripciones
-- 4. ✓ Agregados índices para mejorar búsquedas de usuarios
-- 5. ✓ Agregadas configuraciones para API de QR
-- 6. ✓ Creado trigger mejorado para notificaciones sin recursión
-- 7. ✓ Verificadas todas las columnas necesarias en inscripciones
-- 8. ✓ Agregados índices para mejorar rendimiento
-- 9. ✓ Actualizado contador de inscritos en eventos
-- 10. ✓ Limpiados datos inconsistentes

SELECT 'Script de actualización ejecutado exitosamente' AS mensaje;
