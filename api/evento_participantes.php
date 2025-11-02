<?php
/**
 * API para obtener participantes de un evento
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

// Verificar permisos (solo personal interno)
if (!hasPermission('DIRECCION')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Sin permisos suficientes'
    ]);
    exit;
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$evento_id = intval($_GET['evento_id'] ?? 0);

if ($evento_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID de evento inválido'
    ]);
    exit;
}

try {
    // Verificar que el evento existe
    $stmt = $db->prepare("SELECT costo FROM eventos WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();
    
    if (!$evento) {
        throw new Exception('Evento no encontrado');
    }
    
    $tiene_costo = isset($evento['costo']) && $evento['costo'] > 0;
    
    // Obtener participantes - incluyendo los que se registraron sin usuario
    $sql = "SELECT 
                ei.id,
                ei.fecha_inscripcion,
                ei.estado,
                ei.estado_pago,
                ei.monto_pagado,
                ei.fecha_pago,
                ei.boletos_solicitados,
                COALESCE(u.nombre, ei.nombre_invitado) as nombre,
                COALESCE(u.email, ei.email_invitado) as email,
                COALESCE(e.razon_social, ei.razon_social_invitado) as empresa
            FROM eventos_inscripciones ei
            LEFT JOIN usuarios u ON ei.usuario_id = u.id
            LEFT JOIN empresas e ON ei.empresa_id = e.id
            WHERE ei.evento_id = ?
            ORDER BY ei.fecha_inscripcion DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$evento_id]);
    $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular suma total de boletos
    $total_boletos = 0;
    foreach ($participantes as $participante) {
        $total_boletos += intval($participante['boletos_solicitados'] ?? 1);
    }
    
    echo json_encode([
        'success' => true,
        'participantes' => $participantes,
        'tiene_costo' => $tiene_costo,
        'total' => count($participantes),
        'total_boletos' => $total_boletos
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
