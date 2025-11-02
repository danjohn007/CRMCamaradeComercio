<?php
/**
 * Módulo de Auditoría del Sistema
 * Registro de actividades y logs de auditoría
 * Solo accesible para nivel PRESIDENCIA
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Solo accesible para PRESIDENCIA
requirePermission('PRESIDENCIA');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$usuario_filtro = $_GET['usuario'] ?? '';
$accion_filtro = $_GET['accion'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

try {
    // Construir query con filtros
    $where = ["a.created_at BETWEEN ? AND ?"];
    $params = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];
    
    if ($usuario_filtro) {
        $where[] = "a.usuario_id = ?";
        $params[] = $usuario_filtro;
    }
    
    if ($accion_filtro) {
        $where[] = "a.accion = ?";
        $params[] = $accion_filtro;
    }
    
    if ($tabla_filtro) {
        $where[] = "a.tabla_afectada = ?";
        $params[] = $tabla_filtro;
    }
    
    $whereSql = implode(' AND ', $where);
    
    // Obtener total de registros
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM auditoria a WHERE $whereSql");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $perPage);
    
    // Obtener registros de auditoría
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $db->prepare("
        SELECT a.*, u.nombre as usuario_nombre, u.email as usuario_email
        FROM auditoria a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE $whereSql
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $registros = $stmt->fetchAll();
    
    // Obtener lista de usuarios para filtro
    $usuarios = $db->query("SELECT id, nombre, email FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
    
    // Obtener lista de acciones únicas
    $acciones = $db->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll();
    
    // Obtener lista de tablas únicas
    $tablas = $db->query("SELECT DISTINCT tabla_afectada FROM auditoria WHERE tabla_afectada IS NOT NULL ORDER BY tabla_afectada")->fetchAll();
    
    // Estadísticas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_acciones,
            COUNT(DISTINCT usuario_id) as usuarios_activos,
            COUNT(DISTINCT DATE(created_at)) as dias_con_actividad
        FROM auditoria 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $stats = $stmt->fetch();
    
    // Top 10 acciones más frecuentes
    $stmt = $db->prepare("
        SELECT accion, COUNT(*) as cantidad
        FROM auditoria
        WHERE created_at BETWEEN ? AND ?
        GROUP BY accion
        ORDER BY cantidad DESC
        LIMIT 10
    ");
    $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $topAcciones = $stmt->fetchAll();
    
    // Top 10 usuarios más activos
    $stmt = $db->prepare("
        SELECT u.nombre, u.email, COUNT(a.id) as cantidad
        FROM auditoria a
        JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.created_at BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY cantidad DESC
        LIMIT 10
    ");
    $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $topUsuarios = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Error al cargar los registros de auditoría: ' . $e->getMessage();
    $registros = [];
    $usuarios = [];
    $acciones = [];
    $tablas = [];
    $stats = ['total_acciones' => 0, 'usuarios_activos' => 0, 'dias_con_actividad' => 0];
    $topAcciones = [];
    $topUsuarios = [];
    $totalPages = 1;
    $totalRecords = 0;
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">
        <i class="fas fa-shield-alt mr-2"></i>Auditoría del Sistema
    </h1>

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

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total de Acciones</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($stats['total_acciones']); ?></p>
                </div>
                <i class="fas fa-list-alt text-4xl text-blue-200"></i>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Usuarios Activos</p>
                    <p class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($stats['usuarios_activos']); ?></p>
                </div>
                <i class="fas fa-users text-4xl text-green-200"></i>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Días con Actividad</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo number_format($stats['dias_con_actividad']); ?></p>
                </div>
                <i class="fas fa-calendar-check text-4xl text-purple-200"></i>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Filtros de Búsqueda</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo e($fecha_inicio); ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo e($fecha_fin); ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                <select name="usuario" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filtro == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo e($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Acción</label>
                <select name="accion" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?php echo e($a['accion']); ?>" <?php echo $accion_filtro === $a['accion'] ? 'selected' : ''; ?>>
                            <?php echo e($a['accion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tabla</label>
                <select name="tabla" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?php echo e($t['tabla_afectada']); ?>" <?php echo $tabla_filtro === $t['tabla_afectada'] ? 'selected' : ''; ?>>
                            <?php echo e($t['tabla_afectada']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="md:col-span-5 flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Aplicar Filtros
                </button>
                <a href="auditoria.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Limpiar
                </a>
                <button type="button" onclick="exportarExcel()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-file-excel mr-2"></i>Exportar a Excel
                </button>
            </div>
        </form>
    </div>

    <!-- Top Acciones y Usuarios -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top 10 Acciones -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Top 10 Acciones Más Frecuentes</h2>
            <?php if (!empty($topAcciones)): ?>
                <div class="space-y-2">
                    <?php foreach ($topAcciones as $ta): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <span class="font-medium text-gray-700"><?php echo e($ta['accion']); ?></span>
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                                <?php echo number_format($ta['cantidad']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-4">No hay datos disponibles</p>
            <?php endif; ?>
        </div>

        <!-- Top 10 Usuarios -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Top 10 Usuarios Más Activos</h2>
            <?php if (!empty($topUsuarios)): ?>
                <div class="space-y-2">
                    <?php foreach ($topUsuarios as $tu): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div>
                                <p class="font-medium text-gray-700"><?php echo e($tu['nombre']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($tu['email']); ?></p>
                            </div>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold">
                                <?php echo number_format($tu['cantidad']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-4">No hay datos disponibles</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabla de registros -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800">
                Registros de Auditoría 
                <span class="text-sm font-normal text-gray-600">
                    (<?php echo number_format($totalRecords); ?> registros encontrados)
                </span>
            </h2>
        </div>
        
        <?php if (empty($registros)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-inbox text-5xl mb-4"></i>
                <p class="text-lg">No hay registros de auditoría con los filtros seleccionados</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tabla</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registro ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Detalles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($registros as $reg): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                <?php echo formatDate($reg['created_at'], 'd/m/Y H:i:s'); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-800"><?php echo e($reg['usuario_nombre'] ?: 'Sistema'); ?></div>
                                <?php if ($reg['usuario_email']): ?>
                                    <div class="text-xs text-gray-500"><?php echo e($reg['usuario_email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold <?php 
                                    if (strpos($reg['accion'], 'CREATE') !== false || strpos($reg['accion'], 'INSERT') !== false) {
                                        echo 'bg-green-100 text-green-800';
                                    } elseif (strpos($reg['accion'], 'UPDATE') !== false || strpos($reg['accion'], 'EDIT') !== false) {
                                        echo 'bg-blue-100 text-blue-800';
                                    } elseif (strpos($reg['accion'], 'DELETE') !== false || strpos($reg['accion'], 'REMOVE') !== false) {
                                        echo 'bg-red-100 text-red-800';
                                    } elseif (strpos($reg['accion'], 'LOGIN') !== false) {
                                        echo 'bg-purple-100 text-purple-800';
                                    } else {
                                        echo 'bg-gray-100 text-gray-800';
                                    }
                                ?>">
                                    <?php echo e($reg['accion']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo e($reg['tabla_afectada'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo e($reg['registro_id'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo e($reg['ip_address'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($reg['datos_anteriores'] || $reg['datos_nuevos'] || $reg['detalles']): ?>
                                    <button onclick="verDetalles(<?php echo htmlspecialchars(json_encode($reg), ENT_QUOTES); ?>)" 
                                            class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de detalles -->
<div id="modalDetalles" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Detalles del Registro de Auditoría</h3>
            <button onclick="cerrarModalDetalles()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="modalContent" class="space-y-4">
            <!-- Contenido dinámico -->
        </div>
        
        <div class="mt-6 flex justify-end">
            <button onclick="cerrarModalDetalles()" 
                    class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
function verDetalles(registro) {
    const modal = document.getElementById('modalDetalles');
    const content = document.getElementById('modalContent');
    
    let html = '<div class="grid grid-cols-2 gap-4">';
    html += '<div><strong>Fecha/Hora:</strong> ' + registro.created_at + '</div>';
    html += '<div><strong>Usuario:</strong> ' + (registro.usuario_nombre || 'Sistema') + '</div>';
    html += '<div><strong>Acción:</strong> ' + registro.accion + '</div>';
    html += '<div><strong>Tabla:</strong> ' + (registro.tabla_afectada || '-') + '</div>';
    html += '<div><strong>Registro ID:</strong> ' + (registro.registro_id || '-') + '</div>';
    html += '<div><strong>IP:</strong> ' + (registro.ip_address || '-') + '</div>';
    html += '</div>';
    
    if (registro.detalles) {
        html += '<div class="mt-4"><strong>Detalles:</strong><pre class="mt-2 p-4 bg-gray-100 rounded text-sm overflow-auto">' + registro.detalles + '</pre></div>';
    }
    
    if (registro.datos_anteriores) {
        html += '<div class="mt-4"><strong>Datos Anteriores:</strong><pre class="mt-2 p-4 bg-red-50 rounded text-sm overflow-auto">' + registro.datos_anteriores + '</pre></div>';
    }
    
    if (registro.datos_nuevos) {
        html += '<div class="mt-4"><strong>Datos Nuevos:</strong><pre class="mt-2 p-4 bg-green-50 rounded text-sm overflow-auto">' + registro.datos_nuevos + '</pre></div>';
    }
    
    if (registro.user_agent) {
        html += '<div class="mt-4"><strong>User Agent:</strong><div class="mt-2 p-4 bg-gray-100 rounded text-xs break-all">' + registro.user_agent + '</div></div>';
    }
    
    content.innerHTML = html;
    modal.classList.remove('hidden');
}

function cerrarModalDetalles() {
    document.getElementById('modalDetalles').classList.add('hidden');
}

function exportarExcel() {
    // Construir URL con los filtros actuales
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    alert('Función de exportación en desarrollo. URL: ' + window.location.pathname + '?' + params.toString());
    // TODO: Implementar exportación a Excel
}
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
