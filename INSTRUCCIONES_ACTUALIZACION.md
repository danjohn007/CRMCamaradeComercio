# Instrucciones de Actualización del Sistema CRM

## Resumen de Cambios

Esta actualización incluye:

1. ✅ **Corrección de errores HTTP 500** en módulos de Importar Datos y Usuarios
2. ✅ **Gestión de Empresas Suspendidas** con capacidad de activar/suspender
3. ✅ **Campos de Costo e Imagen** en eventos
4. ✅ **Registro Público a Eventos** sin necesidad de autenticación
5. ✅ **Búsqueda automática por WhatsApp/RFC** para autocompletar datos

---

## 📋 Pasos de Actualización

### 1. Respaldar la Base de Datos

**IMPORTANTE:** Antes de aplicar cualquier cambio, haga un respaldo completo de su base de datos.

```bash
mysqldump -u tu_usuario -p agenciae_canaco > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Aplicar Migración de Base de Datos

La migración SQL se encuentra en: `database/migration_eventos_public.sql`

**Opción A: Aplicar desde línea de comandos**

```bash
mysql -u tu_usuario -p agenciae_canaco < database/migration_eventos_public.sql
```

**Opción B: Aplicar desde phpMyAdmin**

1. Abra phpMyAdmin
2. Seleccione la base de datos `agenciae_canaco`
3. Vaya a la pestaña "SQL"
4. Copie y pegue el contenido de `database/migration_eventos_public.sql`
5. Ejecute el script

**Nota:** Si alguna columna ya existe, el script puede generar errores que puede ignorar de forma segura. El código está diseñado para funcionar con o sin las nuevas columnas (con funcionalidad limitada).

### 3. Verificar Permisos de Carpetas

Asegúrese de que la carpeta de uploads tenga permisos de escritura:

```bash
chmod -R 755 public/uploads
chown -R www-data:www-data public/uploads  # En Linux/Apache
```

### 4. Actualizar Archivos del Sistema

Copie todos los archivos actualizados a su servidor:

- `importar.php` - Corregido para usar PDO
- `usuarios.php` - Corregido para usar PDO
- `empresas.php` - Agregada gestión de suspendidas
- `eventos.php` - Agregados campos de costo e imagen
- `evento_publico.php` - **NUEVO** - Página de registro público

---

## 🎯 Funcionalidades Nuevas

### 1. Importar Datos y Usuarios (Corrección HTTP 500)

**Problema anterior:** Errores HTTP 500 al acceder a estos módulos

**Solución:** Convertido a usar PDO de forma consistente con el resto del sistema

**Verificación:** 
- Acceda a "Importar Datos" desde el menú de administración
- Acceda a "Usuarios" desde el menú de administración
- Ambos módulos deben cargar sin errores

### 2. Empresas Suspendidas

**Nueva funcionalidad:**
- Ver lista de empresas suspendidas
- Suspender empresas activas
- Reactivar empresas suspendidas

**Cómo usar:**
1. Vaya a "Gestión de Empresas"
2. Haga clic en "Ver Suspendidas" en la esquina superior derecha
3. Para suspender una empresa: use el botón de suspender (icono de ban)
4. Para activar una empresa suspendida: use el botón de activar (icono de check)

### 3. Eventos con Costo e Imagen

**Nueva funcionalidad:**
- Campo de costo para eventos (en pesos MXN)
- Subida de imagen para eventos
- Visualización de costo e imagen en vista pública

**Cómo usar:**
1. Al crear o editar un evento, verá nuevos campos:
   - **Costo del Evento (MXN)**: Ingrese el precio (0 para eventos gratuitos)
   - **Imagen del Evento**: Suba una imagen (JPG, PNG, GIF, máx 5MB)
2. El costo y la imagen se mostrarán en la vista del evento

### 4. Registro Público a Eventos

**Nueva funcionalidad:**
- Página pública para registro sin autenticación
- Búsqueda automática por WhatsApp o RFC
- Autocompletado de datos de empresas registradas
- Solicitud de múltiples boletos para colaboradores

**Cómo usar:**
1. Como administrador, abra un evento en "Eventos"
2. Copie el "Enlace Público de Registro" (aparece solo para administradores)
3. Comparta el enlace con los invitados
4. Los invitados pueden:
   - Buscar su empresa por WhatsApp o RFC
   - Registrarse con datos autocompletados
   - Solicitar boletos adicionales para colaboradores

**Ejemplo de enlace:**
```
https://su-dominio.com/evento_publico.php?evento=123
```

---

## 🔒 Seguridad

### Validaciones Implementadas

1. **Sanitización de entradas:** Todos los datos de usuario son sanitizados
2. **Preparación de consultas:** Uso de prepared statements para prevenir SQL injection
3. **Validación de archivos:** Solo se permiten tipos de archivo específicos
4. **Límites de tamaño:** Archivos limitados a 5MB
5. **Verificación de cupo:** Control de capacidad máxima de eventos
6. **Prevención de duplicados:** Validación de emails duplicados en registros

### Recomendaciones Adicionales

1. Mantenga PHP actualizado (7.4 o superior recomendado)
2. Use HTTPS en producción
3. Configure límites de subida en php.ini si es necesario:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

---

## 🐛 Solución de Problemas

### Error: "Columna 'costo' no existe"

**Causa:** La migración no se aplicó correctamente

**Solución:**
1. Verifique que ejecutó el script de migración
2. Verifique en phpMyAdmin si la columna existe:
   ```sql
   SHOW COLUMNS FROM eventos LIKE 'costo';
   ```
3. Si no existe, ejecute manualmente:
   ```sql
   ALTER TABLE eventos ADD COLUMN costo DECIMAL(10,2) DEFAULT 0 AFTER cupo_maximo;
   ```

### Error al subir imágenes

**Causa:** Permisos incorrectos en carpeta de uploads

**Solución:**
```bash
chmod -R 755 public/uploads
chown -R www-data:www-data public/uploads
```

### Registro público no encuentra empresas

**Causa:** La empresa no existe en el sistema o está inactiva

**Solución:**
1. Verifique que la empresa esté registrada en "Gestión de Empresas"
2. Verifique que la empresa esté activa (no suspendida)
3. Asegúrese de que el WhatsApp o RFC coincidan exactamente

---

## 📊 Base de Datos: Esquema de Cambios

### Tabla: `eventos`
```sql
-- Nuevas columnas agregadas:
costo DECIMAL(10,2) DEFAULT 0          -- Costo del evento en MXN
enlace_publico VARCHAR(255)            -- Enlace personalizado público
```

### Tabla: `eventos_inscripciones`
```sql
-- Modificación:
usuario_id INT NULL                     -- Ahora permite NULL para invitados

