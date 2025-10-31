# CRM Cámara de Comercio

Sistema CRM web para la gestión integral de afiliados de la Cámara de Comercio, con control de membresías, vencimientos, eventos, solicitudes comerciales y requerimientos empresariales.

## 🎯 Características Principales

- **Gestión de Afiliados**: Registro y seguimiento completo de empresas afiliadas
- **Sistema de Roles**: 7 niveles de usuario con permisos específicos
- **Módulo de Eventos**: Calendario visual con inscripciones
- **Requerimientos Comerciales**: Match entre empresas tractoras y proveedores
- **Reporteador Avanzado**: Proyección de ingresos y estadísticas
- **Notificaciones Automáticas**: Email y WhatsApp para renovaciones y eventos
- **Importación Masiva**: Carga de datos desde Excel/CSV
- **Búsqueda Global**: Filtros dinámicos por múltiples criterios
- **Auditoría**: Registro de todas las acciones del sistema
- **Responsive Design**: Interfaz adaptable a móviles y tablets

## 🛠️ Tecnologías

- **Backend**: PHP 7.4+ (sin framework, MVC puro)
- **Base de Datos**: MySQL 5.7+
- **Frontend**: HTML5, CSS3 (Tailwind CSS), JavaScript
- **Gráficas**: Chart.js / ApexCharts
- **Calendario**: FullCalendar.js
- **Autenticación**: Sessions + password_hash()

## 📋 Requisitos del Sistema

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor Apache con mod_rewrite habilitado
- Extensiones PHP requeridas:
  - PDO
  - PDO_MySQL
  - JSON
  - Session
  - FileInfo

## 🚀 Instalación

### 1. Clonar o descargar el repositorio

```bash
git clone https://github.com/danjohn007/CRMCamaradeComercio.git
cd CRMCamaradeComercio
```

### 2. Configurar el servidor web

Coloca los archivos en tu directorio web (ej: `/var/www/html/crm` o `C:\xampp\htdocs\crm`)

### 3. Crear la base de datos

Accede a MySQL y ejecuta:

```bash
mysql -u root -p < database/schema.sql
```

O desde phpMyAdmin:
1. Crea una base de datos llamada `crm_camara_comercio`
2. Importa el archivo `database/schema.sql`
3. Opcionalmente, importa `database/sample_data.sql` para datos de ejemplo

### 4. Configurar credenciales de base de datos

Edita el archivo `config/config.php` y ajusta las credenciales:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_camara_comercio');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 5. Configurar permisos

Asegúrate de que el directorio `public/uploads` tenga permisos de escritura:

```bash
chmod -R 755 public/uploads
```

### 6. Verificar instalación

Accede a: `http://localhost/crm/test_connection.php`

Este archivo verificará:
- ✅ URL base detectada correctamente
- ✅ Conexión a la base de datos
- ✅ Extensiones PHP requeridas
- ✅ Permisos de escritura

### 7. Acceder al sistema

URL de acceso: `http://localhost/crm/`

**Credenciales por defecto:**
- Email: `admin@camaraqro.com`
- Contraseña: `password`

⚠️ **IMPORTANTE**: Cambia la contraseña del administrador inmediatamente después del primer acceso.

## 👥 Niveles de Usuario y Permisos

| Rol | Descripción | Permisos Principales |
|-----|-------------|---------------------|
| **PRESIDENCIA** | SuperAdmin | Acceso total al sistema |
| **DIRECCION** | Admin | Gestión de eventos, aprobación de solicitudes, reportes |
| **CONSEJERO** | Consejero | Visualización de reportes y estadísticas |
| **AFILADOR** | Afilador | Alta de empresas, seguimiento de renovaciones |
| **CAPTURISTA** | Capturista | Registro de empresas y actualización básica |
| **ENTIDAD_COMERCIAL** | Empresa Afiliada | Autogestión de perfil, eventos, requerimientos |
| **EMPRESA_TRACTORA** | Empresa Tractora | Generación de requerimientos comerciales |

## 📁 Estructura del Proyecto

