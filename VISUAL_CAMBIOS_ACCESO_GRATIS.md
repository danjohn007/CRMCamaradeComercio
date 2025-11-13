# Guía Visual - Cambios en el Formulario de Eventos

## Cambio en la Interfaz de Usuario

### ANTES de la implementación

```
┌────────────────────────────────────────────────────────────────┐
│ $ Configuración de Precios                                     │
│                                                                 │
│ ┌─────────────────────────┐  ┌─────────────────────────┐     │
│ │ Costo del Evento (MXN)  │  │ Precio de Preventa (MXN)│     │
│ │ [____________________]  │  │ [____________________]  │     │
│ │ Precio regular del boleto│  │ Precio especial hasta  │     │
│ └─────────────────────────┘  │ la fecha límite        │     │
│                               └─────────────────────────┘     │
│                                                                 │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ Fecha Límite de Preventa                                  │  │
│ │ [____________________]                                    │  │
│ │ ℹ Después de esta fecha, se cobrará el precio regular    │  │
│ └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└────────────────────────────────────────────────────────────────┘

❌ Problema: TODOS los eventos con costo daban acceso gratis a afiliados
```

### DESPUÉS de la implementación

```
┌────────────────────────────────────────────────────────────────┐
│ $ Configuración de Precios                                     │
│                                                                 │
│ ┌─────────────────────────┐  ┌─────────────────────────┐     │
│ │ Costo del Evento (MXN)  │  │ Precio de Preventa (MXN)│     │
│ │ [____________________]  │  │ [____________________]  │     │
│ │ Precio regular del boleto│  │ Precio especial hasta  │     │
│ └─────────────────────────┘  │ la fecha límite        │     │
│                               └─────────────────────────┘     │
│                                                                 │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ Fecha Límite de Preventa                                  │  │
│ │ [____________________]                                    │  │
│ │ ℹ Después de esta fecha, se cobrará el precio regular    │  │
│ └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│                                                                 │
│ ☑ Acceso gratis para afiliados vigentes          ← ✨ NUEVO   │
│   Si está marcado, los afiliados con membresía vigente        │
│   recibirán 1 boleto gratis. Si no está marcado, todos        │
│   los asistentes deberán pagar (incluyendo afiliados).        │
│                                                                 │
└────────────────────────────────────────────────────────────────┘

✅ Solución: El organizador puede elegir el comportamiento
```

## Ubicación del Nuevo Campo

El checkbox se encuentra:
- **Sección**: Configuración de Precios
- **Posición**: Después del campo "Fecha Límite de Preventa"
- **Elemento**: Checkbox con texto descriptivo
- **Estado predeterminado**: ☑ Marcado (comportamiento original preservado)

## Casos de Uso Visualizados

### Caso 1: Open Day (Acceso Gratis Habilitado)

```
┌────────────────────────────────────────────────────────────────┐
│ Crear Nuevo Evento: Open Day 2025                              │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Costo del Evento: $500.00 MXN                                 │
│ ☑ Acceso gratis para afiliados vigentes                       │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                          RESULTADO                              │
├────────────────────────────────────────────────────────────────┤
│ Afiliado Activo:    GRATIS ✅ (1 boleto)                      │
│ Afiliado Inactivo:  $500.00 💰                                │
│ No Afiliado:        $500.00 💰                                │
└────────────────────────────────────────────────────────────────┘
```

### Caso 2: Taller Premium (Todos Pagan)

```
┌────────────────────────────────────────────────────────────────┐
│ Crear Nuevo Evento: Taller de Marketing Digital                │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Costo del Evento: $1,500.00 MXN                               │
│ ☐ Acceso gratis para afiliados vigentes                       │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                          RESULTADO                              │
├────────────────────────────────────────────────────────────────┤
│ Afiliado Activo:    $1,500.00 💰                              │
│ Afiliado Inactivo:  $1,500.00 💰                              │
│ No Afiliado:        $1,500.00 💰                              │
└────────────────────────────────────────────────────────────────┘
```

### Caso 3: Evento con Preventa + Acceso Gratis

```
┌────────────────────────────────────────────────────────────────┐
│ Crear Nuevo Evento: Conferencia Anual                          │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Costo del Evento:        $800.00 MXN                          │
│ Precio de Preventa:      $600.00 MXN                          │
│ Fecha Límite:            15/12/2025 23:59                     │
│ ☑ Acceso gratis para afiliados vigentes                       │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│               RESULTADO (Antes del 15/12/2025)                  │
├────────────────────────────────────────────────────────────────┤
│ Afiliado Activo:    GRATIS ✅ (1 boleto)                      │
│ Afiliado Inactivo:  $600.00 💰 (preventa)                     │
│ No Afiliado:        $600.00 💰 (preventa)                     │
├────────────────────────────────────────────────────────────────┤
│              RESULTADO (Después del 15/12/2025)                 │
├────────────────────────────────────────────────────────────────┤
│ Afiliado Activo:    GRATIS ✅ (1 boleto)                      │
│ Afiliado Inactivo:  $800.00 💰 (precio regular)               │
│ No Afiliado:        $800.00 💰 (precio regular)               │
└────────────────────────────────────────────────────────────────┘
```

