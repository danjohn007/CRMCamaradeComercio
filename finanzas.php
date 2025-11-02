<?php
/**
 * Módulo de gestión financiera
 * Permite registrar ingresos y egresos, categorizarlos y generar reportes
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Verificar permisos - solo PRESIDENCIA, DIRECCION y CAPTURISTA
requirePermission('CAPTURISTA');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'dashboard';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Procesar formulario de nueva categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_categoria') {
    $data = [
        'nombre' => sanitize($_POST['nombre'] ?? ''),
        'tipo' => $_POST['tipo'] ?? 'INGRESO',
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'color' => sanitize($_POST['color'] ?? '#3B82F6')
    ];
    
    try {
        if (empty($_POST['categoria_id'])) {
            // Nueva categoría
            $stmt = $db->prepare("INSERT INTO finanzas_categorias (nombre, tipo, descripcion, color) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['nombre'], $data['tipo'], $data['descripcion'], $data['color']]);
            $success = 'Categoría creada exitosamente';
        } else {
            // Editar categoría
            $stmt = $db->prepare("UPDATE finanzas_categorias SET nombre = ?, tipo = ?, descripcion = ?, color = ? WHERE id = ?");
            $stmt->execute([$data['nombre'], $data['tipo'], $data['descripcion'], $data['color'], $_POST['categoria_id']]);
            $success = 'Categoría actualizada exitosamente';
        }
        
        // Registrar auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'MANAGE_FINANZAS_CATEGORIA', 'finanzas_categorias', ?)");
        $stmt->execute([$user['id'], $db->lastInsertId() ?: $_POST['categoria_id']]);
        
    } catch (Exception $e) {
        $error = 'Error al guardar la categoría: ' . $e->getMessage();
    }
}

// Desactivar categoría (soft delete)
if ($action === 'deactivate_categoria' && $id && hasPermission('DIRECCION')) {
    try {
        $stmt = $db->prepare("UPDATE finanzas_categorias SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Registrar auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'DEACTIVATE_FINANZAS_CATEGORIA', 'finanzas_categorias', ?)");
        $stmt->execute([$user['id'], $id]);
        
        $success = 'Categoría desactivada exitosamente';
        $action = 'categorias';
    } catch (Exception $e) {
        $error = 'Error al desactivar la categoría: ' . $e->getMessage();
    }
}

// Activar categoría
if ($action === 'activate_categoria' && $id && hasPermission('DIRECCION')) {
    try {
        $stmt = $db->prepare("UPDATE finanzas_categorias SET activo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Registrar auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'ACTIVATE_FINANZAS_CATEGORIA', 'finanzas_categorias', ?)");
        $stmt->execute([$user['id'], $id]);
        
        $success = 'Categoría activada exitosamente';
        $action = 'categorias_inactivas';
    } catch (Exception $e) {
        $error = 'Error al activar la categoría: ' . $e->getMessage();
    }
}

// Procesar formulario de nuevo movimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_movimiento') {
    $data = [
        'categoria_id' => $_POST['categoria_id'] ?? null,
        'tipo' => $_POST['tipo'] ?? 'INGRESO',
        'concepto' => sanitize($_POST['concepto'] ?? ''),
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'monto' => floatval($_POST['monto'] ?? 0),
        'fecha_movimiento' => $_POST['fecha_movimiento'] ?? date('Y-m-d'),
        'metodo_pago' => sanitize($_POST['metodo_pago'] ?? ''),
        'referencia' => sanitize($_POST['referencia'] ?? ''),
        'empresa_id' => !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null,
        'notas' => sanitize($_POST['notas'] ?? ''),
        'evidencia' => ''
    ];
    
    try {
        // Procesar archivo de evidencia (obligatorio para nuevos movimientos)
        if (empty($_POST['movimiento_id']) && isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
            // Validar tamaño (máx 5MB)
            if ($_FILES['evidencia']['size'] > 5 * 1024 * 1024) {
                throw new Exception('El archivo es demasiado grande. Tamaño máximo: 5MB');
            }
            
            $upload_dir = UPLOAD_PATH . '/finanzas/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'evidencia_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $upload_path)) {
                    $data['evidencia'] = '/public/uploads/finanzas/' . $new_filename;
                }
            } else {
                throw new Exception('Formato de archivo no permitido');
            }
        } elseif (empty($_POST['movimiento_id'])) {
            throw new Exception('La evidencia/comprobante es obligatoria');
        }
        
        if (empty($_POST['movimiento_id'])) {
            // Nuevo movimiento
            $stmt = $db->prepare("
                INSERT INTO finanzas_movimientos 
                (categoria_id, tipo, concepto, descripcion, monto, fecha_movimiento, metodo_pago, referencia, empresa_id, usuario_id, evidencia, notas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['categoria_id'], $data['tipo'], $data['concepto'], $data['descripcion'],
                $data['monto'], $data['fecha_movimiento'], $data['metodo_pago'], $data['referencia'],
                $data['empresa_id'], $user['id'], $data['evidencia'], $data['notas']
            ]);
            $success = 'Movimiento registrado exitosamente';
        } else {
            // Editar movimiento (evidencia no es obligatoria al editar)
            $stmt = $db->prepare("
                UPDATE finanzas_movimientos 
                SET categoria_id = ?, tipo = ?, concepto = ?, descripcion = ?, monto = ?, 
                    fecha_movimiento = ?, metodo_pago = ?, referencia = ?, empresa_id = ?, notas = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['categoria_id'], $data['tipo'], $data['concepto'], $data['descripcion'],
                $data['monto'], $data['fecha_movimiento'], $data['metodo_pago'], $data['referencia'],
                $data['empresa_id'], $data['notas'], $_POST['movimiento_id']
            ]);
            $success = 'Movimiento actualizado exitosamente';
        }
        
        // Registrar auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'MANAGE_FINANZAS_MOVIMIENTO', 'finanzas_movimientos', ?)");
        $stmt->execute([$user['id'], $db->lastInsertId() ?: $_POST['movimiento_id']]);
        
    } catch (Exception $e) {
        $error = 'Error al guardar el movimiento: ' . $e->getMessage();
    }
}

// Eliminar movimiento
if ($action === 'delete_movimiento' && $id && hasPermission('DIRECCION')) {
    try {
        $stmt = $db->prepare("DELETE FROM finanzas_movimientos WHERE id = ?");
        $stmt->execute([$id]);
        
        // Registrar auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'DELETE_FINANZAS_MOVIMIENTO', 'finanzas_movimientos', ?)");
        $stmt->execute([$user['id'], $id]);
        
        $success = 'Movimiento eliminado exitosamente';
        $action = 'movimientos';
    } catch (Exception $e) {
        $error = 'Error al eliminar el movimiento: ' . $e->getMessage();
    }
}

// Dashboard financiero
if ($action === 'dashboard') {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    
    try {
        // Total de ingresos
        $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM finanzas_movimientos WHERE tipo = 'INGRESO' AND fecha_movimiento BETWEEN ? AND ?");
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $totalIngresos = $stmt->fetch()['total'];
        
        // Total de egresos
        $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM finanzas_movimientos WHERE tipo = 'EGRESO' AND fecha_movimiento BETWEEN ? AND ?");
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $totalEgresos = $stmt->fetch()['total'];
        
        // Balance
        $balance = $totalIngresos - $totalEgresos;
        
        // Ingresos por categoría
        $stmt = $db->prepare("
            SELECT c.nombre, c.color, SUM(m.monto) as total, COUNT(m.id) as cantidad
            FROM finanzas_movimientos m
            JOIN finanzas_categorias c ON m.categoria_id = c.id
            WHERE m.tipo = 'INGRESO' AND m.fecha_movimiento BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY total DESC
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $ingresosPorCategoria = $stmt->fetchAll();
        
        // Egresos por categoría
        $stmt = $db->prepare("
            SELECT c.nombre, c.color, SUM(m.monto) as total, COUNT(m.id) as cantidad
            FROM finanzas_movimientos m
            JOIN finanzas_categorias c ON m.categoria_id = c.id
            WHERE m.tipo = 'EGRESO' AND m.fecha_movimiento BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY total DESC
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $egresosPorCategoria = $stmt->fetchAll();
        
        // Movimientos por mes (últimos 12 meses)
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(fecha_movimiento, '%Y-%m') as mes,
                tipo,
                SUM(monto) as total
            FROM finanzas_movimientos
            WHERE fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY mes, tipo
            ORDER BY mes ASC
        ");
        $movimientosPorMes = $stmt->fetchAll();
        
        // Últimos movimientos - evitar duplicados con GROUP BY
        $stmt = $db->prepare("
            SELECT m.*, c.nombre as categoria_nombre, c.color, u.nombre as usuario_nombre, e.razon_social
            FROM finanzas_movimientos m
            JOIN finanzas_categorias c ON m.categoria_id = c.id
            JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN empresas e ON m.empresa_id = e.id
            WHERE m.fecha_movimiento BETWEEN ? AND ?
            GROUP BY m.id
            ORDER BY m.fecha_movimiento DESC, m.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $ultimosMovimientos = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = 'Error al cargar el dashboard: ' . $e->getMessage();
    }
}

// Listar categorías activas
if ($action === 'categorias') {
    try {
        $tipo_filtro = $_GET['tipo'] ?? '';
        
        $where = ['activo = 1'];
        $params = [];
        
        if ($tipo_filtro) {
            $where[] = "tipo = ?";
            $params[] = $tipo_filtro;
        }
        
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        
        $stmt = $db->prepare("SELECT * FROM finanzas_categorias $whereSql ORDER BY tipo, nombre");
        $stmt->execute($params);
        $categorias = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = 'Error al cargar las categorías: ' . $e->getMessage();
    }
}

// Listar categorías inactivas
if ($action === 'categorias_inactivas') {
    try {
        $tipo_filtro = $_GET['tipo'] ?? '';
        
        $where = ['activo = 0'];
        $params = [];
        
        if ($tipo_filtro) {
            $where[] = "tipo = ?";
            $params[] = $tipo_filtro;
        }
        
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        
        $stmt = $db->prepare("SELECT * FROM finanzas_categorias $whereSql ORDER BY tipo, nombre");
        $stmt->execute($params);
        $categorias = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = 'Error al cargar las categorías inactivas: ' . $e->getMessage();
    }
}

// Listar movimientos
if ($action === 'movimientos') {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    $tipo_filtro = $_GET['tipo'] ?? '';
    $categoria_filtro = $_GET['categoria'] ?? '';
    
    try {
        $where = ["m.fecha_movimiento BETWEEN ? AND ?"];
        $params = [$fecha_inicio, $fecha_fin];
        
        if ($tipo_filtro) {
            $where[] = "m.tipo = ?";
            $params[] = $tipo_filtro;
        }
        
        if ($categoria_filtro) {
            $where[] = "m.categoria_id = ?";
            $params[] = $categoria_filtro;
        }
        
        $whereSql = implode(' AND ', $where);
        
        $stmt = $db->prepare("
            SELECT m.*, c.nombre as categoria_nombre, c.color, c.tipo as categoria_tipo,
                   u.nombre as usuario_nombre, e.razon_social
            FROM finanzas_movimientos m
            JOIN finanzas_categorias c ON m.categoria_id = c.id
            JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN empresas e ON m.empresa_id = e.id
            WHERE $whereSql
            ORDER BY m.fecha_movimiento DESC, m.created_at DESC
        ");
        $stmt->execute($params);
        $movimientos = $stmt->fetchAll();
        
        // Obtener categorías para filtro
        $categorias = $db->query("SELECT * FROM finanzas_categorias WHERE activo = 1 ORDER BY tipo, nombre")->fetchAll();
        
    } catch (Exception $e) {
        $error = 'Error al cargar los movimientos: ' . $e->getMessage();
    }
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<?php if ($action === 'dashboard'): ?>
<!-- Dashboard Financiero -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-chart-line mr-2"></i>Dashboard Financiero
        </h1>
        <div class="flex space-x-3">
            <a href="?action=categorias" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-tags mr-2"></i>Categorías Financieras
            </a>
            <button onclick="modalMovimiento()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-plus mr-2"></i>Nuevo Movimiento
            </button>
            <a href="?action=movimientos" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-list mr-2"></i>Ver Todos los Movimientos
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtro de fechas -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="action" value="dashboard">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" 
                       class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" 
                       class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter mr-2"></i>Filtrar
            </button>
            <a href="?action=dashboard" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 inline-block">
                <i class="fas fa-times mr-2"></i>Limpiar
            </a>
        </form>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Total Ingresos</p>
                    <p class="text-3xl font-bold mt-2"><?php echo formatMoney($totalIngresos ?? 0); ?></p>
                    <p class="text-green-100 text-xs mt-1">
                        <?php echo count($ingresosPorCategoria ?? []); ?> categorías
                    </p>
                </div>
                <i class="fas fa-arrow-up text-5xl text-green-200 opacity-50"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm">Total Egresos</p>
                    <p class="text-3xl font-bold mt-2"><?php echo formatMoney($totalEgresos ?? 0); ?></p>
                    <p class="text-red-100 text-xs mt-1">
                        <?php echo count($egresosPorCategoria ?? []); ?> categorías
                    </p>
                </div>
                <i class="fas fa-arrow-down text-5xl text-red-200 opacity-50"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Balance</p>
                    <p class="text-3xl font-bold mt-2"><?php echo formatMoney($balance ?? 0); ?></p>
                    <p class="text-blue-100 text-xs mt-1">
                        <?php echo $balance >= 0 ? 'Positivo' : 'Negativo'; ?>
                    </p>
                </div>
                <i class="fas fa-balance-scale text-5xl text-blue-200 opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Gráficas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Ingresos por categoría -->
        <?php if (!empty($ingresosPorCategoria)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Ingresos por Categoría</h2>
            <div style="position: relative; height: 300px; max-height: 300px;">
                <canvas id="chartIngresos"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Egresos por categoría -->
        <?php if (!empty($egresosPorCategoria)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Egresos por Categoría</h2>
            <div style="position: relative; height: 300px; max-height: 300px;">
                <canvas id="chartEgresos"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tendencia mensual -->
    <?php if (!empty($movimientosPorMes)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Tendencia de Ingresos vs Egresos (Últimos 12 Meses)</h2>
        <div style="position: relative; height: 350px; max-height: 350px;">
            <canvas id="chartTendencia"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimos movimientos -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Últimos Movimientos</h2>
            <a href="?action=movimientos" class="text-blue-600 hover:underline text-sm">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <?php if (empty($ultimosMovimientos)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3"></i>
                <p>No hay movimientos registrados en este período</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($ultimosMovimientos as $mov): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php echo formatDate($mov['fecha_movimiento'], 'd/m/Y'); ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold <?php echo $mov['tipo'] === 'INGRESO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $mov['tipo']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800 font-semibold">
                                <?php echo e($mov['concepto']); ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo e($mov['color']); ?>"></span>
                                    <span class="text-sm text-gray-600"><?php echo e($mov['categoria_nombre']); ?></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-bold <?php echo $mov['tipo'] === 'INGRESO' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo formatMoney($mov['monto']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts de gráficas -->
<script>
<?php if (!empty($ingresosPorCategoria)): ?>
new Chart(document.getElementById('chartIngresos'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($ingresosPorCategoria, 'nombre')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($ingresosPorCategoria, 'total')); ?>,
            backgroundColor: <?php echo json_encode(array_column($ingresosPorCategoria, 'color')); ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { padding: 10, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': $' + context.parsed.toLocaleString('es-MX', {minimumFractionDigits: 2});
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($egresosPorCategoria)): ?>
new Chart(document.getElementById('chartEgresos'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($egresosPorCategoria, 'nombre')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($egresosPorCategoria, 'total')); ?>,
            backgroundColor: <?php echo json_encode(array_column($egresosPorCategoria, 'color')); ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { padding: 10, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': $' + context.parsed.toLocaleString('es-MX', {minimumFractionDigits: 2});
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($movimientosPorMes)): ?>
<?php
// Organizar datos por mes
$meses = [];
$ingresos = [];
$egresos = [];

foreach ($movimientosPorMes as $item) {
    if (!in_array($item['mes'], $meses)) {
        $meses[] = $item['mes'];
    }
}

// Inicializar arrays
foreach ($meses as $mes) {
    $ingresos[$mes] = 0;
    $egresos[$mes] = 0;
}

// Llenar datos
foreach ($movimientosPorMes as $item) {
    if ($item['tipo'] === 'INGRESO') {
        $ingresos[$item['mes']] = $item['total'];
    } else {
        $egresos[$item['mes']] = $item['total'];
    }
}
?>
new Chart(document.getElementById('chartTendencia'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($m) { return date('M Y', strtotime($m . '-01')); }, $meses)); ?>,
        datasets: [
            {
                label: 'Ingresos',
                data: <?php echo json_encode(array_values($ingresos)); ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3
            },
            {
                label: 'Egresos',
                data: <?php echo json_encode(array_values($egresos)); ?>,
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': $' + context.parsed.y.toLocaleString('es-MX', {minimumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString('es-MX');
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php elseif ($action === 'categorias'): ?>
<!-- Gestión de Categorías -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-tags mr-2"></i>Categorías Financieras
        </h1>
        <div class="flex space-x-2">
            <a href="?action=categorias_inactivas" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-archive mr-2"></i>Ver Inactivas
            </a>
            <button onclick="modalCategoria()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>Nueva Categoría
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="flex gap-4">
            <input type="hidden" name="action" value="categorias">
            <select name="tipo" class="px-4 py-2 border rounded-lg">
                <option value="">Todos los tipos</option>
                <option value="INGRESO" <?php echo ($_GET['tipo'] ?? '') === 'INGRESO' ? 'selected' : ''; ?>>Ingresos</option>
                <option value="EGRESO" <?php echo ($_GET['tipo'] ?? '') === 'EGRESO' ? 'selected' : ''; ?>>Egresos</option>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Filtrar
            </button>
        </form>
    </div>

    <!-- Tabla de categorías -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($categorias ?? [] as $cat): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <span class="inline-block w-8 h-8 rounded-full" style="background-color: <?php echo e($cat['color']); ?>"></span>
                    </td>
                    <td class="px-6 py-4 font-semibold text-gray-800"><?php echo e($cat['nombre']); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full font-semibold <?php echo $cat['tipo'] === 'INGRESO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $cat['tipo']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo e($cat['descripcion'] ?: '-'); ?></td>
                    <td class="px-6 py-4 text-right">
                        <button onclick='editarCategoria(<?php echo json_encode($cat); ?>)' 
                                class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (hasPermission('DIRECCION')): ?>
                        <a href="?action=deactivate_categoria&id=<?php echo $cat['id']; ?>" 
                           onclick="return confirm('¿Está seguro de desactivar esta categoría?')"
                           class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de categoría -->
<div id="modalCategoria" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800" id="modalTitle">Nueva Categoría</h3>
            <button onclick="cerrarModalCategoria()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_categoria">
            <input type="hidden" name="categoria_id" id="categoria_id">
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Nombre *</label>
                <input type="text" name="nombre" id="categoria_nombre" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Tipo *</label>
                <select name="tipo" id="categoria_tipo" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="INGRESO">Ingreso</option>
                    <option value="EGRESO">Egreso</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Color</label>
                <input type="color" name="color" id="categoria_color" value="#3B82F6"
                       class="w-full h-10 px-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Descripción</label>
                <textarea name="descripcion" id="categoria_descripcion" rows="3"
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="cerrarModalCategoria()" 
                        class="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300">
                    Cancelar
                </button>
                <button type="submit" 
                        class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function modalCategoria() {
    document.getElementById('modalTitle').textContent = 'Nueva Categoría';
    document.getElementById('categoria_id').value = '';
    document.getElementById('categoria_nombre').value = '';
    document.getElementById('categoria_tipo').value = 'INGRESO';
    document.getElementById('categoria_color').value = '#3B82F6';
    document.getElementById('categoria_descripcion').value = '';
    document.getElementById('modalCategoria').classList.remove('hidden');
}

function editarCategoria(cat) {
    document.getElementById('modalTitle').textContent = 'Editar Categoría';
    document.getElementById('categoria_id').value = cat.id;
    document.getElementById('categoria_nombre').value = cat.nombre;
    document.getElementById('categoria_tipo').value = cat.tipo;
    document.getElementById('categoria_color').value = cat.color;
    document.getElementById('categoria_descripcion').value = cat.descripcion || '';
    document.getElementById('modalCategoria').classList.remove('hidden');
}

function cerrarModalCategoria() {
    document.getElementById('modalCategoria').classList.add('hidden');
}
</script>

<?php elseif ($action === 'categorias_inactivas'): ?>
<!-- Gestión de Categorías Inactivas -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-archive mr-2"></i>Categorías Inactivas
        </h1>
        <a href="?action=categorias" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Volver a Activas
        </a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="flex gap-4">
            <input type="hidden" name="action" value="categorias_inactivas">
            <select name="tipo" class="px-4 py-2 border rounded-lg">
                <option value="">Todos los tipos</option>
                <option value="INGRESO" <?php echo ($_GET['tipo'] ?? '') === 'INGRESO' ? 'selected' : ''; ?>>Ingresos</option>
                <option value="EGRESO" <?php echo ($_GET['tipo'] ?? '') === 'EGRESO' ? 'selected' : ''; ?>>Egresos</option>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Filtrar
            </button>
        </form>
    </div>

    <!-- Tabla de categorías inactivas -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($categorias)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-inbox text-5xl mb-4"></i>
                <p class="text-lg">No hay categorías inactivas</p>
            </div>
        <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($categorias as $cat): ?>
                <tr class="hover:bg-gray-50 opacity-75">
                    <td class="px-6 py-4">
                        <span class="inline-block w-8 h-8 rounded-full" style="background-color: <?php echo e($cat['color']); ?>"></span>
                    </td>
                    <td class="px-6 py-4 font-semibold text-gray-800"><?php echo e($cat['nombre']); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full font-semibold <?php echo $cat['tipo'] === 'INGRESO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $cat['tipo']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo e($cat['descripcion'] ?: '-'); ?></td>
                    <td class="px-6 py-4 text-right">
                        <?php if (hasPermission('DIRECCION')): ?>
                        <a href="?action=activate_categoria&id=<?php echo $cat['id']; ?>" 
                           onclick="return confirm('¿Está seguro de activar esta categoría?')"
                           class="text-green-600 hover:text-green-800">
                            <i class="fas fa-check-circle"></i> Activar
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'movimientos'): ?>
<!-- Listado de Movimientos -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-exchange-alt mr-2"></i>Movimientos Financieros
        </h1>
        <button onclick="modalMovimiento()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nuevo Movimiento
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="hidden" name="action" value="movimientos">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select name="tipo" class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Todos</option>
                    <option value="INGRESO" <?php echo $tipo_filtro === 'INGRESO' ? 'selected' : ''; ?>>Ingresos</option>
                    <option value="EGRESO" <?php echo $tipo_filtro === 'EGRESO' ? 'selected' : ''; ?>>Egresos</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select name="categoria" class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Todas</option>
                    <?php foreach ($categorias ?? [] as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filtro == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo e($cat['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Filtrar
                </button>
                <a href="?action=movimientos" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 text-center">
                    <i class="fas fa-times mr-2"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Resumen -->
    <?php
    $totalMovIngresos = 0;
    $totalMovEgresos = 0;
    foreach ($movimientos ?? [] as $mov) {
        if ($mov['tipo'] === 'INGRESO') {
            $totalMovIngresos += $mov['monto'];
        } else {
            $totalMovEgresos += $mov['monto'];
        }
    }
    $balanceMov = $totalMovIngresos - $totalMovEgresos;
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <p class="text-sm text-gray-600">Ingresos</p>
            <p class="text-2xl font-bold text-green-600"><?php echo formatMoney($totalMovIngresos); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
            <p class="text-sm text-gray-600">Egresos</p>
            <p class="text-2xl font-bold text-red-600"><?php echo formatMoney($totalMovEgresos); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <p class="text-sm text-gray-600">Balance</p>
            <p class="text-2xl font-bold text-blue-600"><?php echo formatMoney($balanceMov); ?></p>
        </div>
    </div>

    <!-- Tabla de movimientos -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($movimientos)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-inbox text-5xl mb-4"></i>
                <p class="text-lg">No hay movimientos registrados con los filtros seleccionados</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($movimientos as $mov): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                <?php echo formatDate($mov['fecha_movimiento'], 'd/m/Y'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold <?php echo $mov['tipo'] === 'INGRESO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $mov['tipo']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-800"><?php echo e($mov['concepto']); ?></div>
                                <?php if ($mov['descripcion']): ?>
                                    <div class="text-xs text-gray-500"><?php echo e(substr($mov['descripcion'], 0, 50)); ?><?php echo strlen($mov['descripcion']) > 50 ? '...' : ''; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo e($mov['color']); ?>"></span>
                                    <span class="text-sm text-gray-600"><?php echo e($mov['categoria_nombre']); ?></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo e($mov['razon_social'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold whitespace-nowrap <?php echo $mov['tipo'] === 'INGRESO' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo formatMoney($mov['monto']); ?>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button onclick='editarMovimiento(<?php echo json_encode($mov); ?>)' 
                                        class="text-blue-600 hover:text-blue-800 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (hasPermission('DIRECCION')): ?>
                                <a href="?action=delete_movimiento&id=<?php echo $mov['id']; ?>" 
                                   onclick="return confirm('¿Está seguro de eliminar este movimiento?')"
                                   class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de movimiento -->
<div id="modalMovimiento" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800" id="modalMovTitle">Nuevo Movimiento</h3>
            <button onclick="cerrarModalMovimiento()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="save_movimiento">
            <input type="hidden" name="movimiento_id" id="movimiento_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Tipo *</label>
                    <select name="tipo" id="movimiento_tipo" required onchange="cargarCategorias()"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="INGRESO">Ingreso</option>
                        <option value="EGRESO">Egreso</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Categoría *</label>
                    <select name="categoria_id" id="movimiento_categoria" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione...</option>
                        <?php foreach ($categorias ?? [] as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" data-tipo="<?php echo $cat['tipo']; ?>">
                                <?php echo e($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Concepto *</label>
                <input type="text" name="concepto" id="movimiento_concepto" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Descripción</label>
                <textarea name="descripcion" id="movimiento_descripcion" rows="2"
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Monto * ($)</label>
                    <input type="number" step="0.01" min="0" name="monto" id="movimiento_monto" required
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Fecha *</label>
                    <input type="date" name="fecha_movimiento" id="movimiento_fecha" required
                           value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Método de Pago</label>
                    <select name="metodo_pago" id="movimiento_metodo"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione...</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Referencia/Folio</label>
                    <input type="text" name="referencia" id="movimiento_referencia"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Empresa (opcional)</label>
                <input type="text" id="empresa_search" placeholder="Buscar empresa..."
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <input type="hidden" name="empresa_id" id="movimiento_empresa">
                <div id="empresa_results" class="mt-2"></div>
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Notas</label>
                <textarea name="notas" id="movimiento_notas" rows="2"
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">
                    Evidencia / Comprobante *
                    <span class="text-red-500 text-xs">(Obligatorio)</span>
                </label>
                <input type="file" name="evidencia" id="movimiento_evidencia" required
                       accept="image/*,application/pdf,.doc,.docx"
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-sm text-gray-500 mt-1">Formatos aceptados: JPG, PNG, PDF, DOC, DOCX (máx. 5MB)</p>
                <div id="evidencia_preview" class="mt-2"></div>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="cerrarModalMovimiento()" 
                        class="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300">
                    Cancelar
                </button>
                <button type="submit" 
                        class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function modalMovimiento() {
    document.getElementById('modalMovTitle').textContent = 'Nuevo Movimiento';
    document.getElementById('movimiento_id').value = '';
    document.getElementById('movimiento_tipo').value = 'INGRESO';
    document.getElementById('movimiento_categoria').value = '';
    document.getElementById('movimiento_concepto').value = '';
    document.getElementById('movimiento_descripcion').value = '';
    document.getElementById('movimiento_monto').value = '';
    document.getElementById('movimiento_fecha').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('movimiento_metodo').value = '';
    document.getElementById('movimiento_referencia').value = '';
    document.getElementById('movimiento_empresa').value = '';
    document.getElementById('empresa_search').value = '';
    document.getElementById('movimiento_notas').value = '';
    cargarCategorias();
    document.getElementById('modalMovimiento').classList.remove('hidden');
}

function editarMovimiento(mov) {
    document.getElementById('modalMovTitle').textContent = 'Editar Movimiento';
    document.getElementById('movimiento_id').value = mov.id;
    document.getElementById('movimiento_tipo').value = mov.tipo;
    document.getElementById('movimiento_categoria').value = mov.categoria_id;
    document.getElementById('movimiento_concepto').value = mov.concepto;
    document.getElementById('movimiento_descripcion').value = mov.descripcion || '';
    document.getElementById('movimiento_monto').value = mov.monto;
    document.getElementById('movimiento_fecha').value = mov.fecha_movimiento;
    document.getElementById('movimiento_metodo').value = mov.metodo_pago || '';
    document.getElementById('movimiento_referencia').value = mov.referencia || '';
    document.getElementById('movimiento_empresa').value = mov.empresa_id || '';
    document.getElementById('empresa_search').value = mov.razon_social || '';
    document.getElementById('movimiento_notas').value = mov.notas || '';
    cargarCategorias();
    document.getElementById('modalMovimiento').classList.remove('hidden');
}

function cerrarModalMovimiento() {
    document.getElementById('modalMovimiento').classList.add('hidden');
}

function cargarCategorias() {
    const tipo = document.getElementById('movimiento_tipo').value;
    const select = document.getElementById('movimiento_categoria');
    const options = select.querySelectorAll('option');
    
    options.forEach(opt => {
        if (opt.value === '') {
            opt.style.display = 'block';
        } else if (opt.dataset.tipo === tipo) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    });
    
    // Si la categoría seleccionada no es del tipo correcto, limpiar
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.tipo && selectedOption.dataset.tipo !== tipo) {
        select.value = '';
    }
}

// Búsqueda de empresas (simplificada)
document.getElementById('empresa_search')?.addEventListener('input', function(e) {
    const term = e.target.value;
    if (term.length < 2) {
        document.getElementById('empresa_results').innerHTML = '';
        return;
    }
    
    // Funcionalidad de búsqueda de empresas puede agregarse en versión futura si es necesario
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
