<?php
/**
 * API para registrar pagos de empresas con evidencia
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

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

// Verificar permisos (al menos CAPTURISTA)
if (!hasPermission('CAPTURISTA')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Sin permisos suficientes'
    ]);
    exit;
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Validar datos requeridos
    $empresa_id = intval($_POST['empresa_id'] ?? 0);
    $concepto = sanitize($_POST['concepto'] ?? '');
    $monto = floatval($_POST['monto'] ?? 0);
    $metodo_pago = $_POST['metodo_pago'] ?? 'EFECTIVO';
    $referencia = sanitize($_POST['referencia'] ?? '');
    $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d H:i:s');
    $notas = sanitize($_POST['notas'] ?? '');
    
    // Validaciones
    if ($empresa_id <= 0) {
        throw new Exception('ID de empresa inválido');
    }
    
    if (empty($concepto)) {
        throw new Exception('El concepto es requerido');
    }
    
    if ($monto <= 0) {
        throw new Exception('El monto debe ser mayor a 0');
    }
    
    $metodos_validos = ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA', 'PAYPAL', 'OTRO'];
    if (!in_array($metodo_pago, $metodos_validos)) {
        throw new Exception('Método de pago inválido');
    }
    
    // Verificar que la empresa existe
    $stmt = $db->prepare("SELECT id, razon_social FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Procesar evidencia si se subió
    $evidencia_pago = null;
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['evidencia'], ['jpg', 'jpeg', 'png', 'pdf'], 5242880); // 5MB max
        if ($result['success']) {
            $evidencia_pago = $result['filename'];
        } else {
            throw new Exception('Error al subir evidencia: ' . $result['message']);
        }
    }
    
    // Insertar pago
    $sql = "INSERT INTO pagos (empresa_id, usuario_id, concepto, monto, metodo_pago, referencia, 
            estado, fecha_pago, notas, evidencia_pago) 
            VALUES (?, ?, ?, ?, ?, ?, 'COMPLETADO', ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $empresa_id,
        $user['id'],
        $concepto,
        $monto,
        $metodo_pago,
        $referencia,
        $fecha_pago,
        $notas,
        $evidencia_pago
    ]);
    
    $pago_id = $db->lastInsertId();
    
    // Registrar en auditoría
    $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos) 
                         VALUES (?, 'REGISTRAR_PAGO', 'pagos', ?, ?)");
    $stmt->execute([
        $user['id'],
        $pago_id,
        json_encode([
            'empresa_id' => $empresa_id,
            'concepto' => $concepto,
            'monto' => $monto,
            'metodo_pago' => $metodo_pago
        ])
    ]);
    
    // Crear notificación para usuarios de la empresa
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE empresa_id = ? AND activo = 1");
    $stmt->execute([$empresa_id]);
    $usuarios_empresa = $stmt->fetchAll();
    
    foreach ($usuarios_empresa as $usuario) {
        $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace) 
                             VALUES (?, 'SISTEMA', ?, ?, ?)");
        $stmt->execute([
            $usuario['id'],
            'Pago Registrado',
            "Se ha registrado un pago de $" . number_format($monto, 2) . " - " . $concepto,
            '/empresas.php?action=view&id=' . $empresa_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado exitosamente',
        'data' => [
            'pago_id' => $pago_id,
            'empresa' => $empresa['razon_social'],
            'monto' => $monto,
            'evidencia' => $evidencia_pago
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
