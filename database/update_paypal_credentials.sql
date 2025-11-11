-- Actualización de credenciales de PayPal en modo Sandbox
-- Credenciales proporcionadas para la aplicación "Canaco"

-- Actualizar o insertar Client ID de PayPal
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_client_id', 'Ads5V1Ttz4gtLmCYSZBxErKYdsA5hc4XvqyE7FVfM7WRLzO-DNuNtXUtzq6GvhMUUvOxiens7EnBeMXD', 'Client ID de PayPal para pagos')
ON DUPLICATE KEY UPDATE valor = 'Ads5V1Ttz4gtLmCYSZBxErKYdsA5hc4XvqyE7FVfM7WRLzO-DNuNtXUtzq6GvhMUUvOxiens7EnBeMXD';

-- Actualizar o insertar Secret Key de PayPal
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_secret', 'EJ6hBDoya6zU3iHQDDrSL-nklSDUbvgVuHVgg9MnwBbVrhJq9MKYV_PsOnKYqKiUy5vQVc5ipxuRcpvv', 'Secret Key de PayPal')
ON DUPLICATE KEY UPDATE valor = 'EJ6hBDoya6zU3iHQDDrSL-nklSDUbvgVuHVgg9MnwBbVrhJq9MKYV_PsOnKYqKiUy5vQVc5ipxuRcpvv';

-- Asegurar que el modo esté en sandbox
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_mode', 'sandbox', 'Modo de operación de PayPal (sandbox o live)')
ON DUPLICATE KEY UPDATE valor = 'sandbox';

-- Actualizar nombre de la aplicación
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_app_name', 'Canaco', 'Nombre de la aplicación en PayPal')
ON DUPLICATE KEY UPDATE valor = 'Canaco';
