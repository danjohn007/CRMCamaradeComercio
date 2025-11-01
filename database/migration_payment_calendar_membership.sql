-- Migration lista para importar (compatible con MySQL sin usar ADD COLUMN IF NOT EXISTS)
-- Autor: Sistema CRM CANACO (modificado para compatibilidad)
-- Fecha: 2025-11-01
-- Descripción: Agrega soporte para registro de pagos con evidencia, calendario,
--              actualización de membresías y participantes de eventos
-- NOTA: Este script comprueba existence de columnas/índices y aplica ADD o MODIFY
--       según corresponda para evitar errores de importación por duplicados.

USE crm_camara_comercio;

-- Variable con la base de datos activa
SET @db := DATABASE();

-- 1) pagos.evidencia_pago -> Añadir si no existe, si existe MODIFY para asegurar tipo/default
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'pagos' AND column_name = 'evidencia_pago';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE pagos ADD COLUMN evidencia_pago VARCHAR(255) DEFAULT NULL AFTER notas',
  'ALTER TABLE pagos MODIFY COLUMN evidencia_pago VARCHAR(255) DEFAULT NULL AFTER notas');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) eventos_inscripciones: agregar columnas de pago (varias columnas)
-- estado_pago
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND column_name = 'estado_pago';

SET @sql = IF(@cnt = 0,
  "ALTER TABLE eventos_inscripciones ADD COLUMN estado_pago ENUM('SIN_PAGO','PENDIENTE','COMPLETADO','CANCELADO') DEFAULT 'SIN_PAGO' AFTER estado",
  "ALTER TABLE eventos_inscripciones MODIFY COLUMN estado_pago ENUM('SIN_PAGO','PENDIENTE','COMPLETADO','CANCELADO') DEFAULT 'SIN_PAGO' AFTER estado");

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- monto_pagado
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND column_name = 'monto_pagado';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos_inscripciones ADD COLUMN monto_pagado DECIMAL(10,2) DEFAULT 0 AFTER estado_pago',
  'ALTER TABLE eventos_inscripciones MODIFY COLUMN monto_pagado DECIMAL(10,2) DEFAULT 0 AFTER estado_pago');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fecha_pago
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND column_name = 'fecha_pago';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos_inscripciones ADD COLUMN fecha_pago DATETIME DEFAULT NULL AFTER monto_pagado',
  'ALTER TABLE eventos_inscripciones MODIFY COLUMN fecha_pago DATETIME DEFAULT NULL AFTER monto_pagado');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- referencia_pago
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND column_name = 'referencia_pago';

SET @sql = IF(@cnt = 0,
  "ALTER TABLE eventos_inscripciones ADD COLUMN referencia_pago VARCHAR(100) DEFAULT NULL AFTER fecha_pago",
  "ALTER TABLE eventos_inscripciones MODIFY COLUMN referencia_pago VARCHAR(100) DEFAULT NULL AFTER fecha_pago");

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Índices para calendario: eventos(fecha_inicio, fecha_fin) y empresas(fecha_renovacion, activo)
-- idx_fecha_rango en eventos
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'eventos' AND index_name = 'idx_fecha_rango';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos ADD INDEX idx_fecha_rango (fecha_inicio, fecha_fin)',
  'SELECT "index idx_fecha_rango ya existe"');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- idx_renovacion_activo en empresas
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'empresas' AND index_name = 'idx_renovacion_activo';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE empresas ADD INDEX idx_renovacion_activo (fecha_renovacion, activo)',
  'SELECT "index idx_renovacion_activo ya existe"');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Tabla membresias_upgrades (creación segura)
CREATE TABLE IF NOT EXISTS membresias_upgrades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    membresia_anterior_id INT,
    membresia_nueva_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('EFECTIVO', 'TRANSFERENCIA', 'TARJETA', 'PAYPAL', 'OTRO') DEFAULT 'PAYPAL',
    estado ENUM('PENDIENTE', 'COMPLETADO', 'CANCELADO') DEFAULT 'PENDIENTE',
    referencia_pago VARCHAR(100),
    paypal_order_id VARCHAR(100),
    notas TEXT,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (membresia_anterior_id) REFERENCES membresias(id) ON DELETE SET NULL,
    FOREIGN KEY (membresia_nueva_id) REFERENCES membresias(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_solicitud (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Tabla perfil_completitud
CREATE TABLE IF NOT EXISTS perfil_completitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL UNIQUE,
    campos_totales INT DEFAULT 20,
    campos_completados INT DEFAULT 0,
    porcentaje DECIMAL(5,2) DEFAULT 0,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) membresias.nivel_orden -> añadir o modificar
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'membresias' AND column_name = 'nivel_orden';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE membresias ADD COLUMN nivel_orden INT DEFAULT 1 AFTER vigencia_meses',
  'ALTER TABLE membresias MODIFY COLUMN nivel_orden INT DEFAULT 1 AFTER vigencia_meses');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- índice idx_nivel en membresias
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'membresias' AND index_name = 'idx_nivel';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE membresias ADD INDEX idx_nivel (nivel_orden)',
  'SELECT "index idx_nivel ya existe"');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Actualizar niveles iniciales (estas consultas no fallarán aunque no haya coincidencias)
