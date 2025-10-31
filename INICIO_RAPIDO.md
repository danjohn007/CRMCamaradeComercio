# 🚀 Inicio Rápido - CRM Cámara de Comercio

## ⚡ Instalación en 5 Minutos

### Paso 1: Requisitos Previos
Asegúrate de tener instalado:
- ✅ PHP 7.4 o superior
- ✅ MySQL 5.7 o superior
- ✅ Servidor Apache con mod_rewrite
- ✅ phpMyAdmin (opcional, recomendado)

### Paso 2: Descargar/Clonar el Proyecto
```bash
git clone https://github.com/danjohn007/CRMCamaradeComercio.git
cd CRMCamaradeComercio
```

O descarga el ZIP y extrae en tu carpeta web (`htdocs`, `www`, etc.)

### Paso 3: Crear Base de Datos

**Opción A - Desde phpMyAdmin:**
1. Abre phpMyAdmin en tu navegador
2. Click en "Nueva" base de datos
3. Nombre: `crm_camara_comercio`
4. Cotejamiento: `utf8mb4_unicode_ci`
5. Click en la base de datos creada
6. Tab "Importar"
7. Selecciona `database/schema.sql`
8. Click "Continuar"
9. Repite con `database/sample_data.sql`

**Opción B - Desde Terminal:**
```bash
mysql -u root -p
CREATE DATABASE crm_camara_comercio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit

mysql -u root -p crm_camara_comercio < database/schema.sql
mysql -u root -p crm_camara_comercio < database/sample_data.sql
```

### Paso 4: Configurar Conexión a la Base de Datos

Edita el archivo `config/config.php`:

```php
// Busca estas líneas y actualiza con tus datos:
define('DB_HOST', 'localhost');        // Tu host MySQL
define('DB_NAME', 'crm_camara_comercio'); // Nombre de tu BD
define('DB_USER', 'root');              // Tu usuario MySQL
define('DB_PASS', '');                  // Tu contraseña MySQL
```

### Paso 5: Verificar Instalación

Abre en tu navegador:
```
http://localhost/CRMCamaradeComercio/test_connection.php
```

Deberías ver:
- ✅ Conexión a la base de datos: OK
- ✅ URL Base detectada: http://localhost/CRMCamaradeComercio
- ✅ Extensiones PHP: OK
- ✅ Permisos de escritura: OK

---

## 🎯 Primer Acceso

### Accede al Sistema:
```
URL: http://localhost/CRMCamaradeComercio/
```

### Credenciales de Administrador:
```
Email: admin@camaraqro.com
Contraseña: password
```

⚠️ **IMPORTANTE:** Cambia la contraseña después del primer login en:
`Perfil → Cambiar Contraseña`

---

## 👥 Usuarios de Prueba Incluidos

Para probar el sistema con diferentes roles, usa estas credenciales:

| Rol | Email | Contraseña | Permisos |
|-----|-------|-----------|----------|
| **PRESIDENCIA** | admin@camaraqro.com | password | Acceso total |
| **Dirección** | direccion@camaraqro.com | password | Gestión completa |
| **Consejero** | consejero@camaraqro.com | password | Reportes y estadísticas |
| **Afilador** | afilador@camaraqro.com | password | Empresas y renovaciones |
| **Capturista** | capturista@camaraqro.com | password | Registro de empresas |
| **Entidad Comercial** | empresa@ejemplo.com | password | Perfil y eventos |
| **Empresa Tractora** | tractora@empresa.com | password | Requerimientos |

---

## 🗺️ Navegación Rápida

### Dashboard
Tu punto de partida con estadísticas y accesos rápidos.

### Empresas
Gestión completa de afiliados:
- `Empresas → Nueva Empresa` para agregar
- Usa filtros para buscar
- Click en nombre para ver detalles

### Eventos
Administra eventos:
- `Eventos → Nuevo Evento` (solo Dirección/Presidencia)
- Ver calendario
- Inscribirte a eventos

### Requerimientos
Marketplace empresarial:
- Empresas Tractoras publican necesidades
- Entidades Comerciales ofertan servicios

### Reportes
Analíticas e ingresos:
- Ingresos por membresía
- Proyección de ingresos
- Estadísticas de empresas

### Configuración
Solo PRESIDENCIA:
- Datos del sistema
- Configurar notificaciones
- APIs y servicios externos

---

## 📋 Tareas Comunes

### Agregar una Nueva Empresa
```
1. Login → Dashboard
2. Empresas → Nueva Empresa
3. Llenar formulario:
   - Razón Social *
   - RFC *
   - Email
   - Teléfono
   - Representante
   - Dirección
   - Membresía *
   - Sector *
   - Categoría *
4. Guardar
```

### Crear un Evento
```
1. Login → Dashboard (Dirección o Presidencia)
2. Eventos → Nuevo Evento
3. Llenar datos:
   - Título *
   - Descripción
   - Fecha y hora *
   - Lugar
   - Tipo (Reunión/Capacitación/Networking)
   - Cupo
4. Guardar
```

