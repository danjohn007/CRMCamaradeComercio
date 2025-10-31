<?php
/**
 * Módulo de reportes y proyección de ingresos
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requirePermission('CONSEJERO');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$tipo = $_GET['tipo'] ?? 'ingresos';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$sector = $_GET['sector'] ?? '';
$membresia = $_GET['membresia'] ?? '';

try {
    // Reporte de ingresos
    if ($tipo === 'ingresos') {
        $where = ["p.fecha_pago BETWEEN ? AND ?"];
        $params = [$fecha_inicio, $fecha_fin];
        
        if (!empty($sector)) {
            $where[] = "e.sector_id = ?";
            $params[] = $sector;
        }
        
        if (!empty($membresia)) {
            $where[] = "e.membresia_id = ?";
            $params[] = $membresia;
        }
        
        $whereSql = implode(' AND ', $where);
        
        // Total de ingresos
        $sql = "SELECT COALESCE(SUM(p.monto), 0) as total
                FROM pagos p
                LEFT JOIN empresas e ON p.empresa_id = e.id
                WHERE $whereSql AND p.estado = 'COMPLETADO'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $totalIngresos = $stmt->fetch()['total'];
        
        // Ingresos por membresía
        $sql = "SELECT m.nombre, m.costo, COUNT(p.id) as cantidad, SUM(p.monto) as total
                FROM pagos p
                LEFT JOIN empresas e ON p.empresa_id = e.id
                LEFT JOIN membresias m ON e.membresia_id = m.id
                WHERE $whereSql AND p.estado = 'COMPLETADO'
                GROUP BY m.id
                ORDER BY total DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ingresosPorMembresia = $stmt->fetchAll();
        
        // Ingresos por sector
        $sql = "SELECT s.nombre, COUNT(p.id) as cantidad, SUM(p.monto) as total
                FROM pagos p
                LEFT JOIN empresas e ON p.empresa_id = e.id
                LEFT JOIN sectores s ON e.sector_id = s.id
                WHERE $whereSql AND p.estado = 'COMPLETADO'
                GROUP BY s.id
                ORDER BY total DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ingresosPorSector = $stmt->fetchAll();
        
        // Ingresos por mes (últimos 12 meses)
        $sql = "SELECT DATE_FORMAT(p.fecha_pago, '%Y-%m') as mes, SUM(p.monto) as total
                FROM pagos p
                WHERE p.fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND p.estado = 'COMPLETADO'
                GROUP BY mes
                ORDER BY mes ASC";
        
        $stmt = $db->query($sql);
        $ingresosPorMes = $stmt->fetchAll();
    }
    
    // Proyección de ingresos
    if ($tipo === 'proyeccion') {
        // Renovaciones próximas (próximos 90 días)
        $sql = "SELECT e.*, m.nombre as membresia_nombre, m.costo,
                DATEDIFF(e.fecha_renovacion, CURDATE()) as dias_vencimiento
                FROM empresas e
                LEFT JOIN membresias m ON e.membresia_id = m.id
                WHERE e.activo = 1 
                AND e.fecha_renovacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY e.fecha_renovacion ASC";
        
        $stmt = $db->query($sql);
        $proximasRenovaciones = $stmt->fetchAll();
        
        // Calcular proyección
        $proyeccion30dias = 0;
        $proyeccion60dias = 0;
        $proyeccion90dias = 0;
        
        foreach ($proximasRenovaciones as $empresa) {
            $dias = $empresa['dias_vencimiento'];
            $monto = $empresa['costo'] ?? 0;
            
            if ($dias <= 30) {
                $proyeccion30dias += $monto;
            }
            if ($dias <= 60) {
                $proyeccion60dias += $monto;
            }
            if ($dias <= 90) {
                $proyeccion90dias += $monto;
            }
        }
    }
    
    // Estadísticas de empresas
    if ($tipo === 'empresas') {
        // Total de empresas
        $totalEmpresas = $db->query("SELECT COUNT(*) as total FROM empresas WHERE activo = 1")->fetch()['total'];
        
        // Empresas por sector
        $sql = "SELECT s.nombre, COUNT(e.id) as cantidad
                FROM empresas e
                LEFT JOIN sectores s ON e.sector_id = s.id
                WHERE e.activo = 1
                GROUP BY s.id
                ORDER BY cantidad DESC";
        $empresasPorSector = $db->query($sql)->fetchAll();
        
        // Empresas por membresía
        $sql = "SELECT m.nombre, COUNT(e.id) as cantidad
                FROM empresas e
                LEFT JOIN membresias m ON e.membresia_id = m.id
                WHERE e.activo = 1
                GROUP BY m.id
                ORDER BY cantidad DESC";
        $empresasPorMembresia = $db->query($sql)->fetchAll();
        
        // Empresas por ciudad
        $sql = "SELECT ciudad, COUNT(*) as cantidad
                FROM empresas
                WHERE activo = 1 AND ciudad IS NOT NULL
                GROUP BY ciudad
                ORDER BY cantidad DESC
                LIMIT 10";
        $empresasPorCiudad = $db->query($sql)->fetchAll();
        
        // Nuevas empresas por mes (último año)
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as cantidad
                FROM empresas
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY mes
                ORDER BY mes ASC";
        $nuevasEmpresasPorMes = $db->query($sql)->fetchAll();
    }
    
    // Reporte de requerimientos
    if ($tipo === 'requerimientos') {
        // Total de requerimientos
        $totalRequerimientos = $db->query("SELECT COUNT(*) as total FROM requerimientos")->fetch()['total'];
        
        // Requerimientos por estado
        $sql = "SELECT estado, COUNT(*) as cantidad
                FROM requerimientos
                GROUP BY estado";
        $requerimientosPorEstado = $db->query($sql)->fetchAll();
        
        // Requerimientos con más respuestas
        $sql = "SELECT r.*, e.razon_social, r.respuestas_count
                FROM requerimientos r
                LEFT JOIN empresas e ON r.empresa_solicitante_id = e.id
                ORDER BY r.respuestas_count DESC
                LIMIT 10";
        $topRequerimientos = $db->query($sql)->fetchAll();
        
        // Búsquedas más frecuentes
        $sql = "SELECT termino, COUNT(*) as cantidad
                FROM busquedas
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND termino != ''
                GROUP BY termino
                ORDER BY cantidad DESC
                LIMIT 15";
        $topBusquedas = $db->query($sql)->fetchAll();
    }
    
} catch (Exception $e) {
    $error = "Error al generar el reporte: " . $e->getMessage();
}

// Obtener filtros
$sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$membresias = $db->query("SELECT * FROM membresias WHERE activo = 1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">
        <i class="fas fa-chart-bar mr-2"></i>Reportes y Estadísticas
    </h1>

    <!-- Tabs de reportes -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="flex border-b border-gray-200 overflow-x-auto">
            <a href="?tipo=ingresos" 
               class="px-6 py-4 font-semibold <?php echo $tipo === 'ingresos' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-dollar-sign mr-2"></i>Ingresos
            </a>
            <a href="?tipo=proyeccion" 
               class="px-6 py-4 font-semibold <?php echo $tipo === 'proyeccion' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-chart-line mr-2"></i>Proyección
            </a>
            <a href="?tipo=empresas" 
               class="px-6 py-4 font-semibold <?php echo $tipo === 'empresas' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-building mr-2"></i>Empresas
            </a>
            <a href="?tipo=requerimientos" 
               class="px-6 py-4 font-semibold <?php echo $tipo === 'requerimientos' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>">
                <i class="fas fa-file-alt mr-2"></i>Requerimientos
            </a>
        </div>
    </div>

    <?php if ($tipo === 'ingresos'): ?>
    <!-- Reporte de Ingresos -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Filtros</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="tipo" value="ingresos">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sector</label>
                <select name="sector" class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Todos</option>
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $sector == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo e($s['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                    Generar Reporte
                </button>
            </div>
        </form>
    </div>

    <!-- Resumen de ingresos -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Ingresos del Período</p>
                    <p class="text-3xl font-bold mt-2"><?php echo formatMoney($totalIngresos ?? 0); ?></p>
                </div>
                <i class="fas fa-dollar-sign text-5xl text-green-200 opacity-50"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Transacciones</p>
                    <p class="text-3xl font-bold mt-2"><?php echo count($ingresosPorMembresia ?? []); ?></p>
                </div>
                <i class="fas fa-receipt text-5xl text-blue-200 opacity-50"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">Promedio</p>
                    <p class="text-3xl font-bold mt-2">
                        <?php 
                        $count = array_sum(array_column($ingresosPorMembresia ?? [], 'cantidad'));
                        echo $count > 0 ? formatMoney(($totalIngresos ?? 0) / $count) : '$0.00'; 
                        ?>
                    </p>
                </div>
                <i class="fas fa-chart-line text-5xl text-purple-200 opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Ingresos por membresía -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Ingresos por Membresía</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Membresía</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($ingresosPorMembresia ?? [] as $item): ?>
                    <tr>
                        <td class="px-6 py-4 font-semibold"><?php echo e($item['nombre'] ?? 'Sin membresía'); ?></td>
                        <td class="px-6 py-4"><?php echo $item['cantidad']; ?></td>
                        <td class="px-6 py-4 font-semibold text-green-600"><?php echo formatMoney($item['total']); ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($totalIngresos > 0) ? ($item['total'] / $totalIngresos * 100) : 0; ?>%"></div>
                                </div>
                                <span><?php echo $totalIngresos > 0 ? number_format($item['total'] / $totalIngresos * 100, 1) : 0; ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ingresos por sector -->
    <?php if (!empty($ingresosPorSector)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Ingresos por Sector</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($ingresosPorSector as $item): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-800 mb-2"><?php echo e($item['nombre'] ?? 'Sin sector'); ?></h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo formatMoney($item['total']); ?></p>
                <p class="text-sm text-gray-600"><?php echo $item['cantidad']; ?> transacciones</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($tipo === 'proyeccion'): ?>
    <!-- Proyección de Ingresos -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center">
                <p class="text-gray-600 mb-2">Próximos 30 días</p>
                <p class="text-3xl font-bold text-green-600"><?php echo formatMoney($proyeccion30dias ?? 0); ?></p>
                <p class="text-sm text-gray-500 mt-2">Renovaciones estimadas</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center">
                <p class="text-gray-600 mb-2">Próximos 60 días</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo formatMoney($proyeccion60dias ?? 0); ?></p>
                <p class="text-sm text-gray-500 mt-2">Renovaciones estimadas</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center">
                <p class="text-gray-600 mb-2">Próximos 90 días</p>
                <p class="text-3xl font-bold text-purple-600"><?php echo formatMoney($proyeccion90dias ?? 0); ?></p>
                <p class="text-sm text-gray-500 mt-2">Renovaciones estimadas</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Próximas Renovaciones (90 días)</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Membresía</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Renovación</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Días Restantes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($proximasRenovaciones ?? [] as $empresa): ?>
                    <tr>
                        <td class="px-6 py-4"><?php echo e($empresa['razon_social']); ?></td>
                        <td class="px-6 py-4"><?php echo e($empresa['membresia_nombre'] ?? 'N/A'); ?></td>
                        <td class="px-6 py-4"><?php echo formatDate($empresa['fecha_renovacion']); ?></td>
                        <td class="px-6 py-4">
                            <?php 
                            $dias = $empresa['dias_vencimiento'];
                            $color = $dias <= 15 ? 'red' : ($dias <= 30 ? 'yellow' : 'green');
                            ?>
                            <span class="px-2 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 rounded text-sm">
                                <?php echo $dias; ?> días
                            </span>
                        </td>
                        <td class="px-6 py-4 font-semibold text-green-600"><?php echo formatMoney($empresa['costo'] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tipo === 'empresas'): ?>
    <!-- Estadísticas de Empresas -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Empresas por sector -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Empresas por Sector</h2>
            <div class="space-y-3">
                <?php foreach ($empresasPorSector ?? [] as $item): ?>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700"><?php echo e($item['nombre'] ?? 'Sin sector'); ?></span>
                    <div class="flex items-center">
                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($totalEmpresas > 0) ? ($item['cantidad'] / $totalEmpresas * 100) : 0; ?>%"></div>
                        </div>
                        <span class="font-semibold"><?php echo $item['cantidad']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Empresas por membresía -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Empresas por Membresía</h2>
            <div class="space-y-3">
                <?php foreach ($empresasPorMembresia ?? [] as $item): ?>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700"><?php echo e($item['nombre'] ?? 'Sin membresía'); ?></span>
                    <div class="flex items-center">
                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo ($totalEmpresas > 0) ? ($item['cantidad'] / $totalEmpresas * 100) : 0; ?>%"></div>
                        </div>
                        <span class="font-semibold"><?php echo $item['cantidad']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top ciudades -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Empresas por Ciudad</h2>
            <div class="space-y-2">
                <?php foreach ($empresasPorCiudad ?? [] as $item): ?>
                <div class="flex items-center justify-between py-2 border-b">
                    <span class="text-gray-700"><?php echo e($item['ciudad']); ?></span>
                    <span class="font-semibold text-blue-600"><?php echo $item['cantidad']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php elseif ($tipo === 'requerimientos'): ?>
    <!-- Estadísticas de Requerimientos -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Requerimientos por estado -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Requerimientos por Estado</h2>
            <div class="space-y-4">
                <?php foreach ($requerimientosPorEstado ?? [] as $item): ?>
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-gray-700"><?php echo e($item['estado']); ?></span>
                        <span class="font-semibold"><?php echo $item['cantidad']; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($totalRequerimientos > 0) ? ($item['cantidad'] / $totalRequerimientos * 100) : 0; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top búsquedas -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Búsquedas Más Frecuentes (30 días)</h2>
            <div class="space-y-2">
                <?php foreach ($topBusquedas ?? [] as $item): ?>
                <div class="flex items-center justify-between py-2 border-b">
                    <span class="text-gray-700"><?php echo e($item['termino']); ?></span>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm"><?php echo $item['cantidad']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Top requerimientos -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Requerimientos con Más Respuestas</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requerimiento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Respuestas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($topRequerimientos ?? [] as $req): ?>
                    <tr>
                        <td class="px-6 py-4">
                            <a href="requerimientos.php?action=view&id=<?php echo $req['id']; ?>" class="text-blue-600 hover:underline">
                                <?php echo e($req['titulo']); ?>
                            </a>
                        </td>
                        <td class="px-6 py-4"><?php echo e($req['razon_social']); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                <?php echo $req['estado']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 font-semibold"><?php echo $req['respuestas_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Botones de exportación -->
    <div class="flex justify-end space-x-4 mt-6">
        <button onclick="window.print()" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            <i class="fas fa-print mr-2"></i>Imprimir
        </button>
        <button onclick="alert('Función de exportación a Excel en desarrollo')" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            <i class="fas fa-file-excel mr-2"></i>Exportar a Excel
        </button>
        <button onclick="alert('Función de exportación a PDF en desarrollo')" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
            <i class="fas fa-file-pdf mr-2"></i>Exportar a PDF
        </button>
    </div>
</div>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