UPDATE membresias SET nivel_orden = 1 WHERE nombre LIKE '%Básica%' OR nombre LIKE '%Basic%';
UPDATE membresias SET nivel_orden = 2 WHERE nombre LIKE '%Estándar%' OR nombre LIKE '%Standard%';
UPDATE membresias SET nivel_orden = 3 WHERE nombre LIKE '%Premium%' OR nombre LIKE '%Oro%' OR nombre LIKE '%Gold%';
UPDATE membresias SET nivel_orden = 4 WHERE nombre LIKE '%Platinum%' OR nombre LIKE '%Platino%' OR nombre LIKE '%VIP%';

-- 7) Configuración PayPal (inserciones seguras)
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_client_id', '', 'Client ID de PayPal para pagos')
ON DUPLICATE KEY UPDATE descripcion = 'Client ID de PayPal para pagos';

INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_secret', '', 'Secret de PayPal para pagos')
ON DUPLICATE KEY UPDATE descripcion = 'Secret de PayPal para pagos';

INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_mode', 'sandbox', 'Modo de PayPal: sandbox o live')
ON DUPLICATE KEY UPDATE descripcion = 'Modo de PayPal: sandbox o live';

-- 8) eventos.costo -> Añadir si no existe, si existe MODIFY para asegurar tipo/default
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos' AND column_name = 'costo';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos ADD COLUMN costo DECIMAL(10,2) DEFAULT 0 AFTER cupo_maximo',
  'ALTER TABLE eventos MODIFY COLUMN costo DECIMAL(10,2) DEFAULT 0 AFTER cupo_maximo');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9) Vista vista_calendario_eventos (CREATE OR REPLACE)
CREATE OR REPLACE VIEW vista_calendario_eventos AS
SELECT 
    e.id,
    'EVENTO' as tipo,
    e.titulo as titulo,
    e.descripcion,
    e.fecha_inicio,
    e.fecha_fin,
    e.ubicacion,
    e.tipo as categoria,
    e.cupo_maximo,
    e.inscritos,
    e.requiere_inscripcion,
    COALESCE(e.costo,0) as costo,
    NULL as empresa_id,
    NULL as empresa_nombre
FROM eventos e
WHERE e.activo = 1;

-- 10) Vista vista_calendario_renovaciones
CREATE OR REPLACE VIEW vista_calendario_renovaciones AS
SELECT 
    emp.id,
    'RENOVACION' as tipo,
    CONCAT('Renovación: ', emp.razon_social) as titulo,
    CONCAT('Vencimiento de membresía - ', COALESCE(m.nombre,'')) as descripcion,
    emp.fecha_renovacion as fecha_inicio,
    emp.fecha_renovacion as fecha_fin,
    emp.direccion_comercial as ubicacion,
    'RENOVACION' as categoria,
    NULL as cupo_maximo,
    NULL as inscritos,
    0 as requiere_inscripcion,
    COALESCE(m.costo,0) as costo,
    emp.id as empresa_id,
    emp.razon_social as empresa_nombre
FROM empresas emp
LEFT JOIN membresias m ON emp.membresia_id = m.id
WHERE emp.activo = 1 
AND emp.fecha_renovacion IS NOT NULL
AND emp.fecha_renovacion >= CURDATE();

-- 11) empresas.porcentaje_perfil -> añadir o modificar
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'empresas' AND column_name = 'porcentaje_perfil';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE empresas ADD COLUMN porcentaje_perfil DECIMAL(5,2) DEFAULT 0 AFTER descripcion',
  'ALTER TABLE empresas MODIFY COLUMN porcentaje_perfil DECIMAL(5,2) DEFAULT 0 AFTER descripcion');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices adicionales (comprobación de existencia antes de crear)
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'eventos' AND index_name = 'idx_eventos_fecha_tipo';

SET @sql = IF(@cnt = 0,
  'CREATE INDEX idx_eventos_fecha_tipo ON eventos(fecha_inicio, tipo, activo)',
  'SELECT "index idx_eventos_fecha_tipo ya existe"');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'empresas' AND index_name = 'idx_empresas_renovacion_fecha';

