# Cambios en el Sistema de Registro y Empresas

## Fecha: 2025-11-05

## Resumen de Cambios

Se implementaron los siguientes ajustes al sistema según los requerimientos especificados:

### 1. Registro Público - Búsqueda Automática por RFC

#### Cambios en `/register.php`:
- **RFC como campo principal**: El campo RFC ahora es el primero en el formulario y tiene funcionalidad de búsqueda automática
- **Auto-carga de datos**: Al ingresar un RFC válido (12-13 caracteres), el sistema busca automáticamente si la empresa ya existe
- **Edición antes de crear cuenta**: Si la empresa existe, los datos se cargan automáticamente pero pueden ser editados antes de crear la cuenta
- **Campos adicionales**: Se agregaron campos de Razón Social, Teléfono y Representante Legal al formulario de registro
- **Lógica de actualización**: Si la empresa existe, se actualizan sus datos; si no existe, se crea una nueva

#### Nueva API:
- **`/api/buscar_empresa_publico.php`**: Endpoint público (sin autenticación) para buscar empresas por RFC
- Retorna datos de la empresa si existe: razon_social, email, telefono, whatsapp, representante, direcciones, etc.

### 2. Módulo de Empresas - Campo Colonia

#### Cambios en la base de datos:
- **Nueva columna**: `colonia_fiscal` agregada a la tabla `empresas`
- **Diferenciación**: Ahora hay dos campos de colonia:
  - `colonia`: Para dirección comercial
  - `colonia_fiscal`: Para dirección fiscal

#### Cambios en `/empresas.php`:
- Formulario actualizado para mostrar ambos campos de colonia claramente etiquetados
- SQL INSERT y UPDATE modificados para incluir `colonia_fiscal`
- Procesamiento de formulario actualizado para manejar ambos campos

### 3. Vendedor/Afiliador - Filtrado por Rol

#### Cambios en `/empresas.php`:
- **Query modificado**: El campo "Vendedor/Afiliador" ahora carga usuarios de la tabla `usuarios` con rol `AFILADOR`
- **Antes**: `SELECT id, nombre FROM vendedores WHERE activo = 1`
- **Ahora**: `SELECT id, nombre FROM usuarios WHERE rol = 'AFILADOR' AND activo = 1`

### 4. Nueva Afiliación / Actualización - Select Único

#### Cambios en `/empresas.php`:
- **De checkboxes a select**: Los campos "Nueva Afiliación" y "Actualización" ahora son un solo select desplegable
- **Campo**: `afiliacion_tipo` con opciones:
  - `nueva`: Nueva Afiliación
  - `actualizacion`: Actualización
- **Procesamiento**: El backend convierte el valor del select a los campos `es_nueva` y `es_actualizacion` en la base de datos

### 5. Tipo de Afiliación - Select Desplegable

#### Cambios en `/empresas.php`:
- **De input text a select**: El campo "Tipo de Afiliación" ahora es un select desplegable
- **Campo**: `tipo_afiliacion_select` con opciones:
  - `SIEM`
  - `MEMBRESÍA`

## Instrucciones de Instalación

### 1. Ejecutar Migración SQL

**IMPORTANTE**: Debe ejecutarse la migración SQL antes de usar el sistema actualizado.

```bash
mysql -u [usuario] -p [nombre_base_datos] < database/migration_registro_ajustes.sql
```

O ejecutar directamente en MySQL:

```sql
-- Agregar campo colonia_fiscal
ALTER TABLE empresas 
ADD COLUMN colonia_fiscal VARCHAR(100) AFTER colonia
COMMENT 'Colonia de la dirección fiscal';

-- Actualizar comentario del campo colonia existente
ALTER TABLE empresas 
MODIFY COLUMN colonia VARCHAR(100) COMMENT 'Colonia de la dirección comercial';

-- Crear índice para optimizar búsquedas
CREATE INDEX idx_colonia_fiscal ON empresas(colonia_fiscal);
```

### 2. Verificar Archivos Actualizados

Asegúrese de que los siguientes archivos estén actualizados:
- `/register.php` - Formulario de registro público con búsqueda por RFC
- `/empresas.php` - Módulo de gestión de empresas con los nuevos campos y selects
- `/api/buscar_empresa_publico.php` - Nueva API pública para búsqueda por RFC
- `/database/migration_registro_ajustes.sql` - Script de migración SQL

## Funcionalidad Detallada

### Flujo de Registro Público

1. **Usuario ingresa RFC**: Al escribir en el campo RFC (mínimo 12 caracteres)
2. **Búsqueda automática**: Sistema busca empresa en la base de datos
3. **Empresa encontrada**:
   - Muestra mensaje verde: "¡Empresa encontrada en el sistema!"
   - Carga automáticamente: razón social, email, teléfono, whatsapp, representante
   - Usuario puede editar cualquier campo antes de continuar
4. **Empresa no encontrada**:
   - Muestra mensaje azul: "RFC no encontrado en el sistema"
   - Usuario completa todos los campos manualmente
