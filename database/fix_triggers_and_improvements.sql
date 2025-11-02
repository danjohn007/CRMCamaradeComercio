-- Script de actualización del sistema (versión compatible con MySQL < 8.0.16)
-- Fecha: 2025-11-02
-- Descripción: Correcciones y mejoras al sistema CRM
-- NOTA: Este script usa comprobaciones en information_schema y sentencias preparadas
-- para ser compatible con servidores que no soportan "IF NOT EXISTS" en ALTER TABLE.

USE crm_camara_comercio;

-- ====================================================================
-- 1. SOLUCIONAR ERROR DE TRIGGER EN TABLA EMPRESAS
-- ====================================================================
-- El error "Can't update table 'empresas' in stored function/trigger" 
-- ocurre cuando un trigger intenta actualizar la misma tabla que lo invocó.
-- Solución: Eliminar triggers problemáticos que causan recursión.

DROP TRIGGER IF EXISTS actualizar_porcentaje_perfil_insert;
DROP TRIGGER IF EXISTS actualizar_porcentaje_perfil_update;

-- ====================================================================
-- 2. AGREGAR COLUMNA imagen A EVENTOS SI NO EXISTE
-- ====================================================================
-- Comprobación en information_schema y ejecución condicional
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos'
                 AND COLUMN_NAME = 'imagen');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos ADD COLUMN imagen VARCHAR(255) DEFAULT NULL AFTER descripcion',
    'SELECT \"columna imagen ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

-- ====================================================================
-- 3. AGREGAR COLUMNA boletos_solicitados A eventos_inscripciones SI NO EXISTE
-- ====================================================================
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'boletos_solicitados');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN boletos_solicitados INT DEFAULT 1 AFTER es_invitado',
    'SELECT \"columna boletos_solicitados ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

-- ====================================================================
-- 4. AGREGAR ÍNDICES PARA MEJORAR BÚSQUEDAS (comprobando existencia)
-- ====================================================================
-- Índices en tabla usuarios
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'usuarios'
                 AND INDEX_NAME = 'idx_whatsapp');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_whatsapp ON usuarios (whatsapp)',
    'SELECT \"idx_whatsapp ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'usuarios'
                 AND INDEX_NAME = 'idx_email');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_email ON usuarios (email)',
    'SELECT \"idx_email ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Índice para empresas
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'empresas'
                 AND INDEX_NAME = 'idx_whatsapp');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_whatsapp ON empresas (whatsapp)',
    'SELECT \"idx_whatsapp (empresas) ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Índices para eventos_inscripciones (invitado)
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND INDEX_NAME = 'idx_whatsapp_invitado');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_whatsapp_invitado ON eventos_inscripciones (whatsapp_invitado)',
    'SELECT \"idx_whatsapp_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND INDEX_NAME = 'idx_email_invitado');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_email_invitado ON eventos_inscripciones (email_invitado)',
    'SELECT \"idx_email_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;

-- ====================================================================
-- 5. AGREGAR NUEVAS CONFIGURACIONES DEL SISTEMA
-- ====================================================================
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
-- 7. VERIFICAR Y CREAR COLUMNAS FALTANTES EN EVENTOS_INSCRIPCIONES (varias)
-- ====================================================================
-- Añadir columnas de invitado si no existen (cada una con comprobación)
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'nombre_invitado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN nombre_invitado VARCHAR(255) DEFAULT NULL',
    'SELECT \"nombre_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'email_invitado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN email_invitado VARCHAR(100) DEFAULT NULL',
    'SELECT \"email_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'whatsapp_invitado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN whatsapp_invitado VARCHAR(20) DEFAULT NULL',
    'SELECT \"whatsapp_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'rfc_invitado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN rfc_invitado VARCHAR(13) DEFAULT NULL',
    'SELECT \"rfc_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'razon_social_invitado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN razon_social_invitado VARCHAR(255) DEFAULT NULL',
    'SELECT \"razon_social_invitado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- Columnas para estado de inscripción y QR
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'estado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN estado ENUM(''PENDIENTE'',''CONFIRMADO'',''CANCELADO'',''ASISTIO'') DEFAULT ''CONFIRMADO''',
    'SELECT \"estado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'codigo_qr');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN codigo_qr VARCHAR(100) DEFAULT NULL',
    'SELECT \"codigo_qr ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- Crear índice único para codigo_qr si se desea único y no existe
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND INDEX_NAME = 'ux_codigo_qr');

SET @stmt = IF(@exists = 0,
    'CREATE UNIQUE INDEX ux_codigo_qr ON eventos_inscripciones (codigo_qr)',
    'SELECT \"ux_codigo_qr ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'boleto_enviado');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN boleto_enviado TINYINT(1) DEFAULT 0',
    'SELECT \"boleto_enviado ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND COLUMN_NAME = 'fecha_envio_boleto');

SET @stmt = IF(@exists = 0,
    'ALTER TABLE eventos_inscripciones ADD COLUMN fecha_envio_boleto DATETIME DEFAULT NULL',
    'SELECT \"fecha_envio_boleto ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ====================================================================
-- 8. CREAR ÍNDICES ADICIONALES PARA RENDIMIENTO (comprobando existencia)
-- ====================================================================
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos'
                 AND INDEX_NAME = 'idx_tipo_fecha');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_tipo_fecha ON eventos (tipo, fecha_inicio)',
    'SELECT \"idx_tipo_fecha ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos'
                 AND INDEX_NAME = 'idx_activo_fecha');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_activo_fecha ON eventos (activo, fecha_inicio)',
    'SELECT \"idx_activo_fecha ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND INDEX_NAME = 'idx_codigo_qr');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_codigo_qr ON eventos_inscripciones (codigo_qr)',
    'SELECT \"idx_codigo_qr ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'eventos_inscripciones'
                 AND INDEX_NAME = 'idx_evento_usuario');

SET @stmt = IF(@exists = 0,
    'CREATE INDEX idx_evento_usuario ON eventos_inscripciones (evento_id, usuario_id)',
    'SELECT \"idx_evento_usuario ya existe\" as mensaje');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- ====================================================================
-- 9. VERIFICAR INTEGRIDAD DE DATOS
-- ====================================================================
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
UPDATE eventos_inscripciones 
SET boletos_solicitados = 1 
WHERE boletos_solicitados IS NULL OR boletos_solicitados < 1;

-- ====================================================================
-- RESUMEN DE CAMBIOS
-- ====================================================================
-- 1. ✓ Eliminados triggers problemáticos que causaban el error de recursión
-- 2. ✓ Agregada columna imagen a eventos (comprobada previamente)
-- 3. ✓ Agregada columna boletos_solicitados a inscripciones (comprobada previamente)
-- 4. ✓ Agregados índices para mejorar búsquedas de usuarios (comprobados)
-- 5. ✓ Agregadas configuraciones para API de QR
-- 6. ✓ Creado trigger mejorado para notificaciones sin recursión
-- 7. ✓ Verificadas todas las columnas necesarias en inscripciones
-- 8. ✓ Agregados índices para mejorar rendimiento (comprobados)
-- 9. ✓ Actualizado contador de inscritos en eventos
-- 10. ✓ Limpiados datos inconsistentes

SELECT 'Script de actualización ejecutado exitosamente' AS mensaje;
