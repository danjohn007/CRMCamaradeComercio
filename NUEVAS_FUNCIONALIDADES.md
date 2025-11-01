# Nuevas Funcionalidades - CRM CANACO

## Resumen de Cambios

Este documento describe las nuevas funcionalidades implementadas en el sistema CRM de la Cámara de Comercio.

---

## 1. Registro de Pagos con Evidencia

### Descripción
Se agregó la capacidad de registrar pagos de empresas directamente desde el listado de empresas, con soporte para adjuntar evidencia de pago.

### Características
- **Botón "REGISTRAR PAGO"** en la columna de acciones del listado de empresas
- **Modal de registro** con los siguientes campos:
  - Concepto del pago
  - Monto
  - Método de pago (Efectivo, Transferencia, Tarjeta, PayPal, Otro)
  - Referencia o folio
  - Fecha de pago
  - Evidencia de pago (archivo JPG, PNG o PDF, máx. 5MB)
  - Notas adicionales
- **Integración con gráficas**: Los pagos se reflejan automáticamente en el dashboard de ingresos
- **Notificaciones**: Se envía notificación automática a los usuarios de la empresa

### Ubicación
- **Archivo**: `empresas.php`
- **API**: `api/registrar_pago.php`

### Permisos Requeridos
- Rol: CAPTURISTA o superior

### SQL
```sql
-- Campo agregado a la tabla pagos
ALTER TABLE pagos ADD COLUMN evidencia_pago VARCHAR(255);
```

---

## 2. Calendario Global de Eventos y Renovaciones

### Descripción
Sistema de calendario interactivo que muestra eventos y fechas de renovación de membresías.

### Características

#### Calendario para Usuarios Internos
- Visualización de **todos los eventos** del sistema
- Visualización de **todas las renovaciones** de empresas afiliadas
- Código de colores:
  - 🔵 Azul: Eventos públicos
  - 🟢 Verde: Eventos internos
  - 🟣 Púrpura: Reuniones de consejo
  - 🟠 Naranja: Renovaciones de membresías

#### Calendario para Usuarios Externos
- Visualización de **eventos públicos** únicamente
- Visualización de **su propia renovación** de membresía
- Interfaz simplificada

### Vistas Disponibles
- Vista mensual
- Vista semanal
- Vista diaria
- Lista de eventos

### Interactividad
- Click en evento para ver detalles
- Enlace directo a la página de detalle
- Filtros por tipo de evento

### Ubicación
- **Archivo**: `calendario.php`
- **API**: `api/calendario_eventos.php`

### Permisos
- **Internos**: PRESIDENCIA, DIRECCION, CONSEJERO, AFILADOR, CAPTURISTA
- **Externos**: ENTIDAD_COMERCIAL, EMPRESA_TRACTORA

### Tecnología
- **FullCalendar.js** v5.11.3 (CDN)
- Vistas SQL para optimización de consultas

---

## 3. Módulo "Mi Membresía"

### Descripción
Módulo para que usuarios externos visualicen y actualicen su membresía.

### Características

#### Información Actual
- Nombre de la membresía
- Descripción y beneficios
- Costo anual
- Fecha de renovación
- Días hasta el vencimiento
- Estado (activa/inactiva)

#### Actualización de Membresía
- **Catálogo de membresías superiores** disponibles
- Tarjetas informativas con:
  - Nombre y nivel
  - Precio
  - Descripción
  - Lista de beneficios
  - Botón "Actualizar Ahora"

#### Pago con PayPal
- Integración con PayPal SDK
- Proceso de pago:
  1. Usuario selecciona membresía superior
  2. Se abre modal de confirmación
  3. Botón de PayPal para completar pago
  4. Actualización automática tras pago exitoso
- Nueva fecha de renovación (12 meses desde la actualización)

### Ubicación
- **Archivo**: `mi_membresia.php`
- **API**: `api/procesar_upgrade_membresia.php`

### Permisos Requeridos
- Rol: ENTIDAD_COMERCIAL o EMPRESA_TRACTORA
- Debe tener empresa asociada

### Configuración Requerida
En el módulo de **Configuración del Sistema** (solo PRESIDENCIA):
- `paypal_client_id`: Client ID de PayPal
- `paypal_secret`: Secret de PayPal
- `paypal_mode`: Modo (sandbox o live)
- `paypal_account`: Email de cuenta de PayPal

