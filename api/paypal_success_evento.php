<?php
/**
 * Endpoint de retorno después de un pago exitoso de PayPal para eventos
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/paypal.php';
require_once __DIR__ . '/../app/helpers/email.php';

$db = Database::getInstance()->getConnection();

try {
    // Obtener token y PayerID de PayPal
    $token = $_GET['token'] ?? null; // Este es el order_id
    $payer_id = $_GET['PayerID'] ?? null;
    $inscripcion_id = intval($_GET['inscripcion_id'] ?? 0);
    
    if (!$token || !$inscripcion_id) {
        throw new Exception('Parámetros inválidos');
    }
    
    // Obtener datos de la inscripción
    $stmt = $db->prepare("
        SELECT ei.*, e.titulo, e.costo, e.fecha_inicio, e.ubicacion
        FROM eventos_inscripciones ei
        JOIN eventos e ON ei.evento_id = e.id
        WHERE ei.id = ?
    ");
    $stmt->execute([$inscripcion_id]);
    $inscripcion = $stmt->fetch();
    
    if (!$inscripcion) {
        throw new Exception('Inscripción no encontrada');
    }
    
    // Verificar que el order_id coincida
    if ($inscripcion['paypal_order_id'] !== $token) {
        throw new Exception('Order ID no coincide');
    }
    
    // Capturar el pago en PayPal
    $capture_result = PayPalHelper::captureOrder($token);
    
    // Verificar que el pago fue completado
    if ($capture_result['status'] === 'COMPLETED') {
        // Actualizar la inscripción como pagada
        $stmt = $db->prepare("
            UPDATE eventos_inscripciones 
            SET estado_pago = 'COMPLETADO',
                fecha_pago = NOW(),
                referencia_pago = ?
            WHERE id = ?
        ");
        
        $capture_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'] ?? $token;
        $stmt->execute([$capture_id, $inscripcion_id]);
        
        // Registrar en auditoría
        try {
            $stmt = $db->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles)
                VALUES (NULL, 'PAGO_EVENTO_PAYPAL', 'eventos_inscripciones', ?, ?)
            ");
            $detalles = json_encode([
                'inscripcion_id' => $inscripcion_id,
                'paypal_order_id' => $token,
                'capture_id' => $capture_id,
                'monto' => $inscripcion['monto_pagado']
            ]);
            $stmt->execute([$inscripcion_id, $detalles]);
        } catch (Exception $e) {
            error_log("Error registrando auditoría: " . $e->getMessage());
        }
        
        // Enviar email con boletos después del pago
        if (!$inscripcion['boleto_enviado']) {
            try {
                require_once __DIR__ . '/../app/helpers/qrcode.php';
                
                $qrCodePath = QRCodeGenerator::saveQRImage(
                    BASE_URL . '/boleto_digital.php?codigo=' . $inscripcion['codigo_qr'],
                    $inscripcion['codigo_qr']
                );
                
                $evento = [
                    'titulo' => $inscripcion['titulo'],
                    'fecha_inicio' => $inscripcion['fecha_inicio'],
                    'fecha_fin' => $inscripcion['fecha_fin'] ?? $inscripcion['fecha_inicio'],
                    'ubicacion' => $inscripcion['ubicacion']
                ];
                
                // Determinar cuántos boletos enviar según si es empresa afiliada
                $es_empresa_afiliada = !empty($inscripcion['empresa_id']);
                $boletos_total = $inscripcion['boletos_solicitados'] ?? 1;
                
                if ($es_empresa_afiliada && $boletos_total > 1) {
                    // Empresa afiliada: enviar solo los boletos adicionales (ya tiene el primero gratis)
                    $boletos_enviados = $boletos_total - 1;
                    EmailHelper::sendEventTicketAfterPayment($inscripcion, $evento, $qrCodePath, $boletos_enviados);
                } else {
                    // No es empresa afiliada: enviar todos los boletos
                    EmailHelper::sendEventTicketAfterPayment($inscripcion, $evento, $qrCodePath, $boletos_total);
                }
                
                $stmt = $db->prepare("UPDATE eventos_inscripciones SET boleto_enviado = 1, fecha_envio_boleto = NOW() WHERE id = ?");
                $stmt->execute([$inscripcion_id]);
            } catch (Exception $e) {
                error_log("Error enviando boleto: " . $e->getMessage());
            }
        }
        
        // Redirigir a página de éxito
        $codigo_qr = $inscripcion['codigo_qr'];
        header('Location: ' . BASE_URL . '/boleto_digital.php?codigo=' . urlencode($codigo_qr) . '&pago=exitoso');
        exit;
        
    } else {
        throw new Exception('El pago no se completó correctamente');
    }
    
} catch (Exception $e) {
    error_log("Error en PayPal success: " . $e->getMessage());
    
    // Redirigir a página de error
    $evento_id = $inscripcion['evento_id'] ?? 0;
    header('Location: ' . BASE_URL . '/evento_publico.php?evento=' . $evento_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
