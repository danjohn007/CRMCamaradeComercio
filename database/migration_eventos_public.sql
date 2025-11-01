-- Migration para agregar funcionalidad de registro público a eventos
-- Fecha: 2025-11-01
-- Descripción: Agrega campos de costo a eventos y permite registro público sin usuario

-- 1. Agregar campo de costo a eventos
ALTER TABLE eventos 
ADD COLUMN costo DECIMAL(10,2) DEFAULT 0 AFTER cupo_maximo,
ADD COLUMN enlace_publico VARCHAR(255) AFTER enlace_externo;

-- 2. Modificar tabla de inscripciones para permitir invitados (sin usuario_id obligatorio)
ALTER TABLE eventos_inscripciones 
MODIFY COLUMN usuario_id INT NULL,
ADD COLUMN nombre_invitado VARCHAR(150) AFTER empresa_id,
ADD COLUMN email_invitado VARCHAR(100) AFTER nombre_invitado,
ADD COLUMN telefono_invitado VARCHAR(20) AFTER email_invitado,
ADD COLUMN whatsapp_invitado VARCHAR(20) AFTER telefono_invitado,
ADD COLUMN rfc_invitado VARCHAR(13) AFTER whatsapp_invitado,
ADD COLUMN boletos_solicitados INT DEFAULT 1 AFTER rfc_invitado,
ADD COLUMN es_invitado TINYINT(1) DEFAULT 0 AFTER boletos_solicitados,
DROP INDEX unique_inscripcion;

-- 3. Crear nuevo índice único que considere también invitados
ALTER TABLE eventos_inscripciones
ADD UNIQUE KEY unique_inscripcion_usuario (evento_id, usuario_id),
ADD UNIQUE KEY unique_inscripcion_invitado (evento_id, email_invitado);

-- 4. Agregar índice para búsqueda por WhatsApp y RFC
ALTER TABLE eventos_inscripciones
ADD INDEX idx_whatsapp (whatsapp_invitado),
ADD INDEX idx_rfc (rfc_invitado);

-- 5. Crear tabla para tokens de enlace público de eventos (para seguridad)
CREATE TABLE IF NOT EXISTS eventos_enlaces_publicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_evento (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
