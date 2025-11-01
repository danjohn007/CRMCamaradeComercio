-- Migration para nuevas funcionalidades del sistema CRM
-- Autor: Sistema CRM CANACO
-- Fecha: 2025-11-01
-- Descripción: Agrega soporte para registro de pagos con evidencia, calendario, 
--              actualización de membresías y participantes de eventos

USE crm_camara_comercio;

-- 1. Agregar campo de evidencia de pago a la tabla pagos
ALTER TABLE pagos 
ADD COLUMN IF NOT EXISTS evidencia_pago VARCHAR(255) DEFAULT NULL AFTER notas,
ADD INDEX idx_evidencia (evidencia_pago);

-- 2. Agregar campos de pago a eventos_inscripciones
ALTER TABLE eventos_inscripciones 
ADD COLUMN IF NOT EXISTS estado_pago ENUM('SIN_PAGO', 'PENDIENTE', 'COMPLETADO', 'CANCELADO') DEFAULT 'SIN_PAGO' AFTER estado,
ADD COLUMN IF NOT EXISTS monto_pagado DECIMAL(10,2) DEFAULT 0 AFTER estado_pago,
ADD COLUMN IF NOT EXISTS fecha_pago DATETIME DEFAULT NULL AFTER monto_pagado,
ADD COLUMN IF NOT EXISTS referencia_pago VARCHAR(100) DEFAULT NULL AFTER fecha_pago;

-- 3. Agregar índices para mejorar rendimiento de calendario
ALTER TABLE eventos 
ADD INDEX IF NOT EXISTS idx_fecha_rango (fecha_inicio, fecha_fin);

ALTER TABLE empresas 
ADD INDEX IF NOT EXISTS idx_renovacion_activo (fecha_renovacion, activo);

-- 4. Tabla para registro de cambios/upgrades de membresías
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

-- 5. Tabla para tracking de completitud de perfil
CREATE TABLE IF NOT EXISTS perfil_completitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL UNIQUE,
    campos_totales INT DEFAULT 20,
    campos_completados INT DEFAULT 0,
    porcentaje DECIMAL(5,2) DEFAULT 0,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Agregar campo para orden de membresías (para determinar niveles superiores)
ALTER TABLE membresias 
ADD COLUMN IF NOT EXISTS nivel_orden INT DEFAULT 1 AFTER vigencia_meses,
ADD INDEX idx_nivel (nivel_orden);

-- Actualizar niveles iniciales de membresías existentes (ajustar según necesidad)
UPDATE membresias SET nivel_orden = 1 WHERE nombre LIKE '%Básica%' OR nombre LIKE '%Basic%';
UPDATE membresias SET nivel_orden = 2 WHERE nombre LIKE '%Estándar%' OR nombre LIKE '%Standard%';
UPDATE membresias SET nivel_orden = 3 WHERE nombre LIKE '%Premium%' OR nombre LIKE '%Oro%' OR nombre LIKE '%Gold%';
UPDATE membresias SET nivel_orden = 4 WHERE nombre LIKE '%Platinum%' OR nombre LIKE '%Platino%' OR nombre LIKE '%VIP%';

-- 7. Agregar configuración de PayPal para membresías si no existe
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_client_id', '', 'Client ID de PayPal para pagos')
ON DUPLICATE KEY UPDATE descripcion = 'Client ID de PayPal para pagos';

INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_secret', '', 'Secret de PayPal para pagos')
ON DUPLICATE KEY UPDATE descripcion = 'Secret de PayPal para pagos';

INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_mode', 'sandbox', 'Modo de PayPal: sandbox o live')
ON DUPLICATE KEY UPDATE descripcion = 'Modo de PayPal: sandbox o live';

-- 8. Agregar campo costo a eventos si no existe (para eventos con pago)
ALTER TABLE eventos 
ADD COLUMN IF NOT EXISTS costo DECIMAL(10,2) DEFAULT 0 AFTER cupo_maximo;

-- 9. Crear vista para facilitar consultas de calendario
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
    e.costo,
    NULL as empresa_id,
    NULL as empresa_nombre
