# Instrucciones de Instalación - Nuevas Funcionalidades

## Resumen de Cambios

Este documento describe las nuevas funcionalidades implementadas y cómo instalarlas:

1. **Directorio Público Mejorado** - Iconos de VER DETALLES, favoritos y calificaciones
2. **Galería de Imágenes de Empresas** - Sistema de slider con zoom (1-5 imágenes por empresa)
3. **Validación de Vencimiento de Membresías** - Actualización automática del estado de empresas
4. **Validación de Membresía en Eventos** - Ya implementado y verificado
5. **Plantillas de Email Mejoradas** - Ya implementado y verificado

## 1. Ejecutar Migración de Base de Datos

Ejecuta el siguiente script SQL para crear las nuevas tablas:

```bash
mysql -u [usuario] -p [nombre_base_datos] < database/migrations/20251111_empresa_imagenes_gallery.sql
```

O ejecuta manualmente el contenido del archivo en phpMyAdmin o tu cliente MySQL preferido.

Este script creará:
- Tabla `empresa_imagenes` para la galería de imágenes
- Tabla `empresa_favoritos` para favoritos de usuarios
- Tabla `empresa_calificaciones` para calificaciones y comentarios
- Columnas `calificacion_promedio` y `total_calificaciones` en la tabla `empresas`

## 2. Configurar Actualización Automática de Membresías

### Opción A: Usar Cron Job (Recomendado para Producción)

Agrega la siguiente línea al crontab para ejecutar diariamente a las 2:00 AM:

```bash
crontab -e
```

Agrega:
```
0 2 * * * /usr/bin/php /path/to/CRMCamaradeComercio/app/cron/actualizar_estado_empresas.php
```

### Opción B: Llamar Manualmente o desde Script

Puedes ejecutar el script manualmente cuando lo necesites:

```bash
php app/cron/actualizar_estado_empresas.php
```

### Opción C: Usar Función Helper (Integración en el Sistema)

El sistema incluye la función `actualizarEstadoEmpresasPorVencimiento()` que puedes llamar desde cualquier parte del código. Por ejemplo, podrías agregarla en el dashboard o en un hook de inicio de sesión:

```php
// Actualizar todas las empresas vencidas
$empresas_actualizadas = actualizarEstadoEmpresasPorVencimiento();

// O actualizar una empresa específica
$empresas_actualizadas = actualizarEstadoEmpresasPorVencimiento($empresa_id);
```

## 3. Verificar Permisos de Directorio

Asegúrate de que el directorio de uploads tenga permisos de escritura:

```bash
chmod 755 public/uploads
chmod 755 public/uploads/logo
mkdir -p logs
chmod 755 logs
```

## 4. Probar las Nuevas Funcionalidades

### 4.1 Galería de Imágenes

1. Inicia sesión como usuario con rol CAPTURISTA o superior
2. Ve a **Empresas** > Editar una empresa existente
3. Verás la nueva sección "Galería de Imágenes"
4. Sube entre 1 y 5 imágenes (JPG, PNG, máximo 5MB cada una)
5. Guarda los cambios

### 4.2 Directorio Público

1. Navega a `/directorio_publico.php`
2. Verás las empresas con:
   - Slider de imágenes (si la empresa tiene múltiples imágenes)
   - Ícono de **VER DETALLES** (visible para todos)
   - Zoom al hacer clic en las imágenes
3. Si inicias sesión, verás además:
   - Ícono de **Favorito** (corazón)
   - Ícono de **Calificar** (estrella)

### 4.3 Favoritos y Calificaciones

1. Inicia sesión con cualquier usuario
2. En el directorio público, haz clic en el ícono de corazón para agregar/quitar favoritos
3. Haz clic en el ícono de estrella para calificar (1-5 estrellas) y dejar un comentario opcional

### 4.4 Verificar Validación de Membresías

Ejecuta manualmente el script para verificar que funciona:

```bash
php app/cron/actualizar_estado_empresas.php
```

Verifica en los logs:
```bash
cat logs/empresas_inactivadas.log
```

### 4.5 Verificar Validación en Registro de Eventos

