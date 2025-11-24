-- Migration: Add salones (rental spaces) module
-- Date: 2025-11-24
-- Description: Creates tables for managing rental spaces (salones, offices) and their reservations

USE crm_camara_comercio;

-- Table for rental spaces (salones, offices, meeting rooms, etc.)
CREATE TABLE IF NOT EXISTS salones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL COMMENT 'Name of the space',
    tipo ENUM('SALON', 'OFICINA', 'AUDITORIO', 'SALA_JUNTAS', 'OTRO') DEFAULT 'SALON' COMMENT 'Type of space',
    descripcion TEXT COMMENT 'Description of the space',
    capacidad INT DEFAULT 0 COMMENT 'Maximum capacity (persons)',
    caracteristicas TEXT COMMENT 'Features and equipment available',
    precio_hora DECIMAL(10,2) DEFAULT 0 COMMENT 'Hourly rate',
    precio_dia DECIMAL(10,2) DEFAULT 0 COMMENT 'Daily rate',
    precio_evento DECIMAL(10,2) DEFAULT 0 COMMENT 'Event rate (full use)',
    formas_pago TEXT COMMENT 'Accepted payment methods',
    procedimiento_contratacion TEXT COMMENT 'Hiring/rental procedure',
    activo TINYINT(1) DEFAULT 1 COMMENT 'Space is active and available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for space reservations
CREATE TABLE IF NOT EXISTS salon_reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL COMMENT 'Reference to salones table',
    empresa_id INT COMMENT 'Reference to empresas table (optional)',
    usuario_id INT NOT NULL COMMENT 'User who made the reservation',
    fecha_inicio DATETIME NOT NULL COMMENT 'Reservation start date/time',
    fecha_fin DATETIME NOT NULL COMMENT 'Reservation end date/time',
    proposito VARCHAR(255) COMMENT 'Purpose of the reservation',
    contacto_nombre VARCHAR(150) COMMENT 'Contact person name',
    contacto_email VARCHAR(100) COMMENT 'Contact email',
    contacto_telefono VARCHAR(20) COMMENT 'Contact phone',
    notas TEXT COMMENT 'Additional notes',
    estado ENUM('PENDIENTE', 'CONFIRMADA', 'CANCELADA') DEFAULT 'PENDIENTE' COMMENT 'Reservation status',
    fecha_confirmacion DATETIME COMMENT 'Confirmation date',
    monto_pagado DECIMAL(10,2) DEFAULT 0 COMMENT 'Amount paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salones(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_salon_fecha (salon_id, fecha_inicio, fecha_fin),
    INDEX idx_estado (estado),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 3 default salones as specified in requirements
INSERT INTO salones (nombre, tipo, descripcion, capacidad, caracteristicas, precio_hora, precio_dia, precio_evento, formas_pago, procedimiento_contratacion, activo) VALUES
('Salón Principal', 'SALON', 'Amplio salón ideal para eventos corporativos, conferencias y presentaciones. Cuenta con excelente iluminación natural y ambiente profesional.', 100, 
 'Proyector de alta resolución\nPantalla de proyección profesional\nSistema de audio con micrófonos inalámbricos\nAire acondicionado\nWiFi de alta velocidad\nMesas y sillas ejecutivas\nPizarra blanca\nIluminación ajustable\nAcceso para personas con discapacidad', 
 500.00, 3500.00, 5000.00, 
 'Efectivo\nTransferencia bancaria\nTarjeta de crédito/débito\nPayPal', 
 '1. Solicitar disponibilidad por correo o teléfono\n2. Enviar carta de solicitud formal\n3. Firma de contrato de arrendamiento\n4. Pago del 50% de anticipo\n5. Liquidación 48 horas antes del evento\n6. Entrega de espacio y verificación de condiciones',
 1),
 
('Salón Ejecutivo', 'SALA_JUNTAS', 'Sala de juntas moderna y elegante, perfecta para reuniones ejecutivas, presentaciones a clientes y videoconferencias. Ambiente privado y profesional.', 30, 
 'Mesa de juntas de lujo\nSillas ejecutivas ergonómicas\nPantalla LED 55 pulgadas\nSistema de videoconferencia HD\nWiFi de alta velocidad\nAire acondicionado\nCafetería/mini bar\nPizarra interactiva\nIluminación LED regulable\nAislamiento acústico', 
 350.00, 2500.00, 3500.00, 
 'Efectivo\nTransferencia bancaria\nTarjeta de crédito/débito', 
 '1. Reservar con al menos 48 horas de anticipación\n2. Llenar formulario de solicitud\n3. Pago del 100% al momento de la reserva\n4. Cancelaciones con menos de 24 horas tienen cargo del 50%\n5. Se entrega acceso 15 minutos antes del evento',
 1),
 
('Auditorio CANACO', 'AUDITORIO', 'Auditorio de gran capacidad equipado con tecnología de punta para conferencias magistrales, seminarios y eventos de gran escala. Diseño moderno con acústica optimizada.', 200, 
 'Sistema de audio profesional\nIluminación escénica LED\nPantalla gigante 4K\n2 proyectores de alta resolución\nCabina de control audiovisual\nSistema de traducción simultánea\nButacas tipo cine\nEscenario amplio\nCamarinos\nAire acondicionado central\nWiFi empresarial\nGrabación y streaming disponible', 
 800.00, 6000.00, 10000.00, 
 'Transferencia bancaria\nCheque certificado\nTarjeta corporativa', 
 '1. Solicitud con mínimo 2 semanas de anticipación\n2. Presentar propuesta del evento\n3. Firma de contrato de arrendamiento\n4. Pago del 60% de anticipo\n5. Liquidación 72 horas antes del evento\n6. Requiere seguro de responsabilidad civil\n7. Se asigna coordinador técnico para el evento',
 1);
