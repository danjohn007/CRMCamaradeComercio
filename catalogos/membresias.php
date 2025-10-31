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
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nueva Membresía
        </a>
    </div>

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
