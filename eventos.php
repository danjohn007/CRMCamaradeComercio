<?php
/**
 * Módulo de gestión de eventos
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

// Procesar inscripción a evento
if ($action === 'inscribir' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/app/helpers/qrcode.php';
    require_once __DIR__ . '/app/helpers/email.php';
    
    try {
        // Verificar que el evento existe y tiene cupo
        $stmt = $db->prepare("SELECT * FROM eventos WHERE id = ? AND activo = 1");
        $stmt->execute([$id]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            $error = 'Evento no encontrado';
        } elseif ($evento['cupo_maximo'] && $evento['inscritos'] >= $evento['cupo_maximo']) {
            $error = 'El evento ya alcanzó su cupo máximo';
        } else {
            // Verificar si ya está inscrito
            $stmt = $db->prepare("SELECT id FROM eventos_inscripciones WHERE evento_id = ? AND usuario_id = ?");
            $stmt->execute([$id, $user['id']]);
            
            if ($stmt->fetch()) {
                $error = 'Ya estás inscrito en este evento';
            } else {
                // Generar código QR único
                $codigo_qr = QRCodeGenerator::generateUniqueCode();
                
                // Obtener datos de empresa si existe
                $empresa_data = null;
                $es_empresa_activa = false;
                if ($user['empresa_id']) {
                    $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
                    $stmt->execute([$user['empresa_id']]);
                    $empresa_data = $stmt->fetch();
                    $es_empresa_activa = ($empresa_data && $empresa_data['activo'] == 1);
                }
                
                // Determinar el precio efectivo del evento (preventa o regular)
                $precio_efectivo = $evento['costo'];
                $ahora = new DateTime();
                
                // Si hay precio de preventa y aún no pasó la fecha límite, usar precio de preventa
                if (isset($evento['precio_preventa']) && $evento['precio_preventa'] > 0 && 
                    !empty($evento['fecha_limite_preventa'])) {
                    $fecha_limite = new DateTime($evento['fecha_limite_preventa']);
                    if ($ahora <= $fecha_limite) {
                        $precio_efectivo = $evento['precio_preventa'];
                    }
                }
                
                // Determinar si requiere pago
                $requiere_pago = false;
                $es_boleto_gratis = false;
                $monto_total = 0;
                
                if ($precio_efectivo > 0) {
                    // Verificar si el evento permite acceso gratis a afiliados
                    $permite_acceso_gratis = isset($evento['acceso_gratis_afiliados']) ? (bool)$evento['acceso_gratis_afiliados'] : true;
                    
                    // Si el evento permite acceso gratis Y la empresa está activa, el boleto es gratis
                    $es_boleto_gratis = $permite_acceso_gratis && $es_empresa_activa;
                    // Si no es boleto gratis, requiere pago
                    $requiere_pago = !$es_boleto_gratis;
                    $monto_total = $requiere_pago ? $precio_efectivo : 0;
                }
                
                // Determinar estado inicial de pago
                $estado_pago_inicial = $requiere_pago ? 'PENDIENTE' : 'SIN_PAGO';
                
                // Inscribir con todos los datos necesarios
                $stmt = $db->prepare("
                    INSERT INTO eventos_inscripciones 
                    (evento_id, usuario_id, empresa_id, nombre_invitado, email_invitado, 
                     razon_social_invitado, codigo_qr, estado, boletos_solicitados, estado_pago, monto_pagado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'CONFIRMADO', 1, ?, ?)
                ");
                $stmt->execute([
                    $id, 
                    $user['id'], 
                    $user['empresa_id'],
                    $user['nombre'],
                    $user['email'],
                    $empresa_data['razon_social'] ?? null,
                    $codigo_qr,
                    $estado_pago_inicial,
                    $monto_total
                ]);
                
                $inscripcion_id = $db->lastInsertId();
                
                // Actualizar contador
                $stmt = $db->prepare("UPDATE eventos SET inscritos = inscritos + 1 WHERE id = ?");
                $stmt->execute([$id]);
                
                // Obtener inscripción completa
                $stmt = $db->prepare("SELECT * FROM eventos_inscripciones WHERE id = ?");
                $stmt->execute([$inscripcion_id]);
                $inscripcion = $stmt->fetch();
                
                // Generar y guardar imagen QR
                $qrCodePath = QRCodeGenerator::saveQRImage(
                    BASE_URL . '/boleto_digital.php?codigo=' . $codigo_qr,
                    $codigo_qr
                );
                
                // Enviar email de confirmación según el estado del pago
                try {
                    if (!$requiere_pago) {
                        // Evento gratuito o empresa afiliada con 1 boleto - enviar confirmación con boleto
                        EmailHelper::sendEventRegistrationConfirmation($inscripcion, $evento, false, 0, $qrCodePath);
                        
                        // Actualizar que el boleto fue enviado
                        $stmt = $db->prepare("UPDATE eventos_inscripciones SET boleto_enviado = 1, fecha_envio_boleto = NOW() WHERE id = ?");
                        $stmt->execute([$inscripcion_id]);
                        
                        if ($es_boleto_gratis) {
                            $success = "¡Inscripción exitosa! Como empresa afiliada, tu boleto es gratuito. Se ha enviado un correo de confirmación con tu boleto digital. ";
                        } else {
                            $success = "¡Inscripción exitosa! Este evento es gratuito. Se ha enviado un correo de confirmación con tu boleto digital. ";
                        }
                        $success .= "<a href='" . BASE_URL . "/boleto_digital.php?codigo=" . urlencode($codigo_qr) . "' target='_blank' class='underline font-bold'>Ver Boleto Digital</a>";
                    } else {
                        // Requiere pago - enviar confirmación con link de pago
                        EmailHelper::sendEventRegistrationConfirmation($inscripcion, $evento, true, $monto_total);
                        
                        $success = "¡Registro exitoso! Se ha enviado un correo de confirmación a tu email. Para completar tu inscripción y obtener ";
                        if ($es_boleto_gratis) {
                            $success .= "tus boletos adicionales";
                        } else {
                            $success .= "tus boletos";
                        }
                        $success .= ", por favor realiza el pago de <strong>\$" . number_format($monto_total, 2) . " MXN</strong>. ";
                        $success .= "<a href='" . BASE_URL . "/boleto_digital.php?codigo=" . urlencode($codigo_qr) . "' target='_blank' class='underline font-bold'>Ir a Pagar</a>";
                    }
                } catch (Exception $e) {
                    // Si falla el envío de email, aún así la inscripción fue exitosa
                    $success = "¡Inscripción exitosa! ";
                    if ($requiere_pago) {
                        $success .= "Para completar tu inscripción, realiza el pago de <strong>\$" . number_format($monto_total, 2) . " MXN</strong>. ";
                    }
                    $success .= "<a href='" . BASE_URL . "/boleto_digital.php?codigo=" . urlencode($codigo_qr) . "' target='_blank' class='underline font-bold'>Ver Detalles</a>";
                    error_log("Error al enviar email de confirmación: " . $e->getMessage());
                }
                
                // Cambiar acción a 'view' para mostrar la página del evento con el mensaje
                $action = 'view';
            }
        }
    } catch (Exception $e) {
        $error = 'Error al procesar la inscripción: ' . $e->getMessage();
        $action = 'view';
    }
}

// Verificar si las columnas de costo y preventa existen en la tabla eventos
$costo_column_exists = false;
$preventa_columns_exist = false;
$acceso_gratis_column_exists = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM eventos LIKE 'costo'");
    $costo_column_exists = $stmt->fetch() !== false;
    
    $stmt = $db->query("SHOW COLUMNS FROM eventos LIKE 'precio_preventa'");
    $preventa_columns_exist = $stmt->fetch() !== false;
    
    $stmt = $db->query("SHOW COLUMNS FROM eventos LIKE 'acceso_gratis_afiliados'");
    $acceso_gratis_column_exists = $stmt->fetch() !== false;
} catch (Exception $e) {
    $costo_column_exists = false;
    $preventa_columns_exist = false;
    $acceso_gratis_column_exists = false;
}

// Procesar formulario de nuevo evento o edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new', 'edit']) && hasPermission('DIRECCION')) {
    $data = [
        'titulo' => sanitize($_POST['titulo'] ?? ''),
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? null,
        'fecha_fin' => $_POST['fecha_fin'] ?? null,
        'ubicacion' => sanitize($_POST['ubicacion'] ?? ''),
        'tipo' => $_POST['tipo'] ?? 'PUBLICO',
        'cupo_maximo' => $_POST['cupo_maximo'] ? (int)$_POST['cupo_maximo'] : null,
        'costo' => ($costo_column_exists && isset($_POST['costo'])) ? (float)$_POST['costo'] : 0,
        'precio_preventa' => ($preventa_columns_exist && isset($_POST['precio_preventa']) && $_POST['precio_preventa'] !== '') ? (float)$_POST['precio_preventa'] : null,
        'fecha_limite_preventa' => ($preventa_columns_exist && !empty($_POST['fecha_limite_preventa'])) ? $_POST['fecha_limite_preventa'] : null,
        'requiere_inscripcion' => isset($_POST['requiere_inscripcion']) ? 1 : 0,
        'acceso_gratis_afiliados' => ($acceso_gratis_column_exists && isset($_POST['acceso_gratis_afiliados'])) ? 1 : 0,
        'imagen' => null
    ];

    // Procesar imagen si se subió
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['imagen'], ['jpg', 'jpeg', 'png', 'gif'], 5242880); // 5MB
        if ($result['success']) {
            $data['imagen'] = $result['filename'];
        } else {
            $error = $result['message'];
        }
    }

    if (!$error) {
        try {
            if ($action === 'new') {
                // Construir SQL dinámicamente basado en si existen las columnas costo, preventa y acceso_gratis
                if ($costo_column_exists && $preventa_columns_exist && $acceso_gratis_column_exists) {
                    $sql = "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, tipo, cupo_maximo, costo, precio_preventa, fecha_limite_preventa, imagen, requiere_inscripcion, acceso_gratis_afiliados, creado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                        $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'], 
                        $data['precio_preventa'], $data['fecha_limite_preventa'], $data['imagen'],
                        $data['requiere_inscripcion'], $data['acceso_gratis_afiliados'], $user['id']
                    ];
                } elseif ($costo_column_exists && $preventa_columns_exist) {
                    $sql = "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, tipo, cupo_maximo, costo, precio_preventa, fecha_limite_preventa, imagen, requiere_inscripcion, creado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                        $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'], 
                        $data['precio_preventa'], $data['fecha_limite_preventa'], $data['imagen'],
                        $data['requiere_inscripcion'], $user['id']
                    ];
                } elseif ($costo_column_exists) {
                    $sql = "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, tipo, cupo_maximo, costo, imagen, requiere_inscripcion, creado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                        $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'], $data['imagen'],
                        $data['requiere_inscripcion'], $user['id']
                    ];
                } else {
                    $sql = "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, tipo, cupo_maximo, imagen, requiere_inscripcion, creado_por) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                        $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['imagen'],
                        $data['requiere_inscripcion'], $user['id']
                    ];
                }
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $success = 'Evento creado exitosamente';
                $action = 'list';
            } else {
                // UPDATE para edición
                if ($data['imagen']) {
                    // Con nueva imagen
                    if ($costo_column_exists && $preventa_columns_exist && $acceso_gratis_column_exists) {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, costo = ?, precio_preventa = ?, fecha_limite_preventa = ?, imagen = ?, requiere_inscripcion = ?, acceso_gratis_afiliados = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'], 
                            $data['precio_preventa'], $data['fecha_limite_preventa'], $data['imagen'],
                            $data['requiere_inscripcion'], $data['acceso_gratis_afiliados'], $id
                        ];
                    } elseif ($costo_column_exists && $preventa_columns_exist) {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, costo = ?, precio_preventa = ?, fecha_limite_preventa = ?, imagen = ?, requiere_inscripcion = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'], 
                            $data['precio_preventa'], $data['fecha_limite_preventa'], $data['imagen'],
                            $data['requiere_inscripcion'], $id
                        ];
                    } elseif ($costo_column_exists) {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, costo = ?, imagen = ?, requiere_inscripcion = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'], $data['imagen'],
                            $data['requiere_inscripcion'], $id
                        ];
                    } else {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, imagen = ?, requiere_inscripcion = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['imagen'],
                            $data['requiere_inscripcion'], $id
                        ];
                    }
                } else {
                    // Sin nueva imagen
                    if ($costo_column_exists && $preventa_columns_exist && $acceso_gratis_column_exists) {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, costo = ?, precio_preventa = ?, fecha_limite_preventa = ?, requiere_inscripcion = ?, acceso_gratis_afiliados = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'],
                            $data['precio_preventa'], $data['fecha_limite_preventa'],
                            $data['requiere_inscripcion'], $data['acceso_gratis_afiliados'], $id
                        ];
                    } elseif ($costo_column_exists && $preventa_columns_exist) {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, costo = ?, precio_preventa = ?, fecha_limite_preventa = ?, requiere_inscripcion = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'],
                            $data['precio_preventa'], $data['fecha_limite_preventa'],
                            $data['requiere_inscripcion'], $id
                        ];
                    } elseif ($costo_column_exists) {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, costo = ?, requiere_inscripcion = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'], $data['costo'],
                            $data['requiere_inscripcion'], $id
                        ];
                    } else {
                        $sql = "UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                                ubicacion = ?, tipo = ?, cupo_maximo = ?, requiere_inscripcion = ? WHERE id = ?";
                        $params = [
                            $data['titulo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                            $data['ubicacion'], $data['tipo'], $data['cupo_maximo'],
                            $data['requiere_inscripcion'], $id
                        ];
                    }
                }
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $success = 'Evento actualizado exitosamente';
                $action = 'list';
            }
        } catch (Exception $e) {
            $error = 'Error al guardar el evento: ' . $e->getMessage();
        }
    }
}

// Obtener evento para edición o vista
if ($action === 'edit' && $id && hasPermission('DIRECCION')) {
    $stmt = $db->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->execute([$id]);
    $evento = $stmt->fetch();
    
    if (!$evento) {
        $error = 'Evento no encontrado';
        $action = 'list';
    }
} elseif ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT e.*, u.nombre as creador_nombre FROM eventos e 
                         LEFT JOIN usuarios u ON e.creado_por = u.id WHERE e.id = ?");
    $stmt->execute([$id]);
    $evento = $stmt->fetch();
    
    if (!$evento) {
        $error = 'Evento no encontrado';
        $action = 'list';
    } else {
        // Verificar si el usuario está inscrito
        $stmt = $db->prepare("SELECT id FROM eventos_inscripciones WHERE evento_id = ? AND usuario_id = ?");
        $stmt->execute([$id, $user['id']]);
        $yaInscrito = $stmt->fetch() ? true : false;
    }
}

// Listar eventos
if ($action === 'list') {
    $where = ["e.activo = 1"];
    $params = [];
    
    // Filtrar por tipo según permisos
    if (!hasPermission('CONSEJERO')) {
        $where[] = "e.tipo = 'PUBLICO'";
    }
    
    if (!empty($_GET['tipo'])) {
        $where[] = "e.tipo = ?";
        $params[] = $_GET['tipo'];
    }
    
    $whereSql = implode(' AND ', $where);
    
    $sql = "SELECT e.*, COUNT(ei.id) as total_inscritos 
            FROM eventos e
            LEFT JOIN eventos_inscripciones ei ON e.id = ei.evento_id
            WHERE $whereSql
            GROUP BY e.id
            ORDER BY e.fecha_inicio DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll();
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Listado de eventos -->
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Calendario de Eventos</h1>
        <?php if (hasPermission('DIRECCION')): ?>
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Nuevo Evento
        </a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo $success; /* Contains safe HTML link */ ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <?php if (hasPermission('CONSEJERO')): ?>
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="flex gap-4">
            <select name="tipo" class="px-4 py-2 border rounded-lg">
                <option value="">Todos los tipos</option>
                <option value="PUBLICO" <?php echo ($_GET['tipo'] ?? '') === 'PUBLICO' ? 'selected' : ''; ?>>Público</option>
                <option value="INTERNO" <?php echo ($_GET['tipo'] ?? '') === 'INTERNO' ? 'selected' : ''; ?>>Interno</option>
                <option value="CONSEJO" <?php echo ($_GET['tipo'] ?? '') === 'CONSEJO' ? 'selected' : ''; ?>>Consejo</option>
                <option value="REUNION" <?php echo ($_GET['tipo'] ?? '') === 'REUNION' ? 'selected' : ''; ?>>Reunión</option>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Filtrar
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Grid de eventos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($eventos as $evento): 
            $fechaInicio = new DateTime($evento['fecha_inicio']);
            $fechaFin = new DateTime($evento['fecha_fin']);
            $ahora = new DateTime();
            $yaInicio = $fechaInicio <= $ahora;
            $yaTermino = $fechaFin < $ahora;
        ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition">
            <!-- Encabezado con fecha -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-3xl font-bold"><?php echo $fechaInicio->format('d'); ?></div>
                        <div class="text-sm"><?php echo $fechaInicio->format('M Y'); ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs opacity-90">
                            <?php echo $fechaInicio->format('H:i'); ?> - <?php echo $fechaFin->format('H:i'); ?>
                        </div>
                        <span class="inline-block px-2 py-1 bg-white bg-opacity-20 rounded text-xs mt-1">
                            <?php echo e($evento['tipo']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Contenido -->
            <div class="p-4">
                <h3 class="font-bold text-lg text-gray-800 mb-2">
                    <?php echo e($evento['titulo']); ?>
                </h3>
                <p class="text-gray-600 text-sm mb-3 line-clamp-3">
                    <?php echo e(substr($evento['descripcion'], 0, 150)); ?>...
                </p>
                
                <?php if ($evento['ubicacion']): ?>
                <div class="flex items-center text-sm text-gray-500 mb-2">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <?php echo e($evento['ubicacion']); ?>
                </div>
                <?php endif; ?>

                <?php if ($evento['cupo_maximo']): ?>
                <div class="flex items-center text-sm text-gray-500 mb-3">
                    <i class="fas fa-users mr-2"></i>
                    <?php echo $evento['total_inscritos']; ?> / <?php echo $evento['cupo_maximo']; ?> inscritos
                </div>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="flex gap-2 mt-4">
                    <a href="?action=view&id=<?php echo $evento['id']; ?>" 
                       class="flex-1 text-center px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                        Ver Detalles
                    </a>
                    <?php if (hasPermission('DIRECCION')): ?>
                    <a href="?action=edit&id=<?php echo $evento['id']; ?>" 
                       class="px-4 py-2 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if ($yaTermino): ?>
                <div class="mt-2 text-center">
                    <span class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded text-xs">
                        Evento finalizado
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($eventos)): ?>
    <div class="bg-white rounded-lg shadow-md p-12 text-center">
        <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">No hay eventos disponibles</p>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'view' && isset($evento)): ?>
