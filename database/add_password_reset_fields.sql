-- Añadir campos para reset de contraseña en la tabla usuarios
-- INSTRUCCIONES: Ejecuta cada par de líneas (SHOW ... y si no existe, ejecutar el ALTER ...) una por una.
--             No ejecutes todo en un único bloque si usas phpMyAdmin en un hosting compartido.

-- 1) Comprobar si existe la columna reset_token
SHOW COLUMNS FROM usuarios LIKE 'reset_token';
-- Si la consulta anterior no devuelve filas, ejecutar:
ALTER TABLE usuarios ADD COLUMN reset_token VARCHAR(64) NULL;

-- 2) Comprobar si existe la columna reset_token_expiry
SHOW COLUMNS FROM usuarios LIKE 'reset_token_expiry';
-- Si la consulta anterior no devuelve filas, ejecutar:
ALTER TABLE usuarios ADD COLUMN reset_token_expiry DATETIME NULL;

-- 3) Comprobar si existe el índice compuesto idx_reset_token_expiry
SHOW INDEX FROM usuarios WHERE Key_name = 'idx_reset_token_expiry';
-- Si la consulta anterior no devuelve filas, ejecutar:
ALTER TABLE usuarios ADD INDEX idx_reset_token_expiry (reset_token, reset_token_expiry);

-- OPCIONAL: Si estás en MySQL 8.0+ y tienes permisos, puedes usar estas versiones con IF NOT EXISTS:
-- ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL, ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME NULL;
-- ALTER TABLE usuarios ADD INDEX IF NOT EXISTS idx_reset_token_expiry (reset_token, reset_token_expiry);
