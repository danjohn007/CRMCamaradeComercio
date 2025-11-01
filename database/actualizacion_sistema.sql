-- Script de Actualización del Sistema CRM (lista para importar en servidor compartido)
-- Fecha: 2025-10-31
-- Nota: En entornos compartidos muchas veces NO se dispone del privilegio SUPER ni EVENT.
-- Por eso este archivo:
--  - NO ejecuta SET GLOBAL event_scheduler = ON;
--  - La sección de creación del EVENT está comentada para evitar errores de privilegios.
-- Si tu hosting permite EVENT y tienes privilegios, descomenta la sección indicada al final.

USE crm_camara_comercio;

-- ======================================================
-- 1. Agregar columna 'preferencias' a usuarios si no existe
-- ======================================================
SET @cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'preferencias'
);
SET @sql := IF(
  @cnt = 0,
  "ALTER TABLE usuarios ADD COLUMN preferencias TEXT COMMENT 'Configuraciones de usuario en formato JSON'",
  "SELECT 'column preferencias already exists'"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ======================================================
-- 2. Agregar columna 'estatus' a empresas para compatibilidad (si no existe)
-- ======================================================
SET @cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND COLUMN_NAME = 'estatus'
);
SET @sql := IF(
  @cnt = 0,
  "ALTER TABLE empresas ADD COLUMN estatus VARCHAR(50) DEFAULT 'Activa' COMMENT 'Estado textual de la empresa'",
  "SELECT 'column estatus already exists'"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ======================================================
-- 3. Actualizar estatus basado en campo activo existente
-- ======================================================
UPDATE empresas SET estatus = CASE 
    WHEN activo = 1 THEN 'Activa'
    WHEN activo = 0 THEN 'Suspendida'
    ELSE 'Activa'
END
WHERE estatus IS NULL OR estatus = '';

-- ======================================================
-- 4. Asegurar que la tabla de auditoría tiene todos los campos necesarios
-- ======================================================
SET @cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'auditoria'
    AND COLUMN_NAME = 'descripcion'
);
SET @sql := IF(
  @cnt > 0,
  "ALTER TABLE auditoria MODIFY COLUMN descripcion TEXT",
  "ALTER TABLE auditoria ADD COLUMN descripcion TEXT"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'auditoria'
    AND COLUMN_NAME = 'ip_address'
);
SET @sql := IF(
  @cnt = 0,
  "ALTER TABLE auditoria ADD COLUMN ip_address VARCHAR(45)",
  "SELECT 'column ip_address already exists'"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @cnt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'auditoria'
    AND COLUMN_NAME = 'user_agent'
);
SET @sql := IF(
  @cnt = 0,
  "ALTER TABLE auditoria ADD COLUMN user_agent TEXT COMMENT 'User agent string del navegador'",
  "SELECT 'column user_agent already exists'"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ======================================================
-- 5. Crear índices para mejorar el rendimiento (si no existen)
-- ======================================================
SET @idx_cnt := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND INDEX_NAME = 'idx_empresas_activo'
);
SET @sql := IF(
  @idx_cnt = 0,
  "CREATE INDEX idx_empresas_activo ON empresas(activo)",
  "SELECT 'index idx_empresas_activo already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_cnt := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND INDEX_NAME = 'idx_empresas_sector'
);
SET @sql := IF(
  @idx_cnt = 0,
  "CREATE INDEX idx_empresas_sector ON empresas(sector_id)",
  "SELECT 'index idx_empresas_sector already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_cnt := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND INDEX_NAME = 'idx_empresas_membresia'
);
SET @sql := IF(
  @idx_cnt = 0,
  "CREATE INDEX idx_empresas_membresia ON empresas(membresia_id)",
  "SELECT 'index idx_empresas_membresia already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_cnt := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificaciones'
    AND INDEX_NAME = 'idx_notificaciones_usuario_leida'
);
SET @sql := IF(
  @idx_cnt = 0,
  "CREATE INDEX idx_notificaciones_usuario_leida ON notificaciones(usuario_id, leida)",
  "SELECT 'index idx_notificaciones_usuario_leida already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_cnt := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificaciones'
    AND INDEX_NAME = 'idx_notificaciones_fecha'
);
SET @sql := IF(
  @idx_cnt = 0,
  "CREATE INDEX idx_notificaciones_fecha ON notificaciones(created_at)",
  "SELECT 'index idx_notificaciones_fecha already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ======================================================
-- 6. Asegurar que existe la tabla de notificaciones con estructura correcta
-- ======================================================
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'SISTEMA',
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    enlace VARCHAR(255),
    leida TINYINT(1) DEFAULT 0,
    enviada_email TINYINT(1) DEFAULT 0,
    enviada_whatsapp TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 7. Insertar configuraciones por defecto si no existen
-- ======================================================
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
('color_primario', '#1E40AF', 'Color primario del sistema (hexadecimal)'),
('color_secundario', '#10B981', 'Color secundario del sistema (hexadecimal)'),
('nombre_sitio', 'CRM Cámara de Comercio', 'Nombre del sitio web'),
('email_sistema', 'info@camara.com', 'Email principal del sistema'),
('dias_aviso_renovacion', '30,15,5', 'Días de aviso antes de renovación'),
('items_por_pagina', '20', 'Elementos por página por defecto'),
('tema_defecto', 'light', 'Tema de color por defecto');

-- ======================================================
-- 8. Actualizar tabla empresas para asegurar columnas de información adicional
-- ======================================================
SET @cols := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND COLUMN_NAME = 'descripcion'
);
SET @sql := IF(
  @cols = 0,
  "ALTER TABLE empresas ADD COLUMN descripcion TEXT",
  "SELECT 'column descripcion already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cols := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND COLUMN_NAME = 'servicios_productos'
);
SET @sql := IF(
  @cols = 0,
  "ALTER TABLE empresas ADD COLUMN servicios_productos TEXT",
  "SELECT 'column servicios_productos already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cols := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND COLUMN_NAME = 'palabras_clave'
);
SET @sql := IF(
  @cols = 0,
  "ALTER TABLE empresas ADD COLUMN palabras_clave TEXT",
  "SELECT 'column palabras_clave already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cols := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresas'
    AND COLUMN_NAME = 'sitio_web'
);
SET @sql := IF(
  @cols = 0,
  "ALTER TABLE empresas ADD COLUMN sitio_web VARCHAR(255)",
  "SELECT 'column sitio_web already exists'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ======================================================
-- 9. Crear vista para reporte de empresas activas
-- ======================================================
CREATE OR REPLACE VIEW v_empresas_activas AS
SELECT 
    e.id,
    e.no_registro,
    e.razon_social,
    e.rfc,
    e.email,
    e.telefono,
    e.representante,
    s.nombre as sector,
    c.nombre as categoria,
    m.nombre as membresia,
    m.costo as costo_membresia,
    e.fecha_renovacion,
    DATEDIFF(e.fecha_renovacion, CURDATE()) as dias_para_renovacion,
    e.activo,
    e.estatus
FROM empresas e
LEFT JOIN sectores s ON e.sector_id = s.id
LEFT JOIN categorias c ON e.categoria_id = c.id
LEFT JOIN membresias m ON e.membresia_id = m.id
WHERE e.activo = 1;

-- ======================================================
-- 10. Crear vista para empresas próximas a vencer
-- ======================================================
CREATE OR REPLACE VIEW v_empresas_por_vencer AS
SELECT 
    e.id,
    e.razon_social,
    e.rfc,
    e.email,
    e.telefono,
    e.fecha_renovacion,
    DATEDIFF(e.fecha_renovacion, CURDATE()) as dias_restantes,
    m.nombre as membresia,
    m.costo as costo
FROM empresas e
LEFT JOIN membresias m ON e.membresia_id = m.id
WHERE e.activo = 1 
  AND e.fecha_renovacion IS NOT NULL
  AND DATEDIFF(e.fecha_renovacion, CURDATE()) BETWEEN 0 AND 30
ORDER BY dias_restantes ASC;

-- ======================================================
-- 11. Trigger para notificar renovaciones próximas
-- ======================================================
DELIMITER //

DROP TRIGGER IF EXISTS notificar_renovacion_proxima//

CREATE TRIGGER notificar_renovacion_proxima
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    DECLARE dias_restantes INT;
    
    IF NEW.fecha_renovacion IS NOT NULL THEN
        SET dias_restantes = DATEDIFF(NEW.fecha_renovacion, CURDATE());
        
        -- Si faltan 30, 15 o 5 días, crear notificación
        IF dias_restantes IN (30, 15, 5) AND OLD.fecha_renovacion = NEW.fecha_renovacion THEN
            -- Insertar notificación para usuarios con permisos
            INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace)
            SELECT 
                u.id,
                'RENOVACION',
                CONCAT('Renovación próxima: ', NEW.razon_social),
                CONCAT('La membresía de ', NEW.razon_social, ' vence en ', dias_restantes, ' días.'),
                CONCAT('/empresas.php?action=view&id=', NEW.id)
            FROM usuarios u
            WHERE u.rol IN ('PRESIDENCIA', 'DIRECCION', 'AFILADOR')
              AND u.activo = 1;
        END IF;
    END IF;
