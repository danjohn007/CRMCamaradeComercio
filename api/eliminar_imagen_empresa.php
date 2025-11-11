<?php
/**
 * API para eliminar imagen de empresa
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado con permisos
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión']);
    exit;
}

if (!hasPermission('CAPTURISTA')) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar imágenes']);
    exit;
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    $imagen_id = intval($input['imagen_id'] ?? 0);
    $empresa_id = intval($input['empresa_id'] ?? 0);
    
    if ($imagen_id <= 0 || $empresa_id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Obtener información de la imagen
    $stmt = $db->prepare("SELECT * FROM empresa_imagenes WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$imagen_id, $empresa_id]);
    $imagen = $stmt->fetch();
    
    if (!$imagen) {
        throw new Exception('Imagen no encontrada');
    }
    
    // Eliminar archivo físico
    $file_path = UPLOAD_PATH . '/' . $imagen['ruta_imagen'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Eliminar de base de datos
    $stmt = $db->prepare("DELETE FROM empresa_imagenes WHERE id = ?");
    $stmt->execute([$imagen_id]);
    
    // Reordenar las imágenes restantes
    $stmt = $db->prepare("SELECT id FROM empresa_imagenes WHERE empresa_id = ? ORDER BY orden ASC");
    $stmt->execute([$empresa_id]);
    $imagenes_restantes = $stmt->fetchAll();
    
    foreach ($imagenes_restantes as $index => $img) {
        $stmt = $db->prepare("UPDATE empresa_imagenes SET orden = ? WHERE id = ?");
        $stmt->execute([$index, $img['id']]);
    }
    
    // Registrar en auditoría
    $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, descripcion) VALUES (?, 'DELETE_IMAGEN_EMPRESA', 'empresa_imagenes', ?, ?)");
    $stmt->execute([$user['id'], $imagen_id, "Eliminada imagen de empresa ID {$empresa_id}"]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Imagen eliminada exitosamente'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
