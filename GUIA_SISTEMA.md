# 📘 Guía Completa del Sistema CRM - Cámara de Comercio

## 🎯 Visión General

Sistema CRM completo para la gestión integral de afiliados de la Cámara de Comercio, desarrollado con tecnologías open source y arquitectura MVC.

## 🚀 Inicio Rápido

### Instalación en 3 pasos:

1. **Crear base de datos**
   ```sql
   CREATE DATABASE crm_camara_comercio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura y datos**
   ```bash
   mysql -u root -p crm_camara_comercio < database/schema.sql
   mysql -u root -p crm_camara_comercio < database/sample_data.sql
   ```

3. **Configurar credenciales**
   - Editar `config/config.php` con datos de tu servidor MySQL
   - Verificar instalación en `http://tu-servidor/test_connection.php`

### Primer Acceso:
```
URL: http://tu-servidor/
Email: admin@camaraqro.com
Password: password
```

**⚠️ IMPORTANTE:** Cambiar contraseña después del primer login.

---

## 👥 Usuarios del Sistema y Credenciales de Prueba

El sistema incluye 7 niveles de usuario con permisos específicos:

### 1. PRESIDENCIA (SuperAdmin)
**Credenciales de prueba:**
- Email: `admin@camaraqro.com`
- Password: `password`

**Permisos:**
- ✅ Acceso total al sistema
- ✅ Configuración global
- ✅ Gestión de usuarios
- ✅ Todos los reportes
- ✅ Gestión de catálogos
- ✅ Control de notificaciones

**Páginas exclusivas:**
- `/configuracion.php` - Configuración del sistema

### 2. Dirección (Admin)
**Credenciales de prueba:**
- Email: `direccion@camaraqro.com`
- Password: `password`

**Permisos:**
- ✅ Alta de eventos
- ✅ Aprobación de solicitudes
- ✅ Actualización de empresas
- ✅ Acceso a reportes de ingresos
- ✅ Gestión de catálogos
- ✅ Gestión de usuarios

**Páginas principales:**
- `/empresas.php` - Gestión completa
- `/eventos.php` - Crear/editar eventos
- `/reportes.php` - Reportes e ingresos
- `/usuarios.php` - Gestión de usuarios
- `/importar.php` - Importación masiva

### 3. Consejeros
**Credenciales de prueba:**
- Email: `consejero@camaraqro.com`
- Password: `password`

**Permisos:**
- ✅ Visualización de reportes
- ✅ Estadísticas y gráficas
- ✅ Calendarios de reuniones
- ✅ Vista de empresas (solo lectura)

**Páginas principales:**
- `/dashboard.php` - Dashboard con estadísticas
- `/reportes.php` - Reportes (solo lectura)
- `/eventos.php` - Ver calendario

### 4. Afiladores
**Credenciales de prueba:**
- Email: `afilador@camaraqro.com`
- Password: `password`

**Permisos:**
- ✅ Alta de empresas
- ✅ Seguimiento de renovaciones
- ✅ Gestión de estatus de afiliaciones
- ✅ Importación de datos

**Páginas principales:**
- `/empresas.php` - Crear/editar empresas
- `/importar.php` - Importación masiva
- `/buscar.php` - Búsqueda de afiliados

### 5. Capturistas
**Credenciales de prueba:**
- Email: `capturista@camaraqro.com`
- Password: `password`

**Permisos:**
- ✅ Registro de nuevas empresas
- ✅ Actualización básica de datos

**Páginas principales:**
- `/empresas.php` - Crear/editar (campos básicos)

### 6. Entidad Comercial (Afiliado)
**Credenciales de prueba:**
- Email: `empresa@ejemplo.com`
- Password: `password`

**Permisos:**
- ✅ Autoregistro
- ✅ Gestión de perfil
- ✅ Publicación de servicios y productos
- ✅ Visualización de eventos
- ✅ Inscripción a eventos
- ✅ Respuesta a requerimientos

**Páginas principales:**
- `/perfil.php` - Edición de perfil
- `/eventos.php` - Ver e inscribirse
- `/requerimientos.php` - Ver y ofertar
- `/dashboard.php` - Vista de afiliado

### 7. Empresa Tractora
**Credenciales de prueba:**
- Email: `tractora@empresa.com`
- Password: `password`

**Permisos:**
- ✅ Generación de solicitudes de requerimientos
- ✅ Conexión con empresas proveedoras
- ✅ Visualización de eventos

