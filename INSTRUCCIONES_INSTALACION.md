# Instrucciones de Instalación - Nuevas Funcionalidades

## Resumen
Este documento contiene las instrucciones paso a paso para instalar y configurar las nuevas funcionalidades del sistema CRM CANACO.

---

## 📋 Prerequisitos

- Acceso al servidor de base de datos MySQL
- Acceso FTP/SSH al servidor web
- Usuario con permisos de PRESIDENCIA en el sistema
- Cuenta de PayPal (opcional, solo si se desea funcionalidad de pagos)

---

## 🚀 Paso 1: Ejecutar Migración SQL

### Opción A: Desde línea de comandos

```bash
# Conectar al servidor
ssh usuario@servidor

# Navegar a la carpeta del proyecto
cd /ruta/al/proyecto

# Ejecutar migración
mysql -u usuario_db -p nombre_base_datos < database/migration_payment_calendar_membership.sql
```

### Opción B: Desde phpMyAdmin

1. Acceder a phpMyAdmin
2. Seleccionar la base de datos `crm_camara_comercio`
3. Ir a la pestaña "SQL"
4. Copiar y pegar el contenido completo del archivo:
   `database/migration_payment_calendar_membership.sql`
5. Click en "Ejecutar"

### Verificación

Ejecutar estas consultas para verificar que todo se instaló correctamente:

```sql
-- Verificar nuevo campo en pagos
SHOW COLUMNS FROM pagos LIKE 'evidencia_pago';

-- Verificar nuevos campos en eventos_inscripciones
SHOW COLUMNS FROM eventos_inscripciones LIKE 'estado_pago';

-- Verificar nueva tabla de upgrades
SHOW TABLES LIKE 'membresias_upgrades';

-- Verificar nueva tabla de completitud
SHOW TABLES LIKE 'perfil_completitud';

-- Verificar triggers
SHOW TRIGGERS WHERE `Trigger` LIKE '%perfil%';

-- Verificar vistas
SHOW FULL TABLES WHERE Table_type = 'VIEW';
```

---

## 📁 Paso 2: Verificar Archivos

Asegurarse de que estos archivos nuevos existen:

### Páginas Principales
- ✅ `calendario.php`
- ✅ `mi_membresia.php`
- ✅ `completar_perfil.php`

### APIs
- ✅ `api/registrar_pago.php`
- ✅ `api/calendario_eventos.php`
- ✅ `api/procesar_upgrade_membresia.php`
- ✅ `api/evento_participantes.php`

### SQL
- ✅ `database/migration_payment_calendar_membership.sql`

### Documentación
- ✅ `NUEVAS_FUNCIONALIDADES.md`
- ✅ `INSTRUCCIONES_INSTALACION.md`

---

## 🔐 Paso 3: Configurar Permisos de Directorios

```bash
# Asegurar que el directorio de uploads tenga permisos correctos
chmod 755 public/uploads

# Crear directorio para evidencias si no existe
mkdir -p public/uploads/evidencias
chmod 755 public/uploads/evidencias

# Verificar que el servidor web pueda escribir
chown -R www-data:www-data public/uploads
```

---

## 💳 Paso 4: Configurar PayPal (Opcional)

### Solo si se desea activar actualización de membresías con pago

#### 4.1 Crear Aplicación en PayPal

1. Ir a https://developer.paypal.com
2. Iniciar sesión con cuenta de PayPal Business
3. Ir a "Dashboard" → "My Apps & Credentials"
4. En "REST API apps", click "Create App"
5. Asignar nombre: "CRM CANACO Membresías"
6. Copiar:
   - **Client ID**
   - **Secret** (click en "Show" para ver)

#### 4.2 Configurar en el Sistema

1. Iniciar sesión con usuario PRESIDENCIA
2. Ir a **Configuración del Sistema**
3. Buscar sección "Configuración de Pagos"
4. Completar:
   ```
   Client ID de PayPal: [pegar aquí]
   Secret de PayPal: [pegar aquí]
   Modo de PayPal: sandbox (para pruebas) o live (para producción)
   Cuenta Principal de PayPal: email@negocio.com
   ```
