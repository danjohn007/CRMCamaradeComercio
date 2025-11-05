<?php
/**
 * API pública para buscar empresa por RFC (sin autenticación)
 * Usado en el registro público de empresas
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$rfc = strtoupper(sanitize($_GET['rfc'] ?? ''));

if (empty($rfc) || strlen($rfc) < 12) {
    echo json_encode(['success' => false, 'error' => 'RFC inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar empresa por RFC
    $stmt = $db->prepare("
        SELECT id, razon_social, rfc, email, telefono, whatsapp, 
               representante, direccion_comercial, direccion_fiscal, 
               colonia, colonia_fiscal, ciudad, estado, codigo_postal,
               sector_id, categoria_id, membresia_id
        FROM empresas 
        WHERE rfc = ?
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
