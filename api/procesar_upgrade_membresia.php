<?php
/**
 * API para procesar upgrades de membresía con PayPal
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

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

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Leer datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $nueva_membresia_id = intval($input['membresia_id'] ?? 0);
    $paypal_order_id = $input['paypal_order_id'] ?? '';
    $monto = floatval($input['monto'] ?? 0);
    
    // Validaciones
    if ($nueva_membresia_id <= 0) {
        throw new Exception('ID de membresía inválido');
    }
    
    if (empty($paypal_order_id)) {
        throw new Exception('ID de orden de PayPal requerido');
    }
    
    if ($monto <= 0) {
        throw new Exception('Monto inválido');
    }
    
    // Obtener información de la empresa actual
    $stmt = $db->prepare("
        SELECT e.*, m.id as membresia_actual_id, m.nivel_orden as nivel_actual
        FROM empresas e
        LEFT JOIN membresias m ON e.membresia_id = m.id
        WHERE e.id = ?
    ");
    $stmt->execute([$user['empresa_id']]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Verificar que la nueva membresía existe y es superior
    $stmt = $db->prepare("
        SELECT * FROM membresias 
        WHERE id = ? AND activo = 1
    ");
    $stmt->execute([$nueva_membresia_id]);
    $nueva_membresia = $stmt->fetch();
    
    if (!$nueva_membresia) {
        throw new Exception('Membresía no encontrada');
    }
    
    // Verificar que no sea la misma membresía actual
    if ($empresa['membresia_actual_id'] !== null && intval($nueva_membresia['id']) === intval($empresa['membresia_actual_id'])) {
        throw new Exception('Ya tienes esta membresía activa');
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Registrar el upgrade en la tabla membresias_upgrades
        $stmt = $db->prepare("
            INSERT INTO membresias_upgrades 
            (empresa_id, usuario_id, membresia_anterior_id, membresia_nueva_id, 
             monto, metodo_pago, estado, paypal_order_id, fecha_completado)
            VALUES (?, ?, ?, ?, ?, 'PAYPAL', 'COMPLETADO', ?, NOW())
        ");
        $stmt->execute([
            $user['empresa_id'],
            $user['id'],
            $empresa['membresia_actual_id'],
            $nueva_membresia_id,
            $monto,
            $paypal_order_id
        ]);
        
        // Actualizar la membresía de la empresa
        $stmt = $db->prepare("
            UPDATE empresas 
            SET membresia_id = ?,
                fecha_renovacion = DATE_ADD(CURDATE(), INTERVAL ? MONTH)
            WHERE id = ?
        ");
        $stmt->execute([
            $nueva_membresia_id,
            $nueva_membresia['vigencia_meses'],
            $user['empresa_id']
        ]);
        
        // Registrar el pago
        $stmt = $db->prepare("
            INSERT INTO pagos 
            (empresa_id, usuario_id, concepto, monto, metodo_pago, referencia, 
             estado, fecha_pago)
            VALUES (?, ?, ?, ?, 'PAYPAL', ?, 'COMPLETADO', NOW())
        ");
        $stmt->execute([
            $user['empresa_id'],
            $user['id'],
            'Actualización de Membresía a ' . $nueva_membresia['nombre'],
            $monto,
            $paypal_order_id
        ]);
        
        // Registrar en auditoría
        $stmt = $db->prepare("
            INSERT INTO auditoria 
            (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos)
            VALUES (?, 'UPGRADE_MEMBRESIA', 'empresas', ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $user['empresa_id'],
            json_encode([
                'membresia_anterior' => $empresa['membresia_actual_id'],
                'membresia_nueva' => $nueva_membresia_id,
                'monto' => $monto,
                'paypal_order_id' => $paypal_order_id
            ])
        ]);
        
        // Crear notificación
        $stmt = $db->prepare("
            INSERT INTO notificaciones 
            (usuario_id, tipo, titulo, mensaje)
            VALUES (?, 'SISTEMA', ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            'Membresía Actualizada',
            'Tu membresía ha sido actualizada exitosamente a ' . $nueva_membresia['nombre']
        ]);
        
        // Commit de la transacción
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Membresía actualizada exitosamente',
            'data' => [
                'nueva_membresia' => $nueva_membresia['nombre'],
                'vigencia_hasta' => date('Y-m-d', strtotime('+' . $nueva_membresia['vigencia_meses'] . ' months'))
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
