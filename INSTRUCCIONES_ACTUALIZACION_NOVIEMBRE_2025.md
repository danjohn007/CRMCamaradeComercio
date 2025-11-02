# Instrucciones de Actualización - Noviembre 2025

## Resumen de Mejoras

Este paquete de actualización incluye las siguientes mejoras al sistema CRM de la Cámara de Comercio:

### 1. **Participantes del Evento - Boletos Solicitados** ✅
- Se muestra el número de boletos solicitados por cada participante registrado
- Se muestra el total de boletos al final de la tabla de participantes
- Mejora la visibilidad del cupo real usado en eventos

### 2. **Calendario de Eventos - Visualización de Imágenes** ✅
- La imagen del evento ahora se muestra en la vista de detalle
- Anteriormente solo era visible al editar el evento
- Mejora la presentación visual de los eventos

### 3. **Inscripción a Eventos - Corrección de Pantalla Blanca** ✅
- Se corrigió el error que causaba pantalla blanca al inscribirse
- Ahora se muestra correctamente el mensaje de confirmación
- Se puede imprimir el boleto digital inmediatamente después de la inscripción

### 4. **Campo Vendedor/Afiliador en Empresas** ✅
- El campo "Vendedor" ahora se llama "Vendedor/Afiliador"
- Carga usuarios con rol AFILADOR del sistema
- Ya no usa la tabla vendedores sino la tabla usuarios

### 5. **Gráficas en Reportes - Altura Fija** ✅
- Se corrigió el crecimiento indefinido vertical de las gráficas
- Aplicado a secciones de Ingresos y Empresas
- Ahora todas las gráficas tienen altura máxima de 300px

### 6. **Módulo Financiero Completo** ✅ ⭐ NUEVO
- **Dashboard financiero** con resumen de ingresos, egresos y balance
- **Gestión de categorías** (ingresos/egresos) con colores personalizables
- **Registro de movimientos** financieros con toda la información necesaria
- **Gráficas visuales** de distribución por categoría y tendencias mensuales
- **Reporteador** con filtros por rango de fechas, tipo y categoría
- **Permisos**: Disponible para PRESIDENCIA, DIRECCION y CAPTURISTA

---

## Pre-requisitos

Antes de aplicar la actualización, asegúrese de:

1. ✅ Tener acceso a la base de datos MySQL
2. ✅ Tener acceso FTP/SSH al servidor web
3. ✅ Tener permisos de administrador del sistema
4. ✅ Realizar un respaldo completo de la base de datos
5. ✅ Realizar un respaldo completo de los archivos del sistema

---

## Instrucciones de Instalación

### Paso 1: Respaldo (CRÍTICO)

```bash
# Respaldo de base de datos
mysqldump -u usuario -p crm_camara_comercio > backup_$(date +%Y%m%d_%H%M%S).sql

# Respaldo de archivos (si usa Linux)
tar -czf backup_archivos_$(date +%Y%m%d_%H%M%S).tar.gz /ruta/al/sistema/
```

### Paso 2: Actualizar Base de Datos

1. Conectarse a MySQL:
```bash
mysql -u usuario -p crm_camara_comercio
```

2. Ejecutar el archivo de actualización:
```sql
SOURCE /ruta/al/archivo/database/actualizacion_noviembre_2025.sql;
```

O usando phpMyAdmin:
- Abrir phpMyAdmin
- Seleccionar la base de datos `crm_camara_comercio`
- Ir a la pestaña "SQL"
- Copiar y pegar el contenido de `database/actualizacion_noviembre_2025.sql`
- Click en "Ejecutar"

3. Verificar que la actualización fue exitosa:
```sql
-- Verificar que las tablas nuevas existen
SHOW TABLES LIKE 'finanzas_%';

-- Verificar que hay categorías pre-cargadas
SELECT COUNT(*) FROM finanzas_categorias;
-- Debe retornar 13 (5 ingresos + 8 egresos)

-- Verificar auditoría
SELECT * FROM auditoria WHERE accion = 'SYSTEM_UPDATE' ORDER BY created_at DESC LIMIT 1;
```