## Flujo de Decisión del Organizador

```
                    ¿El evento tiene costo?
                            │
              ┌─────────────┴─────────────┐
              │                           │
             NO                          SI
              │                           │
         (Gratis para                     │
          todos, sin                      │
          configuración                   │
          necesaria)                      │
                                          │
                    ¿Quieres que afiliados activos
                    tengan acceso gratis?
                            │
              ┌─────────────┴─────────────┐
              │                           │
             SI                          NO
              │                           │
    ☑ Marcar checkbox          ☐ Desmarcar checkbox
              │                           │
              │                           │
    Afiliados activos:         Todos pagan (incluye
    1 boleto GRATIS           afiliados activos)
    Otros: PAGAN                        │
              │                           │
              │                           │
         OPEN DAY              TALLER PREMIUM
         NETWORKING            FUNDRAISER
         CONFERENCIAS          CAPACITACIÓN ESPECIAL
```

## Comparación Lado a Lado

| Aspecto | ANTES | DESPUÉS |
|---------|-------|---------|
| **Control** | ❌ Automático | ✅ Configurable |
| **Flexibilidad** | ❌ Sin opciones | ✅ Dos modos |
| **Interfaz** | Sin selector | Checkbox claro |
| **Predeterminado** | Gratis siempre | ☑ Gratis (mismo) |
| **Casos de uso** | Solo eventos gratis para afiliados | Ambos tipos de eventos |

## Código HTML del Nuevo Campo

```html
<!-- Acceso gratis para afiliados -->
<div class="mt-4 border-t pt-4">
    <label class="flex items-start">
        <input type="checkbox" 
               name="acceso_gratis_afiliados" 
               value="1"
               checked 
               class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="ml-3">
            <span class="block text-gray-700 font-semibold">
                Acceso gratis para afiliados vigentes
            </span>
            <span class="block text-sm text-gray-600 mt-1">
                Si está marcado, los afiliados con membresía vigente 
                recibirán 1 boleto gratis. Si no está marcado, todos 
                los asistentes deberán pagar (incluyendo afiliados).
            </span>
        </span>
    </label>
</div>
```

## Estilos Aplicados

El campo utiliza **TailwindCSS** para mantener consistencia con el resto del sistema:

- `mt-4 border-t pt-4`: Separación visual del resto de la sección
- `flex items-start`: Layout flexible para checkbox + texto
- `rounded border-gray-300 text-blue-600`: Estilos del checkbox
- `focus:ring-blue-500`: Indicador visual al hacer foco
- `font-semibold`: Título en negrita
- `text-sm text-gray-600`: Texto explicativo más pequeño

## Accesibilidad

✅ **Label asociado**: El checkbox tiene un label que describe su función  
✅ **Texto descriptivo**: Explicación clara del comportamiento  
✅ **Estados visuales**: Checked/unchecked claramente visibles  
✅ **Focus ring**: Indicador visual cuando se navega con teclado  

## Compatibilidad de Navegadores

✅ Chrome/Edge (moderno)  
✅ Firefox  
✅ Safari  
✅ Mobile browsers  

El campo usa HTML5 estándar (`<input type="checkbox">`) compatible con todos los navegadores modernos.

## Impacto Visual - Resumen

- **Mínimo**: Solo se agrega 1 campo al formulario existente
- **Claro**: Texto explicativo evita confusión
- **Integrado**: Usa los mismos estilos del sistema
- **No intrusivo**: Separado con borde para distinguirlo
- **Predeterminado sensato**: Marcado por defecto (comportamiento original)

## Retroalimentación del Usuario

El sistema proporciona feedback inmediato:

1. **Al guardar**: Mensaje de confirmación "Evento creado/actualizado exitosamente"
2. **Al registrarse**: 
   - Afiliado con acceso gratis: "¡Inscripción exitosa! Como empresa afiliada, tu boleto es gratuito."
   - Afiliado sin acceso gratis: "Para completar tu inscripción, realiza el pago de $XXX MXN"
3. **En emails**: El correo refleja si el boleto es gratis o requiere pago

---

**Nota**: Esta guía visual complementa la documentación técnica en `GUIA_ACCESO_GRATIS_AFILIADOS.md` y `RESUMEN_ACCESO_GRATIS_AFILIADOS.md`.
