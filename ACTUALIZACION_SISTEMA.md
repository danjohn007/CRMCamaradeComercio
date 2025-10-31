# Actualización del Sistema CRM Cámara de Comercio

## Cambios Implementados - 31 de Octubre de 2025

### 1. Corrección del Menú Desplegable de Usuario ✅

**Problema:** El menú desplegable en la esquina superior derecha desaparecía rápidamente, impidiendo acceder a "Mi Perfil", "Configuración" y "Cerrar Sesión".

**Solución:** 
- Reemplazado el comportamiento `group-hover:block` de Tailwind por CSS personalizado
- Implementado sistema de dropdown que permanece visible al pasar el cursor sobre el menú o sus opciones
- Agregada clase `.dropdown` y `.dropdown-menu` con transiciones suaves

**Archivos modificados:** `app/views/layouts/header.php`

---

### 2. Corrección del Sidebar en Dispositivos Móviles ✅

**Problema:** El menú lateral no se ocultaba automáticamente al hacer clic en un ítem de menú en dispositivos móviles.

**Solución:**
- Agregado event listener en JavaScript que detecta clics en enlaces del sidebar
- Implementada lógica para cerrar el sidebar solo en pantallas menores a 1024px
- Mantiene el comportamiento normal en escritorio

**Archivos modificados:** `app/views/layouts/footer.php`

---

### 3. Corrección de Errores HTTP 500 ✅

**Problema:** Los archivos `importar.php` y `usuarios.php` generaban errores HTTP 500 por inconsistencia en variables de sesión.

**Solución:**
- Corregida inconsistencia entre `$_SESSION['user_role']` y `$_SESSION['user_rol']`
- Actualizados nombres de roles para coincidir con los valores ENUM de la base de datos:
  - `Dirección` → `DIRECCION`
  - `Afiladores` → `AFILADOR`
  - `Consejeros` → `CONSEJERO`
  - `Capturistas` → `CAPTURISTA`
  - Etc.

**Archivos modificados:** 
- `importar.php`
- `usuarios.php`

---

### 4. Nuevos Campos en Configuración ✅

#### 4.1 Adjuntar Logotipo
- Campo de carga de archivo con validación de tipo (JPG, PNG, GIF, SVG)
- Validación de tamaño máximo: 2MB
- Generación de nombres únicos usando timestamp + uniqid()
- Eliminación automática del logo anterior al subir uno nuevo
- Almacenamiento en `public/uploads/logo/`

#### 4.2 Configuración SMTP
Campos agregados:
- **Servidor SMTP:** Host del servidor de correo
- **Puerto SMTP:** Puerto de conexión (default: 587)
- **Usuario SMTP:** Email de autenticación
- **Contraseña SMTP:** Contraseña del correo
- **Seguridad:** TLS o SSL
- **Nombre del Remitente:** Nombre visible en los correos

#### 4.3 Personalización de Diseño Funcional
- Implementado sistema de CSS variables para aplicar colores personalizados
- Los colores primario y secundario ahora se aplican dinámicamente en toda la interfaz
- Selector de color interactivo con vista previa en tiempo real

#### 4.4 Shelly Relay API
Nueva sección para control de acceso a eventos:
- **Habilitar/Deshabilitar:** Switch para activar la integración
- **URL de la API:** Dirección del dispositivo Shelly Relay
- **Canal del Relay:** Selección del canal (0-3)
- Documentación incluida con advertencias de seguridad

**Archivos modificados:** 
- `configuracion.php`
- `app/views/layouts/header.php` (para aplicar colores personalizados)

---

### 5. Mejoras en Gestión de Empresas ✅

#### 5.1 Acciones Visibles con Íconos
Antes: Enlaces de texto simple
Ahora: 
- 👁️ Ver detalles (verde)
- ✏️ Editar (azul)  
- 🚫 Suspender empresa (naranja)

Todos los íconos incluyen tooltips explicativos.

