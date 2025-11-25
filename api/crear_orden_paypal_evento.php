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
        SELECT ei.*, e.titulo, e.costo, e.descripcion, e.precio_preventa, e.fecha_limite_preventa, e.acceso_gratis_afiliados
        FROM eventos_inscripciones ei
        JOIN eventos e ON ei.evento_id = e.id
        WHERE ei.id = ?
    ");
    $stmt->execute([$inscripcion_id]);
    $inscripcion = $stmt->fetch();
    
    if (!$inscripcion) {
        throw new Exception('Inscripción no encontrada');
    }
    
    // Usar el monto que ya fue calculado en evento_publico.php (incluye preventa y boleto gratis)
    $monto_total = floatval($inscripcion['monto_pagado'] ?? 0);
    
    // Si monto_pagado es 0, calcular basado en costo del evento y boletos
    if ($monto_total <= 0) {
        $boletos = intval($inscripcion['boletos_solicitados'] ?? 1);
        $costo_evento = floatval($inscripcion['costo'] ?? 0);
        
        // Verificar si hay precio de preventa aplicable
        $precio_efectivo = $costo_evento;
        $ahora = new DateTime();
        
        if (!empty($inscripcion['precio_preventa']) && $inscripcion['precio_preventa'] > 0 && 
            !empty($inscripcion['fecha_limite_preventa'])) {
            $fecha_limite = new DateTime($inscripcion['fecha_limite_preventa']);
            if ($ahora <= $fecha_limite) {
                $precio_efectivo = floatval($inscripcion['precio_preventa']);
            }
        }
        
        // Calcular boletos a pagar (considerando boleto gratis para afiliados)
        $boletos_a_pagar = $boletos;
        $permite_acceso_gratis = isset($inscripcion['acceso_gratis_afiliados']) ? (bool)$inscripcion['acceso_gratis_afiliados'] : false;
        
        if ($permite_acceso_gratis && !empty($inscripcion['empresa_id'])) {
            // Verificar membresía vigente de la empresa
            $stmt_empresa = $db->prepare("
                SELECT fecha_renovacion FROM empresas WHERE id = ? AND activo = 1
            ");
            $stmt_empresa->execute([$inscripcion['empresa_id']]);
            $empresa = $stmt_empresa->fetch();
            
            if ($empresa && !empty($empresa['fecha_renovacion'])) {
                $fecha_renovacion = new DateTime($empresa['fecha_renovacion']);
                if ($ahora <= $fecha_renovacion) {
                    // Empresa con membresía vigente: primer boleto gratis
                    $boletos_a_pagar = max(0, $boletos - 1);
                }
            }
        }
        
        $monto_total = $precio_efectivo * $boletos_a_pagar;
    }
    
    // Verificar que haya monto a pagar
    if ($monto_total <= 0) {
        throw new Exception('No hay monto pendiente de pago para esta inscripción');
    }
    
    // Verificar que no se haya pagado ya
    if ($inscripcion['estado_pago'] === 'COMPLETADO') {
        throw new Exception('Esta inscripción ya fue pagada');
    }
    
    // Obtener número de boletos
    $boletos = intval($inscripcion['boletos_solicitados'] ?? 1);
    
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
    error_log("Error in crear_orden_paypal_evento.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'Por favor verifica que las credenciales de PayPal estén configuradas correctamente en Configuración del Sistema.'
    ]);
}
