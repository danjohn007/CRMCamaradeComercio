<?php
/**
 * Callback de éxito de PayPal para upgrades de membresía
 * Se ejecuta cuando el usuario completa el pago en PayPal
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/paypal.php';

$db = Database::getInstance()->getConnection();

try {
    // Obtener parámetros de la URL
    $token = $_GET['token'] ?? '';
    $empresa_id = intval($_GET['empresa_id'] ?? 0);
    $membresia_id = intval($_GET['membresia_id'] ?? 0);
    
    if (empty($token) || !$empresa_id || !$membresia_id) {
        throw new Exception('Parámetros inválidos');
    }
    
    // Capturar el pago en PayPal
    $capture = PayPalHelper::captureOrder($token);
    
    // Verificar que el pago fue exitoso
    if ($capture['status'] !== 'COMPLETED') {
        throw new Exception('El pago no fue completado');
    }
    
    // Obtener información de la nueva membresía
    $stmt = $db->prepare("SELECT * FROM membresias WHERE id = ?");
    $stmt->execute([$membresia_id]);
    $nueva_membresia = $stmt->fetch();
    
    if (!$nueva_membresia) {
        throw new Exception('Membresía no encontrada');
    }
    
    // Obtener el monto pagado de la captura
    $monto_pagado = floatval($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Actualizar el upgrade en la tabla membresias_upgrades
        $stmt = $db->prepare("
            UPDATE membresias_upgrades 
            SET estado = 'COMPLETADO',
                fecha_completado = NOW()
            WHERE paypal_order_id = ? AND empresa_id = ?
        ");
        $stmt->execute([$token, $empresa_id]);
        
        // Actualizar la membresía de la empresa
        $stmt = $db->prepare("
            UPDATE empresas 
            SET membresia_id = ?,
                fecha_renovacion = DATE_ADD(CURDATE(), INTERVAL ? MONTH),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $membresia_id,
            intval($nueva_membresia['vigencia_meses']),
            $empresa_id
        ]);
        
        // Registrar el pago
        $stmt = $db->prepare("
            INSERT INTO pagos 
            (empresa_id, concepto, monto, metodo_pago, referencia, 
             estado, fecha_pago)
            VALUES (?, ?, ?, 'PAYPAL', ?, 'COMPLETADO', NOW())
        ");
        $stmt->execute([
            $empresa_id,
            'Actualización de Membresía a ' . $nueva_membresia['nombre'],
            $monto_pagado,
            $token
        ]);
        
        // Obtener el usuario de la empresa para notificaciones
        $stmt = $db->prepare("
            SELECT u.id FROM usuarios u 
            WHERE u.empresa_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$empresa_id]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Crear notificación
            $stmt = $db->prepare("
                INSERT INTO notificaciones 
                (usuario_id, tipo, titulo, mensaje)
                VALUES (?, 'SISTEMA', ?, ?)
            ");
            $stmt->execute([
                $usuario['id'],
                'Membresía Actualizada',
                'Tu membresía ha sido actualizada exitosamente a ' . $nueva_membresia['nombre']
            ]);
            
            // Registrar en auditoría
            $stmt = $db->prepare("
                INSERT INTO auditoria 
                (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos)
                VALUES (?, 'UPGRADE_MEMBRESIA_PAYPAL', 'empresas', ?, ?)
            ");
            $stmt->execute([
                $usuario['id'],
                $empresa_id,
                json_encode([
                    'membresia_nueva' => $membresia_id,
                    'membresia_nombre' => $nueva_membresia['nombre'],
                    'monto' => $monto_pagado,
                    'paypal_order_id' => $token,
                    'vigencia_meses' => $nueva_membresia['vigencia_meses']
                ])
            ]);
        }
        
        // Commit de la transacción
        $db->commit();
        
        // Redirigir con mensaje de éxito
        $_SESSION['success_message'] = '¡Pago completado! Tu membresía ha sido actualizada a ' . $nueva_membresia['nombre'];
        header('Location: ' . BASE_URL . '/mi_membresia.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in paypal_success_membresia.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $_SESSION['error_message'] = 'Error al procesar el pago: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/mi_membresia.php');
    exit;
}
