# Integración de PayPal para Pagos de Eventos

## Descripción General

Este sistema incluye integración completa con PayPal para procesar pagos de eventos. La implementación permite:

- **Boletos gratuitos** para empresas afiliadas activas
- **Pagos con PayPal** para invitados y empresas no afiliadas
- **Bloqueo de impresión de boletos** hasta que el pago se complete
- **Múltiples boletos** por registro con cálculo automático del monto total

## Configuración de PayPal

### 1. Obtener Credenciales de PayPal

1. Accede al [Dashboard de Desarrolladores de PayPal](https://developer.paypal.com/dashboard/)
2. Inicia sesión con tu cuenta de PayPal Business
3. Ve a "My Apps & Credentials"
4. Crea una nueva aplicación o selecciona una existente
5. Copia el **Client ID** y el **Secret**

### 2. Configurar en el Sistema

1. Accede a **Configuración del Sistema** desde el menú principal (requiere permisos de PRESIDENCIA)
2. Busca la sección "Configuración de PayPal"
3. Completa los siguientes campos:

   - **Cuenta Principal de PayPal**: Email de la cuenta que recibirá los pagos
   - **Entorno de PayPal**: 
     - `Sandbox (Pruebas)`: Para desarrollo y pruebas
     - `Live (Producción)`: Para pagos reales
   - **Client ID**: ID de cliente de tu aplicación PayPal
   - **Client Secret**: Secreto del cliente de tu aplicación PayPal
   - **PayPal Plan ID - Mensual**: (Opcional) ID del plan de suscripción mensual
   - **PayPal Plan ID - Anual**: (Opcional) ID del plan de suscripción anual
   - **Webhook URL**: URL para recibir notificaciones de PayPal

4. Guarda la configuración

### 3. Probar la Integración

#### Modo Sandbox (Pruebas)

1. Crea cuentas de prueba en el [Dashboard de PayPal Sandbox](https://developer.paypal.com/developer/accounts/)
2. Configura el sistema en modo "Sandbox"
3. Crea un evento con costo
4. Registra un invitado (sin empresa afiliada)
5. Completa el pago usando las credenciales de prueba de PayPal

#### Modo Live (Producción)

1. Asegúrate de tener una cuenta PayPal Business verificada
2. Obtén las credenciales de producción
3. Cambia el modo a "Live" en la configuración
4. Prueba con un monto pequeño antes de lanzar

## Lógica de Negocio

### Boletos Gratuitos vs Pagados

El sistema determina automáticamente si un boleto es gratuito o requiere pago:

```
SI evento.costo > 0:
    SI empresa_id existe Y empresa.activo = 1:
        → Boleto GRATUITO (empresa afiliada)
    SINO:
        → REQUIERE PAGO
SINO:
    → Boleto GRATUITO (evento sin costo)
```

### Flujo de Pago

1. **Registro del Usuario**
   - Usuario completa el formulario de registro
   - Sistema crea inscripción con `estado_pago = 'PENDIENTE'`
   - Se calcula `monto_pagado = evento.costo * boletos_solicitados`

2. **Presentación del Botón de Pago**
   - Se muestra el botón de PayPal en la página de confirmación
   - También disponible en la página del boleto digital

3. **Creación de Orden**
   - JavaScript llama a `/api/crear_orden_paypal_evento.php`
   - Se crea una orden en PayPal
   - Se guarda `paypal_order_id` en la inscripción

4. **Proceso de Pago**
   - Usuario es redirigido a PayPal
   - Usuario aprueba el pago
   - PayPal redirige de vuelta al sistema

5. **Captura del Pago**
   - `/api/paypal_success_evento.php` captura el pago
   - Actualiza `estado_pago = 'COMPLETADO'`
   - Actualiza `fecha_pago = NOW()`
   - Guarda `referencia_pago` (capture ID)

6. **Envío del Boleto**
   - Se genera el código QR
   - Se envía email con el boleto digital
   - Usuario puede imprimir el boleto

### Estados de Pago

| Estado | Descripción |
|--------|-------------|
| `SIN_PAGO` | Evento gratuito o empresa afiliada |
| `PENDIENTE` | Pago requerido pero no completado |
| `COMPLETADO` | Pago procesado exitosamente |
| `CANCELADO` | Pago cancelado o fallido |

## Archivos Modificados/Creados

### Nuevos Archivos

- `app/helpers/paypal.php` - Helper class para interactuar con PayPal API
- `api/crear_orden_paypal_evento.php` - Endpoint para crear órdenes de pago
- `api/paypal_success_evento.php` - Endpoint para procesar pagos exitosos
- `database/migration_paypal_configuration.sql` - Migración de base de datos

### Archivos Modificados

- `configuracion.php` - Agregada sección de configuración de PayPal
- `evento_publico.php` - Lógica de pago para registros públicos
- `eventos.php` - Lógica de pago para usuarios autenticados
- `boleto_digital.php` - Bloqueo de impresión hasta pago completado

## Campos de Base de Datos

### Tabla: `configuracion`

Nuevas claves de configuración:
- `paypal_account` - Email de la cuenta PayPal
- `paypal_client_id` - Client ID de la aplicación
- `paypal_secret` - Secret de la aplicación
- `paypal_mode` - Modo (sandbox/live)
- `paypal_plan_id_monthly` - ID del plan mensual
- `paypal_plan_id_annual` - ID del plan anual
- `paypal_webhook_url` - URL del webhook

### Tabla: `eventos_inscripciones`

Campos relacionados con pagos:
- `estado_pago` - Estado del pago (SIN_PAGO, PENDIENTE, COMPLETADO, CANCELADO)
- `monto_pagado` - Monto total a pagar
- `fecha_pago` - Fecha en que se completó el pago
- `referencia_pago` - ID de captura de PayPal
- `paypal_order_id` - ID de la orden de PayPal

## Seguridad

### Recomendaciones

1. **Credenciales**
   - Nunca compartas las credenciales de producción
   - Usa variables de entorno para almacenar credenciales en producción
   - Considera encriptar `paypal_secret` en la base de datos

2. **Validación**
   - El sistema valida que el `paypal_order_id` coincida antes de capturar
   - Se registra auditoría de todas las transacciones
   - Se verifica el estado de la orden antes de marcar como pagado

3. **HTTPS**
   - Asegúrate de que el sitio use HTTPS en producción
   - PayPal requiere HTTPS para webhooks en modo live

## Solución de Problemas

### El botón de PayPal no aparece

- Verifica que `paypal_client_id` esté configurado
- Revisa la consola del navegador para errores de JavaScript
- Confirma que el evento tenga `costo > 0`

### Los pagos no se procesan

- Verifica que estés en el modo correcto (sandbox/live)
- Confirma que las credenciales sean válidas
- Revisa los logs de PHP (`error_log()`)
- Verifica que la URL de retorno sea accesible

### El boleto no se envía por email

- Verifica la configuración SMTP
- Revisa los logs de error
- Confirma que `boleto_enviado = 1` después del pago

## Soporte

Para más información sobre la API de PayPal:
- [Documentación de PayPal REST API](https://developer.paypal.com/docs/api/overview/)
- [Órdenes v2](https://developer.paypal.com/docs/api/orders/v2/)
- [Checkout Integration](https://developer.paypal.com/docs/checkout/)

## Próximos Pasos (Opcional)

- Implementar webhooks para notificaciones asíncronas
- Agregar reportes de ingresos por eventos
- Implementar reembolsos desde el panel de administración
- Agregar soporte para otros métodos de pago (tarjeta de crédito directo, OXXO, etc.)
