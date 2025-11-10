# Guía Visual - Precio de Preventa y Emails Mejorados

## 📋 Tabla de Contenidos
1. [Crear Evento con Preventa](#crear-evento-con-preventa)
2. [Vista de Evento con Preventa Activa](#vista-de-evento-con-preventa-activa)
3. [Flujos de Email](#flujos-de-email)
4. [Ejemplos de Uso](#ejemplos-de-uso)

---

## 🎫 Crear Evento con Preventa

### Paso 1: Acceder al Formulario
- Ir a **Eventos** → **Nuevo Evento**
- Completar datos básicos (título, descripción, fechas, ubicación)

### Paso 2: Configurar Precios
En la nueva sección **"Configuración de Precios"**:

```
┌──────────────────────────────────────────────────────┐
│  💰 Configuración de Precios                         │
├──────────────────────────────────────────────────────┤
│                                                       │
│  Costo del Evento (MXN)      Precio de Preventa     │
│  ┌──────────────┐            ┌──────────────┐       │
│  │   500.00     │            │   350.00     │       │
│  └──────────────┘            └──────────────┘       │
│  Precio regular del boleto   Precio especial hasta  │
│                               la fecha límite        │
│                                                       │
│  Fecha Límite de Preventa                           │
│  ┌────────────────────────────┐                     │
│  │  2025-11-15  23:59         │                     │
│  └────────────────────────────┘                     │
│  ℹ️ Después de esta fecha, se cobrará el precio     │
│     regular                                          │
│                                                       │
└──────────────────────────────────────────────────────┘
```

### Ejemplo de Configuración
- **Costo del Evento:** $500.00 MXN
- **Precio de Preventa:** $350.00 MXN
- **Fecha Límite:** 15/11/2025 23:59
- **Ahorro:** $150.00 MXN (30% descuento)

---

## 👀 Vista de Evento con Preventa Activa

### Para Usuarios (Antes de la Fecha Límite)

```
╔═══════════════════════════════════════════════════════╗
║  🎉 TALLER DE INNOVACIÓN EMPRESARIAL                  ║
╠═══════════════════════════════════════════════════════╣
║                                                        ║
║  📅 15 de Noviembre, 2025                             ║
║  🕐 09:00 - 17:00                                     ║
║  📍 Centro de Convenciones                            ║
║                                                        ║
╠═══════════════════════════════════════════════════════╣
║                                                        ║
║  ┌────────────────────────────────────────────────┐  ║
║  │  ⭐ ¡PRECIO DE PREVENTA!                       │  ║
║  │                                                 │  ║
║  │  💰 $350.00 MXN  💵 $500.00 MXN               │  ║
║  │                                                 │  ║
║  │  ⏰ Válido hasta: 10/11/2025 23:59            │  ║
║  │                                                 │  ║
║  │           ┌──────────────┐                     │  ║
║  │           │   AHORRA     │                     │  ║
║  │           │  $150.00     │                     │  ║
║  │           └──────────────┘                     │  ║
║  └────────────────────────────────────────────────┘  ║
║                                                        ║
║  [  🎟️ REGISTRARME AL EVENTO  ]                      ║
║                                                        ║
╚═══════════════════════════════════════════════════════╝
```

### Después de la Fecha Límite

```
╔═══════════════════════════════════════════════════════╗
║  Costo del Evento                                     ║
║                                                        ║
║  💵 $500.00 MXN                                       ║
║                                                        ║
╚═══════════════════════════════════════════════════════╝
```

---

## 📧 Flujos de Email

### Flujo 1: Empresa Activa (Afiliada) - Múltiples Boletos

```
┌─────────────────────────────────────────────┐
│  PASO 1: REGISTRO                           │
│  Usuario registra 3 boletos                 │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  📧 EMAIL 1: CONFIRMACIÓN                   │
├─────────────────────────────────────────────┤
│  ✓ ¡Primer Boleto Confirmado!               │
│  ✓ Como empresa afiliada, es GRATIS        │
│  ✓ Incluye código QR del primer boleto     │
│  ⚠️ Pago pendiente: $700.00 MXN             │
│     (2 boletos adicionales × $350)          │
│  🔗 Link para pagar                         │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  PASO 2: USUARIO PAGA                       │
│  Completa pago vía PayPal                   │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  📧 EMAIL 2: BOLETOS ADICIONALES            │
├─────────────────────────────────────────────┤
│  ✅ ¡Pago Confirmado!                       │
│  🎉 2 Boletos Confirmados                   │
│     + 1 boleto gratuito = 3 boletos total   │
│  🎟️ Incluye código QR para todos           │
│  🖨️ Link para imprimir boletos             │
└─────────────────────────────────────────────┘
```

### Flujo 2: Usuario Regular (No Afiliado) - Con Pago

```
┌─────────────────────────────────────────────┐
│  PASO 1: REGISTRO                           │
│  Usuario registra 2 boletos                 │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  📧 EMAIL 1: CONFIRMACIÓN                   │
├─────────────────────────────────────────────┤
│  ✓ ¡Registro Exitoso!                       │
│  ⚠️ Pago pendiente: $700.00 MXN             │
│     (2 boletos × $350)                      │
│  🔗 Link para pagar                         │
│  ℹ️ Recibirás boletos después del pago     │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  PASO 2: USUARIO PAGA                       │
│  Completa pago vía PayPal                   │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  📧 EMAIL 2: TODOS LOS BOLETOS              │
├─────────────────────────────────────────────┤
│  ✅ ¡Pago Confirmado!                       │
│  🎉 2 Boletos Confirmados                   │
│  🎟️ Incluye código QR                      │
│  🖨️ Link para imprimir boletos             │
└─────────────────────────────────────────────┘
```

### Flujo 3: Evento Gratuito

```
┌─────────────────────────────────────────────┐
│  PASO 1: REGISTRO                           │
│  Usuario registra 2 boletos                 │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  📧 EMAIL ÚNICO: CONFIRMACIÓN               │
├─────────────────────────────────────────────┤
│  ✓ ¡Registro Completado!                    │
│  🎉 2 Boletos Confirmados                   │
│  🎟️ Incluye código QR                      │
│  🖨️ Link para imprimir boletos             │
│  ℹ️ Este evento es gratuito                │
└─────────────────────────────────────────────┘
```

---

## 💡 Ejemplos de Uso

### Caso 1: Conferencia de Negocios

**Configuración:**
- Costo Regular: $1,200.00 MXN
- Preventa: $850.00 MXN
- Fecha Límite: 30 días antes del evento
- Ahorro: $350.00 MXN (29%)

**Resultado:**
- Incentiva registro temprano
- Mayor flujo de caja anticipado
- Reduce riesgo de cancelaciones

### Caso 2: Taller de Capacitación

**Configuración:**
- Costo Regular: $500.00 MXN
- Preventa: $350.00 MXN
- Fecha Límite: 15 días antes del evento
- Ahorro: $150.00 MXN (30%)

**Resultado:**
- Cupos confirmados con anticipación
- Mejor planificación logística
- Mayor satisfacción de participantes

### Caso 3: Networking Empresarial

**Configuración:**
- Costo Regular: $300.00 MXN
- Sin preventa (dejar campos vacíos)

**Resultado:**
- Precio único durante todo el período
- Simplicidad en comunicación
- Flexible hasta último momento

---

## 🔍 Detalles de Implementación

### Cálculo Automático de Precio

El sistema determina automáticamente qué precio aplicar:

```
SI (fecha_actual ≤ fecha_limite_preventa) ENTONCES
    precio = precio_preventa
SINO
    precio = costo_regular
FIN SI
```

### Validaciones Importantes

✅ **Fecha límite < fecha del evento**
✅ **Precio preventa ≤ precio regular**
✅ **Campos opcionales** (eventos sin preventa funcionan igual)

### Beneficios por Tipo de Usuario

| Tipo de Usuario | Beneficio |
|----------------|-----------|
| Empresa Activa | ✅ Primer boleto GRATIS siempre |
| Empresa Activa | ✅ Preventa en boletos adicionales |
| Usuario Regular | ✅ Preventa en todos los boletos |
| Todos | ✅ Ahorro visible y claro |

---

## 📱 Responsive Design

La interfaz se adapta a todos los dispositivos:

```
┌──────────────┐  ┌────────────────────┐  ┌──────────────────────────┐
│   Móvil      │  │      Tablet        │  │       Desktop            │
├──────────────┤  ├────────────────────┤  ├──────────────────────────┤
│ Preventa     │  │ Preventa  Ahorro   │  │ Preventa  Regular Ahorro │
│ $350         │  │ $350     $150      │  │ $350     $500    $150    │
│ Regular      │  │                    │  │                          │
│ $500         │  │ [REGISTRAR]        │  │ [REGISTRARME AL EVENTO]  │
│              │  │                    │  │                          │
│ [REGISTRAR]  │  │                    │  │                          │
└──────────────┘  └────────────────────┘  └──────────────────────────┘
```

---

## 🎯 Mejores Prácticas

### Para Administradores

1. **Establecer precios claros**
   - Diferencia mínima del 20% entre preventa y regular
   - Números redondos fáciles de recordar

2. **Fechas límite estratégicas**
   - 7-30 días antes del evento
   - Alineadas con campañas de marketing

3. **Comunicación efectiva**
   - Destacar el ahorro en promociones
   - Recordar fecha límite en redes sociales

### Para Usuarios

1. **Registrarse temprano**
   - Aprovechar precio de preventa
   - Asegurar cupo en el evento

2. **Empresas afiliadas**
   - Primer boleto siempre gratis
   - Preventa aplica a boletos adicionales

3. **Confirmar pago**
   - Revisar email de confirmación
   - Completar pago antes de fecha límite

---

## 🔗 Enlaces Útiles

- **Documentación Técnica**: `IMPLEMENTACION_PREVENTA_EMAIL.md`
- **Guía de Instalación**: `INSTALLATION_GUIDE.md`
- **Manual de Usuario**: `GUIA_SISTEMA.md`

---

## ❓ Preguntas Frecuentes

**P: ¿Puedo cambiar el precio de preventa después de crear el evento?**
R: Sí, edita el evento y actualiza los campos en "Configuración de Precios".

**P: ¿Qué pasa si no configuro preventa?**
R: El evento funciona normalmente con un solo precio. Los campos son opcionales.

**P: ¿Puedo extender la fecha límite de preventa?**
R: Sí, edita el evento y cambia la "Fecha Límite de Preventa" a una fecha futura.

**P: ¿Las empresas afiliadas pagan preventa?**
R: El primer boleto es gratis. Los boletos adicionales sí aplican para preventa.

**P: ¿Cuántos emails recibe un usuario?**
R: 
- Sin pago: 1 email (confirmación con boletos)
- Con pago: 2 emails (confirmación + boletos después del pago)

---

## 📞 Soporte

Para ayuda adicional:
- 📧 Email: soporte@camaraqro.com
- 📱 WhatsApp: [Número de soporte]
- 🌐 Portal: https://sistema.camaraqro.com

---

**Última actualización:** 10 de Noviembre de 2025
**Versión:** 1.0
