# 🏗️ Arquitectura del Sistema CRM - Cámara de Comercio

## 📐 Arquitectura General

```
┌─────────────────────────────────────────────────────────────┐
│                      CAPA DE PRESENTACIÓN                   │
│                    (HTML + Tailwind CSS)                    │
├─────────────────────────────────────────────────────────────┤
│  Landing  │  Login  │  Register  │  Dashboard  │  Modules  │
└─────────────────────────────────────────────────────────────┘
                            ↓↑
┌─────────────────────────────────────────────────────────────┐
│                   CAPA DE APLICACIÓN (PHP)                  │
│                      Patrón MVC Ligero                      │
├─────────────────────────────────────────────────────────────┤
│  Controllers  │  Views  │  Models  │  Helpers  │  Config   │
└─────────────────────────────────────────────────────────────┘
                            ↓↑
┌─────────────────────────────────────────────────────────────┐
│                    CAPA DE DATOS (MySQL)                    │
│                    15 Tablas Relacionales                   │
├─────────────────────────────────────────────────────────────┤
│  usuarios  │  empresas  │  eventos  │  requerimientos  │... │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗂️ Estructura de Directorios

```
CRMCamaradeComercio/
│
├── 📁 app/                          # Núcleo de la aplicación
│   ├── 📁 controllers/              # Controladores (lógica de negocio)
│   ├── 📁 models/                   # Modelos (acceso a datos)
│   ├── 📁 views/                    # Vistas (presentación)
│   │   ├── 📁 layouts/             # Plantillas globales
│   │   │   ├── header.php          # Encabezado con menú
│   │   │   └── footer.php          # Pie de página
│   │   ├── 📁 auth/                # Vistas de autenticación
│   │   ├── 📁 dashboard/           # Vistas del dashboard
│   │   ├── 📁 empresas/            # Vistas de empresas
│   │   ├── 📁 eventos/             # Vistas de eventos
│   │   └── ...                     # Otras vistas
│   └── 📁 helpers/                  # Funciones auxiliares
│       └── functions.php           # Utilidades globales
│
├── 📁 config/                       # Configuración del sistema
│   ├── config.php                  # Configuración general
│   └── database.php                # Conexión a BD
│
├── 📁 database/                     # Archivos de base de datos
│   ├── schema.sql                  # Estructura de tablas
│   └── sample_data.sql             # Datos de ejemplo
│
├── 📁 public/                       # Recursos públicos
│   ├── 📁 css/                     # Estilos personalizados
│   ├── 📁 js/                      # JavaScript
│   ├── 📁 images/                  # Imágenes del sistema
│   ├── 📁 uploads/                 # Archivos subidos
│   └── plantilla_importacion.csv  # Template CSV
│
├── 📁 catalogos/                    # Módulos de catálogos
│   ├── membresias.php              # Gestión de membresías
│   └── categorias.php              # Gestión de categorías
│
├── 📄 index.php                     # Punto de entrada
├── 📄 login.php                     # Autenticación
├── 📄 register.php                  # Registro de usuarios
├── 📄 dashboard.php                 # Dashboard principal
├── 📄 empresas.php                  # Gestión de empresas
├── 📄 eventos.php                   # Gestión de eventos
├── 📄 requerimientos.php            # Requerimientos comerciales
├── 📄 buscar.php                    # Búsqueda global
├── 📄 reportes.php                  # Reportes e ingresos
├── 📄 notificaciones.php            # Centro de notificaciones
├── 📄 perfil.php                    # Perfil de usuario
├── 📄 usuarios.php                  # Gestión de usuarios
├── 📄 importar.php                  # Importación masiva
├── 📄 configuracion.php             # Configuración sistema
├── 📄 logout.php                    # Cerrar sesión
├── 📄 test_connection.php           # Test de instalación
│
├── 📄 .htaccess                     # URLs amigables
├── 📄 .gitignore                    # Exclusiones Git
├── 📄 README.md                     # Documentación técnica
├── 📄 GUIA_SISTEMA.md               # Manual de usuario
├── 📄 INICIO_RAPIDO.md              # Guía de inicio rápido
└── 📄 ARQUITECTURA.md               # Este archivo
```

---

## 🗃️ Modelo de Datos (Base de Datos)

### Diagrama de Relaciones

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   usuarios   │────▷│  empresas    │────▷│  membresias  │
└──────────────┘     └──────────────┘     └──────────────┘
        │                    │                     
        │                    ├─────▷┌──────────────┐
        │                    │      │  categorias  │
        │                    │      └──────────────┘
        │                    │             │
        │                    │             ▽
        │                    │      ┌──────────────┐
        │                    │      │   sectores   │
        │                    │      └──────────────┘
        │                    │
        │                    ├─────▷┌──────────────────┐
        │                    │      │servicios_productos│
        │                    │      └──────────────────┘
        │                    │
        ▽                    ▽
┌──────────────┐     ┌──────────────┐
│  auditoria   │     │    pagos     │
└──────────────┘     └──────────────┘
        │                    │
        │                    ▽
        │             ┌──────────────┐
        │             │ renovaciones │
        │             └──────────────┘
        │
        ├─────▷┌──────────────────────┐
        │      │   notificaciones     │
        │      └──────────────────────┘
        │
        ├─────▷┌──────────────────────┐
        │      │      eventos         │
        │      └──────────────────────┘
        │             │
        │             ▽
        │      ┌──────────────────────┐
        │      │evento_inscripciones  │
        │      └──────────────────────┘
        │
        └─────▷┌──────────────────────┐
               │   requerimientos     │
               └──────────────────────┘
                      │
                      ▽
               ┌──────────────────────┐
               │requerimiento_propuestas│
               └──────────────────────┘

        ┌──────────────┐
        │configuracion │ (Tabla de configuración global)
        └──────────────┘
```

