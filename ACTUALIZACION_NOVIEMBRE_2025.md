# Actualización del Sistema - Noviembre 2025

## Resumen de Cambios

Esta actualización incluye correcciones críticas y mejoras de funcionalidad del sistema CRM de la Cámara de Comercio.

## Problemas Resueltos

### 1. ✅ Código QR no se imprime correctamente en boletos

**Problema:** El código QR no se visualizaba correctamente al imprimir el boleto digital.

**Solución:**
- Mejorado CSS específico para impresión con dimensiones fijas (200px x 200px)
- Aumentado tamaño predeterminado del QR a 400px para mejor calidad
- Agregada propiedad `image-rendering` para mejor calidad de impresión
- Agregada opción `crossorigin="anonymous"` para compatibilidad

**Archivos modificados:**
- `boleto_digital.php`
- `app/helpers/qrcode.php`
- `configuracion.php`

**Nueva Configuración:**
- **Proveedor de API de QR:** Seleccionar entre Google Charts, QR Server o QuickChart
- **Tamaño de QR:** Configurar tamaño en píxeles (recomendado: 400px)
- Acceso: Configuración → Configuración de Códigos QR

---

### 2. ✅ Lista de participantes del evento incompleta

**Problema:** No se mostraban todos los participantes del evento, faltaban los registrados sin usuario (público).

**Solución:**
- Modificado query para usar `LEFT JOIN` en lugar de `INNER JOIN`
- Agregado uso de `COALESCE` para obtener datos de usuario o invitado
- Incluido campo `boletos_solicitados` en la respuesta
- Calculado suma total de boletos en `total_boletos`

**Archivos modificados:**
- `api/evento_participantes.php`

**Estructura de respuesta mejorada:**
```json
{
  "success": true,
  "participantes": [...],
  "total": 25,
  "total_boletos": 45,
  "tiene_costo": true
}
```

---

### 3. ✅ No permite suspender empresa desde listado

**Problema:** La opción de suspender empresa no funcionaba desde el listado.

**Solución:**
- Agregada verificación explícita de permisos `hasPermission('CAPTURISTA')`
- Mejorados mensajes de error cuando no hay permisos
- Mantenida la funcionalidad de suspender/activar con auditoría

**Archivos modificados:**
- `empresas.php`

**Permisos requeridos:**
- CAPTURISTA o superior para suspender/activar empresas

---

### 4. ✅ Calendario no muestra imágenes de eventos

**Problema:** Las imágenes de los eventos no se mostraban en la vista del calendario.

**Solución:**
- Agregada columna `imagen` en query de eventos
- Incluida URL completa de imagen en respuesta de API
- Modificado modal de calendario para mostrar imagen
- Imagen se muestra en vista de detalle con altura fija de 48 unidades

**Archivos modificados:**
- `api/calendario_eventos.php`
- `calendario.php`

**Resultado:** Al hacer clic en un evento del calendario, se muestra su imagen antes de la descripción.

---

### 5. ✅ Buscador global no busca usuarios

**Problema:** El buscador global no incluía búsqueda de usuarios.

**Solución:**
- Agregado nuevo tipo de búsqueda: "Usuarios"
- Búsqueda por nombre, email y WhatsApp
- Visible solo para usuarios con permiso CAPTURISTA
- Agregados índices en base de datos para mejorar rendimiento

**Archivos modificados:**
- `buscar.php`

**Campos de búsqueda de usuarios:**
- Nombre completo
- Email
- WhatsApp

**Metadata mostrada:**
- Email, WhatsApp, Empresa, Rol

---

### 6. ✅ Error en trigger de tabla empresas

**Problema:** Error "SQLSTATE[HY000]: General error: 1442 Can't update table 'empresas' in stored function/trigger"

**Causa:** Triggers `actualizar_porcentaje_perfil_insert` y `actualizar_porcentaje_perfil_update` intentaban actualizar la misma tabla que los invocó, causando recursión.

**Solución:**
- Eliminados triggers problemáticos
- Creado nuevo trigger `notificar_renovacion_proxima` sin recursión
- El trigger solo crea notificaciones, no actualiza la tabla empresas
- Script SQL completo en `database/fix_triggers_and_improvements.sql`

**Archivos creados:**
- `database/fix_triggers_and_improvements.sql`

**Triggers eliminados:**
- `actualizar_porcentaje_perfil_insert`
- `actualizar_porcentaje_perfil_update`

**Triggers creados/actualizados:**
- `notificar_renovacion_proxima` (mejorado, sin recursión)

---

### 7. ✅ No se genera mensaje de confirmación al inscribirse estando en sesión

**Problema:** Cuando un usuario autenticado se inscribía a un evento, no recibía código QR ni mensaje de confirmación.

**Solución:**
- Agregada generación de código QR único para inscripciones de usuarios autenticados
- Implementado envío de email con boleto digital
- Agregado enlace directo al boleto digital en mensaje de éxito
- Guardados todos los datos necesarios (nombre, email, empresa, código QR)
- Manejo de errores: si falla el email, la inscripción sigue siendo exitosa

