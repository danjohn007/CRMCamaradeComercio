-- Migration: PayPal Configuration for Event Payments
-- Date: 2025-11-10
-- Description: Adds complete PayPal configuration fields to support event payments

USE crm_camara_comercio;

SET @db := DATABASE();

-- 1) PayPal Client ID
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'configuracion' AND column_name = 'paypal_client_id';

INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_client_id', '', 'ID de cliente de la aplicación PayPal')
ON DUPLICATE KEY UPDATE descripcion = 'ID de cliente de la aplicación PayPal';

-- 2) PayPal Client Secret
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_secret', '', 'Secreto del cliente de la aplicación PayPal')
ON DUPLICATE KEY UPDATE descripcion = 'Secreto del cliente de la aplicación PayPal';

-- 3) PayPal Environment/Mode
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_mode', 'sandbox', 'Entorno de PayPal: sandbox o live')
ON DUPLICATE KEY UPDATE descripcion = 'Entorno de PayPal: sandbox o live';

-- 4) PayPal Monthly Plan ID
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_plan_id_monthly', '', 'ID del plan de suscripción mensual en PayPal (P-XXXXXXXXXXXX)')
ON DUPLICATE KEY UPDATE descripcion = 'ID del plan de suscripción mensual en PayPal (P-XXXXXXXXXXXX)';

-- 5) PayPal Annual Plan ID  
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_plan_id_annual', '', 'ID del plan de suscripción anual en PayPal (P-YYYYYYYYYYYY)')
ON DUPLICATE KEY UPDATE descripcion = 'ID del plan de suscripción anual en PayPal (P-YYYYYYYYYYYY)';

-- 6) PayPal Webhook URL
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_webhook_url', '', 'URL para recibir notificaciones de PayPal sobre cambios en suscripciones')
ON DUPLICATE KEY UPDATE descripcion = 'URL para recibir notificaciones de PayPal sobre cambios en suscripciones';

-- 7) Ensure paypal_account exists (Primary PayPal Account)
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('paypal_account', '', 'Cuenta de PayPal para recibir los pagos del sistema')
ON DUPLICATE KEY UPDATE descripcion = 'Cuenta de PayPal para recibir los pagos del sistema';

-- 8) Add paypal_order_id to eventos_inscripciones if not exists
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND column_name = 'paypal_order_id';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos_inscripciones ADD COLUMN paypal_order_id VARCHAR(100) DEFAULT NULL AFTER referencia_pago',
  'ALTER TABLE eventos_inscripciones MODIFY COLUMN paypal_order_id VARCHAR(100) DEFAULT NULL AFTER referencia_pago');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9) Add index for paypal_order_id searches
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND index_name = 'idx_paypal_order';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos_inscripciones ADD INDEX idx_paypal_order (paypal_order_id)',
  'SELECT "index idx_paypal_order already exists"');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 10) Add razon_social_invitado if not exists (for invoice/receipt purposes)
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS
 WHERE table_schema = @db AND table_name = 'eventos_inscripciones' AND column_name = 'razon_social_invitado';

SET @sql = IF(@cnt = 0,
  'ALTER TABLE eventos_inscripciones ADD COLUMN razon_social_invitado VARCHAR(255) DEFAULT NULL AFTER rfc_invitado',
  'ALTER TABLE eventos_inscripciones MODIFY COLUMN razon_social_invitado VARCHAR(255) DEFAULT NULL AFTER rfc_invitado');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