### Tabla de Usuarios
```sql
usuarios
├── id (PK)
├── nombre
├── email (UNIQUE)
├── password (hashed)
├── rol (ENUM: PRESIDENCIA, Dirección, ...)
├── activo (BOOLEAN)
├── fecha_registro
└── ultimo_acceso
```

### Tabla de Empresas
```sql
empresas
├── id (PK)
├── razon_social
├── rfc (UNIQUE)
├── email
├── telefono
├── whatsapp
├── representante
├── direccion_comercial
├── direccion_fiscal
├── ciudad
├── estado
├── codigo_postal
├── sector_id (FK → sectores)
├── categoria_id (FK → categorias)
├── membresia_id (FK → membresias)
├── tipo_afiliacion
├── vendedor
├── fecha_renovacion
├── no_recibo
├── no_factura
├── no_mes
├── engomado
├── estatus
├── fecha_registro
└── usuario_registro_id (FK → usuarios)
```

### Otras Tablas Principales
- **membresias**: Tipos, costos, beneficios, vigencia
- **categorias**: Nombre, descripción, sector_id
- **sectores**: Comercio, Servicios, Turismo
- **eventos**: Título, descripción, fecha, cupo
- **requerimientos**: Necesidades comerciales
- **notificaciones**: Alertas por usuario
- **auditoria**: Registro de todas las acciones

---

## 🔐 Sistema de Autenticación y Permisos

### Flujo de Autenticación

```
┌─────────────┐
│   Usuario   │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│    login.php        │
│  Email + Password   │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│  Validar en BD      │
│  password_verify()  │
└──────┬──────────────┘
       │
       ├──✗ Inválido → Mensaje de error
       │
       └──✓ Válido
          │
          ▼
   ┌──────────────────┐
   │ Crear Sesión     │
   │ $_SESSION[...]   │
   └──────┬───────────┘
          │
          ▼
   ┌──────────────────┐
   │  Actualizar      │
   │ ultimo_acceso    │
   └──────┬───────────┘
          │
          ▼
   ┌──────────────────┐
   │  Registrar       │
   │  Auditoría       │
   └──────┬───────────┘
          │
          ▼
   ┌──────────────────┐
   │  Redirect a      │
   │  dashboard.php   │
   └──────────────────┘
```

### Jerarquía de Permisos

```
PRESIDENCIA (SuperAdmin)
    ↓ (hereda todos los permisos)
Dirección (Admin)
    ↓ (hereda permisos de lectura)
Consejeros (Viewer)
    ← Afiladores (Gestión de empresas)
        ← Capturistas (Registro básico)

Entidad Comercial (Cliente)
Empresa Tractora (Cliente especial)
```

