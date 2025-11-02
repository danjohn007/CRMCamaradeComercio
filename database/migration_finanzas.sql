-- Migración para módulo financiero
-- Fecha: 2025-11-02
-- Descripción: Agrega tablas y funcionalidad para el módulo de finanzas

USE crm_camara_comercio;

-- Tabla de categorías financieras
CREATE TABLE IF NOT EXISTS finanzas_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('INGRESO', 'EGRESO') NOT NULL,
    descripcion TEXT,
    color VARCHAR(7) DEFAULT '#3B82F6',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de movimientos financieros
CREATE TABLE IF NOT EXISTS finanzas_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    tipo ENUM('INGRESO', 'EGRESO') NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    descripcion TEXT,
    monto DECIMAL(10,2) NOT NULL,
    fecha_movimiento DATE NOT NULL,
    metodo_pago VARCHAR(50),
    referencia VARCHAR(100),
    empresa_id INT,
    usuario_id INT NOT NULL,
    comprobante VARCHAR(255),
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES finanzas_categorias(id) ON DELETE RESTRICT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_tipo (tipo),
    INDEX idx_fecha (fecha_movimiento),
    INDEX idx_categoria (categoria_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar categorías por defecto para ingresos
INSERT INTO finanzas_categorias (nombre, tipo, descripcion, color) VALUES
('Membresías', 'INGRESO', 'Ingresos por renovación de membresías', '#10B981'),
('Eventos', 'INGRESO', 'Ingresos por registro a eventos', '#3B82F6'),
('Patrocinios', 'INGRESO', 'Ingresos por patrocinios y donaciones', '#8B5CF6'),
('Servicios', 'INGRESO', 'Ingresos por servicios adicionales', '#F59E0B'),
('Otros Ingresos', 'INGRESO', 'Otros ingresos diversos', '#6B7280');

-- Insertar categorías por defecto para egresos
INSERT INTO finanzas_categorias (nombre, tipo, descripcion, color) VALUES
('Nómina', 'EGRESO', 'Pagos de nómina y salarios', '#EF4444'),
('Renta', 'EGRESO', 'Pagos de renta de oficinas', '#DC2626'),
('Servicios Públicos', 'EGRESO', 'Luz, agua, internet, teléfono', '#F97316'),
('Marketing', 'EGRESO', 'Gastos en publicidad y marketing', '#EC4899'),
('Mantenimiento', 'EGRESO', 'Gastos de mantenimiento y reparaciones', '#F59E0B'),
('Suministros', 'EGRESO', 'Compra de suministros de oficina', '#84CC16'),
('Eventos y Actividades', 'EGRESO', 'Gastos en organización de eventos', '#06B6D4'),
('Otros Egresos', 'EGRESO', 'Otros egresos diversos', '#6B7280');

-- Registrar auditoría de esta migración
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles)
VALUES (1, 'MIGRATION', 'finanzas_categorias', NULL, 'Creación de módulo financiero - categorías y movimientos');

-- Nota: Esta migración es segura para ejecutar múltiples veces gracias a IF NOT EXISTS
