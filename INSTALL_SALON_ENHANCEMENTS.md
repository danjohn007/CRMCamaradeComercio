# Guía de Instalación - Mejoras de Salones y Perfil de Empresa

Esta guía describe cómo instalar las nuevas funcionalidades implementadas para el módulo de salones y la actualización del perfil de empresa.

## Cambios Implementados

### 1. Actualización de Perfil de Empresa
- **Cálculo de Completitud Actualizado**: El progreso de la barra de perfil ahora solo toma en cuenta 16 campos específicos (en lugar de 21):
  - Email, Teléfono, WhatsApp, Representante Legal
  - Dirección Comercial, Colonia (Dirección Comercial)
  - Dirección Fiscal, Colonia (Dirección Fiscal), Ciudad, Código Postal, Estado
  - Sector, Categoría
  - Descripción de la Empresa, Servicios y Productos, Palabras Clave
  
- **Campos Excluidos**: razon_social, rfc, membresia_id, sitio_web, facebook, instagram

### 2. Mejoras del Módulo de Salones

#### a) Galería de Imágenes
- Subir múltiples imágenes por salón
- Marcar imagen principal
- Eliminar imágenes
- Visualización en vista de detalles y edición

#### b) Calendario Interactivo
- Integración con FullCalendar 6.x
- Vista de disponibilidad en tiempo real
- Reservar directamente desde el calendario
- Código de colores para estados de reserva

#### c) Precios Diferenciados
- Tarifas para afiliados
- Tarifas para no afiliados
- Descuento adicional según nivel de membresía
- Cálculo automático de precios

#### d) Sistema de Pagos
- **PayPal**: Integración completa con botones de pago
- **Comprobante**: Opción para subir comprobante de pago
- Confirmación automática de reserva
- Envío de comprobante por email

## Instalación

### Paso 1: Aplicar Migración de Base de Datos

Ejecutar el siguiente archivo SQL en su base de datos:

```bash
mysql -u [usuario] -p [nombre_base_datos] < database/migrations/20251124_salon_enhancements.sql
```

O desde phpMyAdmin:
1. Abrir phpMyAdmin
2. Seleccionar la base de datos `crm_camara_comercio`
3. Ir a la pestaña "SQL"
4. Copiar y pegar el contenido de `database/migrations/20251124_salon_enhancements.sql`
5. Ejecutar

Este script creará:
- Tabla `salon_imagenes` para las imágenes de salones
- Campos de precios diferenciados en `salones`
- Campos de pago en `salon_reservas`
- Campo `descuento_salones` en `membresias`

### Paso 2: Configurar PayPal (Opcional)

Si desea usar PayPal para los pagos:

1. Obtener Client ID de PayPal:
   - Ir a https://developer.paypal.com/
   - Crear una aplicación
   - Copiar el Client ID

2. Agregar Client ID a la configuración del sistema:
   ```sql
   INSERT INTO configuracion (clave, valor, descripcion) 
   VALUES ('paypal_client_id', 'TU_CLIENT_ID_AQUI', 'PayPal Client ID para pagos de salones')
   ON DUPLICATE KEY UPDATE valor = 'TU_CLIENT_ID_AQUI';
   ```

### Paso 3: Crear Directorios de Uploads

Crear los directorios necesarios con permisos de escritura:

```bash
cd /ruta/a/tu/proyecto
mkdir -p public/uploads/salones
mkdir -p public/uploads/comprobantes_salones
chmod -R 755 public/uploads
```

### Paso 4: Configurar Descuentos de Membresías

El script de migración ya establece descuentos por defecto según el costo de la membresía:
- Membresías de $10,000+ : 15% de descuento
- Membresías de $5,000+ : 10% de descuento
- Membresías de $2,000+ : 5% de descuento
- Otras: Sin descuento

Puede ajustar estos valores manualmente en la tabla `membresias`.

### Paso 5: Actualizar Precios de Salones Existentes

Si ya tiene salones creados, la migración automáticamente:
- Copia los precios actuales como precios para afiliados
- Establece precios para no afiliados 30% más altos

Puede ajustar estos valores en la interfaz de edición de salones.

## Verificación

### 1. Perfil de Empresa
- Ir a "Completar mi Perfil"
- Verificar que la barra de progreso refleje solo los 16 campos especificados
- Completar los campos faltantes y ver que el porcentaje se actualiza correctamente

### 2. Galería de Imágenes
- Ir a Salones > Editar un salón
- Subir una imagen en la sección "Galería de Imágenes"
- Verificar que la imagen aparece en la galería
- Marcar como principal
- Ver el salón y verificar que las imágenes se muestran

### 3. Calendario y Reservas
- Ir a Salones > Ver Calendario
- Clic en el calendario para seleccionar fechas
- Completar el formulario de reserva
- Verificar que se calcula el precio correctamente según afiliación

### 4. Pagos
- Crear una reserva
- Proceder al pago
- Probar PayPal (requiere Client ID configurado)
- O subir un comprobante de pago
- Verificar que se recibe el email de confirmación

## Solución de Problemas

### Error: "Tabla salon_imagenes no existe"
- Verificar que se ejecutó correctamente la migración
- Ejecutar: `SHOW TABLES LIKE 'salon_imagenes';`

### Error: "Campo precio_hora_afiliado no existe"
- Verificar que se ejecutó correctamente la migración
- Ejecutar: `DESCRIBE salones;`

### Las imágenes no se guardan
- Verificar permisos de escritura en `public/uploads/salones/`
- Ejecutar: `chmod -R 755 public/uploads`

### PayPal no funciona
- Verificar que el Client ID está configurado correctamente
- Verificar que el archivo JS de PayPal se carga correctamente
- Revisar la consola del navegador para errores

### No llega el email de confirmación
- Verificar configuración de email en `config/config.php`
- Verificar que el servidor tiene configurado un servicio de correo (sendmail, postfix)
- Revisar logs del servidor: `/var/log/mail.log`

## Archivos Modificados/Creados

### Nuevos Archivos
- `database/migrations/20251124_salon_enhancements.sql`
- `api/salon_imagenes.php`
- `api/salon_reservas.php`
- `api/procesar_pago_paypal.php`
- `INSTALL_SALON_ENHANCEMENTS.md` (este archivo)

### Archivos Modificados
- `app/helpers/functions.php` - Función `calcularCompletitudPerfil()` actualizada
- `salones.php` - Agregadas funcionalidades de imágenes, calendario, y pagos

## Soporte

Si encuentra algún problema durante la instalación o uso de estas funcionalidades, por favor:
1. Revisar esta guía de instalación
2. Verificar los logs del servidor
3. Contactar al administrador del sistema