### Paso 3: Subir Archivos Nuevos y Modificados

#### Archivos NUEVOS (crear):
```
finanzas.php
database/migration_finanzas.sql
database/actualizacion_noviembre_2025.sql
INSTRUCCIONES_ACTUALIZACION_NOVIEMBRE_2025.md (este archivo)
```

#### Archivos MODIFICADOS (reemplazar):
```
eventos.php
empresas.php
reportes.php
app/views/layouts/header.php
```

**Opciones para subir archivos:**

**Opción A - FTP:**
1. Conectarse al servidor FTP
2. Subir los archivos a la carpeta correspondiente
3. Verificar permisos (644 para archivos PHP)

**Opción B - SSH/SCP:**
```bash
# Desde la máquina local
scp finanzas.php usuario@servidor:/ruta/al/sistema/
scp eventos.php usuario@servidor:/ruta/al/sistema/
scp empresas.php usuario@servidor:/ruta/al/sistema/
scp reportes.php usuario@servidor:/ruta/al/sistema/
scp app/views/layouts/header.php usuario@servidor:/ruta/al/sistema/app/views/layouts/
```

**Opción C - Git (recomendado):**
```bash
# En el servidor
cd /ruta/al/sistema/
git pull origin main
```

### Paso 4: Verificar Permisos de Archivos

```bash
# Archivos PHP deben tener permisos 644
chmod 644 finanzas.php
chmod 644 eventos.php
chmod 644 empresas.php
chmod 644 reportes.php
chmod 644 app/views/layouts/header.php

# Si usa carpeta uploads, verificar permisos de escritura
chmod 755 public/uploads/
```

### Paso 5: Probar Funcionalidades

Iniciar sesión con un usuario de cada tipo y probar:

#### Como CAPTURISTA o superior:
1. ✅ Ir al menú "Finanzas"
2. ✅ Verificar que se muestra el dashboard
3. ✅ Crear una categoría de prueba
4. ✅ Crear un movimiento de prueba
5. ✅ Verificar que las gráficas se muestran correctamente
6. ✅ Probar los filtros por fecha

#### Como DIRECCION o PRESIDENCIA:
1. ✅ Ir a "Eventos"
2. ✅ Abrir un evento con participantes
3. ✅ Verificar que se muestra la columna "Boletos"
4. ✅ Verificar que se muestra el total al pie
5. ✅ Abrir detalle de un evento con imagen
6. ✅ Verificar que la imagen se muestra

#### Como cualquier usuario:
1. ✅ Inscribirse a un evento público
2. ✅ Verificar que se muestra mensaje de confirmación
3. ✅ Verificar que se puede imprimir el boleto
4. ✅ Ir a "Reportes" y verificar que las gráficas no crecen indefinidamente

#### Como CAPTURISTA o superior (Empresas):
1. ✅ Crear o editar una empresa
2. ✅ Verificar que el campo dice "Vendedor/Afiliador"
3. ✅ Verificar que carga usuarios con rol AFILADOR

---

## Estructura de Base de Datos Nueva

### Tabla: `finanzas_categorias`
```sql
- id (PK, AUTO_INCREMENT)
- nombre VARCHAR(100)
- tipo ENUM('INGRESO', 'EGRESO')
- descripcion TEXT
- color VARCHAR(7)
- activo TINYINT(1)
- created_at TIMESTAMP
- updated_at TIMESTAMP
```

### Tabla: `finanzas_movimientos`
```sql
- id (PK, AUTO_INCREMENT)
- categoria_id (FK -> finanzas_categorias)
- tipo ENUM('INGRESO', 'EGRESO')
- concepto VARCHAR(255)
- descripcion TEXT
- monto DECIMAL(10,2)
- fecha_movimiento DATE
- metodo_pago VARCHAR(50)
- referencia VARCHAR(100)
- empresa_id (FK -> empresas, NULLABLE)
- usuario_id (FK -> usuarios)
- comprobante VARCHAR(255)
- notas TEXT
- created_at TIMESTAMP
- updated_at TIMESTAMP
```

---

## Permisos del Sistema

