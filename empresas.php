<?php
/**
 * Módulo de gestión de empresas afiliadas
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requirePermission('CAPTURISTA');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Procesar formulario de nueva empresa o edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new', 'edit'])) {
    $data = [
        'razon_social' => sanitize($_POST['razon_social'] ?? ''),
        'rfc' => strtoupper(sanitize($_POST['rfc'] ?? '')),
        'email' => sanitize($_POST['email'] ?? ''),
        'telefono' => sanitize($_POST['telefono'] ?? ''),
        'whatsapp' => sanitize($_POST['whatsapp'] ?? ''),
        'representante' => sanitize($_POST['representante'] ?? ''),
        'direccion_comercial' => sanitize($_POST['direccion_comercial'] ?? ''),
        'direccion_fiscal' => sanitize($_POST['direccion_fiscal'] ?? ''),
        'colonia' => sanitize($_POST['colonia'] ?? ''),
        'ciudad' => sanitize($_POST['ciudad'] ?? ''),
        'codigo_postal' => sanitize($_POST['codigo_postal'] ?? ''),
        'estado' => sanitize($_POST['estado'] ?? 'Querétaro'),
        'sector_id' => $_POST['sector_id'] ?? null,
        'categoria_id' => $_POST['categoria_id'] ?? null,
        'membresia_id' => $_POST['membresia_id'] ?? null,
        'vendedor_id' => $_POST['vendedor_id'] ?? null,
        'tipo_afiliacion' => sanitize($_POST['tipo_afiliacion'] ?? ''),
        'fecha_renovacion' => $_POST['fecha_renovacion'] ?? null,
        'es_nueva' => isset($_POST['es_nueva']) ? 1 : 0,
        'es_actualizacion' => isset($_POST['es_actualizacion']) ? 1 : 0,
    ];

    try {
        if ($action === 'new') {
            // Obtener siguiente número de registro
            $stmt = $db->query("SELECT MAX(no_registro) as max_reg FROM empresas");
            $max = $stmt->fetch();
            $data['no_registro'] = ($max['max_reg'] ?? 0) + 1;
            $data['no_mes'] = (int)date('m');
            $data['fecha_recibo'] = date('Y-m-d');
            
            $sql = "INSERT INTO empresas (no_registro, no_mes, fecha_recibo, razon_social, rfc, email, telefono, whatsapp, 
                    representante, direccion_comercial, direccion_fiscal, colonia, ciudad, codigo_postal, estado, 
                    sector_id, categoria_id, membresia_id, vendedor_id, tipo_afiliacion, fecha_renovacion, 
                    es_nueva, es_actualizacion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['no_registro'], $data['no_mes'], $data['fecha_recibo'],
                $data['razon_social'], $data['rfc'], $data['email'], $data['telefono'], $data['whatsapp'],
                $data['representante'], $data['direccion_comercial'], $data['direccion_fiscal'],
                $data['colonia'], $data['ciudad'], $data['codigo_postal'], $data['estado'],
                $data['sector_id'], $data['categoria_id'], $data['membresia_id'], $data['vendedor_id'],
                $data['tipo_afiliacion'], $data['fecha_renovacion'], $data['es_nueva'], $data['es_actualizacion']
            ]);
            
            $empresa_id = $db->lastInsertId();
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'CREATE_EMPRESA', 'empresas', ?)");
            $stmt->execute([$user['id'], $empresa_id]);
            
            $success = 'Empresa registrada exitosamente';
            $action = 'list';
        } else {
            // Editar empresa existente
            $sql = "UPDATE empresas SET razon_social = ?, rfc = ?, email = ?, telefono = ?, whatsapp = ?, 
                    representante = ?, direccion_comercial = ?, direccion_fiscal = ?, colonia = ?, ciudad = ?, 
                    codigo_postal = ?, estado = ?, sector_id = ?, categoria_id = ?, membresia_id = ?, 
                    vendedor_id = ?, tipo_afiliacion = ?, fecha_renovacion = ?, es_nueva = ?, es_actualizacion = ? 
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['razon_social'], $data['rfc'], $data['email'], $data['telefono'], $data['whatsapp'],
                $data['representante'], $data['direccion_comercial'], $data['direccion_fiscal'],
                $data['colonia'], $data['ciudad'], $data['codigo_postal'], $data['estado'],
                $data['sector_id'], $data['categoria_id'], $data['membresia_id'], $data['vendedor_id'],
                $data['tipo_afiliacion'], $data['fecha_renovacion'], $data['es_nueva'], $data['es_actualizacion'],
                $id
            ]);
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'UPDATE_EMPRESA', 'empresas', ?)");
            $stmt->execute([$user['id'], $id]);
            
            $success = 'Empresa actualizada exitosamente';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error al guardar la empresa: ' . $e->getMessage();
    }
}

// Obtener datos para formulario
if (in_array($action, ['new', 'edit'])) {
    // Obtener sectores, categorías, membresías y vendedores
    $sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
    $categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();
    $membresias = $db->query("SELECT * FROM membresias WHERE activo = 1 ORDER BY nombre")->fetchAll();
    $vendedores = $db->query("SELECT * FROM vendedores WHERE activo = 1 ORDER BY nombre")->fetchAll();
    
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$id]);
        $empresa = $stmt->fetch();
        
        if (!$empresa) {
            $error = 'Empresa no encontrada';
            $action = 'list';
        }
    }
}

// Listar empresas con filtros
if ($action === 'list') {
    $where = ["e.activo = 1"];
    $params = [];
    
    // Aplicar filtros
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(e.razon_social LIKE ? OR e.rfc LIKE ? OR e.email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($_GET['sector'])) {
        $where[] = "e.sector_id = ?";
        $params[] = $_GET['sector'];
    }
    
    if (!empty($_GET['categoria'])) {
        $where[] = "e.categoria_id = ?";
        $params[] = $_GET['categoria'];
    }
    
    if (!empty($_GET['membresia'])) {
        $where[] = "e.membresia_id = ?";
        $params[] = $_GET['membresia'];
    }
    
    if (isset($_GET['filter'])) {
        if ($_GET['filter'] === 'vencimiento') {
            $where[] = "e.fecha_renovacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($_GET['filter'] === 'vencidas') {
            $where[] = "e.fecha_renovacion < CURDATE()";
        }
    }
    
    $whereSql = implode(' AND ', $where);
    
    $sql = "SELECT e.*, s.nombre as sector_nombre, c.nombre as categoria_nombre, 
            m.nombre as membresia_nombre, v.nombre as vendedor_nombre
            FROM empresas e
            LEFT JOIN sectores s ON e.sector_id = s.id
            LEFT JOIN categorias c ON e.categoria_id = c.id
            LEFT JOIN membresias m ON e.membresia_id = m.id
            LEFT JOIN vendedores v ON e.vendedor_id = v.id
            WHERE $whereSql
            ORDER BY e.razon_social ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $empresas = $stmt->fetchAll();
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Listado de empresas -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Gestión de Empresas</h1>
        <?php if (hasPermission('CAPTURISTA')): ?>
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nueva Empresa
        </a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" name="search" placeholder="Buscar..." 
                   value="<?php echo e($_GET['search'] ?? ''); ?>"
                   class="px-4 py-2 border rounded-lg">
            <select name="sector" class="px-4 py-2 border rounded-lg">
                <option value="">Todos los sectores</option>
                <?php
                $sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
                foreach ($sectores as $sector):
                ?>
                    <option value="<?php echo $sector['id']; ?>" <?php echo ($_GET['sector'] ?? '') == $sector['id'] ? 'selected' : ''; ?>>
                        <?php echo e($sector['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="membresia" class="px-4 py-2 border rounded-lg">
                <option value="">Todas las membresías</option>
                <?php
                $membresias = $db->query("SELECT * FROM membresias WHERE activo = 1 ORDER BY nombre")->fetchAll();
                foreach ($membresias as $membresia):
                ?>
                    <option value="<?php echo $membresia['id']; ?>" <?php echo ($_GET['membresia'] ?? '') == $membresia['id'] ? 'selected' : ''; ?>>
                        <?php echo e($membresia['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-search mr-2"></i>Buscar
            </button>
        </form>
    </div>

    <!-- Tabla de empresas -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RFC</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sector</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Membresía</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($empresas as $empresa): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800"><?php echo e($empresa['razon_social']); ?></div>
                        <div class="text-sm text-gray-600"><?php echo e($empresa['email']); ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm"><?php echo e($empresa['rfc']); ?></td>
                    <td class="px-6 py-4 text-sm"><?php echo e($empresa['sector_nombre']); ?></td>
                    <td class="px-6 py-4 text-sm"><?php echo e($empresa['membresia_nombre']); ?></td>
                    <td class="px-6 py-4 text-sm">
                        <?php 
                        $dias = diasHastaVencimiento($empresa['fecha_renovacion']);
                        $color = $dias < 0 ? 'red' : ($dias <= 30 ? 'yellow' : 'green');
                        ?>
                        <span class="px-2 py-1 text-xs rounded bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800">
                            <?php echo formatDate($empresa['fecha_renovacion']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <a href="?action=edit&id=<?php echo $empresa['id']; ?>" class="text-blue-600 hover:underline mr-3">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <a href="?action=view&id=<?php echo $empresa['id']; ?>" class="text-green-600 hover:underline">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif (in_array($action, ['new', 'edit'])): ?>
<!-- Formulario de empresa -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">
            <?php echo $action === 'new' ? 'Nueva Empresa' : 'Editar Empresa'; ?>
        </h1>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-lg shadow-md p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Razón Social -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Razón Social *</label>
                    <input type="text" name="razon_social" required
                           value="<?php echo e($empresa['razon_social'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- RFC -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">RFC *</label>
                    <input type="text" name="rfc" required maxlength="13"
                           value="<?php echo e($empresa['rfc'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Email *</label>
                    <input type="email" name="email" required
                           value="<?php echo e($empresa['email'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Teléfono</label>
                    <input type="text" name="telefono"
                           value="<?php echo e($empresa['telefono'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- WhatsApp -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">WhatsApp</label>
                    <input type="text" name="whatsapp"
                           value="<?php echo e($empresa['whatsapp'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Representante -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Representante Legal</label>
                    <input type="text" name="representante"
                           value="<?php echo e($empresa['representante'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Sector -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Sector</label>
                    <select name="sector_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($sectores as $sector): ?>
                            <option value="<?php echo $sector['id']; ?>" 
                                    <?php echo ($empresa['sector_id'] ?? '') == $sector['id'] ? 'selected' : ''; ?>>
                                <?php echo e($sector['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Categoría -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Categoría</label>
                    <select name="categoria_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>"
                                    <?php echo ($empresa['categoria_id'] ?? '') == $categoria['id'] ? 'selected' : ''; ?>>
                                <?php echo e($categoria['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Membresía -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Membresía</label>
                    <select name="membresia_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($membresias as $membresia): ?>
                            <option value="<?php echo $membresia['id']; ?>"
                                    <?php echo ($empresa['membresia_id'] ?? '') == $membresia['id'] ? 'selected' : ''; ?>>
                                <?php echo e($membresia['nombre']); ?> - <?php echo formatMoney($membresia['costo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Vendedor -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Vendedor</label>
                    <select name="vendedor_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?php echo $vendedor['id']; ?>"
                                    <?php echo ($empresa['vendedor_id'] ?? '') == $vendedor['id'] ? 'selected' : ''; ?>>
                                <?php echo e($vendedor['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tipo de Afiliación -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Tipo de Afiliación</label>
                    <input type="text" name="tipo_afiliacion"
                           value="<?php echo e($empresa['tipo_afiliacion'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Fecha de Renovación -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Fecha de Renovación</label>
                    <input type="date" name="fecha_renovacion"
                           value="<?php echo e($empresa['fecha_renovacion'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Direcciones y ubicación -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Dirección Comercial</label>
                    <input type="text" name="direccion_comercial"
                           value="<?php echo e($empresa['direccion_comercial'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Dirección Fiscal</label>
                    <input type="text" name="direccion_fiscal"
                           value="<?php echo e($empresa['direccion_fiscal'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Colonia</label>
                    <input type="text" name="colonia"
                           value="<?php echo e($empresa['colonia'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Ciudad</label>
                    <input type="text" name="ciudad"
                           value="<?php echo e($empresa['ciudad'] ?? 'Santiago de Querétaro'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Código Postal</label>
                    <input type="text" name="codigo_postal"
                           value="<?php echo e($empresa['codigo_postal'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Estado</label>
                    <input type="text" name="estado"
                           value="<?php echo e($empresa['estado'] ?? 'Querétaro'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Checkboxes -->
                <div class="md:col-span-2 flex space-x-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="es_nueva" value="1"
                               <?php echo ($empresa['es_nueva'] ?? 0) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Nueva Afiliación</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="es_actualizacion" value="1"
                               <?php echo ($empresa['es_actualizacion'] ?? 0) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Actualización</span>
                    </label>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=list" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <?php echo $action === 'new' ? 'Registrar Empresa' : 'Guardar Cambios'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