**Páginas principales:**
- `/requerimientos.php` - Crear requerimientos
- `/eventos.php` - Ver eventos

---

## 📄 Estructura de Páginas del Sistema

### Páginas Públicas (sin login):
1. `index.php` - Landing page / Redirección a login
2. `login.php` - Inicio de sesión
3. `register.php` - Registro de nuevos usuarios
4. `test_connection.php` - Verificación de configuración

### Páginas Privadas (requieren login):

#### Dashboard y Navegación
5. `dashboard.php` - Panel principal con KPIs por rol

#### Gestión de Empresas
6. `empresas.php` - CRUD completo de empresas afiliadas
   - Listado con filtros avanzados
   - Crear nueva empresa
   - Editar empresa existente
   - Ver detalles completos
   - Historial de renovaciones

#### Eventos
7. `eventos.php` - Gestión de eventos y calendario
   - Calendario visual
   - Crear evento (Dirección/Presidencia)
   - Editar evento
   - Inscribirse a eventos
   - Ver lista de inscritos

#### Requerimientos Comerciales
8. `requerimientos.php` - Marketplace de necesidades empresariales
   - Listar requerimientos
   - Crear requerimiento (Tractoras)
   - Ofertar/proponer (Entidades Comerciales)
   - Ver propuestas recibidas
   - Filtros por sector/categoría

#### Búsqueda
9. `buscar.php` - Buscador global
   - Búsqueda por nombre, RFC, email
   - Búsqueda por servicios/productos
   - Filtros: sector, categoría, ciudad, membresía
   - Resultados con enlace a perfil

#### Reportes
10. `reportes.php` - Centro de reportes y analíticas
    - Ingresos por membresía/sector
    - Proyección de ingresos (30/60/90 días)
    - Estadísticas de empresas
    - Vencimientos próximos
    - Requerimientos más buscados
    - Exportación a Excel/PDF

#### Notificaciones
11. `notificaciones.php` - Centro de notificaciones
    - Lista de notificaciones por usuario
    - Marcar como leído
    - Notificaciones de renovación
    - Alertas de nuevos requerimientos
    - Recordatorios de eventos

#### Perfil
12. `perfil.php` - Gestión de perfil de usuario
    - Editar datos personales
    - Cambiar contraseña
    - Ver historial de actividad

#### Administración (solo Dirección/Presidencia)
13. `usuarios.php` - Gestión de usuarios del sistema
    - Listar usuarios con filtros
    - Crear nuevo usuario
    - Editar usuario
    - Activar/desactivar
    - Cambiar rol

14. `importar.php` - Importación masiva de datos
    - Subir archivo CSV/Excel
    - Validación de duplicados
    - Mapeo de campos
    - Reporte de importación
    - Plantilla descargable

15. `configuracion.php` - Configuración del sistema (solo Presidencia)
    - Datos generales
    - Configuración de correo
    - APIs (WhatsApp, PayPal)
    - Términos y condiciones
    - Política de privacidad
    - Parámetros de notificaciones

#### Catálogos
16. `catalogos/membresias.php` - Gestión de membresías
    - CRUD de tipos de membresía
    - Costos y beneficios
    - Vigencia y renovación

17. `catalogos/categorias.php` - Gestión de categorías
    - CRUD de categorías empresariales
    - Asociación con sectores

#### Utilidades
18. `logout.php` - Cerrar sesión

---

## 🗄️ Base de Datos

### Tablas Principales (15 tablas):

1. **usuarios** - Usuarios del sistema
   - Campos: id, nombre, email, password, rol, activo, fecha_registro, ultimo_acceso

2. **empresas** - Empresas afiliadas
   - 20+ campos incluyendo: razón social, RFC, email, teléfono, representante, direcciones, membresía, sector, categoría, etc.

3. **membresias** - Catálogo de membresías
   - Campos: nombre, costo, beneficios, vigencia_dias

4. **categorias** - Catálogo de categorías empresariales
   - Campos: nombre, descripcion, sector_id

5. **sectores** - Sectores económicos (Comercio, Servicios, Turismo)

6. **eventos** - Eventos y actividades
   - Campos: titulo, descripcion, fecha, lugar, tipo, cupo

7. **evento_inscripciones** - Inscripciones a eventos

8. **requerimientos** - Requerimientos comerciales
   - Campos: titulo, descripcion, sector, categoria, plazo, empresa_id

9. **requerimiento_propuestas** - Propuestas a requerimientos

10. **notificaciones** - Sistema de notificaciones
    - Campos: usuario_id, tipo, mensaje, leida, fecha