### Matriz de Permisos Simplificada

```
             │ P │ D │ Co│ Af│ Ca│ EC│ ET│
─────────────┼───┼───┼───┼───┼───┼───┼───┤
Configuración│ W │   │   │   │   │   │   │
Usuarios     │ W │ W │   │   │   │   │   │
Catálogos    │ W │ W │   │   │   │   │   │
Empresas     │ W │ W │ R │ W │ W │ R │ R │
Eventos      │ W │ W │ R │ R │ R │ R │ R │
Requerimientos│ R │ R │ R │ R │ R │ W │ W │
Reportes     │ R │ R │ R │ R │   │   │   │
Importar     │ W │ W │   │ W │   │   │   │
─────────────┴───┴───┴───┴───┴───┴───┴───┘
W = Write (Crear/Editar)
R = Read (Solo lectura)
```

---

## 🔄 Flujo de Datos Principal

### Ejemplo: Crear una Nueva Empresa

```
1. Usuario (Afilador) accede a empresas.php
        ↓
2. Click en "Nueva Empresa"
        ↓
3. Formulario con campos (razon_social, RFC, etc.)
        ↓
4. Submit → POST a empresas.php?action=create
        ↓
5. Validación en servidor:
   - RFC válido y único
   - Email válido
   - Campos obligatorios completos
        ↓
6. Si válido:
   a) INSERT en tabla empresas
   b) INSERT en tabla auditoria (registro de acción)
   c) INSERT en tabla notificaciones (programar renovación)
   d) Actualizar estadísticas del usuario
        ↓
7. Redirect a empresas.php con mensaje de éxito
        ↓
8. Vista de listado actualizada
```

### Flujo de Búsqueda Global

```
Usuario ingresa término de búsqueda
        ↓
buscar.php recibe parámetro 'q'
        ↓
Consulta a múltiples tablas:
  - empresas (nombre, RFC, email)
  - servicios_productos (descripción, keywords)
        ↓
Aplica filtros adicionales:
  - Sector
  - Categoría
  - Ciudad
  - Membresía
        ↓
Ordena por relevancia
        ↓
Renderiza resultados con paginación
        ↓
Click en resultado → Ver perfil completo
```

---

## 🎨 Patrón de Diseño (Frontend)

### Layout Global

```html
<!DOCTYPE html>
<html>
<head>
    <title>CRM - Cámara de Comercio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="font-awesome.css">
</head>
<body>
    <!-- HEADER (layouts/header.php) -->
    <nav class="fixed top-0">
        [Logo] [Búsqueda] [Notificaciones] [Usuario]
    </nav>
    
    <!-- SIDEBAR -->
    <aside class="fixed left-0">
        [Dashboard]
        [Empresas]
        [Eventos]
        [Requerimientos]
        [Reportes]
        ---
        [Catálogos]
        [Usuarios]
        [Configuración]
    </aside>
    
    <!-- MAIN CONTENT -->
    <main>
        <!-- Contenido específico de cada página -->
    </main>
    
    <!-- FOOTER (layouts/footer.php) -->
    <footer>
        [Copyright] [Links] [Versión]
    </footer>
</body>
</html>
```

### Componentes Reutilizables

```
┌─────────────────────────────────────┐
│         CARD COMPONENT              │
├─────────────────────────────────────┤
│  [Icon] Título                      │
│  ─────────────────────────────      │
│  Descripción o contenido            │
│  ─────────────────────────────      │
│  [Botón Primario] [Botón Secundario]│
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│         TABLE COMPONENT             │
├─────────────────────────────────────┤
│  Col1    │  Col2    │  Col3 │ Acc  │
│──────────┼──────────┼───────┼──────│
│  Data1   │  Data2   │ Data3 │[Edit]│
│  Data1   │  Data2   │ Data3 │[Del] │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│         FORM COMPONENT              │
├─────────────────────────────────────┤
│  Label                              │
│  [────────────────────────────────] │
│                                     │
│  Label                              │
│  [▼ Select ──────────────────────] │
│                                     │
│  [✓] Checkbox Label                │
│                                     │
│  [Cancelar]  [Guardar]             │
└─────────────────────────────────────┘
```

---

## 🔌 Integraciones Futuras (Estructura Lista)

