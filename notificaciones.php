<?php
/**
 * Módulo de notificaciones del usuario
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Marcar como leída
if ($action === 'marcar_leida' && $id) {
    try {
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $user['id']]);
        
        if (isset($_GET['redirect'])) {
            redirect($_GET['redirect']);
        }
        redirect('/notificaciones.php');
    } catch (Exception $e) {
        $error = "Error al marcar la notificación";
    }
}

// Marcar todas como leídas
if ($action === 'marcar_todas_leidas') {
    try {
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$user['id']]);
        redirect('/notificaciones.php?success=1');
    } catch (Exception $e) {
        $error = "Error al marcar las notificaciones";
    }
}

// Obtener notificaciones
try {
    $filtro = $_GET['filtro'] ?? 'todas';
    $where = ["usuario_id = ?"];
    $params = [$user['id']];
    
    if ($filtro === 'no_leidas') {
        $where[] = "leida = 0";
    } elseif ($filtro !== 'todas') {
        $where[] = "tipo = ?";
        $params[] = strtoupper($filtro);
    }
    
    $whereSql = implode(' AND ', $where);
    
    $sql = "SELECT * FROM notificaciones 
            WHERE $whereSql 
            ORDER BY created_at DESC 
            LIMIT 50";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notificaciones = $stmt->fetchAll();
    
    // Contar no leídas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = 0");
    $stmt->execute([$user['id']]);
    $noLeidas = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $error = "Error al cargar las notificaciones: " . $e->getMessage();
    $notificaciones = [];
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-bell mr-2"></i>Notificaciones
            <?php if ($noLeidas > 0): ?>
                <span class="inline-block px-3 py-1 bg-red-500 text-white rounded-full text-sm ml-2">
                    <?php echo $noLeidas; ?>
                </span>
            <?php endif; ?>
        </h1>
        
        <?php if ($noLeidas > 0): ?>
        <a href="?action=marcar_todas_leidas" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-check-double mr-2"></i>Marcar Todas como Leídas
        </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700">Todas las notificaciones han sido marcadas como leídas</p>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="?filtro=todas" 
               class="px-4 py-2 rounded-lg <?php echo $filtro === 'todas' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                Todas
            </a>
            <a href="?filtro=no_leidas" 
               class="px-4 py-2 rounded-lg <?php echo $filtro === 'no_leidas' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                No Leídas
                <?php if ($noLeidas > 0): ?>
                    <span class="ml-1 px-2 py-1 bg-red-500 text-white rounded-full text-xs"><?php echo $noLeidas; ?></span>
                <?php endif; ?>
            </a>
            <a href="?filtro=renovacion" 
               class="px-4 py-2 rounded-lg <?php echo $filtro === 'renovacion' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                Renovaciones
            </a>
            <a href="?filtro=evento" 
               class="px-4 py-2 rounded-lg <?php echo $filtro === 'evento' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                Eventos
            </a>
            <a href="?filtro=requerimiento" 
               class="px-4 py-2 rounded-lg <?php echo $filtro === 'requerimiento' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                Requerimientos
            </a>
            <a href="?filtro=sistema" 
               class="px-4 py-2 rounded-lg <?php echo $filtro === 'sistema' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                Sistema
            </a>
        </div>
    </div>

    <!-- Lista de notificaciones -->
    <div class="space-y-3">
        <?php if (!empty($notificaciones)): ?>
            <?php 
            $tipoIcons = [
                'RENOVACION' => ['icon' => 'fa-sync-alt', 'color' => 'yellow'],
                'BIENVENIDA' => ['icon' => 'fa-hand-wave', 'color' => 'green'],
                'REQUERIMIENTO' => ['icon' => 'fa-file-alt', 'color' => 'purple'],
                'EVENTO' => ['icon' => 'fa-calendar', 'color' => 'blue'],
                'SISTEMA' => ['icon' => 'fa-cog', 'color' => 'gray'],
                'RECORDATORIO' => ['icon' => 'fa-bell', 'color' => 'orange']
            ];
            
            foreach ($notificaciones as $notif):
                $iconData = $tipoIcons[$notif['tipo']] ?? ['icon' => 'fa-bell', 'color' => 'gray'];
                $icon = $iconData['icon'];
                $color = $iconData['color'];
            ?>
            <div class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition <?php echo !$notif['leida'] ? 'border-l-4 border-blue-500' : ''; ?>">
                <div class="flex items-start">
                    <!-- Icono -->
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 bg-<?php echo $color; ?>-100 rounded-full flex items-center justify-center">
                            <i class="fas <?php echo $icon; ?> text-<?php echo $color; ?>-600 text-xl"></i>
                        </div>
                    </div>

                    <!-- Contenido -->
                    <div class="flex-1">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-semibold text-gray-800 <?php echo !$notif['leida'] ? 'text-lg' : ''; ?>">
                                    <?php echo e($notif['titulo']); ?>
                                    <?php if (!$notif['leida']): ?>
                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full ml-2"></span>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo formatDate($notif['created_at'], 'd/m/Y H:i'); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 text-xs rounded">
                                <?php echo $notif['tipo']; ?>
                            </span>
                        </div>

                        <p class="text-gray-600 mb-3"><?php echo e($notif['mensaje']); ?></p>

                        <!-- Acciones -->
                        <div class="flex items-center gap-4 text-sm">
                            <?php if ($notif['enlace']): ?>
                            <a href="<?php echo e($notif['enlace']); ?>" 
                               class="text-blue-600 hover:underline flex items-center">
                                <i class="fas fa-external-link-alt mr-1"></i>Ver Más
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!$notif['leida']): ?>
                            <a href="?action=marcar_leida&id=<?php echo $notif['id']; ?><?php echo $notif['enlace'] ? '&redirect=' . urlencode($notif['enlace']) : ''; ?>" 
                               class="text-green-600 hover:underline flex items-center">
                                <i class="fas fa-check mr-1"></i>Marcar como Leída
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400 flex items-center">
                                <i class="fas fa-check-double mr-1"></i>Leída
                            </span>
                            <?php endif; ?>

                            <?php if ($notif['enviada_email']): ?>
                            <span class="text-gray-500 flex items-center" title="Enviada por email">
                                <i class="fas fa-envelope mr-1"></i>
                            </span>
                            <?php endif; ?>

                            <?php if ($notif['enviada_whatsapp']): ?>
                            <span class="text-gray-500 flex items-center" title="Enviada por WhatsApp">
                                <i class="fab fa-whatsapp mr-1"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Estado vacío -->
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-bell-slash text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No hay notificaciones</h3>
                <p class="text-gray-500">Cuando tengas nuevas notificaciones, aparecerán aquí</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Configuración de notificaciones -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-8">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-600 text-2xl mr-4 mt-1"></i>
            <div>
                <h3 class="font-semibold text-blue-900 mb-2">Configuración de Notificaciones</h3>
                <p class="text-blue-800 text-sm mb-3">
                    Recibirás notificaciones sobre renovaciones próximas, nuevos eventos, requerimientos que coincidan con tu perfil y actualizaciones del sistema.
                </p>
                <a href="<?php echo BASE_URL; ?>/preferencias.php" 
                   class="text-blue-600 hover:text-blue-700 font-semibold text-sm">
                    <i class="fas fa-cog mr-1"></i>Configurar Preferencias
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
