<?php
/**
 * API para obtener datos de gráficas del dashboard con filtros de fecha
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar permisos
if (!hasPermission('AFILADOR')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos suficientes']);
    exit;
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Obtener parámetros de fecha
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes actual por defecto
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t'); // Último día del mes actual por defecto

try {
    $data = [];
    
    // 1. Empresas por Membresía (filtrado por fecha de creación)
    $stmt = $db->prepare("SELECT m.nombre, COUNT(e.id) as cantidad 
                         FROM membresias m 
                         LEFT JOIN empresas e ON m.id = e.membresia_id 
                             AND e.activo = 1 
                             AND DATE(e.created_at) BETWEEN ? AND ?
                         WHERE m.activo = 1 
                         GROUP BY m.id 
                         ORDER BY cantidad DESC");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['empresasPorMembresia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Empresas por Sector (filtrado por fecha de creación)
    $stmt = $db->prepare("SELECT s.nombre, COUNT(e.id) as cantidad 
                         FROM sectores s 
                         LEFT JOIN empresas e ON s.id = e.sector_id 
                             AND e.activo = 1 
                             AND DATE(e.created_at) BETWEEN ? AND ?
                         WHERE s.activo = 1 
                         GROUP BY s.id 
                         ORDER BY cantidad DESC 
                         LIMIT 10");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['empresasPorSector'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Ingresos por Mes (dentro del rango de fechas)
    $stmt = $db->prepare("SELECT DATE_FORMAT(fecha_pago, '%Y-%m') as mes, 
                         DATE_FORMAT(fecha_pago, '%b') as mes_nombre,
                         SUM(monto) as total 
                         FROM pagos 
                         WHERE fecha_pago BETWEEN ? AND ?
                         AND estado = 'COMPLETADO'
                         GROUP BY mes 
                         ORDER BY mes ASC");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['ingresosPorMes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Nuevas empresas por mes (dentro del rango de fechas)
    $stmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as mes,
                         DATE_FORMAT(created_at, '%b') as mes_nombre,
                         COUNT(*) as cantidad 
                         FROM empresas 
                         WHERE created_at BETWEEN ? AND ?
                         GROUP BY mes 
                         ORDER BY mes ASC");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['nuevasEmpresasPorMes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Estado de empresas (con fecha de creación en el rango)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM empresas 
                         WHERE activo = 1 
                         AND fecha_renovacion >= CURDATE()
                         AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['empresasActivas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM empresas 
                         WHERE activo = 1 
                         AND fecha_renovacion < CURDATE()
                         AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['empresasVencidas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM empresas 
                         WHERE activo = 1 
                         AND fecha_renovacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                         AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['empresasProximasVencer'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 6. Eventos por tipo (filtrado por fecha de inicio)
    $stmt = $db->prepare("SELECT tipo, COUNT(*) as cantidad 
                         FROM eventos 
                         WHERE DATE(fecha_inicio) BETWEEN ? AND ?
                         GROUP BY tipo");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['eventosPorTipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. Requerimientos por estado (filtrado por fecha de creación)
    $stmt = $db->prepare("SELECT estado, COUNT(*) as cantidad 
                         FROM requerimientos 
                         WHERE DATE(created_at) BETWEEN ? AND ?
                         GROUP BY estado");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['requerimientosPorEstado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. Top 10 ciudades (filtrado por fecha de creación)
    $stmt = $db->prepare("SELECT ciudad, COUNT(*) as cantidad 
                         FROM empresas 
                         WHERE activo = 1 
                         AND ciudad IS NOT NULL 
                         AND ciudad != ''
                         AND DATE(created_at) BETWEEN ? AND ?
                         GROUP BY ciudad 
                         ORDER BY cantidad DESC 
                         LIMIT 10");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $data['empresasPorCiudad'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al obtener datos de gráficas',
        'message' => $e->getMessage()
    ]);
}
