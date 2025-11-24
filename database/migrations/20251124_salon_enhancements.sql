-- Migration: Enhance salones module with images, payments and differentiated pricing
-- Date: 2025-11-24
-- Description: Adds image gallery, payment integration, and membership-based pricing for salons

USE crm_camara_comercio;

-- Table for salon images
CREATE TABLE IF NOT EXISTS salon_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL COMMENT 'Reference to salones table',
    ruta_imagen VARCHAR(255) NOT NULL COMMENT 'Path to the image file',
    descripcion VARCHAR(255) COMMENT 'Image description',
    orden INT DEFAULT 0 COMMENT 'Display order',
    es_principal TINYINT(1) DEFAULT 0 COMMENT 'Is this the main image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salones(id) ON DELETE CASCADE,
    INDEX idx_salon_orden (salon_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add pricing fields to salones table for differentiated pricing
ALTER TABLE salones 
ADD COLUMN IF NOT EXISTS precio_hora_afiliado DECIMAL(10,2) DEFAULT 0 COMMENT 'Hourly rate for affiliated members',
ADD COLUMN IF NOT EXISTS precio_dia_afiliado DECIMAL(10,2) DEFAULT 0 COMMENT 'Daily rate for affiliated members',
ADD COLUMN IF NOT EXISTS precio_evento_afiliado DECIMAL(10,2) DEFAULT 0 COMMENT 'Event rate for affiliated members',
ADD COLUMN IF NOT EXISTS precio_hora_no_afiliado DECIMAL(10,2) DEFAULT 0 COMMENT 'Hourly rate for non-affiliated',
ADD COLUMN IF NOT EXISTS precio_dia_no_afiliado DECIMAL(10,2) DEFAULT 0 COMMENT 'Daily rate for non-affiliated',
ADD COLUMN IF NOT EXISTS precio_evento_no_afiliado DECIMAL(10,2) DEFAULT 0 COMMENT 'Event rate for non-affiliated';

-- Add payment fields to salon_reservas table
ALTER TABLE salon_reservas
ADD COLUMN IF NOT EXISTS metodo_pago ENUM('PAYPAL', 'TRANSFERENCIA', 'EFECTIVO', 'COMPROBANTE') COMMENT 'Payment method',
ADD COLUMN IF NOT EXISTS comprobante_pago VARCHAR(255) COMMENT 'Payment receipt file path',
ADD COLUMN IF NOT EXISTS paypal_order_id VARCHAR(100) COMMENT 'PayPal order ID',
ADD COLUMN IF NOT EXISTS paypal_payer_id VARCHAR(100) COMMENT 'PayPal payer ID',
ADD COLUMN IF NOT EXISTS paypal_payment_status VARCHAR(50) COMMENT 'PayPal payment status',
ADD COLUMN IF NOT EXISTS descuento_porcentaje DECIMAL(5,2) DEFAULT 0 COMMENT 'Discount percentage applied',
ADD COLUMN IF NOT EXISTS monto_total DECIMAL(10,2) DEFAULT 0 COMMENT 'Total amount before discount',
ADD COLUMN IF NOT EXISTS monto_descuento DECIMAL(10,2) DEFAULT 0 COMMENT 'Discount amount',
ADD COLUMN IF NOT EXISTS monto_final DECIMAL(10,2) DEFAULT 0 COMMENT 'Final amount after discount',
ADD COLUMN IF NOT EXISTS es_afiliado TINYINT(1) DEFAULT 0 COMMENT 'Is the user affiliated',
ADD COLUMN IF NOT EXISTS nivel_membresia VARCHAR(100) COMMENT 'Membership level at time of reservation',
ADD COLUMN IF NOT EXISTS fecha_pago DATETIME COMMENT 'Payment date',
ADD COLUMN IF NOT EXISTS comprobante_enviado TINYINT(1) DEFAULT 0 COMMENT 'Payment confirmation sent via email';

-- Update existing salon prices to set affiliated/non-affiliated prices
-- Set affiliated prices to current prices (assuming they're for affiliates)
UPDATE salones 
SET precio_hora_afiliado = precio_hora,
    precio_dia_afiliado = precio_dia,
    precio_evento_afiliado = precio_evento,
    precio_hora_no_afiliado = precio_hora * 1.3,
    precio_dia_no_afiliado = precio_dia * 1.3,
    precio_evento_no_afiliado = precio_evento * 1.3
WHERE precio_hora_afiliado IS NULL OR precio_hora_afiliado = 0;

-- Add discount percentage to membresias table
ALTER TABLE membresias
ADD COLUMN IF NOT EXISTS descuento_salones DECIMAL(5,2) DEFAULT 0 COMMENT 'Discount percentage for salon reservations';

-- Update existing memberships with default discounts based on their level/cost
UPDATE membresias SET descuento_salones = 
    CASE 
        WHEN costo >= 10000 THEN 15.00  -- Premium memberships get 15% discount
        WHEN costo >= 5000 THEN 10.00   -- Mid-tier memberships get 10% discount
        WHEN costo >= 2000 THEN 5.00    -- Basic memberships get 5% discount
        ELSE 0.00                        -- No discount for others
    END
WHERE descuento_salones = 0;

-- Add compound index for better reservation conflict checking performance
CREATE INDEX IF NOT EXISTS idx_salon_fecha_estado ON salon_reservas(salon_id, fecha_inicio, fecha_fin, estado);