```
CRMCamaradeComercio/
├── app/
│   ├── controllers/        # Controladores (lógica de negocio)
│   ├── models/            # Modelos (acceso a datos)
│   ├── views/             # Vistas (presentación)
│   │   ├── auth/         # Vistas de autenticación
│   │   ├── dashboard/    # Dashboard
│   │   ├── empresas/     # Gestión de empresas
│   │   ├── eventos/      # Gestión de eventos
│   │   ├── layouts/      # Plantillas (header, footer)
│   │   └── ...
│   └── helpers/          # Funciones auxiliares
├── config/               # Configuración del sistema
│   ├── config.php       # Configuración general
│   └── database.php     # Conexión a BD
├── database/            # Archivos SQL
│   ├── schema.sql       # Estructura de la BD
│   └── sample_data.sql  # Datos de ejemplo
├── public/              # Archivos públicos
│   ├── css/            # Estilos personalizados
│   ├── js/             # JavaScript
│   ├── images/         # Imágenes
│   └── uploads/        # Archivos subidos
├── .htaccess           # Configuración Apache
├── index.php           # Punto de entrada
├── login.php           # Página de login
├── register.php        # Página de registro
├── dashboard.php       # Dashboard principal
├── logout.php          # Cerrar sesión
└── test_connection.php # Test de configuración
```

## 🔧 Configuración Adicional

### URL Amigables

El sistema detecta automáticamente la URL base. Si necesitas instalar en un subdirectorio, no requiere configuración adicional.

### Notificaciones

Para habilitar el envío de emails y WhatsApp, configura las APIs en `config/config.php` o desde el panel de **Configuración** (rol PRESIDENCIA).

### Importación de Excel

Formato de archivo esperado (.xlsx o .csv):

```
No. REGISTRO, No. Mes, FECHA RECIBO, No. DE RECIBO, No. DE FACTURA, 
ENGOMADO, EMPRESA / RAZON SOCIAL, Actualización, Nueva, VENDEDOR, 
TIPO DE AFILIACIÓN, MEMBRESÍA, DIRECCIÓN COMERCIAL, FECHA DE RENOVACIÓN, 
RFC, EMAIL, TELÉFONO, REPRESENTANTE, SECTOR, CATEGORÍA, DIRECCIÓN FISCAL
```

## 📊 Módulos Implementados

- ✅ Sistema de autenticación con roles
- ✅ Dashboard con KPIs por rol
- ✅ Gestión de empresas afiliadas
- ✅ Catálogos administrativos (membresías, categorías, sectores)
- ✅ Módulo de eventos con calendario
- ✅ Sistema de requerimientos comerciales
- ✅ Notificaciones automáticas
- ✅ Búsqueda global y filtros
- ✅ Reporteador de ingresos
- ✅ Auditoría de acciones
- ✅ Importación masiva desde Excel
- ✅ Configuración del sistema
- ✅ Gestión de usuarios

## 🔒 Seguridad

- Contraseñas hasheadas con `password_hash()`
- Protección contra SQL Injection (PDO prepared statements)
- Protección XSS (sanitización de inputs)
- Protección CSRF (tokens)
- Bloqueo de cuenta después de 5 intentos fallidos
- Auditoría completa de acciones

## 🎨 Personalización

El sistema permite personalizar desde el panel de Configuración:
- Nombre del sitio y logotipo
- Colores primarios y secundarios
- Datos de contacto
- Términos y condiciones
- Política de privacidad
- Plantillas de notificaciones

## 📝 Datos de Ejemplo

El archivo `database/sample_data.sql` incluye datos de ejemplo del estado de Querétaro:
- 10 empresas afiliadas
- 3 usuarios de diferentes roles
- Catálogo de sectores, categorías y membresías
- 4 eventos próximos
- Configuración del sistema

## 🐛 Solución de Problemas

### Error de conexión a la base de datos
- Verifica las credenciales en `config/config.php`
- Asegúrate de que MySQL esté ejecutándose
- Confirma que la base de datos exista

### Páginas en blanco
- Revisa el log de errores de PHP
- Verifica que todas las extensiones PHP estén instaladas

### Problemas con uploads
- Verifica permisos del directorio `public/uploads`
- Revisa `upload_max_filesize` en php.ini

### URL base incorrecta
- Verifica la configuración de Apache/mod_rewrite
- Revisa el archivo .htaccess

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:
1. Fork el repositorio
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto es de código abierto y está disponible bajo licencia MIT.

## 📞 Soporte

Para soporte y consultas:
- Email: contacto@camaraqro.com
- GitHub Issues: [Abrir un issue](https://github.com/danjohn007/CRMCamaradeComercio/issues)

## 🗓️ Roadmap

- [ ] Integración completa con APIs de WhatsApp Business
- [ ] Módulo de pagos con PayPal
- [ ] Sincronización con Google Calendar
- [ ] App móvil nativa
- [ ] Sistema de respaldos automáticos
- [ ] Panel de analíticas avanzadas
- [ ] Multi-idioma

---

Desarrollado con ❤️ para la Cámara de Comercio de Querétaro
