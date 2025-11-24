<?php
/**
 * API para gestión de reservas de salones con pagos
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

header('Content-Type: application/json');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'calcular_precio':
            // Calcular precio de reserva según afiliación y membresía
            $salon_id = intval($_POST['salon_id'] ?? 0);
            $tipo_tarifa = sanitize($_POST['tipo_tarifa'] ?? 'hora'); // hora, dia, evento
            $empresa_id = $user['empresa_id'] ?? null;
            
            if (!$salon_id) {
                throw new Exception('ID de salón requerido');
            }
            
            // Obtener información del salón
            $stmt = $db->prepare("SELECT * FROM salones WHERE id = ? AND activo = 1");
            $stmt->execute([$salon_id]);
            $salon = $stmt->fetch();
            
            if (!$salon) {
                throw new Exception('Salón no encontrado o no disponible');
            }
            
            // Determinar si es afiliado y obtener descuento de membresía
            // The descuento_salones column is added by 20251124_salon_enhancements.sql migration
            $es_afiliado = false;
            $descuento_porcentaje = 0;
            $nivel_membresia = null;
            
            if ($empresa_id) {
                // Try with descuento_salones column (enhanced schema)
                try {
                    $stmt = $db->prepare("
                        SELECT e.membresia_id, m.nombre as nombre_membresia, m.descuento_salones
                        FROM empresas e
                        LEFT JOIN membresias m ON e.membresia_id = m.id
                        WHERE e.id = ? AND e.activo = 1
                    ");
                    $stmt->execute([$empresa_id]);
                    $empresa = $stmt->fetch();
                    
                    if ($empresa && $empresa['membresia_id']) {
                        $es_afiliado = true;
                        $descuento_porcentaje = floatval($empresa['descuento_salones'] ?? 0);
                        $nivel_membresia = $empresa['nombre_membresia'];
                    }
                } catch (PDOException $e) {
                    // Only handle "unknown column" error (42S22), rethrow others
                    if ($e->getCode() !== '42S22') {
                        throw $e;
                    }
                    // descuento_salones column doesn't exist - migration not applied
                    // Fallback to basic query without discount column
                    $stmt = $db->prepare("
                        SELECT e.membresia_id, m.nombre as nombre_membresia
                        FROM empresas e
                        LEFT JOIN membresias m ON e.membresia_id = m.id
                        WHERE e.id = ? AND e.activo = 1
                    ");
                    $stmt->execute([$empresa_id]);
                    $empresa = $stmt->fetch();
                    
                    if ($empresa && $empresa['membresia_id']) {
                        $es_afiliado = true;
                        $nivel_membresia = $empresa['nombre_membresia'];
                    }
                }
            }
            
            // Seleccionar el precio según tipo de tarifa y afiliación
            // Enhanced pricing columns (precio_X_afiliado/no_afiliado) are added by migration
            // Fallback to base price (precio_X) if enhanced columns don't exist
            $monto_total = 0;
            $campo_precio_afiliado = "precio_{$tipo_tarifa}_afiliado";
            $campo_precio_no_afiliado = "precio_{$tipo_tarifa}_no_afiliado";
            $campo_precio_base = "precio_{$tipo_tarifa}";
            
            if ($es_afiliado && isset($salon[$campo_precio_afiliado]) && floatval($salon[$campo_precio_afiliado]) > 0) {
                $monto_total = floatval($salon[$campo_precio_afiliado]);
            } elseif (!$es_afiliado && isset($salon[$campo_precio_no_afiliado]) && floatval($salon[$campo_precio_no_afiliado]) > 0) {
                $monto_total = floatval($salon[$campo_precio_no_afiliado]);
            } elseif (isset($salon[$campo_precio_base])) {
                // Fallback to base price when enhanced pricing columns don't exist or are zero
                $monto_total = floatval($salon[$campo_precio_base]);
            }
            
            // Calcular descuento
            $monto_descuento = ($monto_total * $descuento_porcentaje) / 100;
            $monto_final = $monto_total - $monto_descuento;
            
            $response = [
                'success' => true,
                'salon' => [
                    'id' => $salon['id'],
                    'nombre' => $salon['nombre']
                ],
                'es_afiliado' => $es_afiliado,
                'nivel_membresia' => $nivel_membresia,
                'tipo_tarifa' => $tipo_tarifa,
                'monto_total' => $monto_total,
                'descuento_porcentaje' => $descuento_porcentaje,
                'monto_descuento' => $monto_descuento,
                'monto_final' => $monto_final
            ];
            break;
            
        case 'crear_reserva':
            // Crear una nueva reserva
            $salon_id = intval($_POST['salon_id'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?? '';
            $fecha_fin = $_POST['fecha_fin'] ?? '';
            $proposito = sanitize($_POST['proposito'] ?? '');
            $contacto_nombre = sanitize($_POST['contacto_nombre'] ?? '');
            $contacto_email = sanitize($_POST['contacto_email'] ?? '');
            $contacto_telefono = sanitize($_POST['contacto_telefono'] ?? '');
            $notas = sanitize($_POST['notas'] ?? '');
            $tipo_tarifa = sanitize($_POST['tipo_tarifa'] ?? 'hora');
            
            if (!$salon_id || !$fecha_inicio || !$fecha_fin) {
                throw new Exception('Datos incompletos para la reserva');
            }
            
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
                $salon_id,
                $fecha_inicio, $fecha_fin,
                $fecha_inicio, $fecha_fin,
                $fecha_inicio, $fecha_fin
            ]);
            $result = $stmt->fetch();
            
            if ($result['conflictos'] > 0) {
                throw new Exception('El salón no está disponible en las fechas seleccionadas');
            }
            
            // Calcular precio
            $empresa_id = $user['empresa_id'] ?? null;
            $stmt = $db->prepare("SELECT * FROM salones WHERE id = ? AND activo = 1");
            $stmt->execute([$salon_id]);
            $salon = $stmt->fetch();
            
            $es_afiliado = false;
            $descuento_porcentaje = 0;
            $nivel_membresia = null;
            
            if ($empresa_id) {
                // Try with descuento_salones column (enhanced schema from 20251124_salon_enhancements.sql)
                try {
                    $stmt = $db->prepare("
                        SELECT e.membresia_id, m.nombre as nombre_membresia, m.descuento_salones
                        FROM empresas e
                        LEFT JOIN membresias m ON e.membresia_id = m.id
                        WHERE e.id = ? AND e.activo = 1
                    ");
                    $stmt->execute([$empresa_id]);
                    $empresa = $stmt->fetch();
                    
                    if ($empresa && $empresa['membresia_id']) {
                        $es_afiliado = true;
                        $descuento_porcentaje = floatval($empresa['descuento_salones'] ?? 0);
                        $nivel_membresia = $empresa['nombre_membresia'];
                    }
                } catch (PDOException $e) {
                    // Only handle "unknown column" error (42S22), rethrow others
                    if ($e->getCode() !== '42S22') {
                        throw $e;
                    }
                    // descuento_salones column doesn't exist - migration not applied
                    // Fallback to basic query without discount column
                    $stmt = $db->prepare("
                        SELECT e.membresia_id, m.nombre as nombre_membresia
                        FROM empresas e
                        LEFT JOIN membresias m ON e.membresia_id = m.id
                        WHERE e.id = ? AND e.activo = 1
                    ");
                    $stmt->execute([$empresa_id]);
                    $empresa = $stmt->fetch();
                    
                    if ($empresa && $empresa['membresia_id']) {
                        $es_afiliado = true;
                        $nivel_membresia = $empresa['nombre_membresia'];
                    }
                }
            }
            
            // Calculate price with fallback for missing columns from migration
            // Enhanced columns (precio_X_afiliado/no_afiliado) added by 20251124_salon_enhancements.sql
            $campo_precio_afiliado = "precio_{$tipo_tarifa}_afiliado";
            $campo_precio_no_afiliado = "precio_{$tipo_tarifa}_no_afiliado";
            $campo_precio_base = "precio_{$tipo_tarifa}";
            
            $monto_total = 0;
            if ($es_afiliado && isset($salon[$campo_precio_afiliado]) && floatval($salon[$campo_precio_afiliado]) > 0) {
                $monto_total = floatval($salon[$campo_precio_afiliado]);
            } elseif (!$es_afiliado && isset($salon[$campo_precio_no_afiliado]) && floatval($salon[$campo_precio_no_afiliado]) > 0) {
                $monto_total = floatval($salon[$campo_precio_no_afiliado]);
            } elseif (isset($salon[$campo_precio_base])) {
                // Fallback to base price when enhanced pricing columns don't exist or are zero
                $monto_total = floatval($salon[$campo_precio_base]);
            }
            
            $monto_descuento = ($monto_total * $descuento_porcentaje) / 100;
            $monto_final = $monto_total - $monto_descuento;
            
            // Crear reserva - try with enhanced columns first, fallback to basic columns
            // Enhanced columns (monto_total, monto_final, etc.) added by 20251124_salon_enhancements.sql
            try {
                $sql = "INSERT INTO salon_reservas (
                    salon_id, empresa_id, usuario_id, fecha_inicio, fecha_fin,
                    proposito, contacto_nombre, contacto_email, contacto_telefono, notas,
                    estado, monto_total, descuento_porcentaje, monto_descuento, monto_final,
                    es_afiliado, nivel_membresia
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $salon_id, $empresa_id, $user['id'], $fecha_inicio, $fecha_fin,
                    $proposito, $contacto_nombre, $contacto_email, $contacto_telefono, $notas,
                    $monto_total, $descuento_porcentaje, $monto_descuento, $monto_final,
                    $es_afiliado ? 1 : 0, $nivel_membresia
                ]);
            } catch (PDOException $e) {
                // Only handle "unknown column" error (42S22), rethrow others
                if ($e->getCode() !== '42S22') {
                    throw $e;
                }
                // Enhanced columns don't exist - migration not applied
                // Use basic insert with columns from original 20251124_add_salones_module.sql
                $sql = "INSERT INTO salon_reservas (
                    salon_id, empresa_id, usuario_id, fecha_inicio, fecha_fin,
                    proposito, contacto_nombre, contacto_email, contacto_telefono, notas, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $salon_id, $empresa_id, $user['id'], $fecha_inicio, $fecha_fin,
                    $proposito, $contacto_nombre, $contacto_email, $contacto_telefono, $notas
                ]);
            }
            
            $reserva_id = $db->lastInsertId();
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'CREATE_RESERVA_SALON', 'salon_reservas', ?)");
            $stmt->execute([$user['id'], $reserva_id]);
            
            $response = [
                'success' => true,
                'message' => 'Reserva creada exitosamente',
                'reserva_id' => $reserva_id,
                'monto_final' => $monto_final
            ];
            break;
            
        case 'subir_comprobante':
            // Subir comprobante de pago
            $reserva_id = intval($_POST['reserva_id'] ?? 0);
            
            if (!$reserva_id) {
                throw new Exception('ID de reserva requerido');
            }
            
            // Verificar que la reserva existe y pertenece al usuario
            $stmt = $db->prepare("SELECT * FROM salon_reservas WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$reserva_id, $user['id']]);
            $reserva = $stmt->fetch();
            
            if (!$reserva) {
                throw new Exception('Reserva no encontrada');
            }
            
            // Verificar que se subió un archivo
            if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al subir el comprobante');
            }
            
            // Validar tipo de archivo
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['comprobante']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception('Solo se permiten archivos PDF, JPG o PNG');
            }
            
            // Validar tamaño (máx 5MB)
            if ($_FILES['comprobante']['size'] > 5 * 1024 * 1024) {
                throw new Exception('El archivo es demasiado grande. Tamaño máximo: 5MB');
            }
            
            // Crear directorio si no existe
            $upload_dir = UPLOAD_PATH . '/comprobantes_salones/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generar nombre único
            $file_extension = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
            $new_filename = 'comprobante_' . $reserva_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $upload_path)) {
                throw new Exception('Error al guardar el comprobante');
            }
            
            $ruta_comprobante = '/public/uploads/comprobantes_salones/' . $new_filename;
            
            // Actualizar reserva
            $stmt = $db->prepare("UPDATE salon_reservas SET comprobante_pago = ?, metodo_pago = 'COMPROBANTE', fecha_pago = NOW() WHERE id = ?");
            $stmt->execute([$ruta_comprobante, $reserva_id]);
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'UPLOAD_COMPROBANTE_SALON', 'salon_reservas', ?)");
            $stmt->execute([$user['id'], $reserva_id]);
            
            $response = [
                'success' => true,
                'message' => 'Comprobante subido exitosamente',
                'ruta_comprobante' => $ruta_comprobante
            ];
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
