-- Migración de ajustes finales del sistema
-- Fecha: 2025-11-02
-- Descripción: Soporte para ajustes solicitados en el sistema

USE crm_camara_comercio;

-- 1. Agregar campo para evidencia obligatoria en movimientos financieros (si no existe)
ALTER TABLE finanzas_movimientos 
ADD COLUMN IF NOT EXISTS evidencia VARCHAR(255) AFTER comprobante,
ADD COLUMN IF NOT EXISTS evidencia_obligatoria TINYINT(1) DEFAULT 0 AFTER evidencia;

-- 2. Agregar campo para constancia fiscal en usuarios (para asociar empresa)
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS constancia_fiscal VARCHAR(255) AFTER avatar;

-- 3. Agregar índice para mejorar búsquedas de empresas por RFC
CREATE INDEX IF NOT EXISTS idx_empresas_rfc ON empresas(rfc);

-- 4. Asegurar que la tabla de auditoría tenga el campo detalles
ALTER TABLE auditoria
ADD COLUMN IF NOT EXISTS detalles TEXT AFTER datos_nuevos;

-- 5. Crear trigger para evitar duplicados en últimos movimientos financieros
-- Esto ayudará a rastrear el origen del movimiento

ALTER TABLE finanzas_movimientos
ADD COLUMN IF NOT EXISTS origen VARCHAR(50) DEFAULT 'MANUAL' AFTER usuario_id,
ADD COLUMN IF NOT EXISTS pago_id INT NULL AFTER origen,
ADD INDEX IF NOT EXISTS idx_origen (origen);

-- Agregar clave única compuesta para evitar duplicados desde pagos
ALTER TABLE finanzas_movimientos
ADD UNIQUE INDEX IF NOT EXISTS unique_pago_movimiento (pago_id, concepto, monto);

-- 6. Actualizar configuraciones del sistema con valores por defecto si no existen
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('email_contacto', 'contacto@camaraqro.com', 'Email de contacto del sistema')
ON DUPLICATE KEY UPDATE descripcion = 'Email de contacto del sistema';

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('telefono_contacto', '442-123-4567', 'Teléfono de contacto del sistema')
ON DUPLICATE KEY UPDATE descripcion = 'Teléfono de contacto del sistema';

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('horario_atencion', 'Lunes a Viernes 9:00 AM - 6:00 PM', 'Horario de atención')
ON DUPLICATE KEY UPDATE descripcion = 'Horario de atención';

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('whatsapp_chatbot', '', 'WhatsApp para chatbot del sistema')
ON DUPLICATE KEY UPDATE descripcion = 'WhatsApp para chatbot del sistema';

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('footer_link_text', 'Estrategia Digital desarrollada por ID', 'Texto del enlace en el footer')
ON DUPLICATE KEY UPDATE descripcion = 'Texto del enlace en el footer';

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('footer_link_url', 'https://impactosdigitales.com', 'URL del enlace en el footer')
ON DUPLICATE KEY UPDATE descripcion = 'URL del enlace en el footer';

-- 7. Mejorar índices de auditoría para búsquedas más rápidas
CREATE INDEX IF NOT EXISTS idx_auditoria_fecha_accion ON auditoria(created_at, accion);
CREATE INDEX IF NOT EXISTS idx_auditoria_usuario_fecha ON auditoria(usuario_id, created_at);

-- 8. Agregar campos adicionales para el registro de empresas
ALTER TABLE empresas
ADD COLUMN IF NOT EXISTS registro_completado TINYINT(1) DEFAULT 0 AFTER verificado;

-- 9. Crear tabla para seguimiento de asociación de empresas (si no existe)
CREATE TABLE IF NOT EXISTS empresas_asociaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    constancia_fiscal VARCHAR(255),
    estado ENUM('PENDIENTE', 'APROBADO', 'RECHAZADO') DEFAULT 'PENDIENTE',
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta TIMESTAMP NULL,
    aprobado_por INT NULL,
    notas TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Agregar configuración para evidencia obligatoria en movimientos
INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('finanzas_evidencia_obligatoria', '1', 'Si es 1, la evidencia es obligatoria en movimientos financieros')
ON DUPLICATE KEY UPDATE descripcion = 'Si es 1, la evidencia es obligatoria en movimientos financieros';

-- 11. Registrar esta migración en auditoría
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, detalles, created_at)
VALUES (1, 'MIGRATION', 'sistema', 'Migración de ajustes finales del sistema - evidencia obligatoria, asociación empresas, mejoras UI', NOW());

-- Notas importantes:
-- * Este script usa IF NOT EXISTS e IF NOT NULL para ser idempotente
-- * Los campos nuevos se pueden ejecutar múltiples veces sin error
-- * Los índices UNIQUE pueden fallar si hay datos duplicados existentes
-- * Revisar datos existentes antes de ejecutar en producción
