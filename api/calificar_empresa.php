<?php
/**
 * API para calificar empresa
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión']);
    exit;
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    $empresa_id = intval($input['empresa_id'] ?? 0);
    $calificacion = intval($input['calificacion'] ?? 0);
    $comentario = sanitize($input['comentario'] ?? '');
    
    // Validaciones
    if ($empresa_id <= 0) {
        throw new Exception('ID de empresa inválido');
    }
    
    if ($calificacion < 1 || $calificacion > 5) {
        throw new Exception('La calificación debe estar entre 1 y 5');
    }
    
    // Verificar que la empresa existe y está activa
    $stmt = $db->prepare("SELECT id FROM empresas WHERE id = ? AND activo = 1");
    $stmt->execute([$empresa_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Verificar si el usuario ya calificó esta empresa
    $stmt = $db->prepare("SELECT id FROM empresa_calificaciones WHERE empresa_id = ? AND usuario_id = ?");
    $stmt->execute([$empresa_id, $user['id']]);
    $calificacion_existente = $stmt->fetch();
    
    if ($calificacion_existente) {
        // Actualizar calificación existente
        $stmt = $db->prepare("
            UPDATE empresa_calificaciones 
            SET calificacion = ?, comentario = ?, updated_at = NOW()
            WHERE empresa_id = ? AND usuario_id = ?
        ");
        $stmt->execute([$calificacion, $comentario, $empresa_id, $user['id']]);
    } else {
        // Insertar nueva calificación
        $stmt = $db->prepare("
            INSERT INTO empresa_calificaciones (empresa_id, usuario_id, calificacion, comentario)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$empresa_id, $user['id'], $calificacion, $comentario]);
    }
    
    // Actualizar el promedio de calificación en la tabla empresas
    $stmt = $db->prepare("
        SELECT AVG(calificacion) as promedio, COUNT(*) as total
        FROM empresa_calificaciones
        WHERE empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $stats = $stmt->fetch();
    
    $stmt = $db->prepare("
        UPDATE empresas
        SET calificacion_promedio = ?, total_calificaciones = ?
        WHERE id = ?
    ");
    $stmt->execute([
        round($stats['promedio'], 2),
        $stats['total'],
        $empresa_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Calificación registrada exitosamente',
        'calificacion_promedio' => round($stats['promedio'], 2),
        'total_calificaciones' => $stats['total']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