#### 5.2 Función de Suspender Empresa
- Nuevo botón de acción para suspender empresas
- Confirmación antes de ejecutar
- Registro en auditoría
- La empresa pasa a estado `activo = 0`

#### 5.3 Campos Adicionales en Formulario
Nuevos campos agregados al formulario de edición/creación:
- **Descripción de la Empresa:** Área de texto para descripción general
- **Servicios y Productos:** Lista de servicios/productos que ofrece
- **Palabras Clave:** Tags separados por comas para búsquedas
- **Sitio Web:** URL del sitio web de la empresa

**Archivos modificados:** `empresas.php`

---

### 6. Migración de Base de Datos ✅

Se ha creado el archivo `database/migration_update.sql` que incluye:

1. **Nuevos registros de configuración:**
   - Campos SMTP completos
   - Campos Shelly Relay API
   - Logo del sistema

2. **Procedimiento seguro de migración:**
   - Verifica existencia de columnas antes de agregarlas
   - No causa errores si las columnas ya existen
   - Procedimiento `AddColumnIfNotExists` para seguridad

3. **Índices para optimización:**
   - Índice en `palabras_clave` para búsquedas rápidas
   - Índice en `sitio_web`

4. **Registro de auditoría:**
   - Registra la ejecución de la migración

**Archivo creado:** `database/migration_update.sql`

---

## Instrucciones de Instalación

### Paso 1: Aplicar la Migración de Base de Datos

```bash
mysql -u usuario -p nombre_base_datos < database/migration_update.sql
```

O desde phpMyAdmin:
1. Seleccionar la base de datos
2. Ir a la pestaña "SQL"
3. Copiar y pegar el contenido de `migration_update.sql`
4. Ejecutar

### Paso 2: Crear Directorio de Uploads

```bash
mkdir -p public/uploads/logo
chmod 755 public/uploads/logo
```

### Paso 3: Verificar Permisos

Asegurarse de que el servidor web tenga permisos de escritura en:
- `public/uploads/`
- `public/uploads/logo/`

```bash
chown -R www-data:www-data public/uploads/
```

### Paso 4: Probar Funcionalidades

1. **Menú desplegable:** Iniciar sesión y verificar que el menú de usuario permanece visible
2. **Sidebar móvil:** En un dispositivo móvil o simulador, verificar que el menú se cierra al hacer clic
3. **Configuración:** Acceder a Configuración y verificar todos los nuevos campos
4. **Gestión de Empresas:** Crear/editar una empresa y verificar los nuevos campos
5. **Importar/Usuarios:** Verificar que ya no generan error 500

---

## Notas Técnicas

### Compatibilidad
- PHP 7.4+
- MySQL 5.7+
- Navegadores modernos (Chrome, Firefox, Safari, Edge)

### Seguridad
- Validación de tipos de archivo en uploads
- Límite de tamaño de archivo (2MB)
- Sanitización de entradas de usuario
- Nombres de archivo únicos para evitar colisiones

### Rendimiento
- CSS personalizado cargado solo una vez mediante variables CSS
- Índices de base de datos para búsquedas optimizadas
- Carga eficiente de configuraciones

---

## Resolución de Problemas

### Error al subir logo
**Síntoma:** "Error al subir el archivo"
**Solución:** Verificar permisos del directorio `public/uploads/logo/`

### Colores no se aplican
**Síntoma:** Los colores personalizados no cambian la interfaz
**Solución:** Limpiar caché del navegador (Ctrl+Shift+R)

### Error 500 persiste
**Síntoma:** `importar.php` o `usuarios.php` siguen dando error
**Solución:** 
1. Verificar que la migración se ejecutó correctamente
2. Revisar logs de PHP para detalles del error
3. Verificar que las sesiones de usuario tienen el campo `user_rol`

---

## Contacto y Soporte

Para reportar problemas o solicitar ayuda:
- Email: soporte@camaraqro.com
- Documentación: Ver `GUIA_SISTEMA.md`

---

**Fecha de actualización:** 31 de Octubre de 2025
**Versión:** 1.1.0
