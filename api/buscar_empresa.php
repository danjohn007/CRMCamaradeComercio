<?php
/**
 * API para buscar empresa por RFC
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Requiere estar logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$rfc = strtoupper(sanitize($_GET['rfc'] ?? ''));

if (empty($rfc) || strlen($rfc) < 12) {
    echo json_encode(['success' => false, 'error' => 'RFC inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT id, razon_social, rfc, email, telefono, whatsapp, 
               direccion_comercial, ciudad, estado
        FROM empresas 
        WHERE rfc = ? AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$rfc]);
    $empresa = $stmt->fetch();
    
    if ($empresa) {
        echo json_encode([
            'success' => true,
            'empresa' => $empresa
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Empresa no encontrada'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar empresa: ' . $e->getMessage()
    ]);
}
