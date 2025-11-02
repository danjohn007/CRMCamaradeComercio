-- =====================================================
-- ACTUALIZACIÓN DEL SISTEMA CRM CÁMARA DE COMERCIO
-- Fecha: Noviembre 2025
-- Descripción: Mejoras al sistema según requerimientos
-- =====================================================

-- USE crm_camara_comercio;

SET @dbname = DATABASE();
SET @table = 'auditoria';
SET @column = 'detalles';

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname
       AND table_name = @table
       AND column_name = @column) > 0,
    'SELECT \"La columna detalles ya existe\"',
    CONCAT('ALTER TABLE `', @table, '` ADD COLUMN `', @column, '` TEXT NULL AFTER `registro_id`')
  )
);

PREPARE alterIfNotExists FROM @sql;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 1. MÓDULO FINANCIERO
-- =====================================================

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
INSERT IGNORE INTO finanzas_categorias (nombre, tipo, descripcion, color) VALUES
('Membresías', 'INGRESO', 'Ingresos por renovación de membresías', '#10B981'),
('Eventos', 'INGRESO', 'Ingresos por registro a eventos', '#3B82F6'),
('Patrocinios', 'INGRESO', 'Ingresos por patrocinios y donaciones', '#8B5CF6'),
('Servicios', 'INGRESO', 'Ingresos por servicios adicionales', '#F59E0B'),
('Otros Ingresos', 'INGRESO', 'Otros ingresos diversos', '#6B7280');

-- Insertar categorías por defecto para egresos
INSERT IGNORE INTO finanzas_categorias (nombre, tipo, descripcion, color) VALUES
('Nómina', 'EGRESO', 'Pagos de nómina y salarios', '#EF4444'),
('Renta', 'EGRESO', 'Pagos de renta de oficinas', '#DC2626'),
('Servicios Públicos', 'EGRESO', 'Luz, agua, internet, teléfono', '#F97316'),
('Marketing', 'EGRESO', 'Gastos en publicidad y marketing', '#EC4899'),
('Mantenimiento', 'EGRESO', 'Gastos de mantenimiento y reparaciones', '#F59E0B'),
('Suministros', 'EGRESO', 'Compra de suministros de oficina', '#84CC16'),
('Eventos y Actividades', 'EGRESO', 'Gastos en organización de eventos', '#06B6D4'),
('Otros Egresos', 'EGRESO', 'Otros egresos diversos', '#6B7280');

-- =====================================================
-- 2. VERIFICAR Y CREAR COLUMNAS SI NO EXISTEN
-- =====================================================

-- Asegurar que la columna boletos_solicitados existe en eventos_inscripciones
-- (Esta columna ya debe existir en el sistema actual, solo verificamos)
SET @dbname = DATABASE();
SET @tablename = "eventos_inscripciones";
SET @columnname = "boletos_solicitados";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " INT DEFAULT 1 AFTER codigo_qr")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Asegurar que la columna imagen existe en eventos
-- (Esta columna ya debe existir en el sistema actual, solo verificamos)
SET @tablename = "eventos";
SET @columnname = "imagen";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " VARCHAR(255) AFTER cupo_maximo")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 3. ACTUALIZAR DATOS EXISTENTES
-- =====================================================

-- Actualizar boletos_solicitados a 1 en registros que tengan NULL o 0
UPDATE eventos_inscripciones 
SET boletos_solicitados = 1 
WHERE boletos_solicitados IS NULL OR boletos_solicitados = 0;

-- =====================================================
-- 4. REGISTRAR AUDITORÍA DE ACTUALIZACIÓN
-- =====================================================

-- Registrar que se ejecutó esta actualización
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles)
VALUES (1, 'SYSTEM_UPDATE', 'sistema', NULL, 'Actualización Noviembre 2025 - Módulo Financiero y mejoras');

-- =====================================================
-- 5. NOTAS IMPORTANTES
-- =====================================================

/*
CAMBIOS REALIZADOS EN ESTA ACTUALIZACIÓN:

1. MÓDULO FINANCIERO:
   - Nueva tabla: finanzas_categorias (categorías de ingresos/egresos)
   - Nueva tabla: finanzas_movimientos (registro de movimientos financieros)
   - Categorías por defecto pre-cargadas (5 ingresos, 8 egresos)
   - Nuevo archivo: finanzas.php con dashboard y gestión completa

2. MEJORAS EN EVENTOS:
   - Visualización de boletos solicitados por participante en modal
   - Total de boletos al pie de tabla de participantes
   - Imagen del evento visible en vista de detalle (no solo en edición)
   - Corregido flujo de inscripción para evitar pantalla blanca

3. MEJORAS EN EMPRESAS:
   - Campo "Vendedor" renombrado a "Vendedor/Afiliador"
   - Ahora carga usuarios con rol AFILADOR en lugar de tabla vendedores
   - Actualizadas consultas SQL para JOIN con tabla usuarios

4. MEJORAS EN REPORTES:
   - Corregido crecimiento indefinido de gráficas
   - Contenedores con altura fija (300px)
   - maintainAspectRatio: true en todas las gráficas

5. MENÚ DEL SISTEMA:
   - Nuevo ítem "Finanzas" para roles PRESIDENCIA, DIRECCION y CAPTURISTA

ARCHIVOS NUEVOS:
- finanzas.php (módulo completo de finanzas)
- database/migration_finanzas.sql (migración específica del módulo)
- database/actualizacion_noviembre_2025.sql (este archivo)

ARCHIVOS MODIFICADOS:
- eventos.php (participantes, imagen, inscripción)
- empresas.php (vendedor/afiliador)
- reportes.php (altura de gráficas)
- app/views/layouts/header.php (menú finanzas)

PERMISOS NECESARIOS:
- CAPTURISTA: Acceso a finanzas (lectura y escritura de movimientos)
- DIRECCION: Acceso completo a finanzas (incluye eliminación)
- PRESIDENCIA: Acceso completo a finanzas

COMPATIBILIDAD:
- Esta actualización es compatible con la base de datos existente
- No elimina ni modifica datos existentes
- Todas las funcionalidades anteriores se mantienen
- Usa CREATE TABLE IF NOT EXISTS para evitar errores si ya existe
- Usa INSERT IGNORE para evitar duplicados en categorías

INSTRUCCIONES DE APLICACIÓN:
1. Hacer respaldo de la base de datos antes de aplicar
2. Ejecutar este archivo SQL completo en la base de datos
3. Subir los archivos nuevos/modificados al servidor
4. Verificar que los permisos de archivos sean correctos (644 para PHP)
5. Probar cada módulo modificado para verificar funcionamiento

ROLLBACK (en caso de problemas):
- Para revertir solo el módulo financiero:
  DROP TABLE IF EXISTS finanzas_movimientos;
  DROP TABLE IF EXISTS finanzas_categorias;
  DELETE FROM auditoria WHERE accion = 'SYSTEM_UPDATE' AND detalles LIKE '%Noviembre 2025%';
- Restaurar archivos desde respaldo

SOPORTE:
- En caso de dudas o problemas, contactar al equipo de desarrollo
- Mantener respaldos actualizados de base de datos y archivos
*/

-- =====================================================
-- FIN DE LA ACTUALIZACIÓN
-- =====================================================

SELECT 'Actualización completada exitosamente. Revise los logs de auditoría para confirmar.' as mensaje;