### Módulo Finanzas:
- **CAPTURISTA**: Ver dashboard, gestionar categorías, crear/editar movimientos
- **DIRECCION**: Todo lo anterior + eliminar movimientos
- **PRESIDENCIA**: Acceso completo

### Otros módulos (sin cambios):
- Los permisos de eventos, empresas y reportes se mantienen igual

---

## Solución de Problemas

### Problema: Error al ejecutar SQL
**Solución:**
```sql
-- Verificar versión de MySQL
SELECT VERSION();
-- Si es < 5.7, algunas funciones pueden no estar disponibles

-- Verificar que la base de datos existe
SHOW DATABASES LIKE 'crm_camara_comercio';

-- Verificar privilegios del usuario
SHOW GRANTS FOR CURRENT_USER();
```

### Problema: Menú "Finanzas" no aparece
**Causa:** El usuario no tiene rol CAPTURISTA o superior
**Solución:**
```sql
-- Verificar rol del usuario
SELECT id, nombre, email, rol FROM usuarios WHERE email = 'tu@email.com';

-- Si necesario, actualizar rol
UPDATE usuarios SET rol = 'CAPTURISTA' WHERE id = X;
```

### Problema: Gráficas no se muestran
**Causa:** Chart.js no está cargando
**Solución:**
- Verificar que el header.php incluye Chart.js:
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```
- Verificar conexión a Internet del servidor
- Ver consola del navegador para errores JavaScript

### Problema: Pantalla blanca en finanzas.php
**Causa:** Error de PHP
**Solución:**
```bash
# Ver logs de PHP
tail -f /var/log/php_errors.log

# O en Apache
tail -f /var/log/apache2/error.log

# Verificar sintaxis del archivo
php -l finanzas.php
```

### Problema: Imagen del evento no se muestra
**Causa:** Ruta incorrecta o permisos
**Solución:**
```bash
# Verificar que la carpeta uploads existe
ls -la public/uploads/

# Verificar permisos
chmod 755 public/uploads/

# Verificar que las imágenes existen
ls -la public/uploads/*.jpg public/uploads/*.png
```

---

## Rollback (Revertir Actualización)

Si necesita revertir la actualización:

### 1. Restaurar Base de Datos
```bash
# Restaurar desde respaldo
mysql -u usuario -p crm_camara_comercio < backup_FECHA.sql
```

### 2. Remover Solo Módulo Financiero (sin tocar otros cambios)
```sql
-- Eliminar tablas del módulo financiero
DROP TABLE IF EXISTS finanzas_movimientos;
DROP TABLE IF EXISTS finanzas_categorias;

-- Eliminar registro de auditoría
DELETE FROM auditoria 
WHERE accion = 'SYSTEM_UPDATE' 
AND detalles LIKE '%Noviembre 2025%';
```

### 3. Restaurar Archivos Anteriores
```bash
# Desde respaldo de archivos
tar -xzf backup_archivos_FECHA.tar.gz
cp backup/eventos.php /ruta/al/sistema/
cp backup/empresas.php /ruta/al/sistema/
cp backup/reportes.php /ruta/al/sistema/
cp backup/app/views/layouts/header.php /ruta/al/sistema/app/views/layouts/

# Remover archivo nuevo
rm /ruta/al/sistema/finanzas.php
```

---

## Contacto y Soporte

Para dudas o problemas con la actualización:
- **Email**: soporte@camaraqro.com
- **Teléfono**: (442) XXX-XXXX
- **Horario**: Lunes a Viernes, 9:00 - 18:00

---

## Notas Finales

- ✅ Esta actualización es **compatible** con versiones anteriores
- ✅ **No elimina** datos existentes
- ✅ **No modifica** funcionalidades existentes (solo mejora)
- ✅ Puede aplicarse en **producción** sin tiempo de inactividad
- ✅ Se recomienda aplicar en **horario de baja demanda**
- ✅ **Tiempo estimado** de aplicación: 15-30 minutos

**Fecha de liberación:** Noviembre 2025  
**Versión:** 2.1.0  
**Build:** 20251102
