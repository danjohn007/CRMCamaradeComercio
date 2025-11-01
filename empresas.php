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

// Suspender empresa
if ($action === 'suspend' && $id) {
    try {
        $stmt = $db->prepare("UPDATE empresas SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'SUSPEND_EMPRESA', 'empresas', ?)");
        $stmt->execute([$user['id'], $id]);
        
        $success = 'Empresa suspendida exitosamente';
        $action = 'list';
    } catch (Exception $e) {
        $error = 'Error al suspender la empresa: ' . $e->getMessage();
    }
}

// Activar empresa
if ($action === 'activate' && $id) {
    try {
        $stmt = $db->prepare("UPDATE empresas SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'ACTIVATE_EMPRESA', 'empresas', ?)");
        $stmt->execute([$user['id'], $id]);
        
        $success = 'Empresa activada exitosamente';
        $action = 'suspendidas';
    } catch (Exception $e) {
        $error = 'Error al activar la empresa: ' . $e->getMessage();
    }
}

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
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'servicios_productos' => sanitize($_POST['servicios_productos'] ?? ''),
        'palabras_clave' => sanitize($_POST['palabras_clave'] ?? ''),
        'sitio_web' => sanitize($_POST['sitio_web'] ?? ''),
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
                    es_nueva, es_actualizacion, descripcion, servicios_productos, palabras_clave, sitio_web) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['no_registro'], $data['no_mes'], $data['fecha_recibo'],
                $data['razon_social'], $data['rfc'], $data['email'], $data['telefono'], $data['whatsapp'],
                $data['representante'], $data['direccion_comercial'], $data['direccion_fiscal'],
                $data['colonia'], $data['ciudad'], $data['codigo_postal'], $data['estado'],
                $data['sector_id'], $data['categoria_id'], $data['membresia_id'], $data['vendedor_id'],
                $data['tipo_afiliacion'], $data['fecha_renovacion'], $data['es_nueva'], $data['es_actualizacion'],
                $data['descripcion'], $data['servicios_productos'], $data['palabras_clave'], $data['sitio_web']
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
                    vendedor_id = ?, tipo_afiliacion = ?, fecha_renovacion = ?, es_nueva = ?, es_actualizacion = ?,
                    descripcion = ?, servicios_productos = ?, palabras_clave = ?, sitio_web = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['razon_social'], $data['rfc'], $data['email'], $data['telefono'], $data['whatsapp'],
                $data['representante'], $data['direccion_comercial'], $data['direccion_fiscal'],
                $data['colonia'], $data['ciudad'], $data['codigo_postal'], $data['estado'],
                $data['sector_id'], $data['categoria_id'], $data['membresia_id'], $data['vendedor_id'],
                $data['tipo_afiliacion'], $data['fecha_renovacion'], $data['es_nueva'], $data['es_actualizacion'],
                $data['descripcion'], $data['servicios_productos'], $data['palabras_clave'], $data['sitio_web'],
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
if ($action === 'list' || $action === 'suspendidas') {
    $where = [$action === 'suspendidas' ? "e.activo = 0" : "e.activo = 1"];
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

<?php if ($action === 'list' || $action === 'suspendidas'): ?>
<!-- Listado de empresas -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <?php echo $action === 'suspendidas' ? 'Empresas Suspendidas' : 'Gestión de Empresas'; ?>
        </h1>
        <div class="flex space-x-2">
            <?php if ($action === 'suspendidas'): ?>
                <a href="?action=list" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Volver a Activas
                </a>
            <?php else: ?>
                <a href="?action=suspendidas" class="bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-orange-700 transition">
                    <i class="fas fa-ban mr-2"></i>Ver Suspendidas
                </a>
                <?php if (hasPermission('CAPTURISTA')): ?>
                <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Nueva Empresa
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
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
                        <div class="flex items-center space-x-2">
                            <a href="?action=view&id=<?php echo $empresa['id']; ?>" 
                               class="text-green-600 hover:text-green-800" 
                               title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($action !== 'suspendidas'): ?>
                            <a href="?action=edit&id=<?php echo $empresa['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800" 
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="abrirModalPago(<?php echo $empresa['id']; ?>, '<?php echo addslashes($empresa['razon_social']); ?>')" 
                               class="text-purple-600 hover:text-purple-800"
                               title="Registrar pago">
                                <i class="fas fa-dollar-sign"></i>
                            </button>
                            <a href="?action=suspend&id=<?php echo $empresa['id']; ?>" 
                               class="text-orange-600 hover:text-orange-800"
                               title="Suspender empresa"
                               onclick="return confirm('¿Está seguro de suspender esta empresa?')">
                                <i class="fas fa-ban"></i>
                            </a>
                            <?php else: ?>
                            <a href="?action=activate&id=<?php echo $empresa['id']; ?>" 
                               class="text-green-600 hover:text-green-800"
                               title="Activar empresa"
                               onclick="return confirm('¿Está seguro de activar esta empresa?')">
                                <i class="fas fa-check-circle"></i>
                            </a>
                            <?php endif; ?>
                        </div>
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

                <!-- Sección de Información Adicional -->
                <div class="md:col-span-2 mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>Información Adicional
                    </h3>
                </div>

                <!-- Descripción -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Descripción de la Empresa</label>
                    <textarea name="descripcion" rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Breve descripción de la empresa y sus actividades..."><?php echo e($empresa['descripcion'] ?? ''); ?></textarea>
                </div>

                <!-- Servicios y Productos -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-box mr-2"></i>Servicios y Productos
                    </label>
                    <textarea name="servicios_productos" rows="4"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Liste los servicios y productos que ofrece la empresa..."><?php echo e($empresa['servicios_productos'] ?? ''); ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Separe cada servicio o producto con comas o saltos de línea</p>
                </div>

                <!-- Palabras Clave -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-tags mr-2"></i>Palabras Clave
                    </label>
                    <input type="text" name="palabras_clave"
                           value="<?php echo e($empresa['palabras_clave'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="comercio, servicios, tecnología, consultoría...">
                    <p class="text-sm text-gray-500 mt-1">Separe las palabras clave con comas para facilitar las búsquedas</p>
                </div>

                <!-- Sitio Web -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-globe mr-2"></i>Sitio Web
                    </label>
                    <input type="url" name="sitio_web"
                           value="<?php echo e($empresa['sitio_web'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="https://www.ejemplo.com">
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

<?php elseif ($action === 'view' && $id): ?>
<!-- Vista de detalles de empresa -->
<?php
$stmt = $db->prepare("SELECT e.*, s.nombre as sector_nombre, c.nombre as categoria_nombre, 
        m.nombre as membresia_nombre, v.nombre as vendedor_nombre
        FROM empresas e
        LEFT JOIN sectores s ON e.sector_id = s.id
        LEFT JOIN categorias c ON e.categoria_id = c.id
        LEFT JOIN membresias m ON e.membresia_id = m.id
        LEFT JOIN vendedores v ON e.vendedor_id = v.id
        WHERE e.id = ?");
$stmt->execute([$id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    redirect('/empresas.php?error=not_found');
}
?>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Detalles de la Empresa</h1>
            <div class="flex gap-2">
                <a href="?action=edit&id=<?php echo $empresa['id']; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-edit mr-2"></i>Editar
                </a>
                <a href="?action=list" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Header con información principal -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
                <h2 class="text-2xl font-bold mb-2"><?php echo e($empresa['razon_social']); ?></h2>
                <p class="text-blue-100">RFC: <?php echo e($empresa['rfc']); ?></p>
            </div>

            <!-- Información en tabs -->
            <div class="p-6">
                <!-- Información General -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>Información General
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-semibold"><?php echo e($empresa['email'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Teléfono</p>
                            <p class="font-semibold"><?php echo e($empresa['telefono'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">WhatsApp</p>
                            <p class="font-semibold"><?php echo e($empresa['whatsapp'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Representante Legal</p>
                            <p class="font-semibold"><?php echo e($empresa['representante'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Sector</p>
                            <p class="font-semibold"><?php echo e($empresa['sector_nombre'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Categoría</p>
                            <p class="font-semibold"><?php echo e($empresa['categoria_nombre'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Membresía</p>
                            <p class="font-semibold"><?php echo e($empresa['membresia_nombre'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Estatus</p>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $empresa['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $empresa['activo'] ? 'Activa' : 'Suspendida'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                        <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>Ubicación
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-600">Dirección Comercial</p>
                            <p class="font-semibold"><?php echo e($empresa['direccion_comercial'] ?: 'No especificada'); ?></p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-600">Dirección Fiscal</p>
                            <p class="font-semibold"><?php echo e($empresa['direccion_fiscal'] ?: 'No especificada'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Colonia</p>
                            <p class="font-semibold"><?php echo e($empresa['colonia'] ?: 'No especificada'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Ciudad</p>
                            <p class="font-semibold"><?php echo e($empresa['ciudad'] ?: 'No especificada'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Código Postal</p>
                            <p class="font-semibold"><?php echo e($empresa['codigo_postal'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Estado</p>
                            <p class="font-semibold"><?php echo e($empresa['estado'] ?: 'No especificado'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Información de Afiliación -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                        <i class="fas fa-handshake mr-2 text-blue-600"></i>Información de Afiliación
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Fecha de Renovación</p>
                            <p class="font-semibold"><?php echo $empresa['fecha_renovacion'] ? formatDate($empresa['fecha_renovacion']) : 'No especificada'; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Vendedor</p>
                            <p class="font-semibold"><?php echo e($empresa['vendedor_nombre'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tipo de Afiliación</p>
                            <p class="font-semibold"><?php echo e($empresa['tipo_afiliacion'] ?: 'No especificado'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">No. de Registro</p>
                            <p class="font-semibold"><?php echo e($empresa['no_registro'] ?: 'No especificado'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Descripción y Servicios -->
                <?php if ($empresa['descripcion'] || $empresa['servicios_productos'] || $empresa['sitio_web']): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                        <i class="fas fa-file-alt mr-2 text-blue-600"></i>Información Adicional
                    </h3>
                    <?php if ($empresa['descripcion']): ?>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">Descripción</p>
                        <p class="text-gray-800"><?php echo nl2br(e($empresa['descripcion'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($empresa['servicios_productos']): ?>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">Servicios y Productos</p>
                        <p class="text-gray-800"><?php echo nl2br(e($empresa['servicios_productos'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($empresa['sitio_web']): ?>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">Sitio Web</p>
                        <a href="<?php echo e($empresa['sitio_web']); ?>" target="_blank" class="text-blue-600 hover:underline">
                            <?php echo e($empresa['sitio_web']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal para Registrar Pago -->
<div id="modalPago" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-800">Registrar Pago</h3>
            <button onclick="cerrarModalPago()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="formPago" enctype="multipart/form-data" onsubmit="return submitPago(event)">
            <input type="hidden" id="empresa_id" name="empresa_id">
            
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Empresa</label>
                <input type="text" id="empresa_nombre" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Concepto *</label>
                <input type="text" name="concepto" required 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                       placeholder="Ej: Pago de Membresía 2024">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Monto * ($)</label>
                    <input type="number" name="monto" step="0.01" min="0.01" required 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Método de Pago *</label>
                    <select name="metodo_pago" required 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="TARJETA">Tarjeta</option>
                        <option value="PAYPAL">PayPal</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Referencia</label>
                    <input type="text" name="referencia" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                           placeholder="Número de referencia o folio">
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Fecha de Pago *</label>
                    <input type="datetime-local" name="fecha_pago" required 
                           value="<?php echo date('Y-m-d\TH:i'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Evidencia de Pago</label>
                <input type="file" name="evidencia" accept=".jpg,.jpeg,.png,.pdf" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                <p class="text-sm text-gray-500 mt-1">Formatos permitidos: JPG, PNG, PDF (máx. 5MB)</p>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-semibold mb-2">Notas</label>
                <textarea name="notas" rows="3" 
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                          placeholder="Observaciones adicionales"></textarea>
            </div>
            
            <div id="pagoError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                <p class="text-red-700"></p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="cerrarModalPago()" 
                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                    Cancelar
                </button>
                <button type="submit" id="btnGuardarPago"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-2"></i>Registrar Pago
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalPago(empresaId, empresaNombre) {
    document.getElementById('empresa_id').value = empresaId;
    document.getElementById('empresa_nombre').value = empresaNombre;
    document.getElementById('formPago').reset();
    document.getElementById('empresa_id').value = empresaId;
    document.getElementById('empresa_nombre').value = empresaNombre;
    document.getElementById('pagoError').classList.add('hidden');
    document.getElementById('modalPago').classList.remove('hidden');
}

function cerrarModalPago() {
    document.getElementById('modalPago').classList.add('hidden');
    document.getElementById('formPago').reset();
}

async function submitPago(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const btnGuardar = document.getElementById('btnGuardarPago');
    const errorDiv = document.getElementById('pagoError');
    
    // Deshabilitar botón
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Guardando...';
    errorDiv.classList.add('hidden');
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/api/registrar_pago.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Mostrar mensaje de éxito
            alert('Pago registrado exitosamente');
            cerrarModalPago();
            // Recargar página para ver el cambio
            window.location.reload();
        } else {
            throw new Error(result.error || 'Error al registrar el pago');
        }
    } catch (error) {
        errorDiv.querySelector('p').textContent = error.message;
        errorDiv.classList.remove('hidden');
    } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save mr-2"></i>Registrar Pago';
    }
    
    return false;
}

// Cerrar modal al hacer clic fuera de él
document.getElementById('modalPago').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalPago();
    }
});
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