SET @sql = IF(@cnt = 0,
  'CREATE INDEX idx_empresas_renovacion_fecha ON empresas(fecha_renovacion, activo)',
  'SELECT "index idx_empresas_renovacion_fecha ya existe"');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 12) Triggers para actualizar porcentaje de perfil automáticamente
-- Eliminamos triggers previos si existen y luego los creamos (compatible con MySQL)
DROP TRIGGER IF EXISTS actualizar_porcentaje_perfil_insert;
DROP TRIGGER IF EXISTS actualizar_porcentaje_perfil_update;

DELIMITER $$
CREATE TRIGGER actualizar_porcentaje_perfil_insert
AFTER INSERT ON empresas
FOR EACH ROW
BEGIN
    DECLARE campos_completados INT DEFAULT 0;
    DECLARE campos_totales INT DEFAULT 20;
    
    IF NEW.razon_social IS NOT NULL AND NEW.razon_social != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.rfc IS NOT NULL AND NEW.rfc != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.email IS NOT NULL AND NEW.email != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.telefono IS NOT NULL AND NEW.telefono != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.whatsapp IS NOT NULL AND NEW.whatsapp != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.representante IS NOT NULL AND NEW.representante != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.direccion_comercial IS NOT NULL AND NEW.direccion_comercial != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.direccion_fiscal IS NOT NULL AND NEW.direccion_fiscal != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.colonia IS NOT NULL AND NEW.colonia != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.ciudad IS NOT NULL AND NEW.ciudad != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.codigo_postal IS NOT NULL AND NEW.codigo_postal != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.sector_id IS NOT NULL THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.categoria_id IS NOT NULL THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.membresia_id IS NOT NULL THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.descripcion IS NOT NULL AND NEW.descripcion != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.servicios_productos IS NOT NULL AND NEW.servicios_productos != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.palabras_clave IS NOT NULL AND NEW.palabras_clave != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.sitio_web IS NOT NULL AND NEW.sitio_web != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.facebook IS NOT NULL AND NEW.facebook != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.instagram IS NOT NULL AND NEW.instagram != '' THEN SET campos_completados = campos_completados + 1; END IF;
    
    UPDATE empresas SET porcentaje_perfil = (campos_completados * 100.0 / campos_totales) WHERE id = NEW.id;
    
    INSERT INTO perfil_completitud (empresa_id, campos_totales, campos_completados, porcentaje)
    VALUES (NEW.id, campos_totales, campos_completados, (campos_completados * 100.0 / campos_totales))
    ON DUPLICATE KEY UPDATE 
        campos_completados = VALUES(campos_completados),
        porcentaje = VALUES(porcentaje);
END$$

CREATE TRIGGER actualizar_porcentaje_perfil_update
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    DECLARE campos_completados INT DEFAULT 0;
    DECLARE campos_totales INT DEFAULT 20;
    
    IF NEW.razon_social IS NOT NULL AND NEW.razon_social != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.rfc IS NOT NULL AND NEW.rfc != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.email IS NOT NULL AND NEW.email != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.telefono IS NOT NULL AND NEW.telefono != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.whatsapp IS NOT NULL AND NEW.whatsapp != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.representante IS NOT NULL AND NEW.representante != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.direccion_comercial IS NOT NULL AND NEW.direccion_comercial != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.direccion_fiscal IS NOT NULL AND NEW.direccion_fiscal != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.colonia IS NOT NULL AND NEW.colonia != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.ciudad IS NOT NULL AND NEW.ciudad != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.codigo_postal IS NOT NULL AND NEW.codigo_postal != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.sector_id IS NOT NULL THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.categoria_id IS NOT NULL THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.membresia_id IS NOT NULL THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.descripcion IS NOT NULL AND NEW.descripcion != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.servicios_productos IS NOT NULL AND NEW.servicios_productos != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.palabras_clave IS NOT NULL AND NEW.palabras_clave != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.sitio_web IS NOT NULL AND NEW.sitio_web != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.facebook IS NOT NULL AND NEW.facebook != '' THEN SET campos_completados = campos_completados + 1; END IF;
    IF NEW.instagram IS NOT NULL AND NEW.instagram != '' THEN SET campos_completados = campos_completados + 1; END IF;
    
    UPDATE empresas SET porcentaje_perfil = (campos_completados * 100.0 / campos_totales) WHERE id = NEW.id;
    
    INSERT INTO perfil_completitud (empresa_id, campos_totales, campos_completados, porcentaje)
    VALUES (NEW.id, campos_totales, campos_completados, (campos_completados * 100.0 / campos_totales))
    ON DUPLICATE KEY UPDATE 
        campos_completados = VALUES(campos_completados),
        porcentaje = VALUES(porcentaje);
END$$
DELIMITER ;

