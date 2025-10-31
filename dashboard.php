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

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
