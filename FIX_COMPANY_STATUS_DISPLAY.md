# Corrección del Estatus de Empresas en Vista de Detalles

## Problema Identificado

En el módulo de gestión de empresas, las empresas con fecha de renovación vencida se mostraban correctamente en la vista de lista (con el vencimiento en rojo), pero al abrir los detalles de la empresa, aparecían como "Activa" a pesar de que su fecha de vencimiento ya había pasado.

### Comportamiento Anterior

- **Vista de Lista**: ✅ Mostraba correctamente las empresas vencidas con el campo de vencimiento en rojo
- **Vista de Detalles**: ❌ Mostraba el estatus como "Activa" aunque la fecha de renovación hubiera expirado

## Solución Implementada

Se modificó la lógica de visualización del estatus en la vista de detalles de la empresa para que considere tanto el campo `activo` como la fecha de renovación (`fecha_renovacion`).

### Cambios Realizados

**Archivo modificado**: `empresas.php` (líneas 955-966)

**Antes**:
```php
<span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $empresa['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $empresa['activo'] ? 'Activa' : 'Suspendida'; ?>
</span>
```

**Después**:
```php
<?php
// Check if company is truly active based on expiration date
$dias = diasHastaVencimiento($empresa['fecha_renovacion']);
$is_expired = ($dias !== null && $dias < 0);
$is_active = $empresa['activo'] && !$is_expired;
?>
<span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $is_active ? 'Activa' : 'Inactiva'; ?>
</span>
```

### Lógica de la Corrección

1. **Cálculo de días hasta vencimiento**: Se utiliza la función existente `diasHastaVencimiento()` para determinar si la fecha de renovación ha pasado
2. **Verificación de expiración**: Se considera que una empresa está vencida si `$dias < 0` (fecha de renovación en el pasado)
3. **Estado activo real**: Una empresa se considera verdaderamente activa solo si:
   - El campo `activo` en la base de datos es `1` (verdadero), **Y**
   - La fecha de renovación NO ha expirado (`$dias >= 0` o `$dias === null`)
4. **Texto del estatus**: Se cambió "Suspendida" por "Inactiva" para mayor consistencia con la terminología del sistema

## Comportamiento Actual

Después de la corrección:

- **Vista de Lista**: ✅ Muestra empresas vencidas con fecha en rojo
- **Vista de Detalles**: ✅ Muestra estatus como "Inactiva" si la fecha de renovación ha pasado

### Casos de Uso

| Campo `activo` | Fecha Renovación | Estatus Mostrado |
|----------------|------------------|------------------|
| 1 (Activo)     | Vigente (futuro) | **Activa** (verde) |
| 1 (Activo)     | Vencida (pasado) | **Inactiva** (rojo) |
| 1 (Activo)     | Sin fecha        | **Activa** (verde) |
| 0 (Inactivo)   | Cualquiera       | **Inactiva** (rojo) |

## Archivos Modificados

- `empresas.php`: Actualización de la lógica de visualización del estatus en la vista de detalles

## Archivos Relacionados (Sin Cambios)

- `app/helpers/functions.php`: Contiene la función `diasHastaVencimiento()` utilizada
- `empresa_detalle.php`: Vista pública de detalles (no requiere cambios, ya que filtra por `activo = 1`)

## Pruebas Recomendadas

Para verificar la corrección:

1. Acceder al módulo de gestión de empresas (`empresas.php`)
2. Identificar una empresa con fecha de renovación vencida (mostrada en rojo en la lista)
3. Hacer clic en el icono "Ver detalles" (ojo verde)
4. Verificar que el estatus ahora muestre "Inactiva" en lugar de "Activa"

## Notas Técnicas

- **Sin cambios en la base de datos**: Esta corrección solo afecta la lógica de visualización, no modifica el esquema de la base de datos
- **Compatibilidad**: Utiliza funciones existentes del sistema, por lo que no introduce nuevas dependencias
- **Consistencia**: La lógica ahora es consistente entre la vista de lista y la vista de detalles
- **Seguridad**: No se detectaron vulnerabilidades de seguridad en el código modificado

## Fecha de Implementación

13 de noviembre de 2025

## Commit

Hash: 86f9c12
Mensaje: "Fix: Show correct status in company details based on expiration date"