### Importar Empresas desde Excel
```
1. Login → Dashboard (Dirección o Afilador)
2. Importar Datos
3. Descargar plantilla CSV
4. Llenar en Excel
5. Guardar como CSV
6. Subir archivo
7. Revisar resultados
```

### Generar Reporte de Ingresos
```
1. Login → Dashboard (Dirección/Consejeros/Presidencia)
2. Reportes
3. Seleccionar tipo: "Ingresos por Membresía"
4. Filtrar por:
   - Rango de fechas
   - Sector
   - Categoría
5. Ver resultados y gráficas
6. Exportar (futuro: Excel/PDF)
```

### Crear un Usuario Nuevo
```
1. Login → Dashboard (Presidencia o Dirección)
2. Usuarios → Nuevo Usuario
3. Llenar datos:
   - Nombre completo *
   - Email *
   - Contraseña *
   - Rol *
   - Estado (Activo/Inactivo)
4. Guardar
```

---

## 🎨 Personalización Básica

### Cambiar Nombre del Sistema
1. Login como PRESIDENCIA
2. Configuración
3. "Nombre del Sitio"
4. Guardar

También edita `config/config.php`:
```php
define('APP_NAME', 'Tu Nombre de Cámara');
```

### Configurar Notificaciones por Email
1. Login como PRESIDENCIA
2. Configuración
3. Sección "Configuración de Correo"
4. Llenar:
   - Servidor SMTP
   - Puerto
   - Usuario
   - Contraseña
   - Email remitente
5. Guardar

---

## 🔧 Solución Rápida de Problemas

### ❌ Error: "No se puede conectar a la base de datos"
**Solución:**
- Verifica credenciales en `config/config.php`
- Confirma que MySQL esté corriendo
- Prueba con: `mysql -u root -p` en terminal

### ❌ Error: "Página no encontrada (404)"
**Solución:**
- Verifica que `.htaccess` exista en la raíz
- Habilita mod_rewrite en Apache:
  ```bash
  # Ubuntu/Debian
  sudo a2enmod rewrite
  sudo service apache2 restart
  
  # En XAMPP/WAMP ya viene habilitado
  ```

### ❌ Error: "No se pueden subir archivos"
**Solución:**
```bash
# Linux/Mac
chmod 755 public/uploads

# Windows
Click derecho → Propiedades → Seguridad → Editar
```

### ❌ Página en blanco
**Solución:**
1. Revisa logs de error:
   - Linux: `/var/log/apache2/error.log`
   - XAMPP: `xampp/apache/logs/error.log`
2. Activa errores en `config/config.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

---

## 📚 Documentación Adicional

- **README.md** - Documentación técnica completa
- **GUIA_SISTEMA.md** - Manual de usuario detallado con todos los módulos
- **database/schema.sql** - Estructura completa de la base de datos

---

## 🎓 Datos de Ejemplo Incluidos

El sistema viene con datos de prueba de Querétaro:

- ✅ **10 empresas** de diferentes sectores
- ✅ **7 usuarios** (uno por cada rol)
- ✅ **15 categorías** empresariales
- ✅ **4 membresías** (Básica, Premium, Gold, Platinum)
- ✅ **5 eventos** próximos
- ✅ **10 requerimientos** comerciales

Puedes explorar el sistema con estos datos o eliminarlos y empezar desde cero.

---

## ⚙️ Configuración para Producción

Cuando vayas a producción:

1. **Cambia todas las contraseñas**
   - Especialmente la de `admin@camaraqro.com`

2. **Desactiva errores en pantalla**
   ```php
   // En config/config.php
   ini_set('display_errors', 0);
   ```

3. **Configura respaldos automáticos**
   ```bash
   # Cron job diario
   0 2 * * * mysqldump -u user -p password crm_camara_comercio > backup.sql
   ```

4. **SSL/HTTPS**
   - Configura certificado SSL
   - Fuerza HTTPS en `.htaccess`

5. **Optimiza MySQL**
   - Agrega índices según uso
   - Configura cache de queries

---

## 🆘 Soporte

¿Problemas con la instalación?

1. **Revisa la documentación:**
   - README.md
   - GUIA_SISTEMA.md

2. **Verifica test de conexión:**
   - `test_connection.php`

3. **Busca en GitHub:**
   - [GitHub Issues](https://github.com/danjohn007/CRMCamaradeComercio/issues)

4. **Contacta:**
   - Email: contacto@camaraqro.com

---

## ✨ ¡Listo!

Tu sistema CRM está instalado y listo para usar.

**Próximos pasos:**
1. ✅ Cambia la contraseña del admin
2. ✅ Explora el dashboard
3. ✅ Agrega tu primera empresa
4. ✅ Personaliza la configuración
5. ✅ Invita a tu equipo

**¡Disfruta gestionando tu Cámara de Comercio! 🎉**

---

Desarrollado con ❤️ para la Cámara de Comercio de Querétaro
