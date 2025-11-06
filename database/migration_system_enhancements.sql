-- Migración para mejoras del sistema CRM
-- Fecha: Noviembre 2025
-- Descripción: Agrega colores adicionales para personalización y actualiza configuración SMTP

USE crm_camara_comercio;

-- Agregar configuraciones de colores adicionales para diferentes secciones
INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('color_terciario', '#6366F1', 'Color terciario para elementos complementarios')
ON DUPLICATE KEY UPDATE descripcion = 'Color terciario para elementos complementarios';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('color_acento1', '#F59E0B', 'Primer color de acento para destacar elementos')
ON DUPLICATE KEY UPDATE descripcion = 'Primer color de acento para destacar elementos';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('color_acento2', '#EC4899', 'Segundo color de acento para elementos especiales')
ON DUPLICATE KEY UPDATE descripcion = 'Segundo color de acento para elementos especiales';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('color_header', '#1E40AF', 'Color para el encabezado superior (top header)')
ON DUPLICATE KEY UPDATE descripcion = 'Color para el encabezado superior (top header)';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('color_sidebar', '#1F2937', 'Color para la barra lateral de navegación')
ON DUPLICATE KEY UPDATE descripcion = 'Color para la barra lateral de navegación';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('color_footer', '#111827', 'Color para el pie de página (footer)')
ON DUPLICATE KEY UPDATE descripcion = 'Color para el pie de página (footer)';

-- Configuración SMTP para el sistema de correos
-- Usuario: canaco@agenciaexperiencia.com
-- Servidor: agenciaexperiencia.com
-- SMTP Puerto: 465 (SSL)
-- IMAP Puerto: 993
-- POP3 Puerto: 995

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('smtp_host', 'agenciaexperiencia.com', 'Servidor SMTP para envío de correos')
ON DUPLICATE KEY UPDATE valor = 'agenciaexperiencia.com', descripcion = 'Servidor SMTP para envío de correos';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('smtp_port', '465', 'Puerto SMTP (465 para SSL, 587 para TLS)')
ON DUPLICATE KEY UPDATE valor = '465', descripcion = 'Puerto SMTP (465 para SSL, 587 para TLS)';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('smtp_user', 'canaco@agenciaexperiencia.com', 'Usuario para autenticación SMTP')
ON DUPLICATE KEY UPDATE valor = 'canaco@agenciaexperiencia.com', descripcion = 'Usuario para autenticación SMTP';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('smtp_pass', 'Danjohn007', 'Contraseña para autenticación SMTP')
ON DUPLICATE KEY UPDATE valor = 'Danjohn007', descripcion = 'Contraseña para autenticación SMTP';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('smtp_secure', 'ssl', 'Tipo de encriptación (ssl o tls)')
ON DUPLICATE KEY UPDATE valor = 'ssl', descripcion = 'Tipo de encriptación (ssl o tls)';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('smtp_from_name', 'CANACO Querétaro', 'Nombre del remitente en correos')
ON DUPLICATE KEY UPDATE valor = 'CANACO Querétaro', descripcion = 'Nombre del remitente en correos';

-- Configuración adicional para servidores de entrada (informativa)
INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('imap_host', 'agenciaexperiencia.com', 'Servidor IMAP para recepción de correos')
ON DUPLICATE KEY UPDATE valor = 'agenciaexperiencia.com', descripcion = 'Servidor IMAP para recepción de correos';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('imap_port', '993', 'Puerto IMAP con SSL')
ON DUPLICATE KEY UPDATE valor = '993', descripcion = 'Puerto IMAP con SSL';

INSERT INTO configuracion (clave, valor, descripcion) VALUES
    ('pop3_port', '995', 'Puerto POP3 con SSL')
ON DUPLICATE KEY UPDATE valor = '995', descripcion = 'Puerto POP3 con SSL';

-- Verificar las configuraciones insertadas
SELECT 'Configuraciones de colores y SMTP actualizadas correctamente' as mensaje;
