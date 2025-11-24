<?php
/**
 * API para procesar pagos de PayPal para reservas de salones
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

header('Content-Type: application/json');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$response = ['success' => false, 'message' => ''];

try {
    $reserva_id = intval($_POST['reserva_id'] ?? 0);
    $paypal_order_id = sanitize($_POST['paypal_order_id'] ?? '');
    $paypal_payer_id = sanitize($_POST['paypal_payer_id'] ?? '');
    $paypal_payment_status = sanitize($_POST['paypal_payment_status'] ?? '');
    $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
    
    if (!$reserva_id || !$paypal_order_id) {
        throw new Exception('Datos incompletos');
    }
    
    // Verificar que la reserva existe y pertenece al usuario
    $stmt = $db->prepare("SELECT * FROM salon_reservas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$reserva_id, $user['id']]);
    $reserva = $stmt->fetch();
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Actualizar información de pago
    $stmt = $db->prepare("
        UPDATE salon_reservas SET 
            metodo_pago = 'PAYPAL',
            paypal_order_id = ?,
            paypal_payer_id = ?,
            paypal_payment_status = ?,
            monto_pagado = ?,
            fecha_pago = NOW(),
            estado = 'CONFIRMADA',
            fecha_confirmacion = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $paypal_order_id,
        $paypal_payer_id,
        $paypal_payment_status,
        $monto_pagado,
        $reserva_id
    ]);
    
    // Registrar en auditoría
    $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles) VALUES (?, 'PAGO_PAYPAL_SALON', 'salon_reservas', ?, ?)");
    $stmt->execute([$user['id'], $reserva_id, "Pago PayPal procesado. Order ID: $paypal_order_id"]);
    
    // Enviar comprobante por email
    try {
        enviarComprobanteReserva($reserva_id);
    } catch (Exception $e) {
        // Log error but don't fail the payment
        error_log("Error al enviar comprobante: " . $e->getMessage());
    }
    
    $response = [
        'success' => true,
        'message' => 'Pago procesado exitosamente',
        'reserva_id' => $reserva_id
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);

/**
 * Enviar comprobante de reserva por email
 */
function enviarComprobanteReserva($reserva_id) {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información completa de la reserva
    $stmt = $db->prepare("
        SELECT sr.*, s.nombre as salon_nombre, s.tipo as salon_tipo, 
               u.nombre as usuario_nombre, u.email as usuario_email
        FROM salon_reservas sr
        INNER JOIN salones s ON sr.salon_id = s.id
        INNER JOIN usuarios u ON sr.usuario_id = u.id
        WHERE sr.id = ?
    ");
    $stmt->execute([$reserva_id]);
    $reserva = $stmt->fetch();
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Preparar contenido del email
    $to = $reserva['contacto_email'];
    $subject = 'Comprobante de Reserva - ' . $reserva['salon_nombre'];
    
    $body = "Estimado/a {$reserva['contacto_nombre']},\n\n";
    $body .= "Su reserva ha sido confirmada exitosamente.\n\n";
    $body .= "DETALLES DE LA RESERVA:\n";
    $body .= "========================\n";
    $body .= "Salón: {$reserva['salon_nombre']} ({$reserva['salon_tipo']})\n";
    $body .= "Fecha Inicio: " . date('d/m/Y H:i', strtotime($reserva['fecha_inicio'])) . "\n";
    $body .= "Fecha Fin: " . date('d/m/Y H:i', strtotime($reserva['fecha_fin'])) . "\n";
    $body .= "Propósito: {$reserva['proposito']}\n\n";
    
    $body .= "DETALLES DEL PAGO:\n";
    $body .= "==================\n";
    $body .= "Método de Pago: " . ($reserva['metodo_pago'] ?? 'N/A') . "\n";
    $body .= "Monto Total: $" . number_format($reserva['monto_total'], 2) . " MXN\n";
    
    if ($reserva['descuento_porcentaje'] > 0) {
        $body .= "Descuento ({$reserva['descuento_porcentaje']}%): -$" . number_format($reserva['monto_descuento'], 2) . " MXN\n";
    }
    
    $body .= "Monto Pagado: $" . number_format($reserva['monto_final'], 2) . " MXN\n";
    
    if ($reserva['metodo_pago'] == 'PAYPAL') {
        $body .= "ID de Transacción PayPal: {$reserva['paypal_order_id']}\n";
    }
    
    $body .= "\nFecha de Pago: " . date('d/m/Y H:i', strtotime($reserva['fecha_pago'])) . "\n";
    $body .= "Estado: " . ($reserva['estado'] ?? 'PENDIENTE') . "\n\n";
    
    $body .= "INFORMACIÓN DE CONTACTO:\n";
    $body .= "========================\n";
    $body .= "Nombre: {$reserva['contacto_nombre']}\n";
    $body .= "Email: {$reserva['contacto_email']}\n";
    $body .= "Teléfono: {$reserva['contacto_telefono']}\n\n";
    
    if ($reserva['notas']) {
        $body .= "NOTAS ADICIONALES:\n";
        $body .= "==================\n";
        $body .= "{$reserva['notas']}\n\n";
    }
    
    $body .= "Gracias por su reserva.\n\n";
    $body .= "Atentamente,\n";
    $body .= APP_NAME;
    
    // Enviar email
    if (!sendEmail($to, $subject, $body)) {
        throw new Exception('Error al enviar el correo electrónico');
    }
    
    // Marcar como enviado
    $stmt = $db->prepare("UPDATE salon_reservas SET comprobante_enviado = 1 WHERE id = ?");
    $stmt->execute([$reserva_id]);
    
    return true;
}