5. Guardar cambios

#### 4.3 Probar Integración (Modo Sandbox)

1. Crear cuenta de prueba en PayPal Sandbox
2. Iniciar sesión como usuario externo
3. Ir a "Mi Membresía"
4. Intentar actualizar membresía
5. Usar credenciales de prueba de PayPal
6. Verificar que la membresía se actualiza

#### 4.4 Activar Producción

**Solo cuando todo esté probado:**

1. Cambiar modo a "live" en configuración
2. Usar credenciales de producción
3. Verificar con un pago real de bajo monto

---

## 🎯 Paso 5: Configurar Niveles de Membresías

### Importante: Define la jerarquía de membresías

```sql
-- Actualizar niveles de membresías existentes
-- Ajustar según tu nomenclatura

-- Ejemplo 1: Nombres en español
UPDATE membresias SET nivel_orden = 1 WHERE nombre = 'Básica';
UPDATE membresias SET nivel_orden = 2 WHERE nombre = 'Estándar';
UPDATE membresias SET nivel_orden = 3 WHERE nombre = 'Premium';
UPDATE membresias SET nivel_orden = 4 WHERE nombre = 'VIP';

-- O usar LIKE si tienen variaciones
UPDATE membresias SET nivel_orden = 1 WHERE nombre LIKE '%Básica%';
UPDATE membresias SET nivel_orden = 2 WHERE nombre LIKE '%Estándar%';
UPDATE membresias SET nivel_orden = 3 WHERE nombre LIKE '%Premium%' OR nombre LIKE '%Oro%';
UPDATE membresias SET nivel_orden = 4 WHERE nombre LIKE '%VIP%' OR nombre LIKE '%Platinum%';

-- Verificar
SELECT id, nombre, nivel_orden, costo 
FROM membresias 
ORDER BY nivel_orden;
```

### Reglas:
- Nivel más bajo = 1
- Nivel más alto = número mayor
- Solo se pueden upgradear a niveles superiores
- Los costos deberían aumentar con el nivel

---

## 🧪 Paso 6: Pruebas

### 6.1 Probar Registro de Pagos

1. Iniciar sesión como CAPTURISTA o superior
2. Ir a "Empresas"
3. Click en icono 💵 de cualquier empresa
4. Llenar formulario:
   - Concepto: "Prueba de pago"
   - Monto: 100.00
   - Método: Efectivo
   - Adjuntar imagen JPG
5. Guardar
6. Verificar:
   ```sql
   SELECT * FROM pagos ORDER BY id DESC LIMIT 1;
   ```

### 6.2 Probar Calendario

#### Como Usuario Interno:
1. Iniciar sesión como DIRECCION
2. Ir a "Calendario"
3. Verificar que se ven:
   - Eventos públicos (azul)
   - Eventos internos (verde)
   - Renovaciones (naranja)
4. Click en un evento
5. Verificar modal con detalles

#### Como Usuario Externo:
1. Iniciar sesión como ENTIDAD_COMERCIAL
2. Ir a "Calendario"
3. Verificar que solo se ven:
   - Eventos públicos
   - Su propia renovación

### 6.3 Probar Mi Membresía

1. Iniciar sesión como EMPRESA_TRACTORA o ENTIDAD_COMERCIAL
2. Ir a "Mi Membresía"
3. Verificar información de membresía actual
4. Verificar que se muestran membresías superiores
5. Si PayPal está configurado:
   - Click "Actualizar Ahora"
   - Verificar modal
   - **NO completar pago real aún**

### 6.4 Probar Completar Perfil

1. Iniciar sesión como EMPRESA_TRACTORA o ENTIDAD_COMERCIAL
2. Ir a "Completar Perfil"
3. Verificar barra de progreso
4. Completar un campo vacío
5. Guardar
6. Verificar que porcentaje aumenta
7. Verificar en BD:
   ```sql
   SELECT empresa_id, porcentaje 
   FROM perfil_completitud 
   WHERE empresa_id = [ID];
   ```

### 6.5 Probar Ver Participantes

1. Crear evento de prueba con inscripciones
2. Inscribir al menos 2 usuarios
3. Ir a detalle del evento
4. Click "Ver Participantes"
5. Verificar modal con lista