FROM eventos e
WHERE e.activo = 1;

-- 10. Crear vista para renovaciones en calendario
CREATE OR REPLACE VIEW vista_calendario_renovaciones AS
SELECT 
    emp.id,
    'RENOVACION' as tipo,
    CONCAT('Renovación: ', emp.razon_social) as titulo,
    CONCAT('Vencimiento de membresía - ', m.nombre) as descripcion,
    emp.fecha_renovacion as fecha_inicio,
    emp.fecha_renovacion as fecha_fin,
    emp.direccion_comercial as ubicacion,
    'RENOVACION' as categoria,
    NULL as cupo_maximo,
    NULL as inscritos,
    0 as requiere_inscripcion,
    m.costo,
    emp.id as empresa_id,
    emp.razon_social as empresa_nombre
FROM empresas emp
LEFT JOIN membresias m ON emp.membresia_id = m.id
WHERE emp.activo = 1 
AND emp.fecha_renovacion IS NOT NULL
AND emp.fecha_renovacion >= CURDATE();

-- 11. Agregar campos para mejorar el tracking de perfil de empresa
ALTER TABLE empresas
ADD COLUMN IF NOT EXISTS porcentaje_perfil DECIMAL(5,2) DEFAULT 0 AFTER descripcion;

-- Comentarios sobre el uso del sistema
-- La columna evidencia_pago en pagos almacenará la ruta del archivo de evidencia subido
-- Las vistas vista_calendario_eventos y vista_calendario_renovaciones facilitan la obtención de datos para el calendario
-- La tabla membresias_upgrades registra todos los cambios de membresía solicitados
-- La tabla perfil_completitud ayuda a trackear el avance de completitud de perfil

-- Índices adicionales para optimizar consultas de calendario
CREATE INDEX IF NOT EXISTS idx_eventos_fecha_tipo ON eventos(fecha_inicio, tipo, activo);
CREATE INDEX IF NOT EXISTS idx_empresas_renovacion_fecha ON empresas(fecha_renovacion, activo);

-- Trigger para actualizar porcentaje de perfil automáticamente
DELIMITER //

CREATE TRIGGER IF NOT EXISTS actualizar_porcentaje_perfil_insert
AFTER INSERT ON empresas
FOR EACH ROW
BEGIN
    DECLARE campos_completados INT DEFAULT 0;
    DECLARE campos_totales INT DEFAULT 20;
    
    -- Contar campos completados (no NULL y no vacíos)
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
    
    -- Actualizar el porcentaje
    UPDATE empresas SET porcentaje_perfil = (campos_completados * 100.0 / campos_totales) WHERE id = NEW.id;
    
    -- Insertar o actualizar en perfil_completitud
    INSERT INTO perfil_completitud (empresa_id, campos_totales, campos_completados, porcentaje)
    VALUES (NEW.id, campos_totales, campos_completados, (campos_completados * 100.0 / campos_totales))
    ON DUPLICATE KEY UPDATE 
        campos_completados = campos_completados,
        porcentaje = (campos_completados * 100.0 / campos_totales);
END//

CREATE TRIGGER IF NOT EXISTS actualizar_porcentaje_perfil_update
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    DECLARE campos_completados INT DEFAULT 0;
    DECLARE campos_totales INT DEFAULT 20;
    
    -- Contar campos completados (no NULL y no vacíos)
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
    
    -- Actualizar el porcentaje
    UPDATE empresas SET porcentaje_perfil = (campos_completados * 100.0 / campos_totales) WHERE id = NEW.id;
    
    -- Insertar o actualizar en perfil_completitud
    INSERT INTO perfil_completitud (empresa_id, campos_totales, campos_completados, porcentaje)
    VALUES (NEW.id, campos_totales, campos_completados, (campos_completados * 100.0 / campos_totales))
    ON DUPLICATE KEY UPDATE 
        campos_completados = campos_completados,
        porcentaje = (campos_completados * 100.0 / campos_totales);
END//

DELIMITER ;

-- Actualizar porcentajes para empresas existentes
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
