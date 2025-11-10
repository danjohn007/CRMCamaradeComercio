# Solución: Integración PayPal para Membresías

## Problema
El sistema se quedaba cargando al intentar pagar con PayPal para actualizar membresías, sin mostrar la ventana de pago.

## Solución Implementada

### Archivos Creados/Modificados

1. **api/crear_orden_paypal_membresia.php** (NUEVO)
   - Crea órdenes de pago en PayPal para upgrades de membresía
   - Valida permisos y existencia de membresías
   - Guarda el registro pendiente en `membresias_upgrades`

2. **api/paypal_success_membresia.php** (NUEVO)
   - Procesa el callback de éxito de PayPal
   - Captura el pago y actualiza la membresía
   - Registra en auditoría y crea notificaciones

3. **mi_membresia.php** (MODIFICADO)
   - Descomentado el modal de PayPal
   - Actualizado JavaScript para usar la nueva API
   - Agregados logs extensivos para debugging
   - Botones ahora abren modal de PayPal en lugar de formulario POST

4. **test_paypal.php** (NUEVO)
   - Script de diagnóstico para verificar configuración de PayPal
   - Prueba de conexión y obtención de token
   - Botón de prueba del SDK

## Pasos para Diagnosticar

### 1. Verificar Configuración de PayPal

Acceder a: `http://tu-dominio/test_paypal.php`

Este script verificará:
- ✓ Client ID configurado
- ✓ Secret configurado  
- ✓ Modo (sandbox/live)
- ✓ Conexión con PayPal API
- ✓ SDK cargando correctamente

### 2. Verificar en la Consola del Navegador

Abrir las Herramientas de Desarrollador (F12) y revisar la consola:

**Logs esperados cuando funciona correctamente:**
```
Abriendo modal para membresía: 2 Membresía Premium 500
PayPal SDK cargado correctamente
Renderizando botón de PayPal...
Iniciando renderizado de botón PayPal con monto: 500 membresiaId: 2
Botón de PayPal renderizado exitosamente
```

**Al hacer clic en "Pagar":**
```
createOrder llamado
Enviando petición a crear_orden_paypal_membresia.php
Respuesta recibida: 200
Resultado parseado: {success: true, order_id: "..."}
Order ID creado: 8XN12345...
```

### 3. Errores Comunes y Soluciones

#### Error: "PayPal no está configurado correctamente"
**Causa:** Client ID no está en la configuración
**Solución:** 
1. Ir a Configuración → Configuración de PayPal
2. Ingresar Client ID y Secret de tu cuenta PayPal Developer

#### Error: "PayPal SDK no está cargado"
**Causa:** Script de PayPal no se carga
**Solución:**
- Verificar que `paypal_client_id` esté en la base de datos
- Revisar la red (F12 → Network) para ver si hay error al cargar el SDK

#### Error: "Failed to get PayPal access token"
**Causa:** Credenciales incorrectas o modo incorrecto
**Solución:**
1. Verificar que Client ID y Secret sean correctos
2. Si estás en sandbox, usar credenciales de sandbox
3. Si estás en live, usar credenciales de producción

#### Error: "Error al crear la orden de PayPal"
**Causa:** La API no puede crear la orden
**Solución:**
- Revisar los logs del servidor en PHP
- Verificar que el usuario tenga empresa_id
- Verificar que la membresía exista y esté activa

### 4. Verificar Credenciales de PayPal

#### Para Sandbox (Pruebas):
1. Ir a https://developer.paypal.com
2. Crear una app en "My Apps & Credentials"
3. Copiar Client ID y Secret del modo Sandbox
4. Crear una cuenta de prueba (sandbox account)

#### Para Live (Producción):
1. Ir a https://developer.paypal.com
2. Cambiar a modo "Live"
3. Crear app y obtener credenciales Live
4. **IMPORTANTE:** Solo usar en producción con cuenta real

### 5. Probar el Flujo Completo

1. **Login** como usuario con rol ENTIDAD_COMERCIAL o EMPRESA_TRACTORA
2. Ir a **Mi Membresía**
3. Hacer clic en **"Actualizar con PayPal"** en una membresía
4. Debe aparecer un modal con:
   - Información de la membresía actual y nueva
   - Monto a pagar
   - Botón de PayPal (amarillo)
5. Al hacer clic en el botón de PayPal:
   - Debe abrir ventana/redirect a PayPal
   - Iniciar sesión con cuenta PayPal (sandbox o real)
   - Aprobar el pago
   - Redirigir de vuelta al sistema
   - Mostrar mensaje de éxito

## Configuración en Base de Datos

Verificar que existan estas entradas en la tabla `configuracion`:

```sql
SELECT * FROM configuracion WHERE clave LIKE 'paypal%';
```

Debe mostrar:
- `paypal_account` - Cuenta de PayPal
- `paypal_client_id` - Client ID de la app
- `paypal_secret` - Secret de la app
- `paypal_mode` - sandbox o live

Si faltan, insertar:

```sql
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('paypal_account', 'tu-email@paypal.com', 'Cuenta de PayPal'),
('paypal_client_id', 'TU_CLIENT_ID', 'Client ID de PayPal'),
('paypal_secret', 'TU_SECRET', 'Secret de PayPal'),
('paypal_mode', 'sandbox', 'Modo de PayPal: sandbox o live');
```

## Estructura de la Base de Datos

Verificar que exista la tabla `membresias_upgrades`:

```sql
DESCRIBE membresias_upgrades;
```

Debe tener estas columnas:
- id
- empresa_id
- usuario_id
- membresia_anterior_id
- membresia_nueva_id
- monto
- metodo_pago
- estado (PENDIENTE, COMPLETADO, CANCELADO)
- paypal_order_id
- fecha_solicitud
- fecha_completado

## Notas Importantes

1. **Sandbox vs Live**: Asegurarse de usar el modo correcto según el ambiente
2. **HTTPS**: PayPal requiere HTTPS en producción (live)
3. **Webhook**: Opcional, pero recomendado para validación adicional
4. **Logs**: Revisar siempre la consola del navegador y los logs de PHP

## Contacto de Soporte

Si el problema persiste después de seguir estos pasos:
1. Revisar logs en la consola (F12)
2. Revisar logs del servidor PHP
3. Ejecutar test_paypal.php
4. Verificar credenciales en PayPal Developer
