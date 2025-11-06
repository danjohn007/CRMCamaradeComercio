# Instrucciones de Migración y Verificación
## Fix SQL Error 1452 y Funcionalidad de Actualización de Membresía

**Fecha:** 06 de Noviembre de 2024  
**Versión de Migración:** 20251106_fix_membresia_fk.sql

---

## Descripción General

Esta guía proporciona los pasos necesarios para:
1. Ejecutar la migración de base de datos que previene errores SQL 1452 (violación de foreign key)
2. Verificar la funcionalidad de actualización de membresía desde la interfaz de usuario
3. Realizar pruebas de regresión para asegurar que no se rompió funcionalidad existente

---

## Pre-requisitos

- Acceso a la base de datos MySQL (usuario con privilegios de CREATE TRIGGER)
- Acceso SSH al servidor staging/producción
- Usuario de prueba con rol ENTIDAD_COMERCIAL
- Usuario administrativo para pruebas (opcional)
- Navegador web para pruebas de interfaz

---

## Parte A: Ejecutar Migración de Base de Datos

### A.1. Backup de Base de Datos (OBLIGATORIO)

**⚠️ IMPORTANTE:** Siempre hacer backup antes de ejecutar migraciones.

```bash
# Conectarse al servidor
ssh usuario@servidor-staging

# Crear backup de la base de datos
mysqldump -u agenciae_canaco -p agenciae_canaco > backup_$(date +%Y%m%d_%H%M%S).sql

# Verificar que el backup se creó correctamente
ls -lh backup_*.sql
```

### A.2. Ejecutar Migración SQL

```bash
# Ubicarse en el directorio del proyecto
cd /ruta/al/proyecto/CRMCamaradeComercio

# Ejecutar la migración
mysql -u agenciae_canaco -p agenciae_canaco < database/migrations/20251106_fix_membresia_fk.sql
```

**Salida esperada:**
- La última consulta debe mostrar los 2 triggers creados:
  - `before_empresas_insert_check_membresia`
  - `before_empresas_update_check_membresia`

### A.3. Verificar Instalación de Triggers

```sql
-- Conectarse a MySQL
mysql -u agenciae_canaco -p agenciae_canaco

-- Verificar que los triggers existen
SHOW TRIGGERS FROM crm_camara_comercio WHERE `Table` = 'empresas';

-- Verificar el código de los triggers
SHOW CREATE TRIGGER before_empresas_insert_check_membresia;
SHOW CREATE TRIGGER before_empresas_update_check_membresia;
```

**Resultado esperado:**
- Deben aparecer los 2 triggers
- Los triggers deben tener `Timing: BEFORE` y `Event: INSERT/UPDATE`

---

## Parte B: Pruebas de Base de Datos

### B.1. Probar Trigger en INSERT con Membresía Inválida

```sql
-- Test 1: Insertar empresa con membresia_id que NO existe
INSERT INTO empresas (razon_social, rfc, email, membresia_id) 
VALUES ('Empresa Test FK', 'TST123456XXX', 'test@fk.com', 99999);

-- Verificar que NO lanzó Error 1452
-- Verificar que membresia_id se guardó como NULL
SELECT id, razon_social, membresia_id FROM empresas WHERE rfc = 'TST123456XXX';

-- Resultado esperado: membresia_id debe ser NULL
-- Limpiar test
DELETE FROM empresas WHERE rfc = 'TST123456XXX';
```

### B.2. Probar Trigger en UPDATE con Membresía Inválida

```sql
-- Test 2: Actualizar empresa existente con membresia_id inválida
-- Primero obtener una empresa real
SELECT id, razon_social, membresia_id FROM empresas LIMIT 1;

-- Guardar el membresia_id original para restaurar después
-- Supongamos que el ID de la empresa es 1

-- Intentar actualizar con membresia_id inválida
UPDATE empresas SET membresia_id = 88888 WHERE id = 1;

-- Verificar que NO lanzó Error 1452
-- Verificar que membresia_id se estableció a NULL
SELECT id, razon_social, membresia_id FROM empresas WHERE id = 1;

-- Resultado esperado: membresia_id debe ser NULL
-- Restaurar valor original si es necesario
-- UPDATE empresas SET membresia_id = [valor_original] WHERE id = 1;
```