**Archivos modificados:**
- `eventos.php`

**Flujo mejorado:**
1. Usuario se inscribe al evento
2. Sistema genera código QR único
3. Se guarda inscripción con todos los datos
4. Se envía email con boleto digital (si configurado SMTP)
5. Se muestra mensaje de confirmación con enlace a boleto
6. Usuario puede imprimir boleto inmediatamente

---

### 8. ✅ Script SQL de actualización

**Creado:** `database/fix_triggers_and_improvements.sql`

**Contenido del script:**

1. **Solución de triggers problemáticos**
   - Eliminación de triggers que causaban recursión
   - Creación de trigger mejorado para notificaciones

2. **Columnas agregadas:**
   - `eventos.imagen` - Para imágenes en calendario
   - `eventos_inscripciones.boletos_solicitados` - Número de boletos por registro

3. **Índices creados para mejor rendimiento:**
   ```sql
   -- Usuarios
   ALTER TABLE usuarios ADD INDEX idx_whatsapp (whatsapp);
   ALTER TABLE usuarios ADD INDEX idx_email (email);
   
   -- Empresas
   ALTER TABLE empresas ADD INDEX idx_whatsapp (whatsapp);
   
   -- Inscripciones
   ALTER TABLE eventos_inscripciones ADD INDEX idx_whatsapp_invitado (whatsapp_invitado);
   ALTER TABLE eventos_inscripciones ADD INDEX idx_email_invitado (email_invitado);
   ALTER TABLE eventos_inscripciones ADD INDEX idx_codigo_qr (codigo_qr);
   
   -- Eventos
   ALTER TABLE eventos ADD INDEX idx_tipo_fecha (tipo, fecha_inicio);
   ALTER TABLE eventos ADD INDEX idx_activo_fecha (activo, fecha_inicio);
   ```

4. **Nuevas configuraciones del sistema:**
   - `qr_api_provider` - Proveedor de API para códigos QR
   - `qr_size` - Tamaño del código QR para impresión

5. **Actualización de integridad de datos:**
   - Actualizado contador de inscritos en eventos
   - Limpiados datos inconsistentes en boletos_solicitados

---

## Instrucciones de Instalación

### 1. Actualizar archivos del sistema

Los archivos modificados ya están en el repositorio. Asegúrese de tener la última versión:

```bash
git pull origin main
```

### 2. Ejecutar script SQL de actualización

**IMPORTANTE:** Haga un respaldo de la base de datos antes de ejecutar el script.

```bash
# Hacer respaldo
mysqldump -u usuario -p crm_camara_comercio > backup_$(date +%Y%m%d).sql

# Ejecutar script de actualización
mysql -u usuario -p crm_camara_comercio < database/fix_triggers_and_improvements.sql
```

### 3. Verificar configuración

1. Iniciar sesión como PRESIDENCIA
2. Ir a **Configuración → Configuración de Códigos QR**
3. Seleccionar proveedor de API (recomendado: Google Charts por defecto)
4. Establecer tamaño de QR en 400 píxeles
5. Guardar configuración

### 4. Pruebas recomendadas

#### Probar impresión de boleto:
1. Ir a un evento público
2. Registrarse para el evento
3. Abrir boleto digital
4. Imprimir (Ctrl+P o Cmd+P)
5. Verificar que el QR se ve claramente

#### Probar lista de participantes:
1. Ir a un evento con inscripciones
2. Ver lista de participantes
3. Verificar que muestra todos los participantes
4. Verificar suma total de boletos

#### Probar suspender empresa:
1. Ir a Gestión de Empresas
2. En columna de Acciones, hacer clic en ícono de suspender
3. Confirmar la acción
4. Verificar que la empresa se suspende correctamente

#### Probar calendario con imágenes:
1. Ir a Calendario
2. Hacer clic en un evento que tenga imagen
3. Verificar que se muestra la imagen en el modal

#### Probar búsqueda de usuarios:
1. Ir a Búsqueda Global
2. Seleccionar tipo "Usuarios"
3. Buscar por nombre, email o WhatsApp
4. Verificar resultados

#### Probar inscripción con sesión:
1. Iniciar sesión como usuario de empresa
2. Ir a un evento
3. Inscribirse al evento
4. Verificar mensaje de confirmación
5. Hacer clic en "Ver Boleto Digital"
6. Verificar que se muestra el boleto con QR

---

## Archivos Modificados

### PHP Files:
- `api/calendario_eventos.php` - Agregada columna imagen
- `api/evento_participantes.php` - Corregido query para todos los participantes
- `app/helpers/qrcode.php` - Soporte para múltiples APIs de QR
- `boleto_digital.php` - Mejorado CSS de impresión
- `buscar.php` - Agregada búsqueda de usuarios
- `calendario.php` - Mostrar imagen en modal de evento
- `configuracion.php` - Agregada configuración de QR API
- `empresas.php` - Verificación de permisos para suspender
- `eventos.php` - Generación de QR y confirmación para usuarios autenticados

### SQL Files:
- `database/fix_triggers_and_improvements.sql` - Script completo de actualización

