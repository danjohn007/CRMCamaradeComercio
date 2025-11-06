<?php
/**
 * Módulo "Mi Membresía" para usuarios externos
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Solo para usuarios ENTIDAD_COMERCIAL y EMPRESA_TRACTORA
if (!in_array($user['rol'], ['ENTIDAD_COMERCIAL', 'EMPRESA_TRACTORA'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Verificar que el usuario tiene empresa asociada
if (!$user['empresa_id']) {
    $error = 'No tiene una empresa asociada';
} else {
    // Obtener información de la empresa y membresía actual
    $stmt = $db->prepare("
        SELECT e.*, m.nombre as membresia_nombre, m.descripcion as membresia_descripcion,
               m.costo as membresia_costo, m.beneficios as membresia_beneficios,
               m.vigencia_meses, m.nivel_orden as membresia_nivel
        FROM empresas e
        LEFT JOIN membresias m ON e.membresia_id = m.id
        WHERE e.id = ?
    ");
    $stmt->execute([$user['empresa_id']]);
    $empresa = $stmt->fetch();
    
    // Obtener membresías superiores disponibles (o todas si no tiene membresía)
    $nivel_actual = $empresa['membresia_nivel'] ?? 0;
    
    // Si no tiene membresía actual, mostrar todas las membresías activas
    if ($nivel_actual == 0 || empty($empresa['membresia_id'])) {
        $stmt = $db->prepare("
            SELECT * FROM membresias 
            WHERE activo = 1 
            ORDER BY nivel_orden ASC
        ");
        $stmt->execute();
    } else {
        // Si ya tiene membresía, mostrar solo las superiores
        $stmt = $db->prepare("
            SELECT * FROM membresias 
            WHERE activo = 1 
            AND nivel_orden > ?
            ORDER BY nivel_orden ASC
        ");
        $stmt->execute([$nivel_actual]);
    }
    $membresias_superiores = $stmt->fetchAll();
}

// Obtener configuración de PayPal
$stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'paypal%'");
$config_paypal = [];
while ($row = $stmt->fetch()) {
    $config_paypal[$row['clave']] = $row['valor'];
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Mi Membresía</h1>

    <?php if (isset($error)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php else: ?>
        
        <!-- Membresía Actual -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Membresía Actual</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xl font-semibold text-blue-600 mb-2">
                        <?php echo e($empresa['membresia_nombre'] ?? 'Sin membresía'); ?>
                    </h3>
                    <p class="text-gray-600 mb-4">
                        <?php echo e($empresa['membresia_descripcion'] ?? ''); ?>
                    </p>
                    
                    <?php if ($empresa['membresia_beneficios']): ?>
                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-700 mb-2">Beneficios:</h4>
                            <div class="text-gray-600">
                                <?php echo nl2br(e($empresa['membresia_beneficios'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700 font-semibold">Costo Anual:</span>
                            <span class="text-2xl font-bold text-blue-600">
                                $<?php echo number_format($empresa['membresia_costo'] ?? 0, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <?php 
                        // Calculate days once and reuse the value
                        $dias = diasHastaVencimiento($empresa['fecha_renovacion']);
                        ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700 font-semibold">Fecha de Renovación:</span>
                            <span class="font-bold <?php 
                                if ($dias === null) {
                                    echo 'text-gray-400';
                                } else {
                                    echo $dias < 0 ? 'text-red-600' : ($dias <= 30 ? 'text-yellow-600' : 'text-green-600');
                                }
                            ?>">
                                <?php echo formatDate($empresa['fecha_renovacion']); ?>
                            </span>
                        </div>
                        <?php 
                        if ($dias !== null && $dias >= 0):
                        ?>
                            <p class="text-sm text-gray-600 mt-2">
                                <?php echo $dias == 0 ? 'Vence hoy' : "Faltan $dias días"; ?>
                            </p>
                        <?php elseif ($dias !== null && $dias < 0): ?>
                            <p class="text-sm text-red-600 mt-2">
                                Vencida hace <?php echo abs($dias); ?> días
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700 font-semibold">Estado:</span>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold <?php 
                                echo $empresa['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $empresa['activo'] ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Membresías Superiores Disponibles -->
        <?php if (count($membresias_superiores) > 0): ?>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                Actualiza tu Membresía
                <span class="text-sm font-normal text-gray-600">Accede a más beneficios</span>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($membresias_superiores as $membresia): ?>
                <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 text-white">
                        <h3 class="text-xl font-bold"><?php echo e($membresia['nombre']); ?></h3>
                        <p class="text-blue-100 text-sm mt-1">Nivel <?php echo $membresia['nivel_orden']; ?></p>
                    </div>
                    
                    <div class="p-6">
                        <div class="mb-4">
                            <div class="text-3xl font-bold text-gray-800">
                                $<?php echo number_format($membresia['costo'], 2); ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                por <?php echo $membresia['vigencia_meses']; ?> meses
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-4 h-20 overflow-hidden">
                            <?php echo e($membresia['descripcion']); ?>
                        </p>
                        
                        <?php if ($membresia['beneficios']): ?>
                        <div class="mb-4 text-sm">
                            <div class="font-semibold text-gray-700 mb-2">Beneficios:</div>
                            <div class="text-gray-600 h-24 overflow-y-auto">
                                <?php 
                                $beneficios = explode("\n", $membresia['beneficios']);
                                foreach (array_slice($beneficios, 0, 5) as $beneficio):
                                    if (trim($beneficio)):
                                ?>
                                    <div class="flex items-start mb-1">
                                        <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                        <span><?php echo e(trim($beneficio)); ?></span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <button onclick="abrirModalUpgrade(<?php echo $membresia['id']; ?>, '<?php echo addslashes($membresia['nombre']); ?>', <?php echo $membresia['costo']; ?>)"
                                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                            <i class="fas fa-arrow-circle-up mr-2"></i>Actualizar Ahora
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-700 font-semibold">
                        ¡Tienes la membresía de nivel más alto!
                    </p>
                    <p class="text-green-600 text-sm mt-1">
                        Disfruta de todos los beneficios premium de tu membresía.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Modal para Upgrade de Membresía -->
<div id="modalUpgrade" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-800">Actualizar Membresía</h3>
            <button onclick="cerrarModalUpgrade()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="formUpgrade">
            <input type="hidden" id="nueva_membresia_id" name="nueva_membresia_id">
            
            <div class="mb-6">
                <div class="bg-blue-50 p-4 rounded-lg mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-700">Membresía Actual:</span>
                        <span class="font-bold"><?php echo e($empresa['membresia_nombre'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Nueva Membresía:</span>
                        <span class="font-bold text-blue-600" id="nueva_membresia_nombre"></span>
                    </div>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-semibold">Monto a Pagar:</span>
                        <span class="text-3xl font-bold text-green-600" id="monto_upgrade">$0.00</span>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <p class="text-gray-600 mb-4">
                    El pago se procesará a través de PayPal. Una vez completado el pago, tu membresía se actualizará automáticamente.
                </p>
                
                <div id="paypal-button-container"></div>
            </div>
            
            <div id="upgradeMessage" class="hidden mb-4"></div>
        </form>
        
        <div class="flex justify-end">
            <button onclick="cerrarModalUpgrade()" 
                    class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

<!-- PayPal SDK -->
<?php if (!empty($config_paypal['paypal_client_id'])): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo $config_paypal['paypal_client_id']; ?>&currency=MXN"></script>
<?php endif; ?>

<script>
let paypalButtonRendered = false;

function abrirModalUpgrade(membresiaId, membresiaNombre, monto) {
    document.getElementById('nueva_membresia_id').value = membresiaId;
    document.getElementById('nueva_membresia_nombre').textContent = membresiaNombre;
    document.getElementById('monto_upgrade').textContent = '$' + parseFloat(monto).toFixed(2);
    
    // Verificar que PayPal esté configurado
    if (typeof paypal === 'undefined') {
        showMessage('error', 'PayPal no está configurado correctamente. Contacte al administrador.');
        return;
    }
    
    document.getElementById('modalUpgrade').classList.remove('hidden');
    
    // Renderizar botón de PayPal si aún no se ha hecho
    if (!paypalButtonRendered) {
        renderPayPalButton(monto, membresiaId);
        paypalButtonRendered = true;
    }
}

function cerrarModalUpgrade() {
    document.getElementById('modalUpgrade').classList.add('hidden');
}

function renderPayPalButton(monto, membresiaId) {
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: parseFloat(monto).toFixed(2),
                        currency_code: 'MXN'
                    },
                    description: 'Actualización de Membresía - ' + document.getElementById('nueva_membresia_nombre').textContent
                }]
            });
        },
        onApprove: async function(data, actions) {
            try {
                const order = await actions.order.capture();
                
                // Enviar a nuestro servidor para procesar
                const response = await fetch('<?php echo BASE_URL; ?>/api/procesar_upgrade_membresia.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        membresia_id: membresiaId,
                        paypal_order_id: order.id,
                        monto: monto
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('success', '¡Pago completado! Tu membresía ha sido actualizada.');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    throw new Error(result.error || 'Error al procesar la actualización');
                }
            } catch (error) {
                showMessage('error', error.message);
            }
        },
        onError: function(err) {
            showMessage('error', 'Error al procesar el pago con PayPal');
        }
    }).render('#paypal-button-container');
}

function showMessage(type, message) {
    const messageDiv = document.getElementById('upgradeMessage');
    messageDiv.className = type === 'success' 
        ? 'bg-green-50 border-l-4 border-green-500 p-4 mb-4'
        : 'bg-red-50 border-l-4 border-red-500 p-4 mb-4';
    messageDiv.innerHTML = `<p class="text-${type === 'success' ? 'green' : 'red'}-700">${message}</p>`;
    messageDiv.classList.remove('hidden');
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalUpgrade').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalUpgrade();
    }
});
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