<!-- Vista detallada del evento -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <a href="?action=list" class="text-blue-600 hover:underline mb-4 inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Volver a eventos
        </a>

        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                <p class="text-green-700"><?php echo $success; /* Contains safe HTML link */ ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Imagen del evento -->
            <?php if ($evento['imagen']): ?>
            <div class="w-full">
                <img src="<?php echo BASE_URL . '/public/uploads/' . e($evento['imagen']); ?>" 
                     alt="<?php echo e($evento['titulo']); ?>"
                     class="w-full h-64 object-cover">
            </div>
            <?php endif; ?>
            
            <!-- Banner del evento -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-8">
                <span class="inline-block px-3 py-1 bg-white bg-opacity-20 rounded text-sm mb-3">
                    <?php echo e($evento['tipo']); ?>
                </span>
                <h1 class="text-3xl font-bold mb-2"><?php echo e($evento['titulo']); ?></h1>
                <div class="flex items-center space-x-6 text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-calendar mr-2"></i>
                        <?php echo formatDate($evento['fecha_inicio'], 'd/m/Y'); ?>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        <?php echo formatDate($evento['fecha_inicio'], 'H:i'); ?> - <?php echo formatDate($evento['fecha_fin'], 'H:i'); ?>
                    </div>
                </div>
            </div>

            <!-- Contenido del evento -->
            <div class="p-8">
                <div class="prose max-w-none mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Descripción</h2>
                    <p class="text-gray-600 whitespace-pre-line"><?php echo e($evento['descripcion']); ?></p>
                </div>

                <?php if ($evento['ubicacion']): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Ubicación</h2>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-map-marker-alt mr-3 text-red-500"></i>
                        <?php echo e($evento['ubicacion']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($evento['costo']) && $evento['costo'] > 0): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Costo</h2>
                    <?php
                    // Determinar el precio efectivo (preventa o regular)
                    $precio_efectivo = $evento['costo'];
                    $mostrar_preventa = false;
                    $ahora_view = new DateTime();
                    
                    if (isset($evento['precio_preventa']) && $evento['precio_preventa'] > 0 && 
                        !empty($evento['fecha_limite_preventa'])) {
                        $fecha_limite_view = new DateTime($evento['fecha_limite_preventa']);
                        if ($ahora_view <= $fecha_limite_view) {
                            $precio_efectivo = $evento['precio_preventa'];
                            $mostrar_preventa = true;
                        }
                    }
                    ?>
                    
                    <?php if ($mostrar_preventa): ?>
                        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center text-yellow-800 font-bold text-xl mb-1">
                                        <i class="fas fa-tag mr-2"></i>
                                        <span>¡PRECIO DE PREVENTA!</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-3xl font-bold text-green-600">$<?php echo number_format($evento['precio_preventa'], 2); ?> MXN</span>
                                        <span class="ml-3 text-gray-500 line-through">$<?php echo number_format($evento['costo'], 2); ?> MXN</span>
                                    </div>
                                    <div class="text-sm text-yellow-700 mt-2">
                                        <i class="fas fa-clock mr-1"></i>
                                        Válido hasta: <?php echo formatDate($evento['fecha_limite_preventa'], 'd/m/Y H:i'); ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="bg-red-500 text-white px-4 py-2 rounded-lg font-bold">
                                        AHORRA<br>$<?php echo number_format($evento['costo'] - $evento['precio_preventa'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-dollar-sign mr-3 text-green-500"></i>
                            <span class="font-semibold text-2xl">$<?php echo number_format($evento['costo'], 2); ?> MXN</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Enlace público de registro (solo para administradores) -->
                <?php if (hasPermission('DIRECCION')): ?>
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">
                        <i class="fas fa-link mr-2 text-blue-600"></i>
                        Enlace Público de Registro
                    </h2>
                    <p class="text-sm text-gray-600 mb-3">Comparte este enlace para que los invitados puedan registrarse sin iniciar sesión:</p>
                    <div class="flex items-center space-x-2">
                        <input type="text" readonly
                               value="<?php echo BASE_URL; ?>/evento_publico.php?evento=<?php echo $evento['id']; ?>"
                               id="enlacePublico"
                               class="flex-1 px-4 py-2 border rounded-lg bg-white">
                        <button onclick="copiarEnlace()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
                <script>
                function copiarEnlace() {
                    const input = document.getElementById('enlacePublico');
                    input.select();
                    document.execCommand('copy');
                    alert('¡Enlace copiado al portapapeles!');
                }
                </script>
                <?php endif; ?>

                <?php if ($evento['cupo_maximo']): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Disponibilidad</h2>
                    <div class="flex items-center">
                        <div class="flex-1">
                            <div class="bg-gray-200 rounded-full h-4 overflow-hidden">
                                <div class="bg-blue-600 h-full" style="width: <?php echo ($evento['inscritos'] / $evento['cupo_maximo']) * 100; ?>%"></div>
                            </div>
                        </div>
                        <span class="ml-4 text-gray-600 font-semibold">
                            <?php echo $evento['inscritos']; ?> / <?php echo $evento['cupo_maximo']; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ver participantes (solo para administradores) -->
                <?php if (hasPermission('DIRECCION') && $evento['inscritos'] > 0): ?>
                <div class="mb-6">
                    <button onclick="verParticipantes(<?php echo $evento['id']; ?>)" 
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                        <i class="fas fa-users mr-2"></i>Ver Participantes (<?php echo $evento['inscritos']; ?>)
                    </button>
                </div>
                <?php endif; ?>

                <!-- Botón de inscripción -->
                <?php if ($evento['requiere_inscripcion'] && !$yaInscrito): ?>
                    <?php 
                    $fechaInicio = new DateTime($evento['fecha_inicio']);
                    $ahora = new DateTime();
                    $puedeInscribirse = $fechaInicio > $ahora && (!$evento['cupo_maximo'] || $evento['inscritos'] < $evento['cupo_maximo']);
                    ?>
                    
                    <?php if ($puedeInscribirse): ?>
                    <form method="POST" action="?action=inscribir&id=<?php echo $evento['id']; ?>">
                        <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition">
                            <i class="fas fa-check-circle mr-2"></i>Inscribirme al Evento
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="text-center py-3 bg-gray-100 rounded-lg text-gray-600">
                        <?php if ($evento['cupo_maximo'] && $evento['inscritos'] >= $evento['cupo_maximo']): ?>
                            <i class="fas fa-exclamation-circle mr-2"></i>Cupo lleno
                        <?php else: ?>
                            <i class="fas fa-calendar-times mr-2"></i>Inscripciones cerradas
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php elseif ($yaInscrito): ?>
                <div class="text-center py-3 bg-green-100 text-green-700 rounded-lg font-semibold">
                    <i class="fas fa-check-circle mr-2"></i>Ya estás inscrito en este evento
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif (in_array($action, ['new', 'edit']) && hasPermission('DIRECCION')): ?>
<!-- Formulario de evento -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">
            <?php echo $action === 'new' ? 'Nuevo Evento' : 'Editar Evento'; ?>
        </h1>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-8">
            <div class="space-y-6">
                <!-- Título -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Título del Evento *</label>
                    <input type="text" name="titulo" required
                           value="<?php echo e($evento['titulo'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Descripción *</label>
                    <textarea name="descripcion" required rows="6"
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo e($evento['descripcion'] ?? ''); ?></textarea>
                </div>

                <!-- Fechas -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Fecha y Hora de Inicio *</label>
                        <input type="datetime-local" name="fecha_inicio" required
                               value="<?php echo isset($evento['fecha_inicio']) ? date('Y-m-d\TH:i', strtotime($evento['fecha_inicio'])) : ''; ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Fecha y Hora de Fin *</label>
                        <input type="datetime-local" name="fecha_fin" required
                               value="<?php echo isset($evento['fecha_fin']) ? date('Y-m-d\TH:i', strtotime($evento['fecha_fin'])) : ''; ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Ubicación -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Ubicación</label>
                    <input type="text" name="ubicacion"
                           value="<?php echo e($evento['ubicacion'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Tipo -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Tipo de Evento *</label>
                    <select name="tipo" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="PUBLICO" <?php echo ($evento['tipo'] ?? '') === 'PUBLICO' ? 'selected' : ''; ?>>Público</option>
                        <option value="INTERNO" <?php echo ($evento['tipo'] ?? '') === 'INTERNO' ? 'selected' : ''; ?>>Interno</option>
                        <option value="CONSEJO" <?php echo ($evento['tipo'] ?? '') === 'CONSEJO' ? 'selected' : ''; ?>>Consejo</option>
                        <option value="REUNION" <?php echo ($evento['tipo'] ?? '') === 'REUNION' ? 'selected' : ''; ?>>Reunión</option>
                    </select>
                </div>

                <!-- Cupo Máximo -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Cupo Máximo (dejar vacío para ilimitado)</label>
                    <input type="number" name="cupo_maximo" min="1"
                           value="<?php echo e($evento['cupo_maximo'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Costo y Preventa -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-dollar-sign mr-2 text-blue-600"></i>
                        Configuración de Precios
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Costo del Evento (MXN)</label>
                            <input type="number" name="costo" min="0" step="0.01"
                                   value="<?php echo e($evento['costo'] ?? '0'); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="0.00">
                            <p class="text-sm text-gray-500 mt-1">Precio regular del boleto</p>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Precio de Preventa (MXN)</label>
                            <input type="number" name="precio_preventa" min="0" step="0.01"
                                   value="<?php echo e($evento['precio_preventa'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="0.00">
                            <p class="text-sm text-gray-500 mt-1">Precio especial hasta la fecha límite</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Fecha Límite de Preventa</label>
                        <input type="datetime-local" name="fecha_limite_preventa"
                               value="<?php echo isset($evento['fecha_limite_preventa']) ? date('Y-m-d\TH:i', strtotime($evento['fecha_limite_preventa'])) : ''; ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Después de esta fecha, se cobrará el precio regular
                        </p>
                    </div>
                    
                    <!-- Acceso gratis para afiliados -->
                    <div class="mt-4 border-t pt-4">
                        <label class="flex items-start">
                            <input type="checkbox" name="acceso_gratis_afiliados" value="1"
                                   <?php echo (!isset($evento['acceso_gratis_afiliados']) || $evento['acceso_gratis_afiliados']) ? 'checked' : ''; ?>
                                   class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3">
                                <span class="block text-gray-700 font-semibold">Acceso gratis para afiliados vigentes</span>
                                <span class="block text-sm text-gray-600 mt-1">
                                    Si está marcado, los afiliados con membresía vigente recibirán 1 boleto gratis.
                                    Si no está marcado, todos los asistentes deberán pagar (incluyendo afiliados).
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Imagen del evento -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Imagen del Evento</label>
                    <?php if (!empty($evento['imagen'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo BASE_URL . '/public/uploads/' . e($evento['imagen']); ?>" 
                                 alt="Imagen actual" class="w-48 h-auto rounded border">
                            <p class="text-sm text-gray-600 mt-1">Imagen actual</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="imagen" accept="image/*"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Formatos permitidos: JPG, PNG, GIF (máx. 5MB)</p>
                </div>

                <!-- Requiere inscripción -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="requiere_inscripcion" value="1"
                               <?php echo ($evento['requiere_inscripcion'] ?? 0) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Requiere inscripción previa</span>
                    </label>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4 mt-8">
                <a href="?action=list" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <?php echo $action === 'new' ? 'Crear Evento' : 'Guardar Cambios'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal para Ver Participantes -->
<div id="modalParticipantes" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-800">Participantes del Evento</h3>
            <button onclick="cerrarModalParticipantes()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div id="participantesContenido" class="mb-4">
            <!-- Se llenará con JavaScript -->
        </div>
        
        <div class="flex justify-end">
            <button onclick="cerrarModalParticipantes()" 
                    class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
async function verParticipantes(eventoId) {
    const modal = document.getElementById('modalParticipantes');
    const contenido = document.getElementById('participantesContenido');
    
    // Mostrar loader
    contenido.innerHTML = `
        <div class="flex justify-center items-center py-8">
            <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
        </div>
    `;
    modal.classList.remove('hidden');
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/api/evento_participantes.php?evento_id=' + eventoId);
        const data = await response.json();
        
        if (data.success) {
            let html = '';
            
            if (data.participantes.length === 0) {
                html = `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-users text-4xl mb-3"></i>
                        <p>No hay participantes inscritos aún</p>
                    </div>
                `;
            } else {
                html = `
                    <div class="mb-4 text-gray-600">
                        <strong>${data.participantes.length}</strong> participante(s) inscrito(s)
                        <span class="ml-4">-</span>
                        <strong class="ml-2">${data.total_boletos || 0}</strong> boleto(s) total
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Boletos</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Inscripción</th>
                                    ${data.tiene_costo ? '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado Pago</th>' : ''}
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                `;
                
                data.participantes.forEach(p => {
                    html += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-800">${p.nombre}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${p.email || '-'}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${p.empresa || '-'}</td>
                            <td class="px-4 py-3 text-sm text-gray-800 font-semibold">${p.boletos_solicitados || 1}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${formatFecha(p.fecha_inscripcion)}</td>
                    `;
                    
                    if (data.tiene_costo) {
                        let estadoClass = 'bg-gray-100 text-gray-800';
                        let estadoTexto = p.estado_pago;
                        
                        if (p.estado_pago === 'COMPLETADO') {
                            estadoClass = 'bg-green-100 text-green-800';
                            estadoTexto = 'Pagado';
                        } else if (p.estado_pago === 'PENDIENTE') {
                            estadoClass = 'bg-yellow-100 text-yellow-800';
                            estadoTexto = 'Pendiente';
                        } else if (p.estado_pago === 'CANCELADO') {
                            estadoClass = 'bg-red-100 text-red-800';
                            estadoTexto = 'Cancelado';
                        }
                        
                        html += `
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold ${estadoClass}">
                                    ${estadoTexto}
                                </span>
                                ${p.monto_pagado > 0 ? '<br><span class="text-xs text-gray-500">$' + parseFloat(p.monto_pagado).toFixed(2) + '</span>' : ''}
                            </td>
                        `;
                    }
                    
                    html += '</tr>';
                });
                
                html += `
                            </tbody>
                            <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-800 text-right">TOTAL DE BOLETOS:</td>
                                    <td class="px-4 py-3 text-sm font-bold text-blue-600">${data.total_boletos || 0}</td>
                                    <td colspan="${data.tiene_costo ? '2' : '1'}"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;
            }
            
            contenido.innerHTML = html;
        } else {
            throw new Error(data.error || 'Error al cargar participantes');
        }
    } catch (error) {
        contenido.innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <p class="text-red-700">${error.message}</p>
            </div>
        `;
    }
}

function cerrarModalParticipantes() {
    document.getElementById('modalParticipantes').classList.add('hidden');
}

function formatFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalParticipantes')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalParticipantes();
    }
});
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
