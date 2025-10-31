-- Migración de actualización para CRM Cámara de Comercio
-- Ejecutar este script para actualizar la base de datos con las nuevas funcionalidades
-- Fecha: 2025-10-31

USE crm_camara_comercio;

-- Agregar campos de configuración SMTP si no existen
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
('smtp_host', '', 'Servidor SMTP para envío de correos'),
('smtp_port', '587', 'Puerto SMTP'),
('smtp_user', '', 'Usuario SMTP'),
('smtp_pass', '', 'Contraseña SMTP'),
('smtp_secure', 'tls', 'Tipo de seguridad (tls/ssl)'),
('smtp_from_name', 'CRM Cámara de Comercio', 'Nombre del remitente'),
('logo_sistema', '', 'Ruta del logotipo del sistema');

-- Agregar campos de configuración de Shelly Relay API
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
('shelly_api_enabled', '0', 'Habilitar integración con Shelly Relay API'),
('shelly_api_url', '', 'URL de la API de Shelly Relay'),
('shelly_api_channel', '0', 'Canal del Relay a controlar');

-- Verificar si las columnas ya existen antes de agregarlas
-- Nota: MySQL no soporta IF NOT EXISTS para columnas, así que usamos un procedimiento

DELIMITER //

-- Procedimiento para agregar columnas si no existen
DROP PROCEDURE IF EXISTS AddColumnIfNotExists//
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(128),
    IN columnName VARCHAR(128),
    IN columnDefinition VARCHAR(512)
)
BEGIN
    DECLARE columnExists INT;
    
    SELECT COUNT(*) INTO columnExists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND COLUMN_NAME = columnName;
    
    IF columnExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- Las columnas descripcion, servicios_productos, palabras_clave, sitio_web ya existen en el esquema original
-- Solo verificamos que estén presentes

-- Verificar campos en tabla empresas
CALL AddColumnIfNotExists('empresas', 'descripcion', 'TEXT');
CALL AddColumnIfNotExists('empresas', 'servicios_productos', 'TEXT');
CALL AddColumnIfNotExists('empresas', 'palabras_clave', 'TEXT');
CALL AddColumnIfNotExists('empresas', 'sitio_web', 'VARCHAR(255)');

-- Nota: Los campos facebook e instagram ya existen en el esquema original
-- Verificar que existan
CALL AddColumnIfNotExists('empresas', 'facebook', 'VARCHAR(255)');
CALL AddColumnIfNotExists('empresas', 'instagram', 'VARCHAR(255)');

-- Limpiar procedimiento temporal
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;

-- Crear índice para búsquedas en palabras clave si no existe
CREATE INDEX IF NOT EXISTS idx_palabras_clave ON empresas(palabras_clave(100));

-- Crear índice para sitio web si no existe
CREATE INDEX IF NOT EXISTS idx_sitio_web ON empresas(sitio_web);

-- Actualizar valores por defecto de configuración de colores si no existen
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
('color_primario', '#1E40AF', 'Color primario del sistema'),
('color_secundario', '#10B981', 'Color secundario del sistema');

-- Crear directorio de uploads para logos (se debe hacer manualmente en el servidor)
-- mkdir -p public/uploads/logo
-- chmod 755 public/uploads/logo

-- Registro de auditoría de la migración
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, datos_nuevos) 
VALUES (1, 'MIGRATION', 'sistema', 'Actualización de base de datos - Agregados campos SMTP, Shelly API, y campos adicionales de empresas');

-- Fin de la migración
SELECT 'Migración completada exitosamente' AS resultado;
