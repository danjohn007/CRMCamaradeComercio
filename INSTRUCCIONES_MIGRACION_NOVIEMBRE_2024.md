# Instrucciones de Migración - Noviembre 2024

## Resumen de Cambios

Esta actualización agrega las siguientes funcionalidades al sistema:

1. **Barra de progreso de completitud de perfil** - Muestra el porcentaje de completitud del perfil de empresas
2. **Módulo de Salones** - Gestión de espacios rentables (salones, oficinas, auditorios)
3. **Sistema de reservas** - Calendario y gestión de reservas de espacios

## Pasos de Instalación

### 1. Ejecutar Migración de Base de Datos

Ejecute los siguientes scripts SQL en su base de datos en el orden especificado:

#### Script 1: Agregar columna de porcentaje de completitud

```bash
mysql -u [usuario] -p [nombre_base_datos] < database/migrations/20251124_add_perfil_completado.sql
```

O copie y ejecute manualmente el contenido del archivo:
`database/migrations/20251124_add_perfil_completado.sql`

Este script:
- Agrega la columna `perfil_completado_porcentaje` a la tabla `empresas`
- Calcula y establece el porcentaje inicial para todas las empresas existentes
- Crea índice para mejor rendimiento

#### Script 2: Crear tablas del módulo de salones

```bash
mysql -u [usuario] -p [nombre_base_datos] < database/migrations/20251124_add_salones_module.sql
```

O copie y ejecute manualmente el contenido del archivo:
`database/migrations/20251124_add_salones_module.sql`

Este script:
- Crea la tabla `salones` para gestionar espacios rentables
- Crea la tabla `salon_reservas` para gestionar reservas
- Inserta 3 salones de ejemplo (Salón Principal, Salón Ejecutivo, Auditorio CANACO)

### 2. Verificar Permisos de Archivos

Asegúrese de que los siguientes archivos tengan los permisos correctos:

```bash
chmod 644 salones.php
chmod 644 empresas.php
chmod 644 completar_perfil.php
chmod 644 app/helpers/functions.php
chmod 644 app/views/layouts/header.php
```

### 3. Verificar Funcionalidades

#### A. Barra de Progreso de Completitud de Perfil

1. Inicie sesión como usuario con permisos de DIRECCION o superior
2. Navegue a **Empresas** > Seleccione una empresa > Click en el ícono de "Ver" (ojo)
3. Debería ver una barra de progreso con el porcentaje de completitud del perfil
4. Si el perfil está 100% completo, debería mostrar la insignia "Calidad CANACO"

**Ubicaciones donde aparece la barra:**
- `empresas.php` - Vista de detalles de empresa (para usuarios internos)
- `completar_perfil.php` - Página de completar perfil (para empresas)
- `empresa_detalle.php` - Vista pública de empresa

#### B. Módulo de Salones

1. Inicie sesión como usuario con permisos de DIRECCION o superior
2. En el menú lateral, bajo la sección **Administración**, haga click en **Salones**
3. Debería ver los 3 salones precargados:
   - Salón Principal (100 personas)
   - Salón Ejecutivo (30 personas)
   - Auditorio CANACO (200 personas)
4. Pruebe las siguientes acciones:
   - Ver detalles de un salón
   - Ver calendario de disponibilidad
   - Editar información de un salón
   - Crear un nuevo espacio

### 4. Funcionalidades Adicionales

#### Actualización Automática del Porcentaje

El sistema actualiza automáticamente el porcentaje de completitud en los siguientes casos:

1. Al crear una nueva empresa en `empresas.php`
2. Al editar una empresa existente en `empresas.php`
3. Al actualizar el perfil desde `completar_perfil.php` (para usuarios externos)

#### Campos Considerados para la Completitud

El sistema evalúa los siguientes 21 campos:
- razon_social
- rfc
- email
- telefono
- whatsapp
- representante
- direccion_comercial
- direccion_fiscal
- colonia
- ciudad
- codigo_postal
- estado
- sector_id
- categoria_id
- membresia_id
- descripcion
- servicios_productos
- palabras_clave
- sitio_web
- facebook
- instagram

**Nota:** Los campos `vendedor_id` y `no_registro` NO se consideran para el cálculo.

### 5. Características del Módulo de Salones

#### Tipos de Espacios Soportados
- SALON - Salones para eventos
- OFICINA - Oficinas rentables
- AUDITORIO - Auditorios
- SALA_JUNTAS - Salas de juntas
- OTRO - Otros espacios

#### Información que se Gestiona
- Nombre del espacio
- Tipo de espacio
- Descripción
- Capacidad (personas)
- Características y equipamiento
- Precios (por hora, por día, por evento)
- Formas de pago aceptadas
- Procedimiento de contratación
- Estado (activo/inactivo)

#### Sistema de Reservas
- Validación de disponibilidad automática
- Estados de reserva: PENDIENTE, CONFIRMADA, CANCELADA
- Calendario de próximas reservas
- Historial de reservas por espacio

### 6. Solución de Problemas

#### Error: "Call to undefined function actualizarPorcentajeCompletitud()"

**Solución:** Asegúrese de que el archivo `app/helpers/functions.php` incluye la nueva función. Verifique que los cambios se hayan aplicado correctamente.

#### Error: "Unknown column 'perfil_completado_porcentaje' in 'field list'"

**Solución:** La migración de base de datos no se ejecutó correctamente. Ejecute manualmente el script:
```sql
ALTER TABLE empresas 
ADD COLUMN perfil_completado_porcentaje DECIMAL(5,2) DEFAULT 0;
```

#### No aparece la opción "Salones" en el menú

**Solución:** 
1. Verifique que tiene permisos de DIRECCION o superior
2. Limpie la caché del navegador
3. Verifique que el archivo `app/views/layouts/header.php` incluye el enlace a salones

#### Error: "Table 'salones' doesn't exist"

**Solución:** Ejecute el script de migración:
`database/migrations/20251124_add_salones_module.sql`

### 7. Notas Importantes

- ⚠️ **Respaldo:** Haga un respaldo completo de su base de datos antes de ejecutar las migraciones
- 🔒 **Permisos:** Solo usuarios con rol DIRECCION o superior pueden acceder al módulo de salones
- 📊 **Performance:** El campo `perfil_completado_porcentaje` está indexado para mejor rendimiento
- 🎯 **Insignia Calidad CANACO:** Solo se muestra cuando el perfil está 100% completo

### 8. Próximos Pasos

Según los requerimientos del issue, las siguientes funcionalidades están pendientes:

1. **Actualización de perfil desde eventos:** Cuando una empresa se registra a un evento, si falta información en su perfil, el sistema debe solicitarla y agregarla automáticamente
2. **Inscripción escalonada:** Los eventos siguientes deben solicitar información faltante de manera progresiva

Estas funcionalidades serán implementadas en una actualización posterior.

## Contacto

Si encuentra algún problema durante la instalación o tiene preguntas, por favor:
1. Revise la sección de "Solución de Problemas" arriba
2. Verifique los logs del sistema
3. Contacte al administrador del sistema

---

**Versión:** 1.0  
**Fecha:** 24 de Noviembre, 2025  
**Autor:** GitHub Copilot Agent