1. Crea un evento con costo mayor a 0
2. Intenta registrarte como empresa con membresía vencida
3. Verifica que NO se te otorgue el boleto gratuito
4. Intenta registrarte como empresa con membresía vigente
5. Verifica que SÍ se te otorgue el primer boleto gratuito

### 4.6 Verificar Emails con Estilos

1. Registra a un evento
2. Verifica que el email de confirmación incluya:
   - Logo del sistema
   - Colores configurados en el sistema
   - Formato correcto

## 5. Configuración Adicional

### Configurar Logo del Sistema

1. Ve a **Configuración** del sistema
2. Sube el logo en la sección correspondiente
3. El logo aparecerá automáticamente en:
   - Directorio público
   - Emails de confirmación
   - Emails de boletos digitales

### Configurar Colores del Sistema

1. Ve a **Configuración** del sistema
2. Configura los colores primario, secundario y de acento
3. Los colores se aplicarán automáticamente en:
   - Directorio público
   - Emails del sistema
   - Página de detalle de empresa

## 6. Solución de Problemas

### Las imágenes no se suben

- Verifica permisos del directorio `public/uploads/`
- Verifica el límite de tamaño de archivo en PHP (`upload_max_filesize` y `post_max_size` en php.ini)
- Verifica que el formulario tenga `enctype="multipart/form-data"`

### Los favoritos/calificaciones no funcionan

- Verifica que las tablas `empresa_favoritos` y `empresa_calificaciones` existan
- Verifica que el usuario esté autenticado
- Revisa la consola del navegador para errores de JavaScript

### Las empresas no se inactivan automáticamente

- Verifica que el cron job esté configurado correctamente
- Ejecuta manualmente el script para ver si hay errores
- Verifica que las empresas tengan `fecha_renovacion` y `membresia_id` configurados

### El slider de imágenes no funciona

- Verifica que Swiper JS se esté cargando correctamente (revisa la consola del navegador)
- Verifica que las imágenes existan en `public/uploads/`
- Limpia la caché del navegador

## 7. APIs Disponibles

Las siguientes APIs están disponibles para uso:

### POST /api/toggle_favorito.php
Agrega o quita una empresa de favoritos
```json
{
  "empresa_id": 123
}
```

### POST /api/calificar_empresa.php
Califica una empresa (requiere autenticación)
```json
{
  "empresa_id": 123,
  "calificacion": 5,
  "comentario": "Excelente servicio"
}
```

### POST /api/eliminar_imagen_empresa.php
Elimina una imagen de la galería (requiere rol CAPTURISTA)
```json
{
  "imagen_id": 456,
  "empresa_id": 123
}
```

## 8. Mantenimiento

### Limpiar Imágenes Huérfanas

Si eliminas empresas, las imágenes asociadas se eliminan automáticamente (CASCADE). Pero si hay imágenes huérfanas por alguna razón, puedes ejecutar:

```sql
DELETE ei FROM empresa_imagenes ei
LEFT JOIN empresas e ON ei.empresa_id = e.id
WHERE e.id IS NULL;
```

### Revisar Logs

Los logs de actualización de membresías se guardan en:
```
logs/empresas_inactivadas.log
```

Revísalos periódicamente para monitorear las inactivaciones automáticas.

## 9. Seguridad

- Las APIs de favoritos y calificaciones requieren autenticación
- La API de eliminación de imágenes requiere rol CAPTURISTA o superior
- Las imágenes se validan por tipo (JPG, PNG) y tamaño (5MB máximo)
- Todos los inputs se sanitizan para prevenir XSS e inyección SQL

## 10. Características Técnicas

### Límites Implementados
- Máximo 5 imágenes por empresa
- Máximo 5MB por imagen
- Formatos permitidos: JPG, JPEG, PNG
- Calificaciones: 1-5 estrellas

### Optimizaciones
- Las imágenes se cargan bajo demanda
- El slider usa lazy loading
- Las consultas usan índices en la base de datos
- Las imágenes se ordenan por el campo `orden`

## Soporte

Si encuentras problemas durante la instalación o uso de estas funcionalidades, revisa:

1. Logs del servidor web (Apache/Nginx)
2. Logs de PHP
3. Logs de MySQL
4. Consola del navegador (F12)

Para reportar bugs o solicitar mejoras, contacta al equipo de desarrollo.
