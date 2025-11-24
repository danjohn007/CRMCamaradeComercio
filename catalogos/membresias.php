<?php
/**
 * Catálogo de Membresías
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requirePermission('DIRECCION');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new', 'edit'])) {
    $data = [
        'nombre' => sanitize($_POST['nombre'] ?? ''),
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'costo' => $_POST['costo'] ?? 0,
        'beneficios' => sanitize($_POST['beneficios'] ?? ''),
        'vigencia_meses' => $_POST['vigencia_meses'] ?? 12,
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    try {
        if ($action === 'new') {
            $sql = "INSERT INTO membresias (nombre, descripcion, costo, beneficios, vigencia_meses, activo) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['descripcion'], $data['costo'],
                $data['beneficios'], $data['vigencia_meses'], $data['activo']
            ]);
            $success = 'Membresía creada exitosamente';
        } else {
            $sql = "UPDATE membresias SET nombre = ?, descripcion = ?, costo = ?, beneficios = ?, 
                    vigencia_meses = ?, activo = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['descripcion'], $data['costo'],
                $data['beneficios'], $data['vigencia_meses'], $data['activo'], $id
            ]);
            $success = 'Membresía actualizada exitosamente';
        }
        $action = 'list';
    } catch (Exception $e) {
        $error = 'Error al guardar la membresía: ' . $e->getMessage();
    }
}

// Obtener membresía para edición
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM membresias WHERE id = ?");
    $stmt->execute([$id]);
    $membresia = $stmt->fetch();
    
    if (!$membresia) {
        $error = 'Membresía no encontrada';
        $action = 'list';
    }
}

// Listar membresías
if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM membresias ORDER BY nombre ASC");
    $membresias = $stmt->fetchAll();
}

include __DIR__ . '/../app/views/layouts/header.php';
?>

<?php if ($action === 'list'): ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Catálogo de Membresías</h1>
        <div class="flex gap-3">
            <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>Nueva Membresía
            </a>
            <button onclick="mostrarEnlacePublico()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-link mr-2"></i>Enlace Público
            </button>
        </div>
    </div>
    
    <!-- Modal de Enlace Público -->
    <div id="modalEnlacePublico" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-link text-green-600 mr-2"></i>
                    Enlace de Afiliación Pública
                </h3>
                <button onclick="cerrarEnlacePublico()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <p class="text-gray-600 mb-4">
                Comparte este enlace para que nuevas empresas puedan registrarse y pagar su membresía directamente, 
                sin necesidad de iniciar sesión.
            </p>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <div class="flex items-center gap-3">
                    <input type="text" 
                           id="enlacePublicoInput" 
                           readonly
                           value="<?php echo BASE_URL; ?>/afiliacion_publica.php"
                           class="flex-1 px-4 py-2 border rounded-lg bg-white">
                    <button onclick="copiarEnlace()" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 whitespace-nowrap">
                        <i class="fas fa-copy mr-2"></i>Copiar
                    </button>
                </div>
            </div>
            
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-800">Enlaces por Membresía:</h4>
                <?php foreach ($membresias as $memb): ?>
                    <?php if ($memb['activo']): ?>
                    <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800"><?php echo e($memb['nombre']); ?></p>
                            <input type="text" 
                                   readonly
                                   value="<?php echo BASE_URL; ?>/afiliacion_publica.php?membresia=<?php echo $memb['id']; ?>"
                                   class="w-full px-3 py-1 text-sm border rounded bg-white mt-1">
                        </div>
                        <button onclick="copiarEnlaceEspecifico(<?php echo $memb['id']; ?>)" 
                                class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-6 pt-4 border-t">
                <p class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    El enlace general muestra todas las membresías activas. Los enlaces específicos llevan directo al formulario de esa membresía.
                </p>
            </div>
        </div>
    </div>
    
    <script>
    function mostrarEnlacePublico() {
        document.getElementById('modalEnlacePublico').classList.remove('hidden');
    }
    
    function cerrarEnlacePublico() {
        document.getElementById('modalEnlacePublico').classList.add('hidden');
    }
    
    function copiarEnlace() {
        const input = document.getElementById('enlacePublicoInput');
        input.select();
        document.execCommand('copy');
        
        // Mostrar feedback
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>¡Copiado!';
        btn.classList.add('bg-green-600');
        btn.classList.remove('bg-blue-600');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('bg-green-600');
            btn.classList.add('bg-blue-600');
        }, 2000);
    }
    
    function copiarEnlaceEspecifico(membresiaId) {
        const enlace = '<?php echo BASE_URL; ?>/afiliacion_publica.php?membresia=' + membresiaId;
        
        // Crear input temporal para copiar
        const input = document.createElement('input');
        input.value = enlace;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        
        // Mostrar feedback
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.add('bg-green-600');
        btn.classList.remove('bg-blue-600');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('bg-green-600');
            btn.classList.add('bg-blue-600');
        }, 2000);
    }
    
    // Cerrar modal al hacer clic fuera
    document.getElementById('modalEnlacePublico')?.addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarEnlacePublico();
        }
    });
    </script>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($membresias as $memb): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden <?php echo !$memb['activo'] ? 'opacity-60' : ''; ?>">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6">
                <h3 class="text-2xl font-bold mb-2"><?php echo e($memb['nombre']); ?></h3>
                <div class="text-3xl font-bold">
                    <?php echo formatMoney($memb['costo']); ?>
                </div>
                <p class="text-sm text-blue-100 mt-2">
                    Vigencia: <?php echo $memb['vigencia_meses']; ?> meses
                </p>
            </div>
            
            <div class="p-6">
                <p class="text-gray-600 mb-4"><?php echo e($memb['descripcion']); ?></p>
                
                <?php if ($memb['beneficios']): ?>
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-800 mb-2">Beneficios:</h4>
                    <p class="text-sm text-gray-600"><?php echo nl2br(e($memb['beneficios'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="flex items-center gap-2 mb-4">
                    <span class="px-3 py-1 bg-<?php echo $memb['activo'] ? 'green' : 'gray'; ?>-100 text-<?php echo $memb['activo'] ? 'green' : 'gray'; ?>-800 rounded text-sm">
                        <?php echo $memb['activo'] ? 'Activa' : 'Inactiva'; ?>
                    </span>
                </div>
                
                <div class="flex gap-2">
                    <a href="?action=edit&id=<?php echo $memb['id']; ?>" 
                       class="flex-1 text-center px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-edit mr-1"></i>Editar
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif (in_array($action, ['new', 'edit'])): ?>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">
            <?php echo $action === 'new' ? 'Nueva Membresía' : 'Editar Membresía'; ?>
        </h1>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-lg shadow-md p-8">
            <div class="space-y-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Nombre *</label>
                    <input type="text" name="nombre" required
                           value="<?php echo e($membresia['nombre'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="ej: Básica, Plata, Oro">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Descripción</label>
                    <textarea name="descripcion" rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Descripción breve de la membresía"><?php echo e($membresia['descripcion'] ?? ''); ?></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Costo *</label>
                        <input type="number" name="costo" required step="0.01" min="0"
                               value="<?php echo e($membresia['costo'] ?? ''); ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="0.00">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Vigencia (meses) *</label>
                        <input type="number" name="vigencia_meses" required min="1"
                               value="<?php echo e($membresia['vigencia_meses'] ?? 12); ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Beneficios</label>
                    <textarea name="beneficios" rows="6"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Lista los beneficios de esta membresía..."><?php echo e($membresia['beneficios'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" value="1"
                               <?php echo ($membresia['activo'] ?? 1) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Membresía activa</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=list" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <?php echo $action === 'new' ? 'Crear Membresía' : 'Guardar Cambios'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
