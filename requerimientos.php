<?php
/**
 * Módulo de requerimientos comerciales
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Procesar nuevo requerimiento
if ($action === 'new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user['empresa_id']) {
        $error = 'Debes tener una empresa asociada para crear requerimientos';
    } else {
        $data = [
            'titulo' => sanitize($_POST['titulo'] ?? ''),
            'descripcion' => sanitize($_POST['descripcion'] ?? ''),
            'sector_id' => $_POST['sector_id'] ?? null,
            'categoria_id' => $_POST['categoria_id'] ?? null,
            'palabras_clave' => sanitize($_POST['palabras_clave'] ?? ''),
            'presupuesto_estimado' => $_POST['presupuesto_estimado'] ?? null,
            'plazo_dias' => $_POST['plazo_dias'] ?? null,
            'prioridad' => $_POST['prioridad'] ?? 'MEDIA',
        ];

        try {
            $sql = "INSERT INTO requerimientos (titulo, descripcion, empresa_solicitante_id, usuario_creador_id, 
                    sector_id, categoria_id, palabras_clave, presupuesto_estimado, plazo_dias, prioridad) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['titulo'], $data['descripcion'], $user['empresa_id'], $user['id'],
                $data['sector_id'], $data['categoria_id'], $data['palabras_clave'],
                $data['presupuesto_estimado'], $data['plazo_dias'], $data['prioridad']
            ]);
            
            $requerimiento_id = $db->lastInsertId();
            
            // Buscar empresas coincidentes y enviar notificaciones
            // TODO: Implementar lógica de matching y notificaciones
            
            $success = 'Requerimiento publicado exitosamente';
            $action = 'list';
        } catch (Exception $e) {
            $error = 'Error al publicar el requerimiento: ' . $e->getMessage();
        }
    }
}

// Procesar respuesta a requerimiento
if ($action === 'responder' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user['empresa_id']) {
        $error = 'Debes tener una empresa asociada para responder requerimientos';
    } else {
        $data = [
            'mensaje' => sanitize($_POST['mensaje'] ?? ''),
            'propuesta_economica' => $_POST['propuesta_economica'] ?? null,
            'tiempo_entrega_dias' => $_POST['tiempo_entrega_dias'] ?? null,
        ];

        try {
            $sql = "INSERT INTO requerimientos_respuestas (requerimiento_id, empresa_proveedora_id, usuario_id, 
                    mensaje, propuesta_economica, tiempo_entrega_dias) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $id, $user['empresa_id'], $user['id'],
                $data['mensaje'], $data['propuesta_economica'], $data['tiempo_entrega_dias']
            ]);
            
            // Actualizar contador
            $stmt = $db->prepare("UPDATE requerimientos SET respuestas_count = respuestas_count + 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = 'Respuesta enviada exitosamente';
            $action = 'view';
        } catch (Exception $e) {
            $error = 'Error al enviar la respuesta: ' . $e->getMessage();
        }
    }
}

// Obtener requerimiento para vista
if ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT r.*, e.razon_social as empresa_nombre, s.nombre as sector_nombre, 
                         c.nombre as categoria_nombre, u.nombre as usuario_nombre
                         FROM requerimientos r
                         LEFT JOIN empresas e ON r.empresa_solicitante_id = e.id
                         LEFT JOIN sectores s ON r.sector_id = s.id
                         LEFT JOIN categorias c ON r.categoria_id = c.id
                         LEFT JOIN usuarios u ON r.usuario_creador_id = u.id
                         WHERE r.id = ?");
    $stmt->execute([$id]);
    $requerimiento = $stmt->fetch();
    
    if (!$requerimiento) {
        $error = 'Requerimiento no encontrado';
        $action = 'list';
    } else {
        // Obtener respuestas
        $stmt = $db->prepare("SELECT rr.*, e.razon_social as empresa_nombre, u.nombre as usuario_nombre
                             FROM requerimientos_respuestas rr
                             LEFT JOIN empresas e ON rr.empresa_proveedora_id = e.id
                             LEFT JOIN usuarios u ON rr.usuario_id = u.id
                             WHERE rr.requerimiento_id = ?
                             ORDER BY rr.created_at DESC");
        $stmt->execute([$id]);
        $respuestas = $stmt->fetchAll();
        
        // Verificar si el usuario ya respondió
        $stmt = $db->prepare("SELECT id FROM requerimientos_respuestas WHERE requerimiento_id = ? AND empresa_proveedora_id = ?");
        $stmt->execute([$id, $user['empresa_id']]);
        $yaRespondio = $stmt->fetch() ? true : false;
    }
}

// Listar requerimientos
if ($action === 'list') {
    $where = [];
    $params = [];
    
    // Filtrar según tipo de usuario
    if ($user['empresa_id'] && !hasPermission('CONSEJERO')) {
        // Entidades comerciales ven todos los requerimientos abiertos
        $where[] = "r.estado = 'ABIERTO'";
    }
    
    if (!empty($_GET['estado'])) {
        $where[] = "r.estado = ?";
        $params[] = $_GET['estado'];
    }
    
    if (!empty($_GET['sector'])) {
        $where[] = "r.sector_id = ?";
        $params[] = $_GET['sector'];
    }
    
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT r.*, e.razon_social as empresa_nombre, s.nombre as sector_nombre, 
            c.nombre as categoria_nombre
            FROM requerimientos r
            LEFT JOIN empresas e ON r.empresa_solicitante_id = e.id
            LEFT JOIN sectores s ON r.sector_id = s.id
            LEFT JOIN categorias c ON r.categoria_id = c.id
            $whereSql
            ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requerimientos = $stmt->fetchAll();
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Listado de requerimientos -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Requerimientos Comerciales</h1>
        <?php if ($user['empresa_id']): ?>
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nuevo Requerimiento
        </a>
        <?php endif; ?>
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
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <select name="estado" class="px-4 py-2 border rounded-lg">
                <option value="">Todos los estados</option>
                <option value="ABIERTO" <?php echo ($_GET['estado'] ?? '') === 'ABIERTO' ? 'selected' : ''; ?>>Abierto</option>
                <option value="EN_PROCESO" <?php echo ($_GET['estado'] ?? '') === 'EN_PROCESO' ? 'selected' : ''; ?>>En Proceso</option>
                <option value="CERRADO" <?php echo ($_GET['estado'] ?? '') === 'CERRADO' ? 'selected' : ''; ?>>Cerrado</option>
            </select>
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
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter mr-2"></i>Filtrar
            </button>
        </form>
    </div>

    <!-- Grid de requerimientos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($requerimientos as $req): 
            $prioridadColors = [
                'BAJA' => 'gray',
                'MEDIA' => 'blue',
                'ALTA' => 'yellow',
                'URGENTE' => 'red'
            ];
            $prioridadColor = $prioridadColors[$req['prioridad']] ?? 'gray';
            
            $estadoColors = [
                'ABIERTO' => 'green',
                'EN_PROCESO' => 'blue',
                'CERRADO' => 'gray',
                'CANCELADO' => 'red'
            ];
            $estadoColor = $estadoColors[$req['estado']] ?? 'gray';
        ?>
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-xl transition">
            <!-- Header -->
            <div class="flex justify-between items-start mb-4">
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                        <?php echo e($req['titulo']); ?>
                    </h3>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-building mr-1"></i>
                        <?php echo e($req['empresa_nombre']); ?>
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <span class="px-3 py-1 bg-<?php echo $prioridadColor; ?>-100 text-<?php echo $prioridadColor; ?>-800 rounded text-xs font-semibold">
                        <?php echo $req['prioridad']; ?>
                    </span>
                    <span class="px-3 py-1 bg-<?php echo $estadoColor; ?>-100 text-<?php echo $estadoColor; ?>-800 rounded text-xs font-semibold">
                        <?php echo $req['estado']; ?>
                    </span>
                </div>
            </div>

            <!-- Descripción -->
            <p class="text-gray-600 mb-4 line-clamp-3">
                <?php echo e(substr($req['descripcion'], 0, 200)); ?>...
            </p>

            <!-- Metadatos -->
            <div class="space-y-2 mb-4">
                <?php if ($req['sector_nombre']): ?>
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-tag mr-2 text-gray-400"></i>
                    <?php echo e($req['sector_nombre']); ?>
                    <?php if ($req['categoria_nombre']): ?>
                        - <?php echo e($req['categoria_nombre']); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($req['presupuesto_estimado']): ?>
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-dollar-sign mr-2 text-gray-400"></i>
                    Presupuesto: <?php echo formatMoney($req['presupuesto_estimado']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($req['plazo_dias']): ?>
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-clock mr-2 text-gray-400"></i>
                    Plazo: <?php echo $req['plazo_dias']; ?> días
                </div>
                <?php endif; ?>
                
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-comments mr-2 text-gray-400"></i>
                    <?php echo $req['respuestas_count']; ?> respuesta(s)
                </div>
                
                <div class="flex items-center text-sm text-gray-500">
                    <i class="fas fa-calendar mr-2"></i>
                    Publicado: <?php echo formatDate($req['created_at'], 'd/m/Y'); ?>
                </div>
            </div>

            <!-- Palabras clave -->
            <?php if ($req['palabras_clave']): ?>
            <div class="mb-4">
                <?php 
                $keywords = explode(',', $req['palabras_clave']);
                foreach (array_slice($keywords, 0, 5) as $keyword): 
                ?>
                    <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded mr-1 mb-1">
                        <?php echo e(trim($keyword)); ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Botón de acción -->
            <div class="flex gap-2">
                <a href="?action=view&id=<?php echo $req['id']; ?>" 
                   class="flex-1 text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Ver Detalles
                </a>
                <?php if ($req['estado'] === 'ABIERTO' && $user['empresa_id'] && $req['empresa_solicitante_id'] != $user['empresa_id']): ?>
                <a href="?action=responder&id=<?php echo $req['id']; ?>" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-reply"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($requerimientos)): ?>
    <div class="bg-white rounded-lg shadow-md p-12 text-center">
        <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">No hay requerimientos disponibles</p>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'view' && isset($requerimiento)): ?>
<!-- Vista detallada del requerimiento -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <a href="?action=list" class="text-blue-600 hover:underline mb-4 inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Volver a requerimientos
        </a>

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

        <div class="bg-white rounded-lg shadow-md p-8 mb-6">
            <!-- Header del requerimiento -->
            <div class="flex justify-between items-start mb-6">
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo e($requerimiento['titulo']); ?></h1>
                    <p class="text-gray-600">
                        <i class="fas fa-building mr-2"></i><?php echo e($requerimiento['empresa_nombre']); ?>
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded font-semibold text-center">
                        <?php echo $requerimiento['prioridad']; ?>
                    </span>
                    <span class="px-4 py-2 bg-green-100 text-green-800 rounded font-semibold text-center">
                        <?php echo $requerimiento['estado']; ?>
                    </span>
                </div>
            </div>

            <!-- Descripción completa -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-3">Descripción del Requerimiento</h2>
                <p class="text-gray-700 whitespace-pre-line"><?php echo e($requerimiento['descripcion']); ?></p>
            </div>

            <!-- Detalles -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <?php if ($requerimiento['sector_nombre'] || $requerimiento['categoria_nombre']): ?>
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Sector y Categoría</h3>
                    <p class="text-gray-600">
                        <?php echo e($requerimiento['sector_nombre'] ?? 'N/A'); ?>
                        <?php if ($requerimiento['categoria_nombre']): ?>
                            - <?php echo e($requerimiento['categoria_nombre']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if ($requerimiento['presupuesto_estimado']): ?>
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Presupuesto Estimado</h3>
                    <p class="text-gray-600"><?php echo formatMoney($requerimiento['presupuesto_estimado']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($requerimiento['plazo_dias']): ?>
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Plazo de Entrega</h3>
                    <p class="text-gray-600"><?php echo $requerimiento['plazo_dias']; ?> días</p>
                </div>
                <?php endif; ?>
                
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Fecha de Publicación</h3>
                    <p class="text-gray-600"><?php echo formatDate($requerimiento['created_at'], 'd/m/Y H:i'); ?></p>
                </div>
            </div>

            <!-- Palabras clave -->
            <?php if ($requerimiento['palabras_clave']): ?>
            <div class="mb-6">
                <h3 class="font-semibold text-gray-700 mb-2">Palabras Clave</h3>
                <div>
                    <?php 
                    $keywords = explode(',', $requerimiento['palabras_clave']);
                    foreach ($keywords as $keyword): 
                    ?>
                        <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 text-sm rounded mr-2 mb-2">
                            <?php echo e(trim($keyword)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Botón para responder -->
            <?php if ($requerimiento['estado'] === 'ABIERTO' && $user['empresa_id'] && 
                      $requerimiento['empresa_solicitante_id'] != $user['empresa_id'] && !$yaRespondio): ?>
            <div class="border-t pt-6">
                <a href="?action=responder&id=<?php echo $requerimiento['id']; ?>" 
                   class="inline-block bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-reply mr-2"></i>Enviar Propuesta
                </a>
            </div>
            <?php elseif ($yaRespondio): ?>
            <div class="border-t pt-6">
                <p class="text-green-700 bg-green-50 px-4 py-3 rounded">
                    <i class="fas fa-check-circle mr-2"></i>Ya has enviado una propuesta para este requerimiento
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Respuestas -->
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                Propuestas Recibidas (<?php echo count($respuestas); ?>)
            </h2>

            <?php if (!empty($respuestas)): ?>
            <div class="space-y-4">
                <?php foreach ($respuestas as $respuesta): ?>
                <div class="border border-gray-200 rounded-lg p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-800"><?php echo e($respuesta['empresa_nombre']); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo formatDate($respuesta['created_at'], 'd/m/Y H:i'); ?></p>
                        </div>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                            <?php echo $respuesta['estado']; ?>
                        </span>
                    </div>
                    
                    <p class="text-gray-700 mb-4"><?php echo e($respuesta['mensaje']); ?></p>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <?php if ($respuesta['propuesta_economica']): ?>
                        <div>
                            <span class="text-gray-600">Propuesta Económica:</span>
                            <span class="font-semibold text-gray-800">
                                <?php echo formatMoney($respuesta['propuesta_economica']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($respuesta['tiempo_entrega_dias']): ?>
                        <div>
                            <span class="text-gray-600">Tiempo de Entrega:</span>
                            <span class="font-semibold text-gray-800">
                                <?php echo $respuesta['tiempo_entrega_dias']; ?> días
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-center text-gray-500 py-8">Aún no hay propuestas para este requerimiento</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($action === 'new'): ?>
<!-- Formulario de nuevo requerimiento -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Publicar Nuevo Requerimiento</h1>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-lg shadow-md p-8">
            <div class="space-y-6">
                <!-- Título -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Título del Requerimiento *</label>
                    <input type="text" name="titulo" required
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="ej: Proveedor de uniformes empresariales">
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Descripción Detallada *</label>
                    <textarea name="descripcion" required rows="6"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Describe claramente qué estás buscando, especificaciones, requisitos, etc."></textarea>
                </div>

                <!-- Sector y Categoría -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Sector</label>
                        <select name="sector_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Seleccionar...</option>
                            <?php
                            $sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
                            foreach ($sectores as $sector):
                            ?>
                                <option value="<?php echo $sector['id']; ?>"><?php echo e($sector['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Categoría</label>
                        <select name="categoria_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Seleccionar...</option>
                            <?php
                            $categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();
                            foreach ($categorias as $categoria):
                            ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo e($categoria['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Palabras clave -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Palabras Clave</label>
                    <input type="text" name="palabras_clave"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="separadas por comas: uniformes, bordado, ropa">
                    <p class="text-sm text-gray-500 mt-1">Facilita el match con proveedores</p>
                </div>

                <!-- Presupuesto y plazo -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Presupuesto Estimado</label>
                        <input type="number" name="presupuesto_estimado" step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Plazo (días)</label>
                        <input type="number" name="plazo_dias" min="1"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="30">
                    </div>
                </div>

                <!-- Prioridad -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Prioridad</label>
                    <select name="prioridad" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="BAJA">Baja</option>
                        <option value="MEDIA" selected>Media</option>
                        <option value="ALTA">Alta</option>
                        <option value="URGENTE">Urgente</option>
                    </select>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=list" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Publicar Requerimiento
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'responder' && $id): ?>
<!-- Formulario de respuesta -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <?php
        $stmt = $db->prepare("SELECT * FROM requerimientos WHERE id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        ?>
        
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Enviar Propuesta</h1>
        <p class="text-gray-600 mb-6">Para: <?php echo e($req['titulo']); ?></p>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="?action=responder&id=<?php echo $id; ?>" class="bg-white rounded-lg shadow-md p-8">
            <div class="space-y-6">
                <!-- Mensaje -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Propuesta Detallada *</label>
                    <textarea name="mensaje" required rows="8"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Describe tu propuesta, experiencia, capacidades, etc."></textarea>
                </div>

                <!-- Propuesta económica y tiempo -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Propuesta Económica</label>
                        <input type="number" name="propuesta_economica" step="0.01" min="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Tiempo de Entrega (días)</label>
                        <input type="number" name="tiempo_entrega_dias" min="1"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="30">
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=view&id=<?php echo $id; ?>" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-paper-plane mr-2"></i>Enviar Propuesta
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