### SQL
```sql
-- Tabla de tracking de upgrades
CREATE TABLE membresias_upgrades (
    id INT PRIMARY KEY,
    empresa_id INT,
    usuario_id INT,
    membresia_anterior_id INT,
    membresia_nueva_id INT,
    monto DECIMAL(10,2),
    metodo_pago ENUM(...),
    estado ENUM(...),
    paypal_order_id VARCHAR(100),
    ...
);

-- Campo para ordenar membresías por nivel
ALTER TABLE membresias ADD COLUMN nivel_orden INT;
```

---

## 4. Módulo "Completar mi Perfil"

### Descripción
Herramienta para que usuarios externos completen la información de su empresa.

### Características

#### Indicador de Progreso
- **Porcentaje visual** de completitud (0-100%)
- Barra de progreso con gradiente
- Contador de campos completados (X de 20)

#### Campos Evaluados (20 total)
1. Razón Social *
2. RFC
3. Email
4. Teléfono
5. WhatsApp
6. Representante Legal
7. Dirección Comercial
8. Dirección Fiscal
9. Colonia
10. Ciudad
11. Código Postal
12. Sector
13. Categoría
14. Descripción de la Empresa
15. Servicios/Productos
16. Palabras Clave
17. Sitio Web
18. Facebook
19. Instagram
20. Estado

#### Formulario Organizado
Secciones:
- 📋 Información Básica
- 📍 Ubicación
- 🏷️ Clasificación
- 💼 Información de Negocio
- 🌐 Presencia en Línea

#### Indicadores Visuales
- ✅ Campos completados
- 🟠 Campos incompletos (con etiqueta "Incompleto")
- 🔴 Campos requeridos

### Cálculo Automático
El porcentaje se actualiza automáticamente mediante **triggers de MySQL**:
- Trigger `actualizar_porcentaje_perfil_insert`
- Trigger `actualizar_porcentaje_perfil_update`

### Ubicación
- **Archivo**: `completar_perfil.php`

### Permisos Requeridos
- Rol: ENTIDAD_COMERCIAL o EMPRESA_TRACTORA
- Debe tener empresa asociada

### SQL
```sql
-- Tabla de tracking de completitud
CREATE TABLE perfil_completitud (
    id INT PRIMARY KEY,
    empresa_id INT UNIQUE,
    campos_totales INT DEFAULT 20,
    campos_completados INT,
    porcentaje DECIMAL(5,2),
    ...
);

-- Campo en empresas
ALTER TABLE empresas ADD COLUMN porcentaje_perfil DECIMAL(5,2);
```

---

## 5. Visualizador de Participantes de Eventos

### Descripción
Funcionalidad para que administradores vean quiénes se han inscrito a un evento.

### Características

#### Botón "Ver Participantes"
- Visible solo para usuarios con rol DIRECCION o superior
- Muestra el número de inscritos
- Aparece en la vista de detalle del evento

#### Modal de Participantes
Tabla con la siguiente información:
- Nombre del participante
- Email
- Empresa (si aplica)
- Fecha de inscripción
- **Estado de pago** (si el evento tiene costo):
  - 💚 Pagado (COMPLETADO)
  - 🟡 Pendiente (PENDIENTE)
  - 🔴 Cancelado (CANCELADO)
  - ⚪ Sin pago (SIN_PAGO)
- Monto pagado

#### Casos de Uso
- Verificar asistencia confirmada
- Control de pagos para eventos de pago
- Seguimiento de participación

### Ubicación
- **Archivo**: `eventos.php` (vista detallada)
- **API**: `api/evento_participantes.php`

### Permisos Requeridos
- Rol: DIRECCION o superior

### SQL
```sql
-- Campos agregados a eventos_inscripciones
ALTER TABLE eventos_inscripciones 
ADD COLUMN estado_pago ENUM('SIN_PAGO', 'PENDIENTE', 'COMPLETADO', 'CANCELADO'),
ADD COLUMN monto_pagado DECIMAL(10,2),
ADD COLUMN fecha_pago DATETIME,
ADD COLUMN referencia_pago VARCHAR(100);

-- Campo agregado a eventos (si no existe)
ALTER TABLE eventos ADD COLUMN costo DECIMAL(10,2) DEFAULT 0;
```

---

## Navegación

Las nuevas páginas se agregaron al menú de navegación:

### Para Usuarios Internos
- 📅 **Calendario** (todos los roles internos)

