# Resumen de Implementación: Integración de PayPal para Pagos de Eventos

## Objetivo

Implementar un sistema completo de pagos con PayPal para eventos, donde:
- Empresas afiliadas activas reciben boletos **GRATIS**
- Invitados y empresas no afiliadas deben **PAGAR** mediante PayPal
- Los boletos **NO SE PUEDEN IMPRIMIR** hasta que se complete el pago
- Se calcula el total según el número de boletos solicitados

## ¿Qué se ha implementado?

### 1. Configuración de PayPal en el Panel de Administración

**Archivo modificado:** `configuracion.php`

Se agregó una sección completa de "Configuración de PayPal" con los siguientes campos:

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| Cuenta Principal de PayPal | Email de la cuenta que recibe pagos | webmaster@impactosdigitales.com |
| Entorno de PayPal | Sandbox (pruebas) o Live (producción) | Sandbox |
| Client ID | ID de cliente de la aplicación PayPal | AZd7a_Vpfv6vuzd1CKF3e5a1OPu4jcK... |
| Client Secret | Secreto del cliente | ••••••••••••••••••••••• |
| PayPal Plan ID - Mensual | ID del plan mensual (opcional) | P-XXXXXXXXXXXX |
| PayPal Plan ID - Anual | ID del plan anual (opcional) | P-YYYYYYYYYYYY |
| Webhook URL | URL para notificaciones de PayPal | https://yourdomain.com/webhook/paypal |

La interfaz coincide con la imagen de referencia proporcionada.

### 2. Lógica de Negocio: ¿Gratis o Pago?

El sistema determina automáticamente si un boleto es gratis o requiere pago:

```
┌─────────────────────────────────┐
│ ¿El evento tiene costo > 0?     │
└────────────┬────────────────────┘
             │
       ┌─────▼─────┐
       │    SÍ     │
       └─────┬─────┘
             │
   ┌─────────▼──────────────────────┐
   │ ¿Usuario tiene empresa_id Y    │
   │ empresa.activo = 1?            │
   └──────────┬─────────────────────┘
              │
        ┌─────┴─────┐
        │           │
    ┌───▼──┐    ┌───▼──┐
    │  SÍ  │    │  NO  │
    └───┬──┘    └───┬──┘
        │           │
        ▼           ▼
   ┌────────┐  ┌─────────┐
   │ GRATIS │  │  PAGAR  │
   │(afilia │  │(invitado│
   │   do)  │  │o no afi)│
   └────────┘  └─────────┘
```

### 3. Flujo de Pago Implementado

```
┌──────────────────────────────────────────────────────────────┐
│ PASO 1: Usuario se registra al evento                       │
│ - Completa formulario                                        │
│ - Sistema verifica si empresa está activa                    │
│ - Crea inscripción con estado_pago según corresponda         │
└──────────────────────────┬───────────────────────────────────┘
                           │
                           ▼
                    ┌──────────────┐
                    │ ¿Requiere    │
                    │   pago?      │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │                         │
          ┌───▼───┐                 ┌───▼───┐
          │  NO   │                 │  SÍ   │
          │(Gratis│                 │ (Pago)│
          └───┬───┘                 └───┬───┘
              │                         │
              ▼                         ▼
    ┌──────────────────┐      ┌──────────────────┐
    │ PASO 2: Enviar   │      │ PASO 2: Mostrar  │
    │ boleto por email │      │ botón de PayPal  │
    │ inmediatamente   │      └─────────┬────────┘
    └──────────────────┘                │
                                        ▼
                              ┌──────────────────┐
                              │ PASO 3: Usuario  │
                              │ hace clic en     │
                              │ botón PayPal     │
                              └─────────┬────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ PASO 4: Sistema  │
                              │ crea orden en    │
                              │ PayPal API       │
                              └─────────┬────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ PASO 5: Usuario  │
                              │ redirigido a     │
                              │ PayPal y paga    │
                              └─────────┬────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ PASO 6: PayPal   │
                              │ redirige back a  │
                              │ nuestro sistema  │
                              └─────────┬────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ PASO 7: Sistema  │
                              │ captura el pago  │
                              │ en PayPal        │
                              └─────────┬────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ PASO 8: Marcar   │
                              │ como COMPLETADO  │
                              │ y enviar boleto  │
                              └──────────────────┘
```

### 4. Bloqueo de Impresión de Boletos

**Archivo modificado:** `boleto_digital.php`

El sistema ahora verifica el estado del pago antes de mostrar el boleto:

- Si `estado_pago = 'PENDIENTE'` → Muestra mensaje de error y botón de PayPal
- Si `estado_pago = 'COMPLETADO'` → Permite imprimir el boleto
- Si `estado_pago = 'SIN_PAGO'` → Permite imprimir el boleto (evento gratis o empresa afiliada)

### 5. Archivos Creados

#### a) `app/helpers/paypal.php`

Helper class con métodos para interactuar con la API de PayPal:

```php
- PayPalHelper::getAccessToken()      // Obtiene token de acceso
- PayPalHelper::createOrder()         // Crea orden de pago
- PayPalHelper::captureOrder()        // Captura el pago
- PayPalHelper::getOrderDetails()     // Obtiene detalles de orden
- PayPalHelper::isConfigured()        // Verifica si está configurado
- PayPalHelper::getClientId()         // Para uso en frontend
```

#### b) `api/crear_orden_paypal_evento.php`