11. **auditoria** - Registro de todas las acciones
    - Campos: usuario_id, accion, tabla, registro_id, detalles

12. **configuracion** - Configuración del sistema
    - Campos: clave, valor (key-value store)

13. **servicios_productos** - Servicios y productos de empresas

14. **pagos** - Registro de pagos

15. **renovaciones** - Historial de renovaciones

---

## 🔑 Permisos por Rol - Matriz Detallada

| Módulo | PRESIDENCIA | Dirección | Consejeros | Afiladores | Capturistas | Ent. Comercial | Emp. Tractora |
|--------|-------------|-----------|------------|------------|-------------|----------------|---------------|
| Dashboard | ✅ Total | ✅ Total | ✅ Estadísticas | ✅ Empresas | ✅ Básico | ✅ Personal | ✅ Personal |
| Empresas | ✅ CRUD | ✅ CRUD | 👁️ Ver | ✅ CRUD | ✅ Crear/Editar básico | 👁️ Ver otras | 👁️ Ver |
| Eventos | ✅ CRUD | ✅ CRUD | 👁️ Ver | 👁️ Ver + Inscribir | 👁️ Ver + Inscribir | 👁️ Ver + Inscribir | 👁️ Ver + Inscribir |
| Requerimientos | 👁️ Ver todo | 👁️ Ver todo | 👁️ Ver | 👁️ Ver | 👁️ Ver | ✅ Responder | ✅ Crear + Ver |
| Reportes | ✅ Todos | ✅ Todos | 👁️ Ver | 👁️ Limitados | ❌ | ❌ | ❌ |
| Búsqueda | ✅ Total | ✅ Total | ✅ Total | ✅ Total | ✅ Total | ✅ Limitado | ✅ Limitado |
| Notificaciones | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Usuarios | ✅ CRUD | ✅ CRUD | ❌ | ❌ | ❌ | ❌ | ❌ |
| Importar | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Catálogos | ✅ CRUD | ✅ CRUD | ❌ | ❌ | ❌ | ❌ | ❌ |

**Leyenda:**
- ✅ Acceso completo
- 👁️ Solo lectura
- ❌ Sin acceso

---

## 📊 Flujos de Trabajo Principales

### 1. Alta de Nueva Empresa (Afilador/Dirección)
```
1. Login → Dashboard
2. Ir a "Empresas" → "Nueva Empresa"
3. Completar formulario (20+ campos)
4. Seleccionar membresía, sector, categoría
5. Guardar → Se registra en auditoria
6. Sistema programa notificación de renovación
```

### 2. Publicación de Requerimiento (Empresa Tractora)
```
1. Login → Dashboard
2. Ir a "Requerimientos" → "Nuevo Requerimiento"
3. Completar: título, descripción, sector, categoría, plazo
4. Publicar
5. Sistema notifica a empresas que coincidan
6. Entidades Comerciales pueden ofertar
7. Tractora revisa propuestas
```

### 3. Inscripción a Evento (Cualquier usuario)
```
1. Login → Dashboard
2. Ir a "Eventos"
3. Ver calendario y lista de eventos
4. Click en evento → "Inscribirme"
5. Sistema registra inscripción
6. Notificación de confirmación
7. Recordatorio 1 día antes del evento
```

### 4. Generación de Reporte de Ingresos (Dirección/Consejeros)
```
1. Login → Dashboard
2. Ir a "Reportes"
3. Seleccionar tipo: "Ingresos por Membresía"
4. Filtrar por fecha, sector, categoría
5. Ver gráficas y estadísticas
6. Exportar a Excel/PDF
```

### 5. Importación Masiva de Empresas (Dirección/Afiladores)
```
1. Login → Dashboard
2. Ir a "Importar Datos"
3. Descargar plantilla CSV
4. Llenar datos en Excel
5. Guardar como CSV
6. Subir archivo
7. Sistema valida duplicados y campos
8. Ver reporte: importados/duplicados/errores
9. Registros se agregan a la base de datos
```

---

## 🎨 Personalización del Sistema

### Desde Panel de Configuración (Presidencia):

1. **Datos Generales**
   - Nombre de la cámara
   - Logo (upload)
   - Colores primarios y secundarios
   - Datos de contacto

2. **Notificaciones**
   - Email SMTP settings
   - WhatsApp API (Twilio, etc.)
   - Días de aviso: 30/15/5 días antes
   - Plantillas de mensajes

