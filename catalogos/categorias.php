<?php
/**
 * Catálogo de Categorías
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
        'sector_id' => $_POST['sector_id'] ?? null,
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    try {
        if ($action === 'new') {
            $sql = "INSERT INTO categorias (nombre, descripcion, sector_id, activo) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$data['nombre'], $data['descripcion'], $data['sector_id'], $data['activo']]);
            $success = 'Categoría creada exitosamente';
        } else {
            $sql = "UPDATE categorias SET nombre = ?, descripcion = ?, sector_id = ?, activo = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$data['nombre'], $data['descripcion'], $data['sector_id'], $data['activo'], $id]);
            $success = 'Categoría actualizada exitosamente';
        }
        $action = 'list';
    } catch (Exception $e) {
        $error = 'Error al guardar la categoría: ' . $e->getMessage();
    }
}

// Obtener sectores para el formulario
$sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Obtener categoría para edición
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        $error = 'Categoría no encontrada';
        $action = 'list';
    }
}

// Listar categorías
if ($action === 'list') {
    $stmt = $db->query("SELECT c.*, s.nombre as sector_nombre, COUNT(e.id) as empresas_count 
                       FROM categorias c 
                       LEFT JOIN sectores s ON c.sector_id = s.id
                       LEFT JOIN empresas e ON c.id = e.categoria_id
                       GROUP BY c.id
                       ORDER BY c.nombre ASC");
    $categorias = $stmt->fetchAll();
}

include __DIR__ . '/../app/views/layouts/header.php';
?>

<?php if ($action === 'list'): ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Catálogo de Categorías</h1>
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nueva Categoría
        </a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sector</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($categorias as $cat): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800"><?php echo e($cat['nombre']); ?></div>
                        <?php if ($cat['descripcion']): ?>
                        <div class="text-sm text-gray-600"><?php echo e($cat['descripcion']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($cat['sector_nombre']): ?>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                            <?php echo e($cat['sector_nombre']); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400">Sin sector</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-semibold text-gray-700"><?php echo $cat['empresas_count']; ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-<?php echo $cat['activo'] ? 'green' : 'gray'; ?>-100 text-<?php echo $cat['activo'] ? 'green' : 'gray'; ?>-800 rounded text-sm">
                            <?php echo $cat['activo'] ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="text-blue-600 hover:underline">
                            <i class="fas fa-edit mr-1"></i>Editar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif (in_array($action, ['new', 'edit'])): ?>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">
            <?php echo $action === 'new' ? 'Nueva Categoría' : 'Editar Categoría'; ?>
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
                           value="<?php echo e($categoria['nombre'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Descripción</label>
                    <textarea name="descripcion" rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo e($categoria['descripcion'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Sector</label>
                    <select name="sector_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Sin sector específico</option>
                        <?php foreach ($sectores as $sector): ?>
                            <option value="<?php echo $sector['id']; ?>" 
                                    <?php echo ($categoria['sector_id'] ?? '') == $sector['id'] ? 'selected' : ''; ?>>
                                <?php echo e($sector['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" value="1"
                               <?php echo ($categoria['activo'] ?? 1) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Categoría activa</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=list" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <?php echo $action === 'new' ? 'Crear Categoría' : 'Guardar Cambios'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
