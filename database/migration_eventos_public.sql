-- Migration para agregar funcionalidad de registro público a eventos
-- Fecha: 2025-11-01
-- Descripción: Agrega campos de costo a eventos y permite registro público sin usuario
-- IMPORTANTE: Ejecutar este script una sola vez. Si alguna columna ya existe, comentar esa línea.

-- 1. Agregar campo de costo a eventos
-- Verificar primero si la columna existe con: SHOW COLUMNS FROM eventos LIKE 'costo';
ALTER TABLE eventos 
ADD COLUMN costo DECIMAL(10,2) DEFAULT 0 AFTER cupo_maximo;

-- Agregar enlace público después de imagen
ALTER TABLE eventos 
ADD COLUMN enlace_publico VARCHAR(255) AFTER imagen;

-- 2. Modificar tabla de inscripciones para permitir invitados (sin usuario_id obligatorio)
ALTER TABLE eventos_inscripciones 
MODIFY COLUMN usuario_id INT NULL;

ALTER TABLE eventos_inscripciones 
ADD COLUMN nombre_invitado VARCHAR(150) AFTER empresa_id,
ADD COLUMN email_invitado VARCHAR(100) AFTER nombre_invitado,
ADD COLUMN telefono_invitado VARCHAR(20) AFTER email_invitado,
ADD COLUMN whatsapp_invitado VARCHAR(20) AFTER telefono_invitado,
ADD COLUMN rfc_invitado VARCHAR(13) AFTER whatsapp_invitado,
ADD COLUMN boletos_solicitados INT DEFAULT 1 AFTER rfc_invitado,
ADD COLUMN es_invitado TINYINT(1) DEFAULT 0 AFTER boletos_solicitados;

-- 3. Modificar índice único para permitir invitados
-- Primero eliminar el índice existente (ignorar error si no existe)
ALTER TABLE eventos_inscripciones DROP INDEX unique_inscripcion;

-- Crear nuevos índices únicos que consideren invitados
-- Nota: Los índices únicos permiten NULL, por lo que funcionarán correctamente
ALTER TABLE eventos_inscripciones
ADD UNIQUE KEY unique_inscripcion_usuario (evento_id, usuario_id);

ALTER TABLE eventos_inscripciones
ADD UNIQUE KEY unique_inscripcion_invitado (evento_id, email_invitado);

-- 4. Agregar índices para búsqueda por WhatsApp y RFC
ALTER TABLE eventos_inscripciones
ADD INDEX idx_whatsapp (whatsapp_invitado);

ALTER TABLE eventos_inscripciones
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