---

## 📊 Paso 7: Verificar Dashboard

1. Iniciar sesión como CONSEJERO o superior
2. Ir al Dashboard
3. Verificar gráfica de "Ingresos por Mes"
4. Confirmar que los pagos registrados aparecen
5. Filtrar por rango de fechas

---

## 🔧 Paso 8: Configuraciones Adicionales (Opcional)

### 8.1 Ajustar Límite de Tamaño de Archivo

Si necesitas permitir archivos más grandes:

```php
// En config/config.php
define('MAX_FILE_SIZE', 10485760); // 10MB en vez de 5MB
```

También ajustar en `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 10M
```

### 8.2 Personalizar Colores del Calendario

En `calendario.php`, buscar y modificar:
```javascript
// Líneas aproximadas 91-94
$color = '#3B82F6'; // Azul -> Cambiar código hex
if ($evento['tipo'] === 'INTERNO') $color = '#10B981'; // Verde
if ($evento['tipo'] === 'CONSEJO') $color = '#8B5CF6'; // Púrpura
```

---

## ⚠️ Problemas Comunes y Soluciones

### Problema 1: "No se ve el calendario"
**Solución:**
- Verificar que FullCalendar.js se carga (revisar consola del navegador)
- Verificar que hay eventos en el rango de fechas visible
- Verificar permisos de usuario

### Problema 2: "Error al registrar pago"
**Solución:**
```sql
-- Verificar que campo existe
SHOW COLUMNS FROM pagos LIKE 'evidencia_pago';

-- Si no existe, ejecutar:
ALTER TABLE pagos ADD COLUMN evidencia_pago VARCHAR(255);
```

### Problema 3: "Botón de PayPal no aparece"
**Solución:**
- Verificar que `paypal_client_id` está configurado
- Revisar consola del navegador para errores de JavaScript
- Verificar que no hay bloqueador de scripts

### Problema 4: "Porcentaje de perfil no se actualiza"
**Solución:**
```sql
-- Verificar que triggers existen
SHOW TRIGGERS LIKE '%perfil%';

-- Si no existen, ejecutar migración completa de nuevo

-- Forzar recálculo manual:
UPDATE empresas SET updated_at = NOW() WHERE id = [ID];
```

### Problema 5: "No se pueden subir archivos"
**Solución:**
```bash
# Verificar permisos
ls -la public/uploads

# Corregir permisos
chmod 755 public/uploads
chown www-data:www-data public/uploads
```

---

## 📞 Soporte

Si encuentras algún problema durante la instalación:

1. Revisar logs de PHP: `/var/log/apache2/error.log` o `/var/log/php-fpm/error.log`
2. Revisar logs de MySQL: `/var/log/mysql/error.log`
3. Verificar errores en consola del navegador (F12)
4. Consultar documentación: `NUEVAS_FUNCIONALIDADES.md`

---

## ✅ Checklist de Instalación

Marca cada ítem al completarlo:

- [ ] Migración SQL ejecutada correctamente
- [ ] Archivos verificados en el servidor
- [ ] Permisos de directorios configurados
- [ ] PayPal configurado (si aplica)
- [ ] Niveles de membresías definidos
- [ ] Prueba de registro de pagos exitosa
- [ ] Prueba de calendario exitosa (interno y externo)
- [ ] Prueba de Mi Membresía exitosa
- [ ] Prueba de Completar Perfil exitosa
- [ ] Prueba de Ver Participantes exitosa
- [ ] Dashboard actualizado con datos de pagos
- [ ] Usuarios informados de nuevas funcionalidades

---

## 🎉 Instalación Completada

Una vez que todos los items del checklist están marcados, la instalación está completa.

**Próximos pasos:**
1. Informar a los usuarios sobre las nuevas funcionalidades
2. Capacitar al personal en el uso de registro de pagos
3. Configurar membresías para habilitar upgrades
4. Monitorear logs por los primeros días

---

**Fecha de instalación**: _______________

**Instalado por**: _______________

**Versión**: 1.0.0

**Última actualización de este documento**: 2025-11-01