### B.3. Probar INSERT/UPDATE con Membresía Válida

```sql
-- Test 3: Verificar que las operaciones normales siguen funcionando
-- Obtener un membresia_id válido
SELECT id, nombre FROM membresias WHERE activo = 1 LIMIT 1;
-- Supongamos que el ID es 1

-- Insertar empresa con membresia_id válida
INSERT INTO empresas (razon_social, rfc, email, membresia_id) 
VALUES ('Empresa Test Válida', 'VAL123456XXX', 'test@valida.com', 1);

-- Verificar que se guardó correctamente
SELECT id, razon_social, membresia_id FROM empresas WHERE rfc = 'VAL123456XXX';

-- Resultado esperado: membresia_id debe ser 1 (no NULL)
-- Limpiar test
DELETE FROM empresas WHERE rfc = 'VAL123456XXX';
```

---

## Parte C: Pruebas de Interfaz de Usuario

### C.1. Preparación de Entorno de Pruebas

1. **Verificar que los archivos fueron desplegados:**
   ```bash
   # Verificar migración
   ls -l database/migrations/20251106_fix_membresia_fk.sql
   
   # Verificar endpoint backend
   ls -l public/actions/update_membresia.php
   
   # Verificar vista parcial
   ls -l app/views/empresas/partials/membresia_buttons.php
   ```

2. **Asegurarse que hay membresías activas en la base de datos:**
   ```sql
   SELECT id, nombre, costo, activo, vigencia_meses FROM membresias WHERE activo = 1;
   ```
   Debe haber al menos 2 membresías activas para poder probar el cambio.

### C.2. Prueba con Usuario ENTIDAD_COMERCIAL

**Objetivo:** Verificar que un usuario externo puede actualizar su membresía.

1. **Iniciar sesión:**
   - Abrir navegador en: `https://staging.sitio.com/login.php`
   - Iniciar sesión con un usuario que tenga rol `ENTIDAD_COMERCIAL`
   - Verificar que el usuario tiene una empresa asociada

2. **Navegar a "Mi Membresía":**
   - Click en el menú "Mi Membresía" o navegar a: `https://staging.sitio.com/mi_membresia.php`

3. **Verificar visualización:**
   - ✅ Se debe mostrar la membresía actual del usuario
   - ✅ Se deben mostrar todas las membresías disponibles
   - ✅ La membresía actual debe tener el botón "Membresía Actual" (deshabilitado)
   - ✅ Las otras membresías deben tener el botón "Actualizar Ahora" (habilitado)

4. **Actualizar membresía:**
   - Click en el botón "Actualizar Ahora" de una membresía diferente
   - ✅ Debe aparecer un diálogo de confirmación con el nombre y costo de la membresía
   - Click en "Aceptar" para confirmar
   - ✅ La página debe recargar y mostrar mensaje de éxito
   - ✅ La nueva membresía debe aparecer como "Membresía Actual"
   - ✅ La fecha de renovación debe haberse actualizado

5. **Verificar en base de datos:**
   ```sql
   -- Obtener el ID de la empresa del usuario
   SELECT u.email, e.id as empresa_id, e.razon_social, e.membresia_id, 
          e.fecha_renovacion, m.nombre as membresia_nombre
   FROM usuarios u
   LEFT JOIN empresas e ON u.empresa_id = e.id
   LEFT JOIN membresias m ON e.membresia_id = m.id
   WHERE u.email = 'email_usuario@test.com';
   ```
   - ✅ `membresia_id` debe ser el nuevo ID
   - ✅ `fecha_renovacion` debe ser una fecha futura (actual + vigencia_meses)

