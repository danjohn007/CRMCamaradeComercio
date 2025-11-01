<?php
/**
 * Dashboard principal del sistema
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Obtener estadísticas según el rol
$stats = [];

try {
    if (hasPermission('AFILADOR')) {
        // Estadísticas para administradores y afiladores
        $stmt = $db->query("SELECT COUNT(*) as total FROM empresas WHERE activo = 1");
        $stats['total_empresas'] = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM empresas WHERE activo = 1 AND fecha_renovacion >= CURDATE()");
        $stats['empresas_activas'] = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM empresas WHERE activo = 1 AND fecha_renovacion < CURDATE()");
        $stats['empresas_vencidas'] = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM empresas WHERE activo = 1 AND fecha_renovacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $stats['proximas_vencer'] = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM eventos WHERE fecha_inicio >= NOW()");
        $stats['eventos_proximos'] = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM requerimientos WHERE estado = 'ABIERTO'");
        $stats['requerimientos_abiertos'] = $stmt->fetch()['total'];
        
        // Ingresos del mes actual
        $stmt = $db->query("SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE()) AND estado = 'COMPLETADO'");
        $stats['ingresos_mes'] = $stmt->fetch()['total'];
        
        // Próximas renovaciones
        $stmt = $db->prepare("SELECT e.*, m.nombre as membresia_nombre, m.costo 
                             FROM empresas e 
                             LEFT JOIN membresias m ON e.membresia_id = m.id 
                             WHERE e.activo = 1 AND e.fecha_renovacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                             ORDER BY e.fecha_renovacion ASC 
                             LIMIT 5");
        $stmt->execute();
        $proximas_renovaciones = $stmt->fetchAll();
    } else {
        // Estadísticas para entidades comerciales
        if ($user['empresa_id']) {
            $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$user['empresa_id']]);
            $stats['empresa'] = $stmt->fetch();
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM requerimientos WHERE empresa_solicitante_id = ?");
            $stmt->execute([$user['empresa_id']]);
            $stats['mis_requerimientos'] = $stmt->fetch()['total'];
        }
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM eventos WHERE fecha_inicio >= NOW() AND tipo = 'PUBLICO'");
        $stats['eventos_disponibles'] = $stmt->fetch()['total'];
    }
    
    // Notificaciones no leídas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = 0");
    $stmt->execute([$user['id']]);
    $stats['notificaciones_pendientes'] = $stmt->fetch()['total'];
    
    // Próximos eventos
    $stmt = $db->prepare("SELECT * FROM eventos WHERE fecha_inicio >= NOW() ORDER BY fecha_inicio ASC LIMIT 3");
    $stmt->execute();
    $proximos_eventos = $stmt->fetchAll();
    
    // Datos para gráficas (solo para roles administrativos)
    if (hasPermission('AFILADOR')) {
        // 1. Empresas por membresía
        $stmt = $db->query("SELECT m.nombre, COUNT(e.id) as cantidad 
                           FROM membresias m 
                           LEFT JOIN empresas e ON m.id = e.membresia_id AND e.activo = 1 
                           WHERE m.activo = 1 
                           GROUP BY m.id 
                           ORDER BY cantidad DESC");
        $empresasPorMembresia = $stmt->fetchAll();
        
        // 2. Empresas por sector
        $stmt = $db->query("SELECT s.nombre, COUNT(e.id) as cantidad 
                           FROM sectores s 
                           LEFT JOIN empresas e ON s.id = e.sector_id AND e.activo = 1 
                           WHERE s.activo = 1 
                           GROUP BY s.id 
                           ORDER BY cantidad DESC 
                           LIMIT 10");
        $empresasPorSector = $stmt->fetchAll();
        
        // 3. Ingresos por mes (últimos 6 meses)
        $stmt = $db->query("SELECT DATE_FORMAT(fecha_pago, '%Y-%m') as mes, 
                           DATE_FORMAT(fecha_pago, '%b') as mes_nombre,
                           SUM(monto) as total 
                           FROM pagos 
                           WHERE fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                           AND estado = 'COMPLETADO'
                           GROUP BY mes 
                           ORDER BY mes ASC");
        $ingresosPorMes = $stmt->fetchAll();
        
        // 4. Nuevas empresas por mes (últimos 6 meses)
        $stmt = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as mes,
                           DATE_FORMAT(created_at, '%b') as mes_nombre,
                           COUNT(*) as cantidad 
                           FROM empresas 
                           WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                           GROUP BY mes 
                           ORDER BY mes ASC");
        $nuevasEmpresasPorMes = $stmt->fetchAll();
        
        // 5. Estado de empresas (activas vs vencidas)
        $empresasActivas = $stats['empresas_activas'] ?? 0;
        $empresasVencidas = $stats['empresas_vencidas'] ?? 0;
        $empresasProximasVencer = $stats['proximas_vencer'] ?? 0;
        
        // 6. Eventos por tipo
        $stmt = $db->query("SELECT tipo, COUNT(*) as cantidad 
                           FROM eventos 
                           GROUP BY tipo");
        $eventosPorTipo = $stmt->fetchAll();
        
        // 7. Requerimientos por estado
        $stmt = $db->query("SELECT estado, COUNT(*) as cantidad 
                           FROM requerimientos 
                           GROUP BY estado");
        $requerimientosPorEstado = $stmt->fetchAll();
        
        // 8. Top 10 ciudades con más empresas
        $stmt = $db->query("SELECT ciudad, COUNT(*) as cantidad 
                           FROM empresas 
                           WHERE activo = 1 AND ciudad IS NOT NULL AND ciudad != ''
                           GROUP BY ciudad 
                           ORDER BY cantidad DESC 
                           LIMIT 10");
        $empresasPorCiudad = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Bienvenida -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">¡Bienvenido, <?php echo e($user['nombre'] ?: 'Usuario'); ?>!</h1>
        <p class="text-gray-600 mt-2">Rol: <span class="font-semibold"><?php echo e($user['rol']); ?></span></p>
    </div>

    <?php if (hasPermission('AFILADOR')): ?>
    <!-- Dashboard Administrativo -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Empresas -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Empresas</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($stats['total_empresas']); ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Empresas Activas -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Empresas Activas</p>
                    <p class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($stats['empresas_activas']); ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Próximas a Vencer -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Por Vencer (30 días)</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo number_format($stats['proximas_vencer']); ?></p>
                </div>
                <div class="bg-yellow-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Ingresos del Mes -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Ingresos del Mes</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo formatMoney($stats['ingresos_mes']); ?></p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Acciones Rápidas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Próximas Renovaciones -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Próximas Renovaciones</h2>
            <?php if (!empty($proximas_renovaciones)): ?>
                <div class="space-y-3">
                    <?php foreach ($proximas_renovaciones as $empresa): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800"><?php echo e($empresa['razon_social']); ?></p>
                                <p class="text-sm text-gray-600">Vence: <?php echo formatDate($empresa['fecha_renovacion']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-800"><?php echo formatMoney($empresa['costo']); ?></p>
                                <span class="text-xs text-yellow-600"><?php echo diasHastaVencimiento($empresa['fecha_renovacion']); ?> días</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="empresas.php?filter=vencimiento" class="block text-center text-blue-600 hover:underline mt-4">
                    Ver todas →
                </a>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No hay renovaciones próximas</p>
            <?php endif; ?>
        </div>

        <!-- Acciones Rápidas -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Acciones Rápidas</h2>
            <div class="grid grid-cols-2 gap-4">
                <?php if (hasPermission('CAPTURISTA')): ?>
                <a href="empresas.php?action=new" class="flex flex-col items-center justify-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Nueva Empresa</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('DIRECCION')): ?>
                <a href="eventos.php?action=new" class="flex flex-col items-center justify-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                    <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Nuevo Evento</span>
                </a>
                <?php endif; ?>

                <a href="reportes.php" class="flex flex-col items-center justify-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                    <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Reportes</span>
                </a>

                <a href="buscar.php" class="flex flex-col items-center justify-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                    <svg class="w-8 h-8 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Buscar</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Sección de Gráficas Analíticas -->
    <div class="mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-chart-line mr-2"></i>Análisis y Tendencias
            </h2>
            
            <!-- Filtro de Fechas -->
            <div class="bg-white rounded-lg shadow-md p-4 flex flex-col sm:flex-row gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                    <input 
                        type="date" 
                        id="fecha_inicio" 
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                    <input 
                        type="date" 
                        id="fecha_fin" 
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <button 
                    id="btnFiltrarGraficas" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                >
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <button 
                    id="btnResetFiltro" 
                    class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition"
                    title="Restablecer a mes actual"
                >
                    <i class="fas fa-redo"></i>
                </button>
            </div>
        </div>
        
        <!-- Gráficas - Fila 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfica 1: Empresas por Membresía -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Empresas por Membresía</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartMembresias"></canvas>
                </div>
            </div>
            
            <!-- Gráfica 2: Empresas por Sector -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Top 10 Sectores</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartSectores"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Gráficas - Fila 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfica 3: Ingresos por Mes -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Ingresos Últimos 6 Meses</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartIngresos"></canvas>
                </div>
            </div>
            
            <!-- Gráfica 4: Nuevas Empresas por Mes -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Nuevas Afiliaciones (6 Meses)</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartNuevasEmpresas"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Gráficas - Fila 3 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfica 5: Estado de Empresas -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Estado de Membresías</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartEstadoEmpresas"></canvas>
                </div>
            </div>
            
            <!-- Gráfica 6: Eventos por Tipo -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Eventos por Tipo</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartEventos"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Gráficas - Fila 4 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfica 7: Requerimientos por Estado -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Requerimientos por Estado</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartRequerimientos"></canvas>
                </div>
            </div>
            
            <!-- Gráfica 8: Empresas por Ciudad -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Top 10 Ciudades</h3>
                <div style="position: relative; height: 250px;">
                    <canvas id="chartCiudades"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Dashboard para Entidad Comercial -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Eventos Disponibles</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $stats['eventos_disponibles']; ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <?php if (isset($stats['mis_requerimientos'])): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Mis Requerimientos</p>
                    <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $stats['mis_requerimientos']; ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Notificaciones</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $stats['notificaciones_pendientes']; ?></p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Próximos Eventos -->
    <?php if (!empty($proximos_eventos)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Próximos Eventos</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($proximos_eventos as $evento): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <h3 class="font-semibold text-gray-800 mb-2"><?php echo e($evento['titulo']); ?></h3>
                    <p class="text-sm text-gray-600 mb-2"><?php echo e(substr($evento['descripcion'], 0, 100)); ?>...</p>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <?php echo formatDate($evento['fecha_inicio'], 'd/m/Y H:i'); ?>
                    </div>
                    <a href="eventos.php?id=<?php echo $evento['id']; ?>" class="text-blue-600 hover:underline text-sm">
                        Ver detalles →
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (hasPermission('AFILADOR')): ?>
<script>
// Configuración de colores
const chartColors = {
    primary: '#1E40AF',
    secondary: '#10B981',
    warning: '#F59E0B',
    danger: '#EF4444',
    info: '#3B82F6',
    purple: '#8B5CF6',
    pink: '#EC4899',
    indigo: '#6366F1',
    teal: '#14B8A6',
    cyan: '#06B6D4'
};

const colorPalette = [
    chartColors.primary,
    chartColors.secondary,
    chartColors.info,
    chartColors.purple,
    chartColors.warning,
    chartColors.danger,
    chartColors.pink,
    chartColors.indigo,
    chartColors.teal,
    chartColors.cyan
];

// Almacenar referencias a los gráficos para poder actualizarlos
let chartInstances = {};

// Configuración base para API
const BASE_API_URL = <?php echo json_encode(BASE_URL); ?>;

// 1. Gráfica de Empresas por Membresía (Doughnut)
<?php if (!empty($empresasPorMembresia)): ?>
chartInstances.chartMembresias = new Chart(document.getElementById('chartMembresias'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($empresasPorMembresia, 'nombre')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($empresasPorMembresia, 'cantidad')); ?>,
            backgroundColor: colorPalette,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: { padding: 10, font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + ' empresas';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// 2. Gráfica de Empresas por Sector (Bar horizontal)
<?php if (!empty($empresasPorSector)): ?>
chartInstances.chartSectores = new Chart(document.getElementById('chartSectores'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($empresasPorSector, 'nombre')); ?>,
        datasets: [{
            label: 'Empresas',
            data: <?php echo json_encode(array_column($empresasPorSector, 'cantidad')); ?>,
            backgroundColor: chartColors.info,
            borderColor: chartColors.primary,
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Empresas: ' + context.parsed.x;
                    }
                }
            }
        },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>

// 3. Gráfica de Ingresos por Mes (Line)
<?php if (!empty($ingresosPorMes)): ?>
chartInstances.chartIngresos = new Chart(document.getElementById('chartIngresos'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($ingresosPorMes, 'mes_nombre')); ?>,
        datasets: [{
            label: 'Ingresos',
            data: <?php echo json_encode(array_column($ingresosPorMes, 'total')); ?>,
            borderColor: chartColors.secondary,
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Ingresos: $' + context.parsed.y.toLocaleString('es-MX', {minimumFractionDigits: 2});
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

// 4. Gráfica de Nuevas Empresas por Mes (Bar)
<?php if (!empty($nuevasEmpresasPorMes)): ?>
chartInstances.chartNuevasEmpresas = new Chart(document.getElementById('chartNuevasEmpresas'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($nuevasEmpresasPorMes, 'mes_nombre')); ?>,
        datasets: [{
            label: 'Nuevas Afiliaciones',
            data: <?php echo json_encode(array_column($nuevasEmpresasPorMes, 'cantidad')); ?>,
            backgroundColor: chartColors.purple,
            borderColor: chartColors.purple,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Nuevas: ' + context.parsed.y + ' empresas';
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>

// 5. Gráfica de Estado de Empresas (Pie)
chartInstances.chartEstadoEmpresas = new Chart(document.getElementById('chartEstadoEmpresas'), {
    type: 'pie',
    data: {
        labels: ['Activas', 'Próximas a Vencer', 'Vencidas'],
        datasets: [{
            data: [
                <?php echo $empresasActivas; ?>,
                <?php echo $empresasProximasVencer; ?>,
                <?php echo $empresasVencidas; ?>
            ],
            backgroundColor: [
                chartColors.secondary,
                chartColors.warning,
                chartColors.danger
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// 6. Gráfica de Eventos por Tipo (Doughnut)
<?php if (!empty($eventosPorTipo)): ?>
chartInstances.chartEventos = new Chart(document.getElementById('chartEventos'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($eventosPorTipo, 'tipo')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($eventosPorTipo, 'cantidad')); ?>,
            backgroundColor: [chartColors.info, chartColors.warning, chartColors.purple],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 12 } }
            }
        }
    }
});
<?php endif; ?>

// 7. Gráfica de Requerimientos por Estado (Bar)
<?php if (!empty($requerimientosPorEstado)): ?>
chartInstances.chartRequerimientos = new Chart(document.getElementById('chartRequerimientos'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($requerimientosPorEstado, 'estado')); ?>,
        datasets: [{
            label: 'Requerimientos',
            data: <?php echo json_encode(array_column($requerimientosPorEstado, 'cantidad')); ?>,
            backgroundColor: [
                chartColors.warning,
                chartColors.secondary,
                chartColors.danger,
                chartColors.info
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>

// 8. Gráfica de Empresas por Ciudad (Bar horizontal)
<?php if (!empty($empresasPorCiudad)): ?>
chartInstances.chartCiudades = new Chart(document.getElementById('chartCiudades'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($empresasPorCiudad, 'ciudad')); ?>,
        datasets: [{
            label: 'Empresas',
            data: <?php echo json_encode(array_column($empresasPorCiudad, 'cantidad')); ?>,
            backgroundColor: chartColors.teal,
            borderColor: chartColors.cyan,
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>

// Función para inicializar las fechas con el mes actual
function initializeDateFilter() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    
    if (fechaInicio && fechaFin) {
        fechaInicio.value = firstDay.toISOString().split('T')[0];
        fechaFin.value = lastDay.toISOString().split('T')[0];
    }
}

// Función para actualizar las gráficas con datos filtrados
async function actualizarGraficas() {
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    if (!fechaInicio || !fechaFin) {
        alert('Por favor selecciona ambas fechas');
        return;
    }
    
    try {
        // Mostrar indicador de carga
        document.body.style.cursor = 'wait';
        
        const response = await fetch(`${BASE_API_URL}/api/dashboard_charts.php?fecha_inicio=${encodeURIComponent(fechaInicio)}&fecha_fin=${encodeURIComponent(fechaFin)}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener datos');
        }
        
        const data = result.data;
        
        // Actualizar cada gráfica
        if (chartInstances.chartMembresias && data.empresasPorMembresia.length > 0) {
            chartInstances.chartMembresias.data.labels = data.empresasPorMembresia.map(item => item.nombre);
            chartInstances.chartMembresias.data.datasets[0].data = data.empresasPorMembresia.map(item => parseInt(item.cantidad));
            chartInstances.chartMembresias.update();
        }
        
        if (chartInstances.chartSectores && data.empresasPorSector.length > 0) {
            chartInstances.chartSectores.data.labels = data.empresasPorSector.map(item => item.nombre);
            chartInstances.chartSectores.data.datasets[0].data = data.empresasPorSector.map(item => parseInt(item.cantidad));
            chartInstances.chartSectores.update();
        }
        
        if (chartInstances.chartIngresos && data.ingresosPorMes.length > 0) {
            chartInstances.chartIngresos.data.labels = data.ingresosPorMes.map(item => item.mes_nombre);
            chartInstances.chartIngresos.data.datasets[0].data = data.ingresosPorMes.map(item => parseFloat(item.total));
            chartInstances.chartIngresos.update();
        }
        
        if (chartInstances.chartNuevasEmpresas && data.nuevasEmpresasPorMes.length > 0) {
            chartInstances.chartNuevasEmpresas.data.labels = data.nuevasEmpresasPorMes.map(item => item.mes_nombre);
            chartInstances.chartNuevasEmpresas.data.datasets[0].data = data.nuevasEmpresasPorMes.map(item => parseInt(item.cantidad));
            chartInstances.chartNuevasEmpresas.update();
        }
        
        if (chartInstances.chartEstadoEmpresas) {
            chartInstances.chartEstadoEmpresas.data.datasets[0].data = [
                parseInt(data.empresasActivas || 0),
                parseInt(data.empresasProximasVencer || 0),
                parseInt(data.empresasVencidas || 0)
            ];
            chartInstances.chartEstadoEmpresas.update();
        }
        
        if (chartInstances.chartEventos && data.eventosPorTipo.length > 0) {
            chartInstances.chartEventos.data.labels = data.eventosPorTipo.map(item => item.tipo);
            chartInstances.chartEventos.data.datasets[0].data = data.eventosPorTipo.map(item => parseInt(item.cantidad));
            chartInstances.chartEventos.update();
        }
        
        if (chartInstances.chartRequerimientos && data.requerimientosPorEstado.length > 0) {
            chartInstances.chartRequerimientos.data.labels = data.requerimientosPorEstado.map(item => item.estado);
            chartInstances.chartRequerimientos.data.datasets[0].data = data.requerimientosPorEstado.map(item => parseInt(item.cantidad));
            chartInstances.chartRequerimientos.update();
        }
        
        if (chartInstances.chartCiudades && data.empresasPorCiudad.length > 0) {
            chartInstances.chartCiudades.data.labels = data.empresasPorCiudad.map(item => item.ciudad);
            chartInstances.chartCiudades.data.datasets[0].data = data.empresasPorCiudad.map(item => parseInt(item.cantidad));
            chartInstances.chartCiudades.update();
        }
        
    } catch (error) {
        console.error('Error al actualizar gráficas:', error);
        alert('Error al actualizar las gráficas: ' + error.message);
    } finally {
        document.body.style.cursor = 'default';
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    initializeDateFilter();
    
    const btnFiltrar = document.getElementById('btnFiltrarGraficas');
    const btnReset = document.getElementById('btnResetFiltro');
    
    if (btnFiltrar) {
        btnFiltrar.addEventListener('click', actualizarGraficas);
    }
    
    if (btnReset) {
        btnReset.addEventListener('click', function() {
            initializeDateFilter();
            actualizarGraficas();
        });
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
