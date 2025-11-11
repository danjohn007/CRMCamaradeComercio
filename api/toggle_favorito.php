<?php
/**
 * API para toggle favorito de empresa
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
    
    if ($empresa_id <= 0) {
        throw new Exception('ID de empresa inválido');
    }
    
    // Verificar si ya existe el favorito
    $stmt = $db->prepare("SELECT id FROM empresa_favoritos WHERE empresa_id = ? AND usuario_id = ?");
    $stmt->execute([$empresa_id, $user['id']]);
    $favorito = $stmt->fetch();
    
    if ($favorito) {
        // Eliminar favorito
        $stmt = $db->prepare("DELETE FROM empresa_favoritos WHERE empresa_id = ? AND usuario_id = ?");
        $stmt->execute([$empresa_id, $user['id']]);
        
        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'message' => 'Empresa eliminada de favoritos'
        ]);
    } else {
        // Agregar favorito
        $stmt = $db->prepare("INSERT INTO empresa_favoritos (empresa_id, usuario_id) VALUES (?, ?)");
        $stmt->execute([$empresa_id, $user['id']]);
        
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'message' => 'Empresa agregada a favoritos'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