5. **Crear cuenta**: Al enviar el formulario:
   - Si empresa existe: actualiza datos de la empresa
   - Si no existe: crea nueva empresa
   - Crea usuario vinculado a la empresa
   - Envía email de verificación

### Flujo de Gestión de Empresas (Módulo Interno)

1. **Campo Vendedor/Afiliador**: Solo muestra usuarios con rol "AFILADOR"
2. **Campos de Colonia**: Dos campos separados para direcciones comercial y fiscal
3. **Tipo de Registro**: Select único con opciones "Nueva Afiliación" o "Actualización"
4. **Tipo de Afiliación**: Select único con opciones "SIEM" o "MEMBRESÍA"

## Campos de Base de Datos

### Tabla: empresas

Campos modificados/agregados:
- `colonia` VARCHAR(100) - Colonia de dirección comercial
- `colonia_fiscal` VARCHAR(100) - **NUEVO** - Colonia de dirección fiscal
- `vendedor_id` INT - Referencia a usuarios.id (con rol AFILADOR)
- `tipo_afiliacion` VARCHAR(100) - Valores: 'SIEM' o 'MEMBRESÍA'
- `es_nueva` TINYINT(1) - 1 para Nueva Afiliación, 0 para otro
- `es_actualizacion` TINYINT(1) - 1 para Actualización, 0 para otro

## Validaciones

### Registro Público (register.php):
- RFC: 12-13 caracteres, formato válido
- Email: formato válido, único en sistema
- WhatsApp: 10 dígitos numéricos
- Contraseña: mínimo 8 caracteres
- Todos los campos marcados con * son obligatorios
- **Validación de empresa_id**: Se verifica que el ID de empresa corresponda al RFC para prevenir manipulación

### Gestión de Empresas (empresas.php):
- RFC: obligatorio, 12-13 caracteres
- Razón Social: obligatorio
- Email: obligatorio, formato válido
- Vendedor: opcional, solo usuarios AFILADOR
- Tipo de Afiliación: opcional, SIEM o MEMBRESÍA
- Tipo de Registro: opcional, Nueva o Actualización

## Seguridad

### Protecciones Implementadas:

1. **Rate Limiting en API Pública**:
   - Máximo 10 búsquedas por RFC por minuto por sesión
   - Previene enumeración masiva de empresas
   - Retorna código HTTP 429 cuando se excede el límite

2. **Limitación de Datos Expuestos**:
   - API pública solo retorna: id, razon_social, rfc, email, telefono, whatsapp, representante
   - No se exponen datos sensibles como direcciones completas, sectores, membresías

3. **Validación de Empresa ID**:
   - Se verifica que el empresa_id proporcionado corresponda al RFC ingresado
   - Previene que usuarios manipulen el campo oculto para modificar otras empresas

4. **Rate Limiting Cliente**:
   - Debouncing de 800ms en búsquedas por RFC
   - Mínimo 1 segundo entre búsquedas consecutivas
   - Previene sobrecarga del servidor

5. **Protección XSS**:
   - Sanitización de datos en JavaScript antes de insertar en formulario
   - Validación de content-type en respuestas API
   - Uso de textContent en lugar de innerHTML donde es posible

6. **SQL Injection Prevention**:
   - Uso de consultas preparadas (prepared statements) en todas las queries
   - Parámetros sanitizados antes de uso

7. **Transacciones de Base de Datos**:
   - Uso de transacciones para garantizar consistencia de datos
   - Rollback automático en caso de error

## Compatibilidad

- **PHP**: 7.4+
- **MySQL**: 5.7+
- **Navegadores**: Chrome, Firefox, Safari, Edge (últimas versiones)
- **JavaScript**: ES6+ (async/await)

## Notas Técnicas

1. **Transacciones**: El registro público usa transacciones para garantizar consistencia de datos
2. **Debouncing**: La búsqueda por RFC tiene un delay de 500ms para evitar múltiples llamadas
3. **Seguridad**: La API pública solo retorna datos necesarios y valida el formato del RFC
4. **Índices**: Se agregó índice en colonia_fiscal para mejorar rendimiento de búsquedas

## Testing Recomendado

### Casos de Prueba:

1. **Registro con RFC nuevo**:
   - Ingresar RFC no existente
   - Completar formulario
   - Verificar creación de empresa y usuario

2. **Registro con RFC existente**:
   - Ingresar RFC existente
   - Verificar auto-carga de datos
   - Modificar datos
   - Verificar actualización de empresa

3. **Gestión de empresas - Crear**:
   - Verificar que solo aparecen AFILADOREs en vendedor
   - Probar ambos campos de colonia
   - Seleccionar tipo de afiliación
   - Seleccionar tipo de registro

4. **Gestión de empresas - Editar**:
   - Verificar que datos se cargan correctamente
   - Verificar que select de tipo de afiliación muestra valor correcto
   - Verificar que select de tipo de registro muestra valor correcto

## Soporte

Para cualquier problema o duda sobre estos cambios, consultar:
- Documentación completa del sistema: `GUIA_SISTEMA.md`
- Arquitectura del sistema: `ARQUITECTURA.md`
- Historial de cambios: `CAMBIOS_IMPLEMENTADOS.md`