### Para Usuarios Externos
- 📅 **Calendario** (vista filtrada)
- 💳 **Mi Membresía** (ENTIDAD_COMERCIAL, EMPRESA_TRACTORA)
- ✏️ **Completar Perfil** (ENTIDAD_COMERCIAL, EMPRESA_TRACTORA)

---

## Instalación

### 1. Ejecutar Migración SQL
```bash
mysql -u usuario -p nombre_bd < database/migration_payment_calendar_membership.sql
```

### 2. Configurar PayPal (Opcional)
Si se desea usar la funcionalidad de actualización de membresías:

1. Crear cuenta de desarrollador en https://developer.paypal.com
2. Obtener Client ID y Secret
3. Ir a **Configuración del Sistema** en el CRM
4. Completar campos de PayPal:
   - Client ID
   - Secret
   - Modo (sandbox para pruebas, live para producción)
   - Email de cuenta PayPal

### 3. Verificar Permisos de Directorio
```bash
chmod 755 public/uploads
chmod 755 public/uploads/evidencias
```

### 4. Actualizar Niveles de Membresías
Revisar y ajustar el campo `nivel_orden` en la tabla `membresias`:
- 1 = Básica
- 2 = Estándar
- 3 = Premium
- 4 = VIP/Platinum

---

## Seguridad

### Validaciones Implementadas
- ✅ Autenticación requerida en todos los endpoints
- ✅ Verificación de permisos por rol
- ✅ Sanitización de inputs
- ✅ Validación de tipos de archivo
- ✅ Límite de tamaño de archivo (5MB)
- ✅ Protección contra SQL injection (prepared statements)
- ✅ Validación de referencias (empresa_id, usuario_id, etc.)

### Archivos de Evidencia
- Extensiones permitidas: JPG, JPEG, PNG, PDF
- Tamaño máximo: 5MB (constante `MAX_FILE_SIZE`)
- Ubicación: `public/uploads/`
- Nombres únicos con timestamp

---

## Soporte y Mantenimiento

### Logs y Auditoría
Todas las acciones importantes se registran en la tabla `auditoria`:
- Registro de pagos
- Actualización de membresías
- Actualización de perfil de empresa

### Notificaciones
El sistema envía notificaciones automáticas para:
- Nuevo pago registrado (a usuarios de la empresa)
- Membresía actualizada (al usuario que realizó la actualización)

---

## Notas Técnicas

### Dependencias Externas
- **FullCalendar.js** v5.11.3 (https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/)
- **PayPal SDK** (https://www.paypal.com/sdk/js)
- **Font Awesome** 6.4.0 (iconos)
- **Tailwind CSS** (estilos)
- **Chart.js** 4.4.0 (gráficas)

### Compatibilidad
- PHP 7.4+
- MySQL 5.7+
- Navegadores modernos (Chrome, Firefox, Safari, Edge)

### Performance
- Uso de índices en campos de consulta frecuente
- Vistas SQL para optimizar consultas del calendario
- Carga asíncrona de datos en modales
- Triggers para cálculo automático de porcentajes

---

## Ejemplos de Uso

### Ejemplo 1: Registrar un Pago
```
1. Ir a "Empresas"
2. Buscar la empresa
3. Click en el ícono 💵 "Registrar Pago"
4. Llenar formulario:
   - Concepto: "Renovación Membresía 2024"
   - Monto: 5000.00
   - Método: Transferencia
   - Adjuntar comprobante (PDF/JPG)
5. Click "Registrar Pago"
6. Verificar en Dashboard -> Gráfica de Ingresos
```

### Ejemplo 2: Actualizar Membresía (Usuario Externo)
```
1. Login como usuario externo
2. Ir a "Mi Membresía"
3. Ver membresía actual
4. Explorar opciones superiores
5. Click "Actualizar Ahora" en la deseada
6. Pagar con PayPal
7. Confirmación automática
```

### Ejemplo 3: Completar Perfil
```
1. Login como usuario externo
2. Ir a "Completar Perfil"
3. Ver progreso actual (ej: 60%)
4. Completar campos faltantes marcados en 🟠
5. Guardar cambios
6. Ver progreso actualizado
```

---

## Contacto y Soporte

Para reportar problemas o sugerencias sobre estas funcionalidades, contactar al administrador del sistema.

**Última actualización**: 2025-11-01