### Documentation:
- `ACTUALIZACION_NOVIEMBRE_2025.md` - Este archivo

---

## Configuraciones Nuevas

Acceder a través de: **Configuración del Sistema** (solo PRESIDENCIA)

### Configuración de Códigos QR

| Campo | Descripción | Valores | Por defecto |
|-------|-------------|---------|-------------|
| API para Generación de QR | Proveedor de servicio de QR | Google Charts API, QR Server API, QuickChart API | Google Charts API |
| Tamaño de QR (píxeles) | Tamaño del código QR | 200-1000 (incrementos de 50) | 400 |

**Recomendaciones:**
- Para mejor calidad de impresión: usar 400px o mayor
- Google Charts API es confiable pero tiene límites de tasa
- QR Server API es más robusto para alto volumen
- QuickChart API es moderno y rápido

---

## Notas Técnicas

### Cambios en Base de Datos

**Columnas agregadas:**
```sql
ALTER TABLE eventos ADD COLUMN imagen VARCHAR(255);
ALTER TABLE eventos_inscripciones ADD COLUMN boletos_solicitados INT DEFAULT 1;
```

**Índices agregados:**
- 8 nuevos índices para mejorar rendimiento de búsquedas
- Índices compuestos en eventos para consultas por tipo y fecha

**Triggers eliminados:**
- `actualizar_porcentaje_perfil_insert`
- `actualizar_porcentaje_perfil_update`

**Triggers modificados:**
- `notificar_renovacion_proxima` - Ahora sin recursión

### Compatibilidad

- **PHP:** 7.4+
- **MySQL:** 5.7+
- **Navegadores:** Chrome, Firefox, Safari, Edge (últimas 2 versiones)

### Seguridad

- Búsqueda de usuarios restringida a personal con permisos CAPTURISTA
- Suspender/activar empresas requiere permisos CAPTURISTA
- Todas las acciones se registran en auditoría

---

## Solución de Problemas

### El QR aún no se imprime bien

1. Verificar configuración en sistema (tamaño debe ser 400px mínimo)
2. Probar con otro proveedor de API (cambiar a QR Server o QuickChart)
3. Limpiar caché del navegador
4. Usar Google Chrome para mejores resultados de impresión

### No aparecen todos los participantes

1. Verificar que ejecutó el script SQL de actualización
2. Revisar que la columna `boletos_solicitados` existe
3. Verificar permisos del usuario (debe ser DIRECCION o superior)

### Error al suspender empresa

1. Verificar que el usuario tiene rol CAPTURISTA o superior
2. Revisar que se ejecutó el script SQL (triggers eliminados)
3. Ver logs de error en PHP para más detalles

### No se muestran imágenes en calendario

1. Verificar que los eventos tienen imágenes subidas
2. Verificar permisos del directorio `public/uploads/`
3. Limpiar caché del navegador

### Usuarios no aparecen en búsqueda

1. Verificar que el usuario tiene permisos CAPTURISTA
2. Verificar que ejecutó el script SQL (índices creados)
3. Probar con términos de búsqueda exactos

### No llega email de confirmación al inscribirse

1. Verificar configuración SMTP en Configuración del Sistema
2. Revisar logs de error en PHP
3. La inscripción es exitosa aunque falle el email
4. Usuario puede acceder a boleto desde enlace en mensaje de éxito

---

## Mejoras Futuras Sugeridas

1. **API de QR local:** Implementar generación de QR sin dependencia de APIs externas
2. **Notificaciones push:** Agregar notificaciones en tiempo real para inscripciones
3. **Exportar participantes:** Botón para exportar lista de participantes a Excel
4. **Estadísticas de eventos:** Dashboard con métricas de asistencia
5. **QR Scanner mobile:** App móvil para escanear códigos QR en entrada de eventos

---

## Soporte

Para reportar problemas o solicitar nuevas funcionalidades:

1. Crear issue en GitHub
2. Contactar al equipo de desarrollo
3. Revisar la documentación en `/docs`

---

## Changelog

### [2.1.0] - 2025-11-02

#### Agregado
- Configuración de API de códigos QR en sistema
- Búsqueda de usuarios en buscador global
- Imágenes de eventos en calendario
- Generación de QR y confirmación para usuarios autenticados
- Campo `boletos_solicitados` en API de participantes
- 8 nuevos índices en base de datos
- Script SQL completo de actualización

#### Corregido
- Impresión de código QR en boletos
- Lista incompleta de participantes de eventos
- Funcionalidad de suspender empresa
- Error de trigger en tabla empresas
- Mensaje de confirmación al inscribirse estando en sesión

#### Modificado
- Query de participantes usa LEFT JOIN
- Triggers eliminan recursión
- CSS de impresión mejorado para QR
- Permisos explícitos para suspender empresas

#### Eliminado
- Triggers `actualizar_porcentaje_perfil_insert`
- Triggers `actualizar_porcentaje_perfil_update`

---

**Fecha de actualización:** 2 de Noviembre de 2025  
**Versión:** 2.1.0  
**Estado:** ✅ Implementado y Probado
