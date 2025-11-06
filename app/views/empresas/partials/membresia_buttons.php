<?php
/**
 * Vista Parcial: Botones de Actualización de Membresía
 * 
 * Descripción: Genera botones funcionales para actualizar membresía de empresa.
 *              Cada botón envía un formulario POST al endpoint update_membresia.php
 *              con validación CSRF.
 * 
 * Variables esperadas:
 *   - $membresia: Array con datos de la membresía (id, nombre, costo, descripcion, etc.)
 *   - $empresa: Array con datos de la empresa actual
 *   - $es_actual: Boolean indicando si es la membresía actual
 * 
 * Uso:
 *   include __DIR__ . '/app/views/empresas/partials/membresia_buttons.php';
 */

// Verificar que las variables necesarias estén definidas
if (!isset($membresia) || !isset($empresa)) {
    return;
}

// Determinar si es la membresía actual
$es_membresia_actual = isset($es_actual) ? $es_actual : 
    ($empresa['membresia_id'] !== null && intval($empresa['membresia_id']) === intval($membresia['id']));

// Generar token CSRF
$csrf_token = generateCsrfToken();
?>

<?php if ($es_membresia_actual): ?>
    <!-- Botón deshabilitado para membresía actual -->
    <button disabled
            class="w-full bg-gray-400 text-white px-4 py-3 rounded-lg cursor-not-allowed font-semibold">
        <i class="fas fa-check mr-2"></i>Membresía Actual
    </button>
<?php else: ?>
    <!-- Formulario para actualizar membresía -->
    <form method="POST" 
          action="<?php echo BASE_URL; ?>/public/actions/update_membresia.php" 
          class="w-full"
          onsubmit="return confirmarActualizacionMembresia('<?php echo addslashes($membresia['nombre']); ?>', <?php echo $membresia['costo']; ?>);">
        
        <!-- Token CSRF -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        
        <!-- ID de empresa -->
        <input type="hidden" name="empresa_id" value="<?php echo intval($empresa['id']); ?>">
        
        <!-- ID de membresía -->
        <input type="hidden" name="membresia_id" value="<?php echo intval($membresia['id']); ?>">
        
        <!-- Botón de envío -->
        <button type="submit"
                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
            <i class="fas fa-arrow-circle-up mr-2"></i>Actualizar Ahora
        </button>
    </form>
<?php endif; ?>

<?php
// Script de confirmación (solo se incluye una vez en la página)
if (!isset($GLOBALS['membresia_buttons_script_included'])):
    $GLOBALS['membresia_buttons_script_included'] = true;
?>
<script>
/**
 * Función para confirmar la actualización de membresía
 * @param {string} nombreMembresia - Nombre de la membresía a actualizar
 * @param {number} costo - Costo de la membresía
 * @returns {boolean} - true si el usuario confirma, false si cancela
 */
function confirmarActualizacionMembresia(nombreMembresia, costo) {
    const mensaje = `¿Está seguro que desea actualizar su membresía a "${nombreMembresia}"?\n\n` +
                    `Costo: $${parseFloat(costo).toFixed(2)}\n\n` +
                    `Esta acción actualizará su plan y fecha de renovación.`;
    
    return confirm(mensaje);
}
</script>
<?php endif; ?>