6. **Verificar auditoría:**
   ```sql
   -- Verificar que se registró en auditoría
   SELECT * FROM auditoria 
   WHERE accion = 'UPDATE_MEMBRESIA' 
   ORDER BY created_at DESC 
   LIMIT 5;
   ```
   - ✅ Debe existir un registro con la acción `UPDATE_MEMBRESIA`
   - ✅ Debe contener los datos de la membresía anterior y nueva

### C.3. Prueba de Seguridad: Usuario Intentando Modificar Otra Empresa

**Objetivo:** Verificar que un usuario ENTIDAD_COMERCIAL NO puede modificar empresas ajenas.

1. **Preparar prueba:**
   - Obtener el ID de otra empresa (no la del usuario logueado)
   ```sql
   SELECT id, razon_social FROM empresas WHERE id != [empresa_id_usuario] LIMIT 1;
   ```

2. **Intentar actualización no autorizada:**
   - Usar herramienta como cURL o Postman para enviar request POST:
   ```bash
   curl -X POST https://staging.sitio.com/public/actions/update_membresia.php \
     -H "Cookie: [copiar cookies de sesión]" \
     -d "csrf_token=[token]&empresa_id=[otra_empresa_id]&membresia_id=1"
   ```

3. **Resultado esperado:**
   - ✅ Debe redirigir con mensaje de error: "Solo puede actualizar la membresía de su propia empresa"
   - ✅ La empresa ajena NO debe haberse modificado

### C.4. Prueba con Usuario Administrativo

**Objetivo:** Verificar que usuarios administrativos pueden actualizar cualquier empresa.

1. **Iniciar sesión con usuario CAPTURISTA o superior**

2. **Desde el módulo de empresas:**
   - Navegar a: `https://staging.sitio.com/empresas.php`
   - Seleccionar una empresa para editar
   - (NOTA: Esta funcionalidad puede requerir integración adicional en el módulo empresas.php)

3. **Alternativamente, probar endpoint directamente:**
   - Enviar POST al endpoint con empresa_id de cualquier empresa
   - ✅ Debe permitir la actualización sin restricciones

---

## Parte D: Pruebas de Regresión

**Objetivo:** Asegurar que la nueva funcionalidad no rompió características existentes.

### D.1. Verificar Creación de Empresas

1. **Desde interfaz de captura de empresas:**
   - Navegar a: `https://staging.sitio.com/empresas.php?action=new`
   - Llenar todos los campos requeridos
   - Seleccionar una membresía válida del dropdown
   - Guardar
   - ✅ La empresa debe guardarse correctamente
   - ✅ `membresia_id` debe tener el valor seleccionado

2. **Crear empresa sin membresía:**
   - Crear nueva empresa sin seleccionar membresía
   - ✅ La empresa debe guardarse correctamente
   - ✅ `membresia_id` debe ser NULL

### D.2. Verificar Edición de Empresas

1. **Editar empresa existente:**
   - Navegar a: `https://staging.sitio.com/empresas.php?action=edit&id=[id]`
   - Cambiar la membresía a otra válida
   - Guardar
   - ✅ La empresa debe actualizarse correctamente
   - ✅ `membresia_id` debe tener el nuevo valor

### D.3. Verificar Módulo de Pago con PayPal (Existente)

**IMPORTANTE:** El nuevo endpoint `public/actions/update_membresia.php` es independiente 
del flujo existente de PayPal en `api/procesar_upgrade_membresia.php`.

1. **Verificar que el flujo de PayPal sigue funcionando:**
   - Navegar a: `https://staging.sitio.com/mi_membresia.php`
   - Si el modal de PayPal está implementado, verificar que se abre correctamente
   - El botón de PayPal debe renderizarse (no probar pago real en staging)