3. **Integrations**
   - PayPal Account (merchant ID)
   - Google Calendar API
   - Email service (SendGrid, etc.)

4. **Legal**
   - Términos y condiciones (editor WYSIWYG ready)
   - Política de privacidad

---

## 🔐 Seguridad Implementada

### Autenticación
- ✅ Passwords hasheados con `password_hash()` (bcrypt)
- ✅ Validación de email con formato RFC
- ✅ Captcha en registro (suma matemática)
- ✅ Control de sesiones con timeout
- ✅ Logout seguro

### Protección de Datos
- ✅ PDO Prepared Statements (SQL Injection)
- ✅ Sanitización de inputs (XSS)
- ✅ Validación de tipos de datos
- ✅ CSRF tokens (estructura lista)
- ✅ Permisos por rol en cada página

### Auditoría
- ✅ Registro de todas las acciones CRUD
- ✅ Tracking de login/logout
- ✅ Historial por usuario
- ✅ Timestamps en todas las tablas

---

## 📱 Diseño Responsive

El sistema es completamente responsive gracias a Tailwind CSS:

### Desktop (>1024px)
- Sidebar fijo visible
- Tablas con todas las columnas
- Gráficas expandidas

### Tablet (768px - 1024px)
- Sidebar colapsable
- Tablas scrolleables
- Elementos adaptados

### Mobile (<768px)
- Menú hamburguesa
- Cards en lugar de tablas
- Botones grandes touch-friendly
- Formularios optimizados

---

## 🧪 Testing y Validación

### Test de Conexión
```
URL: http://tu-servidor/test_connection.php
```

Verifica:
- ✅ Conexión a MySQL
- ✅ Base de datos existe
- ✅ Tablas creadas
- ✅ Permisos de escritura
- ✅ URL Base detectada
- ✅ Extensiones PHP requeridas

### Datos de Prueba
El sistema incluye datos de ejemplo de Querétaro:
- 10 empresas afiliadas
- 7 usuarios (uno por rol)
- 3 sectores
- 15 categorías
- 4 membresías
- 5 eventos próximos
- 10 requerimientos

---

## 📞 Troubleshooting

### Problema: "No se puede conectar a la base de datos"
**Solución:**
1. Verificar credenciales en `config/config.php`
2. Confirmar que MySQL está corriendo
3. Verificar permisos del usuario MySQL

### Problema: "Página en blanco"
**Solución:**
1. Activar display_errors en php.ini
2. Revisar logs de Apache/PHP
3. Verificar permisos de archivos (755 directorios, 644 archivos)

### Problema: "Error al subir archivos"
**Solución:**
1. Verificar permisos de `public/uploads` (chmod 755)
2. Aumentar `upload_max_filesize` en php.ini
3. Aumentar `post_max_size` en php.ini

### Problema: "URLs no funcionan (404)"
**Solución:**
1. Habilitar mod_rewrite en Apache
2. Verificar que .htaccess esté presente
3. Revisar AllowOverride en Apache config

---

## 📈 Roadmap Futuro

### Fase 2 (Próximos 3 meses)
- [ ] Integración completa WhatsApp Business API
- [ ] Sistema de pagos con PayPal/Stripe
- [ ] Notificaciones por email (SendGrid/Mailgun)
- [ ] Exportación real a Excel con PHPSpreadsheet
- [ ] Gráficas interactivas con Chart.js/ApexCharts

### Fase 3 (6 meses)
- [ ] Sincronización con Google Calendar
- [ ] Sistema de respaldos automáticos
- [ ] Multi-idioma (ES/EN)
- [ ] API REST para integraciones
- [ ] App móvil (React Native/Flutter)

### Fase 4 (1 año)
- [ ] Inteligencia artificial para matching
- [ ] Chatbot para soporte
- [ ] Analíticas predictivas
- [ ] Dashboard ejecutivo avanzado

---

## 🤝 Soporte y Contacto

**Desarrollo:**
- GitHub: https://github.com/danjohn007/CRMCamaradeComercio

**Instalación y Configuración:**
- Documentación: README.md
- Issues: GitHub Issues

**Cámara de Comercio de Querétaro:**
- Email: contacto@camaraqro.com
- Teléfono: +52 (442) 123-4567
- Dirección: Av. 5 de Febrero #123, Centro, Querétaro

---

## 📄 Licencia

Este proyecto es open source bajo licencia MIT.

---

**Versión:** 1.0.0  
**Fecha:** Octubre 2025  
**Desarrollado con ❤️ para la Cámara de Comercio de Querétaro**
