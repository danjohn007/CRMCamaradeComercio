<?php
/**
 * API para crear una orden de pago en PayPal para upgrade de membresía
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/paypal.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Solo para usuarios externos con empresa
if (!in_array($user['rol'], ['ENTIDAD_COMERCIAL', 'EMPRESA_TRACTORA']) || !$user['empresa_id']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Sin permisos suficientes'
    ]);
    exit;
}

try {
    // Obtener datos de la solicitud
    $input = json_decode(file_get_contents('php://input'), true);
    
    $membresia_id = intval($input['membresia_id'] ?? 0);
    
    if (!$membresia_id) {
        throw new Exception('ID de membresía requerido');
    }
    
    // Obtener información de la empresa actual
    $stmt = $db->prepare("
        SELECT e.*, m.id as membresia_actual_id, m.nombre as membresia_actual_nombre
        FROM empresas e
        LEFT JOIN membresias m ON e.membresia_id = m.id
        WHERE e.id = ?
    ");
    $stmt->execute([$user['empresa_id']]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Verificar que la nueva membresía existe y es válida
    $stmt = $db->prepare("
        SELECT * FROM membresias 
        WHERE id = ? AND activo = 1
    ");
    $stmt->execute([$membresia_id]);
    $nueva_membresia = $stmt->fetch();
    
    if (!$nueva_membresia) {
        throw new Exception('Membresía no encontrada');
    }
    
    // Verificar que no sea la misma membresía actual
    if ($empresa['membresia_actual_id'] !== null && intval($nueva_membresia['id']) === intval($empresa['membresia_actual_id'])) {
        throw new Exception('Ya tienes esta membresía activa');
    }
    
    $monto_total = floatval($nueva_membresia['costo']);
    
    if ($monto_total <= 0) {
        throw new Exception('Monto inválido para esta membresía');
    }
    
    // Crear descripción del pago
    $descripcion = "Actualización de Membresía a " . $nueva_membresia['nombre'];
    
    // Crear orden en PayPal
    $return_url = BASE_URL . '/api/paypal_success_membresia.php?empresa_id=' . $empresa['id'] . '&membresia_id=' . $membresia_id;
    $cancel_url = BASE_URL . '/mi_membresia.php?error=pago_cancelado';
    
    $order = PayPalHelper::createOrder($descripcion, $monto_total, 'MXN', $return_url, $cancel_url);
    
    // Guardar la orden pendiente en la tabla membresias_upgrades
    $stmt = $db->prepare("
        INSERT INTO membresias_upgrades 
        (empresa_id, usuario_id, membresia_anterior_id, membresia_nueva_id, 
         monto, metodo_pago, estado, paypal_order_id)
        VALUES (?, ?, ?, ?, ?, 'PAYPAL', 'PENDIENTE', ?)
    ");
    $stmt->execute([
        $user['empresa_id'],
        $user['id'],
        $empresa['membresia_actual_id'],
        $membresia_id,
        $monto_total,
        $order['id']
    ]);
    
    // Retornar datos de la orden
    echo json_encode([
        'success' => true,
        'order_id' => $order['id'],
        'approval_url' => $order['links'][1]['href'] ?? null, // Link para aprobar el pago
        'monto' => $monto_total
    ]);
    
} catch (Exception $e) {
    error_log("Error in crear_orden_paypal_membresia.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'Por favor verifica que las credenciales de PayPal estén configuradas correctamente en Configuración del Sistema.'
    ]);
}
