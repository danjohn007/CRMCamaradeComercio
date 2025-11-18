# Instrucciones de Migración: Corrección de Foreign Key vendedor_id

## Problema Identificado

El sistema tiene una inconsistencia entre la base de datos y el código de la aplicación:

- **Base de datos**: La tabla `empresas.vendedor_id` tiene una FK que apunta a `vendedores(id)`
- **Aplicación**: El código carga y usa usuarios con rol `AFILADOR` de la tabla `usuarios`
- **Resultado**: Las importaciones fallan con error de foreign key constraint

## Solución

Se ha creado una migración que corrige esta inconsistencia cambiando la FK para que apunte a `usuarios(id)`.

## Pasos para Aplicar la Migración

### 1. Respaldar la Base de Datos (IMPORTANTE)

```bash
mysqldump -u agenciae_canaco -p agenciae_canaco > backup_antes_migracion_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Aplicar la Migración

```bash
mysql -u agenciae_canaco -p agenciae_canaco < database/migrations/20251118_fix_vendedor_fk_to_usuarios.sql
```

### 3. Verificar la Migración

```sql
-- Conectarse a la base de datos
mysql -u agenciae_canaco -p agenciae_canaco

-- Verificar que la FK apunta correctamente a usuarios
SHOW CREATE TABLE empresas;

-- Debe mostrar algo como:
-- CONSTRAINT `fk_empresas_vendedor_usuario` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
```

## Cambios Realizados en el Código

### 1. `importar.php`
- ✅ Mejorada la búsqueda de vendedores/afiliadores
- ✅ Agregada búsqueda parcial como fallback
- ✅ Mensajes de error más detallados con advertencias
- ✅ Documentación actualizada

### 2. `empresas.php`
- ✅ Eliminado el workaround temporal
- ✅ Validación actualizada para usar tabla `usuarios` con rol `AFILADOR`

### 3. Nueva Migración SQL
- ✅ `database/migrations/20251118_fix_vendedor_fk_to_usuarios.sql`

## Funcionalidad Corregida

Después de aplicar estos cambios:

1. ✅ La importación de empresas desde CSV/Excel funcionará correctamente
2. ✅ El campo VENDEDOR se vinculará correctamente a usuarios con rol AFILADOR
3. ✅ Se mostrarán advertencias si no se encuentra un vendedor, pero la importación continuará
4. ✅ Mensajes de error más claros para facilitar la depuración

## Formato del Archivo CSV

El campo `VENDEDOR` debe contener el nombre de un usuario activo con rol AFILADOR:

```csv
EMPRESA / RAZON SOCIAL,RFC,EMAIL,TELÉFONO,REPRESENTANTE,DIRECCIÓN COMERCIAL,DIRECCIÓN FISCAL,SECTOR,CATEGORÍA,MEMBRESÍA,TIPO DE AFILIACIÓN,VENDEDOR,FECHA DE RENOVACIÓN,No. DE RECIBO,No. DE FACTURA,ENGOMADO
```

### Ejemplo:
```csv
Mi Empresa SA,ABCD123456XYZ,empresa@example.com,4421234567,Juan Pérez,Calle 123,Calle 456,Comercio,Abarrotes y Alimentos,Básica,Nueva,María González,2026-12-31,REC001,FAC001,ENG001
```

**Nota**: Si el vendedor no se encuentra, la empresa se importará con `vendedor_id = NULL` y se mostrará una advertencia.

## Notas Importantes

- La tabla `vendedores` se mantiene por compatibilidad con datos históricos pero ya no se usa activamente
- Los vendedor_id existentes que no correspondan a usuarios válidos se establecerán en NULL automáticamente
- Todas las empresas con vendedor_id NULL podrán ser actualizadas posteriormente desde la interfaz web
