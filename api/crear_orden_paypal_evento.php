<?php
/**
 * API para crear una orden de pago en PayPal para eventos
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/paypal.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos de la solicitud
    $input = json_decode(file_get_contents('php://input'), true);
    
    $inscripcion_id = intval($input['inscripcion_id'] ?? 0);
    
    if (!$inscripcion_id) {
        throw new Exception('ID de inscripción requerido');
    }
    
    // Obtener datos de la inscripción
    $stmt = $db->prepare("
        SELECT ei.*, e.titulo, e.costo, e.descripcion
        FROM eventos_inscripciones ei
        JOIN eventos e ON ei.evento_id = e.id
        WHERE ei.id = ?
    ");
    $stmt->execute([$inscripcion_id]);
    $inscripcion = $stmt->fetch();
    
    if (!$inscripcion) {
        throw new Exception('Inscripción no encontrada');
    }
    
    // Verificar que el evento tenga costo
    if ($inscripcion['costo'] <= 0) {
        throw new Exception('Este evento no requiere pago');
    }
    
    // Verificar que no se haya pagado ya
    if ($inscripcion['estado_pago'] === 'COMPLETADO') {
        throw new Exception('Esta inscripción ya fue pagada');
    }
    
    // Calcular monto total según número de boletos
    $boletos = intval($inscripcion['boletos_solicitados'] ?? 1);
    $monto_total = $inscripcion['costo'] * $boletos;
    
    // Crear descripción del pago
    $descripcion = "Evento: " . $inscripcion['titulo'] . " - " . $boletos . " boleto(s)";
    
    // Crear orden en PayPal
    $return_url = BASE_URL . '/api/paypal_success_evento.php?inscripcion_id=' . $inscripcion_id;
    $cancel_url = BASE_URL . '/evento_publico.php?evento=' . $inscripcion['evento_id'] . '&error=pago_cancelado';
    
    $order = PayPalHelper::createOrder($descripcion, $monto_total, 'MXN', $return_url, $cancel_url);
    
    // Actualizar estado de pago a PENDIENTE y guardar order_id
    $stmt = $db->prepare("
        UPDATE eventos_inscripciones 
        SET estado_pago = 'PENDIENTE', 
            paypal_order_id = ?,
            monto_pagado = ?
        WHERE id = ?
    ");
    $stmt->execute([$order['id'], $monto_total, $inscripcion_id]);
    
    // Retornar datos de la orden
    echo json_encode([
        'success' => true,
        'order_id' => $order['id'],
        'approval_url' => $order['links'][1]['href'] ?? null, // Link para aprobar el pago
        'monto' => $monto_total,
        'boletos' => $boletos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
