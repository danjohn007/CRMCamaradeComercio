<?php
/**
 * API pública para buscar empresa por RFC (sin autenticación)
 * Usado en el registro público de empresas
 * Incluye protección contra abuso mediante rate limiting básico
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Rate limiting básico por sesión
session_start();
$now = time();
$limit_window = 60; // 60 segundos
$max_requests = 10; // máximo 10 búsquedas por minuto

if (!isset($_SESSION['rfc_searches'])) {
    $_SESSION['rfc_searches'] = [];
}

// Limpiar búsquedas antiguas
$_SESSION['rfc_searches'] = array_filter($_SESSION['rfc_searches'], function($timestamp) use ($now, $limit_window) {
    return ($now - $timestamp) < $limit_window;
});

// Verificar límite
if (count($_SESSION['rfc_searches']) >= $max_requests) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Demasiadas solicitudes. Intente nuevamente en un momento.']);
    exit;
}

// Registrar búsqueda actual
$_SESSION['rfc_searches'][] = $now;

$rfc = strtoupper(sanitize($_GET['rfc'] ?? ''));

if (empty($rfc) || strlen($rfc) < 12) {
    echo json_encode(['success' => false, 'error' => 'RFC inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar empresa por RFC - Solo retornar datos necesarios para el registro
    // No exponer información sensible completa
    $stmt = $db->prepare("
        SELECT id, razon_social, rfc, email, telefono, whatsapp, representante
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
        'error' => 'Error al buscar empresa'
    ]);
}