2. **Coexistencia de ambos métodos:**
   - ✅ El botón "Actualizar Ahora" con POST directo debe funcionar
   - ✅ El modal de PayPal (si existe) debe seguir funcionando
   - Los dos métodos NO deben interferir entre sí

---

## Parte E: Escenarios de Error

### E.1. CSRF Token Inválido

1. **Simular token inválido:**
   - Editar HTML en navegador para cambiar el valor del campo `csrf_token`
   - Enviar formulario
   - ✅ Debe mostrar error: "Token de seguridad inválido"

### E.2. Membresía No Existente

1. **Intentar actualizar a membresía inválida:**
   - Modificar HTML para cambiar `membresia_id` a un valor inexistente (ej: 99999)
   - Enviar formulario
   - ✅ Debe mostrar error: "La membresía seleccionada no existe o no está disponible"

### E.3. Usuario No Autenticado

1. **Intentar acceder sin sesión:**
   - Cerrar sesión
   - Intentar acceder directamente a: `https://staging.sitio.com/public/actions/update_membresia.php`
   - ✅ Debe redirigir a login

---

## Parte F: Rollback (Si es Necesario)

Si algo sale mal y necesita revertir los cambios:

### F.1. Rollback de Base de Datos

```sql
-- Conectarse a MySQL
mysql -u agenciae_canaco -p agenciae_canaco

-- Eliminar triggers
DROP TRIGGER IF EXISTS before_empresas_insert_check_membresia;
DROP TRIGGER IF EXISTS before_empresas_update_check_membresia;

-- Restaurar backup si es necesario
-- mysql -u agenciae_canaco -p agenciae_canaco < backup_YYYYMMDD_HHMMSS.sql
```

### F.2. Rollback de Archivos

```bash
# Eliminar archivos nuevos
rm -f public/actions/update_membresia.php
rm -f app/views/empresas/partials/membresia_buttons.php
rm -f database/migrations/20251106_fix_membresia_fk.sql

# Si modificó mi_membresia.php, revertir con git
git checkout mi_membresia.php
```

---

## Checklist Final

Antes de considerar la migración completa, verificar:

- [ ] Backup de base de datos creado y verificado
- [ ] Migración SQL ejecutada sin errores
- [ ] Triggers instalados correctamente (verificado con SHOW TRIGGERS)
- [ ] Test B.1: INSERT con membresia_id inválida → no error, membresia_id = NULL ✅
- [ ] Test B.2: UPDATE con membresia_id inválida → no error, membresia_id = NULL ✅
- [ ] Test B.3: INSERT/UPDATE con membresia_id válida → funciona correctamente ✅
- [ ] Archivos backend y frontend desplegados correctamente
- [ ] Test C.2: Usuario ENTIDAD_COMERCIAL puede actualizar su membresía ✅
- [ ] Test C.3: Usuario NO puede actualizar empresa ajena ✅
- [ ] Test D.1: Creación de empresas sigue funcionando ✅
- [ ] Test D.2: Edición de empresas sigue funcionando ✅
- [ ] Test E.1: Validación CSRF funciona ✅
- [ ] Test E.2: Validación de membresía funciona ✅
- [ ] Auditoría registra correctamente las actualizaciones ✅
- [ ] Sin errores en logs de PHP
- [ ] Sin errores en logs de MySQL
- [ ] Documentación actualizada ✅

---

## Contacto y Soporte

Si encuentra problemas durante la ejecución de estas pruebas:

1. Revise los logs de errores:
   ```bash
   # Logs de PHP
   tail -f /var/log/apache2/error.log
   
   # Logs de MySQL
   tail -f /var/log/mysql/error.log
   ```

2. Verifique la configuración de la base de datos en `config/database.php`

3. Asegúrese que el usuario de base de datos tiene permisos de TRIGGER

4. Contacte al equipo de desarrollo con los detalles del error

---

**Fin del documento**
