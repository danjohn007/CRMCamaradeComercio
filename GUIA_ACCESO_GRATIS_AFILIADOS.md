# Guía - Selector de Acceso Gratis para Afiliados en Eventos

## Descripción General

Esta funcionalidad permite a los organizadores de eventos controlar si los afiliados activos (empresas con membresía vigente) reciben acceso gratuito al evento o si todos los asistentes deben pagar, independientemente de su estado de afiliación.

## Configuración al Crear/Editar Eventos

### Ubicación del Selector

En el formulario de creación/edición de eventos, dentro de la sección **"Configuración de Precios"**, encontrarás una nueva opción:

```
┌─────────────────────────────────────────────────────────────┐
│ ☑ Acceso gratis para afiliados vigentes                    │
│                                                              │
│ Si está marcado, los afiliados con membresía vigente        │
│ recibirán 1 boleto gratis.                                  │
│ Si no está marcado, todos los asistentes deberán pagar      │
│ (incluyendo afiliados).                                      │
└─────────────────────────────────────────────────────────────┘
```

### Opciones Disponibles

#### Opción 1: Acceso Gratis Habilitado ✅ (Predeterminado)
- **Checkbox marcado**: ☑ Acceso gratis para afiliados vigentes
- **Comportamiento**: 
  - Afiliados activos reciben **1 boleto gratis**
  - Afiliados inactivos y no afiliados **pagan el precio del evento**
- **Caso de uso**: Eventos exclusivos como Open Days, conferencias para miembros, etc.

#### Opción 2: Todos Pagan ❌
- **Checkbox desmarcado**: ☐ Acceso gratis para afiliados vigentes
- **Comportamiento**: 
  - **Todos los asistentes pagan** el precio del evento
  - Incluye tanto afiliados activos como no afiliados
- **Caso de uso**: Eventos de recaudación de fondos, talleres especiales con costo para todos, etc.

## Ejemplos de Uso

### Ejemplo 1: Open Day con Acceso Gratis para Afiliados
```
Costo del Evento: $500.00 MXN
☑ Acceso gratis para afiliados vigentes

Resultado:
- Afiliado activo: $0.00 (1 boleto gratis)
- No afiliado: $500.00
```

### Ejemplo 2: Taller Premium con Costo para Todos
```
Costo del Evento: $1,500.00 MXN
☐ Acceso gratis para afiliados vigentes

Resultado:
- Afiliado activo: $1,500.00
- No afiliado: $1,500.00
```

### Ejemplo 3: Evento con Preventa y Acceso Gratis
```
Costo del Evento: $800.00 MXN
Precio de Preventa: $600.00 MXN
Fecha Límite: 15/12/2025
☑ Acceso gratis para afiliados vigentes

Resultado (antes de la fecha límite):
- Afiliado activo: $0.00 (1 boleto gratis)
- No afiliado: $600.00 (precio de preventa)

Resultado (después de la fecha límite):
- Afiliado activo: $0.00 (1 boleto gratis)
- No afiliado: $800.00 (precio regular)
```

## Integración con Otras Funcionalidades

### Compatibilidad con Preventa
- El selector de acceso gratis es **independiente** del sistema de preventa
- Ambas funcionalidades pueden usarse simultáneamente
- Los afiliados activos siguen recibiendo acceso gratis incluso durante el período de preventa (si la opción está habilitada)

### Registro Público de Eventos
- La configuración se respeta tanto en el registro interno (eventos.php) como en el registro público (evento_publico.php)
- Los afiliados que se registren a través del enlace público también recibirán el beneficio (si aplica)

## Preguntas Frecuentes

**P: ¿Cuál es el comportamiento predeterminado?**  
R: Por defecto, la opción está **habilitada** (checkbox marcado), manteniendo el comportamiento original donde los afiliados activos reciben 1 boleto gratis.

**P: ¿Puedo cambiar esta configuración después de crear el evento?**  
R: Sí, puedes editar el evento en cualquier momento y cambiar esta configuración. Los cambios se aplicarán a nuevas inscripciones.

**P: ¿Esto afecta a eventos ya existentes?**  
R: Los eventos existentes tendrán el valor predeterminado (acceso gratis habilitado). Si deseas cambiar esto, debes editar el evento manualmente.

**P: ¿Los afiliados pueden solicitar boletos adicionales?**  
R: Sí. Los afiliados activos reciben 1 boleto gratis (si la opción está habilitada). Cualquier boleto adicional tendrá el costo aplicable.

**P: ¿Qué determina si un afiliado está "activo"?**  
R: Un afiliado se considera activo si:
- La empresa está marcada como activa (campo `activo = 1`)
- La membresía no ha expirado (la fecha actual es anterior o igual a `fecha_renovacion`)

## Implementación Técnica

### Campo de Base de Datos
```sql
ALTER TABLE eventos ADD COLUMN acceso_gratis_afiliados TINYINT(1) DEFAULT 1;
```

- **Campo**: `acceso_gratis_afiliados`
- **Tipo**: TINYINT(1)
- **Valor predeterminado**: 1 (habilitado)
- **Valores**: 
  - 1 = Acceso gratis habilitado
  - 0 = Todos pagan

### Migración
Para aplicar esta funcionalidad a una instalación existente, ejecuta:
```bash
mysql -u usuario -p nombre_base_datos < database/migration_acceso_gratis_afiliados.sql
```

## Notas Adicionales

- Esta funcionalidad no afecta eventos gratuitos (costo = 0)
- La lógica se aplica solo cuando el evento tiene un costo definido
- El sistema mantiene compatibilidad con versiones anteriores (comportamiento predeterminado preservado)