-- Nuevas columnas:
nombre_invitado VARCHAR(150)            -- Nombre del invitado
email_invitado VARCHAR(100)             -- Email del invitado (único por evento)
telefono_invitado VARCHAR(20)           -- Teléfono del invitado
whatsapp_invitado VARCHAR(20)           -- WhatsApp del invitado
rfc_invitado VARCHAR(13)                -- RFC del invitado
boletos_solicitados INT DEFAULT 1       -- Cantidad de boletos
es_invitado TINYINT(1) DEFAULT 0        -- Bandera de invitado sin usuario
```

### Nueva Tabla: `eventos_enlaces_publicos`
```sql
CREATE TABLE eventos_enlaces_publicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);
```

---

## ✅ Lista de Verificación Post-Actualización

- [ ] Respaldo de base de datos completado
- [ ] Migración SQL ejecutada sin errores críticos
- [ ] Permisos de carpeta uploads verificados
- [ ] Acceso a "Importar Datos" funciona correctamente
- [ ] Acceso a "Usuarios" funciona correctamente
- [ ] Sección "Empresas Suspendidas" accesible
- [ ] Formulario de eventos muestra campos de costo e imagen
- [ ] Subida de imagen funciona correctamente
- [ ] Página de registro público accesible desde enlace
- [ ] Búsqueda por WhatsApp/RFC funciona
- [ ] Registro de invitados se guarda correctamente
- [ ] Contador de inscritos se actualiza

---

## 📞 Soporte

Si encuentra problemas durante la actualización:

1. Revise los logs de error de PHP: `/var/log/apache2/error.log` o similar
2. Revise los logs de MySQL para errores de base de datos
3. Verifique la configuración en `config/config.php`
4. Contacte al equipo de desarrollo

---

## 📝 Notas de Versión

**Versión:** 2.1.0  
**Fecha:** Noviembre 2025  
**Compatibilidad:** PHP 7.4+, MySQL 5.7+

### Cambios Principales
- Corrección de errores HTTP 500 en módulos de importación y usuarios
- Nueva gestión de empresas suspendidas
- Eventos con costo e imagen
- Sistema de registro público para eventos
- Mejoras en seguridad y validación

### Compatibilidad Hacia Atrás
- ✅ Totalmente compatible con datos existentes
- ✅ El sistema funciona sin ejecutar migración (funcionalidad limitada)
- ✅ No se requieren cambios en configuración existente