Endpoint API que:
1. Recibe `inscripcion_id`
2. Verifica que el evento tenga costo
3. Calcula monto total (costo × boletos)
4. Crea orden en PayPal
5. Actualiza inscripción con `paypal_order_id`
6. Retorna datos de la orden (incluye URL de aprobación)

#### c) `api/paypal_success_evento.php`

Endpoint que maneja el retorno de PayPal:
1. Recibe `token` (order_id) y `inscripcion_id`
2. Verifica que el order_id coincida
3. Captura el pago en PayPal
4. Actualiza `estado_pago = 'COMPLETADO'`
5. Guarda `fecha_pago` y `referencia_pago`
6. Envía email con boleto digital
7. Registra auditoría
8. Redirige al boleto digital

#### d) `database/migration_paypal_configuration.sql`

Migración SQL que:
- Agrega campos de configuración de PayPal
- Agrega `paypal_order_id` a `eventos_inscripciones`
- Agrega `razon_social_invitado` a `eventos_inscripciones`
- Crea índices necesarios

### 6. Archivos Modificados

#### a) `evento_publico.php`

**Cambios:**
- Determina si evento requiere pago basado en costo y afiliación
- Calcula `monto_total = costo × boletos_solicitados`
- Establece `estado_pago` correcto al registrar
- Muestra botón de PayPal después del registro si requiere pago
- Mensajes condicionales según estado de pago

#### b) `eventos.php`

**Cambios:**
- Misma lógica que evento_publico.php pero para usuarios autenticados
- Verifica si usuario tiene empresa activa
- Calcula monto y establece estado de pago
- Redirige a pagar si es necesario

#### c) `configuracion.php`

**Cambios:**
- Agregada sección completa de "Configuración de PayPal"
- 7 nuevos campos de configuración
- Info box con enlace al Dashboard de PayPal
- Guarda nuevos campos en la base de datos

## Estados de Pago

| Estado | Descripción | ¿Puede imprimir? |
|--------|-------------|------------------|
| `SIN_PAGO` | Evento gratis o empresa afiliada | ✅ Sí |
| `PENDIENTE` | Requiere pago, no completado | ❌ No - muestra botón PayPal |
| `COMPLETADO` | Pago procesado exitosamente | ✅ Sí |
| `CANCELADO` | Pago cancelado o fallido | ❌ No - contactar admin |

## Campos de Base de Datos

### Tabla `eventos_inscripciones`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `estado_pago` | ENUM | SIN_PAGO, PENDIENTE, COMPLETADO, CANCELADO |
| `monto_pagado` | DECIMAL(10,2) | Monto total a pagar |
| `fecha_pago` | DATETIME | Fecha del pago completado |
| `referencia_pago` | VARCHAR(100) | ID de captura de PayPal |
| `paypal_order_id` | VARCHAR(100) | ID de la orden de PayPal |

## Integración con PayPal SDK

El sistema usa:
- **PayPal JavaScript SDK** en el frontend para el botón
- **PayPal REST API v2** en el backend para órdenes y captura
- **Client-side Flow**: Usuario aprueba en PayPal, backend captura

```html
<!-- Carga del SDK en páginas con pago -->
<script src="https://www.paypal.com/sdk/js?client-id=CLIENT_ID&currency=MXN&locale=es_MX"></script>
```

## Seguridad

✅ **Implementado:**
- Validación de order_id antes de capturar
- Verificación de que la inscripción corresponda al order_id
- Auditoría de todas las transacciones
- Uso de HTTPS requerido en producción

## Pruebas

### Para probar en Sandbox:

1. Configurar credentials de sandbox
2. Cambiar modo a "Sandbox"
3. Crear evento con costo > 0
4. Registrarse como invitado
5. Usar credenciales de prueba de PayPal

### Para producción:

1. Obtener credentials de aplicación live
2. Cambiar modo a "Live"
3. Probar con monto pequeño primero
4. Verificar que lleguen los pagos a la cuenta

## Documentación Adicional

Ver archivo `PAYPAL_INTEGRATION.md` para:
- Guía completa de configuración
- Solución de problemas
- Recomendaciones de seguridad
- Enlaces a documentación de PayPal

## Resumen de Cambios

| Categoría | Cantidad |
|-----------|----------|
| Archivos nuevos | 5 |
| Archivos modificados | 4 |
| Campos DB agregados | 8 |
| Endpoints API | 2 |
| Helpers creados | 1 |

## Impacto en el Usuario

### Usuario Final (Invitado/No afiliado):
1. Se registra al evento
2. Ve mensaje que debe pagar
3. Hace clic en botón de PayPal
4. Paga en PayPal
5. Recibe boleto por email
6. Puede imprimir boleto

### Usuario Final (Empresa Afiliada):
1. Se registra al evento
2. Ve mensaje "Como empresa afiliada, tu boleto es gratis"
3. Recibe boleto por email inmediatamente
4. Puede imprimir boleto

### Administrador:
1. Configura PayPal una sola vez
2. Los pagos se procesan automáticamente
3. Puede ver estado de pago en participantes
4. Auditoría completa de transacciones

## Conclusión

✅ **Sistema completo de pagos con PayPal implementado**
✅ **Lógica de negocios: gratis para afiliados, pago para invitados**
✅ **Bloqueo de impresión hasta pago completado**
✅ **Interfaz de configuración coincide con requisitos**
✅ **Todos los archivos validados sin errores de sintaxis**
✅ **Documentación completa incluida**

El sistema está listo para ser probado en entorno de sandbox y luego desplegado a producción.