END//

DELIMITER ;

-- ======================================================
-- 12. Procedimiento almacenado para limpiar notificaciones antiguas
-- ======================================================
DELIMITER //

DROP PROCEDURE IF EXISTS limpiar_notificaciones_antiguas//

CREATE PROCEDURE limpiar_notificaciones_antiguas()
BEGIN
    -- Eliminar notificaciones leídas con más de 90 días
    DELETE FROM notificaciones 
    WHERE leida = 1 
      AND created_at < DATE_SUB(CURDATE(), INTERVAL 90 DAY);
    
    -- Eliminar notificaciones no leídas con más de 180 días
    DELETE FROM notificaciones 
    WHERE leida = 0 
      AND created_at < DATE_SUB(CURDATE(), INTERVAL 180 DAY);
END//

DELIMITER ;

-- ======================================================
-- 13. Función para obtener estadísticas de empresas por sector
-- ======================================================
DELIMITER //

DROP FUNCTION IF EXISTS contar_empresas_sector//

CREATE FUNCTION contar_empresas_sector(sector_id_param INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(*) INTO total
    FROM empresas
    WHERE sector_id = sector_id_param
      AND activo = 1;
    
    RETURN total;
END//

DELIMITER ;

-- ======================================================
-- 14. (Opcional) Inserción de datos de prueba para notificaciones
--     (comentada por defecto — solo para desarrollo)
-- ======================================================
/*
INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje) 
SELECT 
    id,
    'BIENVENIDA',
    'Bienvenido al Sistema CRM',
    'Gracias por usar nuestro sistema. Configura tus preferencias para una mejor experiencia.'
FROM usuarios 
WHERE id NOT IN (SELECT DISTINCT usuario_id FROM notificaciones WHERE tipo = 'BIENVENIDA')
LIMIT 10;
*/

-- ======================================================
-- 15. Actualizar permisos y roles
-- ======================================================
UPDATE usuarios SET rol = 'PRESIDENCIA' WHERE rol = 'ADMIN' OR rol = 'ADMINISTRADOR';

-- ======================================================
-- 16. Asegurar integridad referencial (corregir referencias inválidas a NULL)
-- ======================================================
UPDATE empresas SET sector_id = NULL WHERE sector_id NOT IN (SELECT id FROM sectores);
UPDATE empresas SET categoria_id = NULL WHERE categoria_id NOT IN (SELECT id FROM categorias);
UPDATE empresas SET membresia_id = NULL WHERE membresia_id NOT IN (SELECT id FROM membresias);

-- ======================================================
-- 17. Optimizar tablas
-- ======================================================
OPTIMIZE TABLE empresas;
OPTIMIZE TABLE usuarios;
OPTIMIZE TABLE notificaciones;
OPTIMIZE TABLE auditoria;

-- ======================================================
-- 18. Evento para limpiar notificaciones automáticamente
--     (SECCIÓN COMENTADA: en servidores compartidos es común NO tener privilegio EVENT
--      ni permitir SET GLOBAL; por eso lo dejamos comentado. Si tu proveedor lo permite,
--      descomenta y ejecuta.)
-- ======================================================
/*
-- Si tienes privilegios para EVENT y deseas activar el scheduler, el proveedor debe habilitarlo.
-- SET GLOBAL event_scheduler = ON;    -- requiere privilegio SUPER/SYSTEM_VARIABLES_ADMIN (NO ejecutar en hosting compartido)
DROP EVENT IF EXISTS limpiar_notificaciones_mensual;

CREATE EVENT IF NOT EXISTS limpiar_notificaciones_mensual
ON SCHEDULE EVERY 1 MONTH
STARTS CURRENT_TIMESTAMP
DO
CALL limpiar_notificaciones_antiguas();
*/

-- ======================================================
-- Mensaje final
-- ======================================================
SELECT 'Script preparado para importación en servidor compartido. Revisa advertencias sobre eventos y privilegios.' AS mensaje;
