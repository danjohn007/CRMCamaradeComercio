# Diagnóstico PayPal - Eventos

## Problema Reportado
El botón de PayPal se muestra en `evento_publico.php?evento=7` pero no abre la ventana de PayPal al hacer clic.

## Cambios Aplicados

### 1. Mejoras en `evento_publico.php`
- ✅ Agregados logs extensivos en consola
- ✅ Verificación de carga del SDK de PayPal
- ✅ Validación de Client ID antes de cargar el SDK
- ✅ Manejo de errores mejorado con mensajes claros
- ✅ Feedback visual durante el proceso de pago

### 2. Logs Agregados
Los siguientes mensajes aparecerán en la consola del navegador (F12):

**Al cargar la página:**
```
Inicializando botón de PayPal para eventos...
PayPal SDK cargado correctamente
```

**Al hacer clic en el botón:**
```
createOrder llamado
Enviando petición a crear_orden_paypal_evento.php
Respuesta recibida: 200
Datos de la orden: {success: true, order_id: "..."}
Order ID: 8XN12345...
Botón de PayPal renderizado exitosamente
```

**Al completar el pago:**
```
Pago aprobado: {orderID: "...", payerID: "..."}
```

## Pasos para Diagnosticar

### 1. Verificar Configuración de PayPal

Ejecutar el script de diagnóstico:
```
http://tu-dominio/test_paypal.php
```

Debe mostrar:
- ✓ Client ID configurado
- ✓ Secret configurado
- ✓ Token de acceso obtenido
- ✓ Botón de prueba funcionando

### 2. Abrir la Consola del Navegador

1. Ir a `evento_publico.php?evento=7`
2. Presionar **F12** para abrir DevTools
3. Ir a la pestaña **Console**
4. Registrarse en el evento (si aún no está registrado)
5. Observar los logs cuando aparece el botón de PayPal

### 3. Verificar Errores Comunes

#### Error: "PayPal SDK no está cargado"
**Causa:** El script de PayPal no se cargó desde internet
**Solución:**
- Verificar conexión a internet
- Verificar que el Client ID sea válido
- Revisar la consola para errores de red (pestaña Network en F12)

#### Error: "Error al crear la orden"
**Causa:** La API de creación de orden falló
**Solución:**
1. Revisar logs del servidor PHP
2. Verificar que las credenciales de PayPal sean correctas
3. Asegurarse de que el modo (sandbox/live) sea correcto

#### El botón aparece pero no pasa nada al hacer clic
**Causa:** JavaScript bloqueado o error en createOrder
**Solución:**
1. Revisar la consola (F12) para errores
2. Verificar que no haya bloqueadores de pop-ups activos
3. Intentar en modo incógnito del navegador

### 4. Verificar en la Pestaña Network (Red)

1. Abrir DevTools (F12)
2. Ir a la pestaña **Network**
3. Hacer clic en el botón de PayPal
4. Buscar la llamada a `crear_orden_paypal_evento.php`
5. Verificar:
   - **Status:** Debe ser 200 OK
   - **Response:** Debe contener `{"success":true,"order_id":"..."}`

### 5. Probar con Cuenta de Sandbox

Si estás en modo **sandbox**:

1. Ir a https://developer.paypal.com
2. Crear una cuenta personal de sandbox (si no tienes)
3. Al pagar, iniciar sesión con esa cuenta de prueba
4. **NO** usar tu cuenta real de PayPal

Datos típicos de cuenta sandbox:
```
Email: sb-xxxxx@personal.example.com
Password: (la que configuraste en PayPal Developer)
```

### 6. Verificar Base de Datos

Verificar que la configuración de PayPal esté en la BD:

```sql
SELECT * FROM configuracion WHERE clave LIKE 'paypal%';
```

Debe mostrar:
- `paypal_client_id` con un valor largo (aprox. 80 caracteres)
- `paypal_secret` con un valor largo
- `paypal_mode` con valor `sandbox` o `live`

### 7. Verificar Estructura de Tabla

```sql
DESCRIBE eventos_inscripciones;
```

Debe tener estas columnas:
- `paypal_order_id` VARCHAR(100)
- `estado_pago` ENUM('PENDIENTE', 'COMPLETADO', ...)
- `monto_pagado` DECIMAL(10,2)

## Flujo Esperado

1. **Usuario se registra** → Se crea inscripción con estado_pago = 'PENDIENTE'
2. **Aparece botón PayPal** → SDK se carga con Client ID
3. **Usuario hace clic** → `createOrder()` llama a API
4. **API crea orden** → Retorna `order_id`
5. **PayPal abre ventana** → Usuario inicia sesión y paga
6. **onApprove se ejecuta** → Redirige a `paypal_success_evento.php`
7. **API captura pago** → Actualiza estado_pago = 'COMPLETADO'
8. **Usuario recibe boleto** → Email con código QR

## Posibles Problemas y Soluciones

### Problema 1: Botón no aparece
- Verificar que `$_SESSION['pending_payment_inscripcion_id']` esté definida
- Verificar que el monto sea mayor a 0
- Revisar que el usuario se haya registrado correctamente

### Problema 2: Botón aparece pero está deshabilitado
- Verificar Client ID en configuración
- Revisar consola para errores de JavaScript

### Problema 3: Al hacer clic no pasa nada
- **MÁS COMÚN:** Bloqueador de pop-ups activo
- Solución: Permitir pop-ups para el sitio
- Alternativamente: Usar modo incógnito

### Problema 4: Error "Invalid credentials"
- Verificar que estés usando credenciales del modo correcto (sandbox/live)
- Si usas sandbox, usar Client ID y Secret de sandbox
- Si usas live, usar credenciales de producción

### Problema 5: Pide tarjeta real en sandbox
- Estás usando credenciales de **live** por error
- Cambiar a credenciales de **sandbox** en configuración
- Verificar `paypal_mode` = 'sandbox'

## Comandos Útiles para Debugging

### Ver últimas inscripciones pendientes de pago
```sql
SELECT id, nombre_invitado, email_invitado, estado_pago, monto_pagado, paypal_order_id
FROM eventos_inscripciones 
WHERE estado_pago = 'PENDIENTE' 
ORDER BY fecha_inscripcion DESC 
LIMIT 5;
```

### Ver configuración actual de PayPal
```sql
SELECT clave, 
       CASE 
           WHEN clave LIKE '%secret%' THEN CONCAT(LEFT(valor, 10), '...')
           ELSE valor 
       END as valor
FROM configuracion 
WHERE clave LIKE 'paypal%';
```

### Verificar si hay errores en logs PHP
Revisar el archivo de logs del servidor web (ubicación depende del servidor).

## Contacto de Soporte

Si después de estos pasos el problema persiste:

1. **Tomar screenshot** de la consola del navegador (F12)
2. **Exportar** los datos de configuración de PayPal (sin el secret)
3. **Copiar** el ID de la inscripción que está intentando pagar
4. **Revisar** logs del servidor PHP para errores

## Notas Importantes

- ⚠️ **Sandbox vs Live:** Asegúrate de usar el modo correcto
- ⚠️ **Pop-ups:** Los bloqueadores pueden interferir con PayPal
- ⚠️ **HTTPS:** PayPal requiere HTTPS en producción
- ⚠️ **Credenciales:** Nunca compartir el Secret públicamente