-- 13) Actualizar porcentajes para empresas existentes (insertar o actualizar perfil_completitud)
INSERT INTO perfil_completitud (empresa_id, campos_totales, campos_completados, porcentaje)
SELECT 
    id,
    20 as campos_totales,
    (
        (CASE WHEN razon_social IS NOT NULL AND razon_social != '' THEN 1 ELSE 0 END) +
        (CASE WHEN rfc IS NOT NULL AND rfc != '' THEN 1 ELSE 0 END) +
        (CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) +
        (CASE WHEN telefono IS NOT NULL AND telefono != '' THEN 1 ELSE 0 END) +
        (CASE WHEN whatsapp IS NOT NULL AND whatsapp != '' THEN 1 ELSE 0 END) +
        (CASE WHEN representante IS NOT NULL AND representante != '' THEN 1 ELSE 0 END) +
        (CASE WHEN direccion_comercial IS NOT NULL AND direccion_comercial != '' THEN 1 ELSE 0 END) +
        (CASE WHEN direccion_fiscal IS NOT NULL AND direccion_fiscal != '' THEN 1 ELSE 0 END) +
        (CASE WHEN colonia IS NOT NULL AND colonia != '' THEN 1 ELSE 0 END) +
        (CASE WHEN ciudad IS NOT NULL AND ciudad != '' THEN 1 ELSE 0 END) +
        (CASE WHEN codigo_postal IS NOT NULL AND codigo_postal != '' THEN 1 ELSE 0 END) +
        (CASE WHEN sector_id IS NOT NULL THEN 1 ELSE 0 END) +
        (CASE WHEN categoria_id IS NOT NULL THEN 1 ELSE 0 END) +
        (CASE WHEN membresia_id IS NOT NULL THEN 1 ELSE 0 END) +
        (CASE WHEN descripcion IS NOT NULL AND descripcion != '' THEN 1 ELSE 0 END) +
        (CASE WHEN servicios_productos IS NOT NULL AND servicios_productos != '' THEN 1 ELSE 0 END) +
        (CASE WHEN palabras_clave IS NOT NULL AND palabras_clave != '' THEN 1 ELSE 0 END) +
        (CASE WHEN sitio_web IS NOT NULL AND sitio_web != '' THEN 1 ELSE 0 END) +
        (CASE WHEN facebook IS NOT NULL AND facebook != '' THEN 1 ELSE 0 END) +
        (CASE WHEN instagram IS NOT NULL AND instagram != '' THEN 1 ELSE 0 END)
    ) as campos_completados,
    (
        (
            (CASE WHEN razon_social IS NOT NULL AND razon_social != '' THEN 1 ELSE 0 END) +
            (CASE WHEN rfc IS NOT NULL AND rfc != '' THEN 1 ELSE 0 END) +
            (CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) +
            (CASE WHEN telefono IS NOT NULL AND telefono != '' THEN 1 ELSE 0 END) +
            (CASE WHEN whatsapp IS NOT NULL AND whatsapp != '' THEN 1 ELSE 0 END) +
            (CASE WHEN representante IS NOT NULL AND representante != '' THEN 1 ELSE 0 END) +
            (CASE WHEN direccion_comercial IS NOT NULL AND direccion_comercial != '' THEN 1 ELSE 0 END) +
            (CASE WHEN direccion_fiscal IS NOT NULL AND direccion_fiscal != '' THEN 1 ELSE 0 END) +
            (CASE WHEN colonia IS NOT NULL AND colonia != '' THEN 1 ELSE 0 END) +
            (CASE WHEN ciudad IS NOT NULL AND ciudad != '' THEN 1 ELSE 0 END) +
            (CASE WHEN codigo_postal IS NOT NULL AND codigo_postal != '' THEN 1 ELSE 0 END) +
            (CASE WHEN sector_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN categoria_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN membresia_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN descripcion IS NOT NULL AND descripcion != '' THEN 1 ELSE 0 END) +
            (CASE WHEN servicios_productos IS NOT NULL AND servicios_productos != '' THEN 1 ELSE 0 END) +
            (CASE WHEN palabras_clave IS NOT NULL AND palabras_clave != '' THEN 1 ELSE 0 END) +
            (CASE WHEN sitio_web IS NOT NULL AND sitio_web != '' THEN 1 ELSE 0 END) +
            (CASE WHEN facebook IS NOT NULL AND facebook != '' THEN 1 ELSE 0 END) +
            (CASE WHEN instagram IS NOT NULL AND instagram != '' THEN 1 ELSE 0 END)
        ) * 100.0 / 20
    ) as porcentaje
FROM empresas
ON DUPLICATE KEY UPDATE 
    campos_completados = VALUES(campos_completados),
    porcentaje = VALUES(porcentaje);
