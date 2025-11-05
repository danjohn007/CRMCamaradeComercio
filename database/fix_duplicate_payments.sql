-- Migration to fix duplicate payments issue
-- Date: 2025-11-05
-- Description: Adds origen and pago_id columns to finanzas_movimientos if they don't exist

-- Add origen and pago_id columns to finanzas_movimientos table
ALTER TABLE finanzas_movimientos
ADD COLUMN IF NOT EXISTS origen VARCHAR(50) DEFAULT 'MANUAL' COMMENT 'Origen del movimiento: MANUAL o PAGO' AFTER usuario_id;

ALTER TABLE finanzas_movimientos
ADD COLUMN IF NOT EXISTS pago_id INT NULL COMMENT 'ID del pago asociado si origen=PAGO' AFTER origen;

-- Add index for origen to improve query performance
ALTER TABLE finanzas_movimientos
ADD INDEX IF NOT EXISTS idx_origen (origen);

-- Add foreign key for pago_id if it doesn't exist
SET @db_name = DATABASE();
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db_name
    AND TABLE_NAME = 'finanzas_movimientos'
    AND CONSTRAINT_NAME = 'fk_finanzas_movimientos_pago'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE finanzas_movimientos ADD CONSTRAINT fk_finanzas_movimientos_pago FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE SET NULL',
    'SELECT ''Foreign key already exists'' AS result'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records to have origen='MANUAL' if NULL
UPDATE finanzas_movimientos SET origen = 'MANUAL' WHERE origen IS NULL;

SELECT 'Migration completed successfully. Columns origen and pago_id have been added to finanzas_movimientos table.' AS status;