### Email (SMTP)
```php
// En configuracion tabla:
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => '587',
'smtp_user' => 'sistema@camara.com',
'smtp_pass' => '***',
'smtp_from' => 'noreply@camara.com'
```

### WhatsApp (Twilio API)
```php
// En configuracion tabla:
'whatsapp_api_key' => 'SK...',
'whatsapp_number' => '+52...'
```

### PayPal (Pagos Online)
```php
// En configuracion tabla:
'paypal_client_id' => '...',
'paypal_secret' => '...',
'paypal_mode' => 'sandbox' // o 'live'
```

### Google Calendar
```php
// En configuracion tabla:
'google_calendar_api_key' => '...',
'google_calendar_id' => '...'
```

---

## 📊 Estadísticas del Proyecto

```
┌─────────────────────────────────────────┐
│       MÉTRICAS DEL PROYECTO             │
├─────────────────────────────────────────┤
│  Total archivos PHP:           23      │
│  Líneas de código PHP:      6,036      │
│  Tablas de base de datos:     15      │
│  Páginas funcionales:         18      │
│  Roles de usuario:             7      │
│  Módulos principales:         14      │
│  Documentos README:            3      │
│  Usuarios de prueba:           7      │
│  Empresas de ejemplo:         10      │
└─────────────────────────────────────────┘
```

---

## 🚀 Escalabilidad

### Para crecer el sistema:

1. **Más Usuarios**
   - Implementar cache (Redis/Memcached)
   - Optimizar queries (índices)
   - Connection pooling

2. **Más Datos**
   - Particionamiento de tablas
   - Archivado de datos históricos
   - CDN para archivos estáticos

3. **Más Funciones**
   - API REST para móviles
   - Microservicios para módulos pesados
   - Cola de trabajos (cron jobs)

---

## 🛡️ Seguridad en Capas

```
┌────────────────────────────────────────┐
│  CAPA 1: Servidor Web (Apache/Nginx)  │
│  • SSL/TLS (HTTPS)                     │
│  • Firewall                            │
│  • Rate limiting                       │
└────────────────────────────────────────┘
           ↓
┌────────────────────────────────────────┐
│  CAPA 2: Aplicación (PHP)              │
│  • Validación de inputs                │
│  • Sanitización (XSS)                  │
│  • Prepared statements (SQL Injection) │
│  • Password hashing (bcrypt)           │
│  • Session management                  │
│  • CSRF tokens                         │
└────────────────────────────────────────┘
           ↓
┌────────────────────────────────────────┐
│  CAPA 3: Base de Datos (MySQL)         │
│  • Usuarios con permisos limitados     │
│  • Conexiones encriptadas              │
│  • Backups automáticos                 │
│  • Logs de auditoría                   │
└────────────────────────────────────────┘
```

---

## 📝 Convenciones de Código

### Nomenclatura PHP
```php
// Variables: snake_case
$nombre_usuario = "Juan";

// Funciones: camelCase
function obtenerUsuario($id) { }

// Clases: PascalCase
class UsuarioModel { }

// Constantes: UPPER_CASE
define('DB_HOST', 'localhost');
```

### Nomenclatura Base de Datos
```sql
-- Tablas: plural, snake_case
CREATE TABLE usuarios (...);

-- Columnas: snake_case
fecha_registro, razon_social

-- Foreign Keys: tabla_id
usuario_id, empresa_id
```

### Estructura de Archivos
```
Cada página sigue el patrón:

1. session_start()
2. require dependencies
3. Validar autenticación
4. Validar permisos
5. Procesar acciones (POST/GET)
6. Consultar datos
7. include header.php
8. HTML de contenido
9. include footer.php
```

---

## 🎯 Puntos Clave de la Arquitectura

1. **MVC Ligero**: Sin framework pesado, PHP puro
2. **Base de Datos Normalizada**: 15 tablas relacionadas
3. **Seguridad First**: Hashing, prepared statements, validación
4. **Responsive Design**: Tailwind CSS, mobile-first
5. **Escalable**: Estructura permite crecimiento
6. **Documentado**: 3 niveles de documentación
7. **Auditable**: Registro de todas las acciones
8. **Flexible**: Configuración sin tocar código

---

Diseñado y desarrollado con ❤️ para la Cámara de Comercio
