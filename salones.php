<?php
/**
 * Módulo de gestión de salones y espacios rentables
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requirePermission('DIRECCION');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Procesar formulario de nuevo salón o edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new', 'edit'])) {
    $data = [
        'nombre' => sanitize($_POST['nombre'] ?? ''),
        'tipo' => sanitize($_POST['tipo'] ?? 'SALON'),
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'capacidad' => intval($_POST['capacidad'] ?? 0),
        'caracteristicas' => sanitize($_POST['caracteristicas'] ?? ''),
        'precio_hora' => floatval($_POST['precio_hora'] ?? 0),
        'precio_dia' => floatval($_POST['precio_dia'] ?? 0),
        'precio_evento' => floatval($_POST['precio_evento'] ?? 0),
        'precio_hora_afiliado' => floatval($_POST['precio_hora_afiliado'] ?? 0),
        'precio_dia_afiliado' => floatval($_POST['precio_dia_afiliado'] ?? 0),
        'precio_evento_afiliado' => floatval($_POST['precio_evento_afiliado'] ?? 0),
        'precio_hora_no_afiliado' => floatval($_POST['precio_hora_no_afiliado'] ?? 0),
        'precio_dia_no_afiliado' => floatval($_POST['precio_dia_no_afiliado'] ?? 0),
        'precio_evento_no_afiliado' => floatval($_POST['precio_evento_no_afiliado'] ?? 0),
        'formas_pago' => sanitize($_POST['formas_pago'] ?? ''),
        'procedimiento_contratacion' => sanitize($_POST['procedimiento_contratacion'] ?? ''),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    try {
        if ($action === 'new') {
            $sql = "INSERT INTO salones (nombre, tipo, descripcion, capacidad, caracteristicas, 
                    precio_hora, precio_dia, precio_evento, 
                    precio_hora_afiliado, precio_dia_afiliado, precio_evento_afiliado,
                    precio_hora_no_afiliado, precio_dia_no_afiliado, precio_evento_no_afiliado,
                    formas_pago, procedimiento_contratacion, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['tipo'], $data['descripcion'], $data['capacidad'],
                $data['caracteristicas'], $data['precio_hora'], $data['precio_dia'], $data['precio_evento'],
                $data['precio_hora_afiliado'], $data['precio_dia_afiliado'], $data['precio_evento_afiliado'],
                $data['precio_hora_no_afiliado'], $data['precio_dia_no_afiliado'], $data['precio_evento_no_afiliado'],
                $data['formas_pago'], $data['procedimiento_contratacion'], $data['activo']
            ]);
            
            $salon_id = $db->lastInsertId();
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'CREATE_SALON', 'salones', ?)");
            $stmt->execute([$user['id'], $salon_id]);
            
            $success = 'Salón registrado exitosamente';
            $action = 'list';
        } else {
            // Editar salón existente
            $sql = "UPDATE salones SET nombre = ?, tipo = ?, descripcion = ?, capacidad = ?, 
                    caracteristicas = ?, precio_hora = ?, precio_dia = ?, precio_evento = ?,
                    precio_hora_afiliado = ?, precio_dia_afiliado = ?, precio_evento_afiliado = ?,
                    precio_hora_no_afiliado = ?, precio_dia_no_afiliado = ?, precio_evento_no_afiliado = ?,
                    formas_pago = ?, procedimiento_contratacion = ?, activo = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['tipo'], $data['descripcion'], $data['capacidad'],
                $data['caracteristicas'], $data['precio_hora'], $data['precio_dia'], $data['precio_evento'],
                $data['precio_hora_afiliado'], $data['precio_dia_afiliado'], $data['precio_evento_afiliado'],
                $data['precio_hora_no_afiliado'], $data['precio_dia_no_afiliado'], $data['precio_evento_no_afiliado'],
                $data['formas_pago'], $data['procedimiento_contratacion'], $data['activo'], $id
            ]);
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'UPDATE_SALON', 'salones', ?)");
            $stmt->execute([$user['id'], $id]);
            
            $success = 'Salón actualizado exitosamente';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error al guardar el salón: ' . $e->getMessage();
    }
}

// Eliminar salón (soft delete - mark as inactive instead of deleting)
if ($action === 'delete' && $id) {
    try {
        // Check for existing reservations
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM salon_reservas WHERE salon_id = ? AND estado != 'CANCELADA'");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $error = 'No se puede eliminar el salón porque tiene reservas activas. Cancele las reservas primero.';
        } else {
            // Soft delete - mark as inactive
            $stmt = $db->prepare("UPDATE salones SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'DELETE_SALON', 'salones', ?)");
            $stmt->execute([$user['id'], $id]);
            
            $success = 'Salón desactivado exitosamente';
        }
        $action = 'list';
    } catch (Exception $e) {
        $error = 'Error al eliminar el salón: ' . $e->getMessage();
    }
}

// Procesar reserva de salón
if ($action === 'reservar' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reserva_data = [
        'salon_id' => $id,
        'empresa_id' => intval($_POST['empresa_id'] ?? 0),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin' => $_POST['fecha_fin'] ?? '',
        'proposito' => sanitize($_POST['proposito'] ?? ''),
        'contacto_nombre' => sanitize($_POST['contacto_nombre'] ?? ''),
        'contacto_email' => sanitize($_POST['contacto_email'] ?? ''),
        'contacto_telefono' => sanitize($_POST['contacto_telefono'] ?? ''),
        'notas' => sanitize($_POST['notas'] ?? ''),
    ];

    try {
        // Verificar disponibilidad
        $stmt = $db->prepare("
            SELECT COUNT(*) as conflictos 
            FROM salon_reservas 
            WHERE salon_id = ? 
            AND estado != 'CANCELADA'
            AND (
                (fecha_inicio BETWEEN ? AND ?) OR
                (fecha_fin BETWEEN ? AND ?) OR
                (? BETWEEN fecha_inicio AND fecha_fin) OR
                (? BETWEEN fecha_inicio AND fecha_fin)
            )
        ");
        $stmt->execute([
            $id,
            $reserva_data['fecha_inicio'], $reserva_data['fecha_fin'],
            $reserva_data['fecha_inicio'], $reserva_data['fecha_fin'],
            $reserva_data['fecha_inicio'], $reserva_data['fecha_fin']
        ]);
        $result = $stmt->fetch();

        if ($result['conflictos'] > 0) {
            $error = 'El salón no está disponible en las fechas seleccionadas';
        } else {
            $sql = "INSERT INTO salon_reservas (salon_id, empresa_id, usuario_id, fecha_inicio, fecha_fin, 
                    proposito, contacto_nombre, contacto_email, contacto_telefono, notas, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $reserva_data['salon_id'],
                $reserva_data['empresa_id'],
                $user['id'],
                $reserva_data['fecha_inicio'],
                $reserva_data['fecha_fin'],
                $reserva_data['proposito'],
                $reserva_data['contacto_nombre'],
                $reserva_data['contacto_email'],
                $reserva_data['contacto_telefono'],
                $reserva_data['notas']
            ]);
            
            $reserva_id = $db->lastInsertId();
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'CREATE_RESERVA_SALON', 'salon_reservas', ?)");
            $stmt->execute([$user['id'], $reserva_id]);
            
            $success = 'Reserva creada exitosamente. Estatus: PENDIENTE de confirmación';
            $action = 'view';
        }
    } catch (Exception $e) {
        $error = 'Error al crear la reserva: ' . $e->getMessage();
    }
}

// Obtener datos para formulario
if (in_array($action, ['new', 'edit'])) {
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM salones WHERE id = ?");
        $stmt->execute([$id]);
        $salon = $stmt->fetch();
        
        if (!$salon) {
            $error = 'Salón no encontrado';
            $action = 'list';
        } else {
            // Obtener imágenes del salón
            $stmt = $db->prepare("SELECT * FROM salon_imagenes WHERE salon_id = ? ORDER BY orden ASC");
            $stmt->execute([$id]);
            $salon_imagenes = $stmt->fetchAll();
        }
    }
}

// Listar salones
if ($action === 'list') {
    $sql = "SELECT * FROM salones ORDER BY tipo ASC, nombre ASC";
    $stmt = $db->query($sql);
    $salones = $stmt->fetchAll();
}

// Ver detalles del salón
if ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT * FROM salones WHERE id = ?");
    $stmt->execute([$id]);
    $salon = $stmt->fetch();
    
    if (!$salon) {
        $error = 'Salón no encontrado';
        $action = 'list';
    } else {
        // Obtener imágenes del salón
        $stmt = $db->prepare("SELECT * FROM salon_imagenes WHERE salon_id = ? ORDER BY orden ASC");
        $stmt->execute([$id]);
        $salon_imagenes = $stmt->fetchAll();
        
        // Obtener reservas del salón
        $stmt = $db->prepare("
            SELECT sr.*, e.razon_social, u.nombre as usuario_nombre
            FROM salon_reservas sr
            LEFT JOIN empresas e ON sr.empresa_id = e.id
            LEFT JOIN usuarios u ON sr.usuario_id = u.id
            WHERE sr.salon_id = ?
            ORDER BY sr.fecha_inicio DESC
        ");
        $stmt->execute([$id]);
        $reservas = $stmt->fetchAll();
    }
}

// Ver calendario de disponibilidad
if ($action === 'calendario' && $id) {
    $stmt = $db->prepare("SELECT * FROM salones WHERE id = ?");
    $stmt->execute([$id]);
    $salon = $stmt->fetch();
    
    if (!$salon) {
        $error = 'Salón no encontrado';
        $action = 'list';
    } else {
        // Obtener reservas para el calendario (próximos 3 meses)
        $stmt = $db->prepare("
            SELECT * FROM salon_reservas 
            WHERE salon_id = ? 
            AND estado != 'CANCELADA'
            AND fecha_inicio >= CURDATE()
            AND fecha_inicio <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
            ORDER BY fecha_inicio ASC
        ");
        $stmt->execute([$id]);
        $reservas_calendario = $stmt->fetchAll();
    }
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Listado de salones -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Gestión de Salones y Espacios</h1>
        <?php if (hasPermission('DIRECCION')): ?>
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nuevo Espacio
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

    <!-- Grid de salones -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($salones as $salon): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?php echo e($salon['nombre']); ?></h3>
                    <span class="px-3 py-1 text-xs rounded-full <?php echo $salon['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $salon['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
                
                <div class="mb-4">
                    <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                        <?php echo e($salon['tipo']); ?>
                    </span>
                </div>
                
                <p class="text-gray-600 text-sm mb-4">
                    <?php echo e(substr($salon['descripcion'], 0, 100)) . (strlen($salon['descripcion']) > 100 ? '...' : ''); ?>
                </p>
                
                <div class="mb-4 space-y-2 text-sm">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-users w-5"></i>
                        <span>Capacidad: <?php echo $salon['capacidad']; ?> personas</span>
                    </div>
                    <?php if ($salon['precio_hora'] > 0): ?>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-clock w-5"></i>
                        <span><?php echo formatMoney($salon['precio_hora']); ?>/hora</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($salon['precio_dia'] > 0): ?>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-calendar-day w-5"></i>
                        <span><?php echo formatMoney($salon['precio_dia']); ?>/día</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex gap-2 mt-4">
                    <a href="?action=view&id=<?php echo $salon['id']; ?>" 
                       class="flex-1 text-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-eye mr-1"></i>Ver
                    </a>
                    <a href="?action=calendario&id=<?php echo $salon['id']; ?>" 
                       class="flex-1 text-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-calendar mr-1"></i>Calendario
                    </a>
                    <?php if (hasPermission('DIRECCION')): ?>
                    <a href="?action=edit&id=<?php echo $salon['id']; ?>" 
                       class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif (in_array($action, ['new', 'edit'])): ?>
<!-- Formulario de salón -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">
            <?php echo $action === 'new' ? 'Nuevo Salón/Espacio' : 'Editar Salón/Espacio'; ?>
        </h1>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-lg shadow-md p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Nombre -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Nombre del Espacio *</label>
                    <input type="text" name="nombre" required
                           value="<?php echo e($salon['nombre'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Tipo -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Tipo de Espacio *</label>
                    <select name="tipo" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="SALON" <?php echo ($salon['tipo'] ?? '') == 'SALON' ? 'selected' : ''; ?>>Salón</option>
                        <option value="OFICINA" <?php echo ($salon['tipo'] ?? '') == 'OFICINA' ? 'selected' : ''; ?>>Oficina</option>
                        <option value="AUDITORIO" <?php echo ($salon['tipo'] ?? '') == 'AUDITORIO' ? 'selected' : ''; ?>>Auditorio</option>
                        <option value="SALA_JUNTAS" <?php echo ($salon['tipo'] ?? '') == 'SALA_JUNTAS' ? 'selected' : ''; ?>>Sala de Juntas</option>
                        <option value="OTRO" <?php echo ($salon['tipo'] ?? '') == 'OTRO' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>

                <!-- Capacidad -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Capacidad (personas) *</label>
                    <input type="number" name="capacidad" required min="1"
                           value="<?php echo e($salon['capacidad'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Descripción -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Descripción</label>
                    <textarea name="descripcion" rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo e($salon['descripcion'] ?? ''); ?></textarea>
                </div>

                <!-- Características -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Características y Equipamiento</label>
                    <textarea name="caracteristicas" rows="4"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Ej: Proyector, WiFi, Aire acondicionado, Pizarra..."><?php echo e($salon['caracteristicas'] ?? ''); ?></textarea>
                </div>

                <!-- Precios (mantener por compatibilidad) -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Hora (Base)</label>
                    <input type="number" name="precio_hora" step="0.01" min="0"
                           value="<?php echo e($salon['precio_hora'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Día (Base)</label>
                    <input type="number" name="precio_dia" step="0.01" min="0"
                           value="<?php echo e($salon['precio_dia'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Evento Completo (Base)</label>
                    <input type="number" name="precio_evento" step="0.01" min="0"
                           value="<?php echo e($salon['precio_evento'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Precios Diferenciados -->
                <div class="md:col-span-2 mt-6">
                    <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">
                        <i class="fas fa-tags mr-2"></i>Precios Diferenciados (Afiliados vs No Afiliados)
                    </h3>
                </div>

                <!-- Precios para Afiliados -->
                <div class="md:col-span-2">
                    <h4 class="text-md font-semibold text-green-700 mb-3">
                        <i class="fas fa-user-check mr-2"></i>Tarifas para Afiliados
                    </h4>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Hora - Afiliados</label>
                    <input type="number" name="precio_hora_afiliado" step="0.01" min="0"
                           value="<?php echo e($salon['precio_hora_afiliado'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Día - Afiliados</label>
                    <input type="number" name="precio_dia_afiliado" step="0.01" min="0"
                           value="<?php echo e($salon['precio_dia_afiliado'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Evento - Afiliados</label>
                    <input type="number" name="precio_evento_afiliado" step="0.01" min="0"
                           value="<?php echo e($salon['precio_evento_afiliado'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>

                <!-- Precios para No Afiliados -->
                <div class="md:col-span-2 mt-4">
                    <h4 class="text-md font-semibold text-orange-700 mb-3">
                        <i class="fas fa-user mr-2"></i>Tarifas para No Afiliados
                    </h4>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Hora - No Afiliados</label>
                    <input type="number" name="precio_hora_no_afiliado" step="0.01" min="0"
                           value="<?php echo e($salon['precio_hora_no_afiliado'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Día - No Afiliados</label>
                    <input type="number" name="precio_dia_no_afiliado" step="0.01" min="0"
                           value="<?php echo e($salon['precio_dia_no_afiliado'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Precio por Evento - No Afiliados</label>
                    <input type="number" name="precio_evento_no_afiliado" step="0.01" min="0"
                           value="<?php echo e($salon['precio_evento_no_afiliado'] ?? '0'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="md:col-span-2 mt-2">
                    <p class="text-sm text-gray-600 italic">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los afiliados también recibirán un descuento adicional según su nivel de membresía.
                    </p>
                </div>

                <!-- Formas de Pago -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Formas de Pago Aceptadas</label>
                    <textarea name="formas_pago" rows="2"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Ej: Efectivo, Transferencia, Tarjeta de crédito/débito"><?php echo e($salon['formas_pago'] ?? ''); ?></textarea>
                </div>

                <!-- Procedimiento de Contratación -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Procedimiento de Contratación</label>
                    <textarea name="procedimiento_contratacion" rows="4"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Describe el proceso para rentar este espacio..."><?php echo e($salon['procedimiento_contratacion'] ?? ''); ?></textarea>
                </div>

                <!-- Activo -->
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" value="1" 
                               <?php echo ($salon['activo'] ?? 1) ? 'checked' : ''; ?>
                               class="mr-2 rounded">
                        <span class="text-gray-700 font-semibold">Espacio activo y disponible</span>
                    </label>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=list" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <?php echo $action === 'new' ? 'Registrar Espacio' : 'Guardar Cambios'; ?>
                </button>
            </div>
        </form>

        <?php if ($action === 'edit' && isset($salon)): ?>
        <!-- Galería de Imágenes -->
        <div class="bg-white rounded-lg shadow-md p-8 mt-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-images mr-2"></i>Galería de Imágenes
            </h2>

            <!-- Subir nueva imagen -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h3 class="font-semibold text-gray-700 mb-3">Subir Nueva Imagen</h3>
                <form id="form-upload-imagen" class="flex gap-4 items-end">
                    <input type="hidden" name="salon_id" value="<?php echo $salon['id']; ?>">
                    <div class="flex-1">
                        <label class="block text-sm text-gray-600 mb-1">Seleccionar Imagen</label>
                        <input type="file" name="imagen" accept="image/*" required
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm text-gray-600 mb-1">Descripción (opcional)</label>
                        <input type="text" name="descripcion" placeholder="Descripción de la imagen"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-upload mr-2"></i>Subir
                    </button>
                </form>
            </div>

            <!-- Listado de imágenes -->
            <div id="galeria-imagenes" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php if (isset($salon_imagenes) && count($salon_imagenes) > 0): ?>
                    <?php foreach ($salon_imagenes as $imagen): ?>
                    <div class="relative group" data-imagen-id="<?php echo $imagen['id']; ?>">
                        <img src="<?php echo BASE_URL . e($imagen['ruta_imagen']); ?>" 
                             alt="<?php echo e($imagen['descripcion']); ?>"
                             class="w-full h-48 object-cover rounded-lg shadow-md">
                        
                        <?php if ($imagen['es_principal']): ?>
                        <span class="absolute top-2 left-2 px-3 py-1 bg-green-600 text-white text-xs rounded-full">
                            <i class="fas fa-star mr-1"></i>Principal
                        </span>
                        <?php endif; ?>
                        
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-3 rounded-b-lg opacity-0 group-hover:opacity-100 transition">
                            <p class="text-white text-sm truncate"><?php echo e($imagen['descripcion']); ?></p>
                        </div>
                        
                        <div class="absolute top-2 right-2 flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <?php if (!$imagen['es_principal']): ?>
                            <button onclick="setPrincipal(<?php echo $imagen['id']; ?>)" 
                                    class="px-3 py-1 bg-yellow-500 text-white text-xs rounded hover:bg-yellow-600"
                                    title="Marcar como principal">
                                <i class="fas fa-star"></i>
                            </button>
                            <?php endif; ?>
                            <button onclick="deleteImagen(<?php echo $imagen['id']; ?>)" 
                                    class="px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600"
                                    title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-3 text-center py-8 text-gray-500">
                        <i class="fas fa-images text-5xl mb-3"></i>
                        <p>No hay imágenes. Sube la primera imagen para este salón.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        // Upload imagen
        document.getElementById('form-upload-imagen').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/salon_imagenes.php?action=upload', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Imagen subida exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error al subir la imagen: ' + error.message);
            }
        });

        // Marcar como principal
        async function setPrincipal(imagenId) {
            if (!confirm('¿Desea marcar esta imagen como principal?')) return;
            
            try {
                const formData = new FormData();
                formData.append('imagen_id', imagenId);
                
                const response = await fetch('<?php echo BASE_URL; ?>/api/salon_imagenes.php?action=set_principal', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Imagen principal actualizada');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        // Eliminar imagen
        async function deleteImagen(imagenId) {
            if (!confirm('¿Está seguro de eliminar esta imagen?')) return;
            
            try {
                const formData = new FormData();
                formData.append('imagen_id', imagenId);
                
                const response = await fetch('<?php echo BASE_URL; ?>/api/salon_imagenes.php?action=delete', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Imagen eliminada');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        </script>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'view' && isset($salon)): ?>
<!-- Vista de detalles del salón -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800"><?php echo e($salon['nombre']); ?></h1>
            <div class="flex gap-2">
                <a href="?action=calendario&id=<?php echo $salon['id']; ?>" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-calendar mr-2"></i>Ver Calendario
                </a>
                <?php if (hasPermission('DIRECCION')): ?>
                <a href="?action=edit&id=<?php echo $salon['id']; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-edit mr-2"></i>Editar
                </a>
                <?php endif; ?>
                <a href="?action=list" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                <p class="text-green-700"><?php echo e($success); ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6">
            <!-- Galería de Imágenes -->
            <?php if (isset($salon_imagenes) && count($salon_imagenes) > 0): ?>
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-images mr-2"></i>Galería de Imágenes
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($salon_imagenes as $imagen): ?>
                    <div class="relative group">
                        <img src="<?php echo BASE_URL . e($imagen['ruta_imagen']); ?>" 
                             alt="<?php echo e($imagen['descripcion']); ?>"
                             class="w-full h-48 object-cover rounded-lg shadow-md cursor-pointer hover:opacity-90 transition"
                             onclick="openImageModal('<?php echo BASE_URL . e($imagen['ruta_imagen']); ?>')">
                        
                        <?php if ($imagen['es_principal']): ?>
                        <span class="absolute top-2 left-2 px-3 py-1 bg-green-600 text-white text-xs rounded-full">
                            <i class="fas fa-star mr-1"></i>Principal
                        </span>
                        <?php endif; ?>
                        
                        <?php if ($imagen['descripcion']): ?>
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-3 rounded-b-lg opacity-0 group-hover:opacity-100 transition">
                            <p class="text-white text-sm"><?php echo e($imagen['descripcion']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Información básica -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div>
                    <p class="text-sm text-gray-600">Tipo</p>
                    <p class="font-semibold text-lg"><?php echo e($salon['tipo']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Capacidad</p>
                    <p class="font-semibold text-lg"><?php echo $salon['capacidad']; ?> personas</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Estatus</p>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold <?php echo $salon['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $salon['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
            </div>

            <?php if ($salon['descripcion']): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Descripción</h3>
                <p class="text-gray-600"><?php echo nl2br(e($salon['descripcion'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($salon['caracteristicas']): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Características y Equipamiento</h3>
                <p class="text-gray-600"><?php echo nl2br(e($salon['caracteristicas'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Precios Diferenciados -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tarifas</h3>
                
                <!-- Tarifas para Afiliados -->
                <div class="mb-6">
                    <h4 class="text-md font-semibold text-green-700 mb-3">
                        <i class="fas fa-user-check mr-2"></i>Tarifas para Afiliados
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-green-50 rounded-lg p-4 border-2 border-green-200">
                            <p class="text-sm text-gray-600">Por Hora</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo formatMoney($salon['precio_hora_afiliado'] ?? $salon['precio_hora']); ?></p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border-2 border-green-200">
                            <p class="text-sm text-gray-600">Por Día</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo formatMoney($salon['precio_dia_afiliado'] ?? $salon['precio_dia']); ?></p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border-2 border-green-200">
                            <p class="text-sm text-gray-600">Evento Completo</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo formatMoney($salon['precio_evento_afiliado'] ?? $salon['precio_evento']); ?></p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2 italic">
                        <i class="fas fa-info-circle mr-1"></i>
                        Descuento adicional según nivel de membresía
                    </p>
                </div>
                
                <!-- Tarifas para No Afiliados -->
                <div>
                    <h4 class="text-md font-semibold text-orange-700 mb-3">
                        <i class="fas fa-user mr-2"></i>Tarifas para No Afiliados
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-orange-50 rounded-lg p-4 border-2 border-orange-200">
                            <p class="text-sm text-gray-600">Por Hora</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo formatMoney($salon['precio_hora_no_afiliado'] ?? $salon['precio_hora']); ?></p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-4 border-2 border-orange-200">
                            <p class="text-sm text-gray-600">Por Día</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo formatMoney($salon['precio_dia_no_afiliado'] ?? $salon['precio_dia']); ?></p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-4 border-2 border-orange-200">
                            <p class="text-sm text-gray-600">Evento Completo</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo formatMoney($salon['precio_evento_no_afiliado'] ?? $salon['precio_evento']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($salon['formas_pago']): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Formas de Pago</h3>
                <p class="text-gray-600"><?php echo nl2br(e($salon['formas_pago'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($salon['procedimiento_contratacion']): ?>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Procedimiento de Contratación</h3>
                <p class="text-gray-600"><?php echo nl2br(e($salon['procedimiento_contratacion'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Reservas recientes -->
            <?php if (!empty($reservas)): ?>
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Reservas</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha Inicio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha Fin</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($reservas as $reserva): ?>
                            <tr>
                                <td class="px-4 py-2 text-sm"><?php echo e($reserva['razon_social'] ?: $reserva['contacto_nombre']); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo formatDate($reserva['fecha_inicio'], 'd/m/Y H:i'); ?></td>
                                <td class="px-4 py-2 text-sm"><?php echo formatDate($reserva['fecha_fin'], 'd/m/Y H:i'); ?></td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php 
                                        echo $reserva['estado'] == 'CONFIRMADA' ? 'bg-green-100 text-green-800' : 
                                             ($reserva['estado'] == 'CANCELADA' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); 
                                        ?>">
                                        <?php echo e($reserva['estado']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($action === 'calendario' && isset($salon)): ?>
<!-- Vista de calendario de disponibilidad -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Calendario: <?php echo e($salon['nombre']); ?></h1>
            <a href="?action=view&id=<?php echo $salon['id']; ?>" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div id="calendario"></div>
            
            <?php if (!empty($reservas_calendario)): ?>
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Próximas Reservas</h3>
                <div class="space-y-3">
                    <?php foreach ($reservas_calendario as $reserva): ?>
                    <div class="border rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo formatDate($reserva['fecha_inicio'], 'd/m/Y H:i'); ?> - <?php echo formatDate($reserva['fecha_fin'], 'H:i'); ?></p>
                                <p class="text-sm text-gray-600 mt-1"><?php echo e($reserva['proposito']); ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php 
                                echo $reserva['estado'] == 'CONFIRMADA' ? 'bg-green-100 text-green-800' : 
                                     ($reserva['estado'] == 'CANCELADA' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); 
                                ?>">
                                <?php echo e($reserva['estado']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="mt-8 text-center p-8 bg-gray-50 rounded-lg">
                <i class="fas fa-calendar-check text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-600">No hay reservas programadas para los próximos 3 meses</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<?php endif; ?>

<!-- Modal para ver imagen en grande -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center p-4" onclick="closeImageModal()">
    <div class="max-w-4xl max-h-full">
        <img id="modalImage" src="" alt="" class="max-w-full max-h-screen object-contain rounded-lg shadow-2xl">
    </div>
</div>

<script>
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
