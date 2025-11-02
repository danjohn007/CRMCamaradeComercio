<?php
/**
 * API para obtener eventos y renovaciones para el calendario
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

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Parámetros
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');
$mostrar_eventos = ($_GET['mostrar_eventos'] ?? '1') === '1';
$mostrar_renovaciones = ($_GET['mostrar_renovaciones'] ?? '1') === '1';
$mostrar_reuniones = ($_GET['mostrar_reuniones'] ?? '1') === '1';

// Determinar si es usuario interno
$roles_internos = ['PRESIDENCIA', 'DIRECCION', 'CONSEJERO', 'AFILADOR', 'CAPTURISTA'];
$es_interno = in_array($user['rol'], $roles_internos);

try {
    $events = [];
    
    // 1. Obtener eventos
    if ($mostrar_eventos) {
        if ($es_interno) {
            // Usuarios internos ven todos los eventos
            $sql = "SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, 
                    tipo, cupo_maximo, inscritos, costo, requiere_inscripcion, imagen
                    FROM eventos 
                    WHERE activo = 1 
                    AND fecha_inicio BETWEEN ? AND ?
                    ORDER BY fecha_inicio";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
        } else {
            // Usuarios externos solo ven eventos públicos
            $sql = "SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, 
                    tipo, cupo_maximo, inscritos, costo, requiere_inscripcion, imagen
                    FROM eventos 
                    WHERE activo = 1 
                    AND tipo = 'PUBLICO'
                    AND fecha_inicio BETWEEN ? AND ?
                    ORDER BY fecha_inicio";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
        }
        
        $eventos = $stmt->fetchAll();
        
        foreach ($eventos as $evento) {
            $color = '#3B82F6'; // Azul por defecto (público)
            if ($evento['tipo'] === 'INTERNO') $color = '#10B981'; // Verde
            if ($evento['tipo'] === 'CONSEJO') $color = '#8B5CF6'; // Púrpura
            if ($evento['tipo'] === 'REUNION') $color = '#6366F1'; // Índigo
            
            $events[] = [
                'id' => 'evento_' . $evento['id'],
                'title' => $evento['titulo'],
                'start' => $evento['fecha_inicio'],
                'end' => $evento['fecha_fin'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'tipo' => 'EVENTO',
                    'evento_tipo' => $evento['tipo'],
                    'description' => $evento['descripcion'],
                    'ubicacion' => $evento['ubicacion'],
                    'cupo_maximo' => $evento['cupo_maximo'],
                    'inscritos' => $evento['inscritos'],
                    'costo' => $evento['costo'],
                    'requiere_inscripcion' => $evento['requiere_inscripcion'],
                    'imagen' => $evento['imagen'] ? BASE_URL . '/public/uploads/' . $evento['imagen'] : null,
                    'url' => BASE_URL . '/eventos.php?action=view&id=' . $evento['id']
                ]
            ];
        }
    }
    
    // 2. Obtener renovaciones (solo para usuarios internos o para empresa específica)
    if ($mostrar_renovaciones) {
        if ($es_interno) {
            // Usuarios internos ven todas las renovaciones
            $sql = "SELECT e.id, e.razon_social, e.fecha_renovacion, 
                    m.nombre as membresia_nombre, m.costo
                    FROM empresas e
                    LEFT JOIN membresias m ON e.membresia_id = m.id
                    WHERE e.activo = 1 
                    AND e.fecha_renovacion BETWEEN ? AND ?
                    ORDER BY e.fecha_renovacion";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
        } else {
            // Usuarios externos solo ven su propia renovación
            if ($user['empresa_id']) {
                $sql = "SELECT e.id, e.razon_social, e.fecha_renovacion, 
                        m.nombre as membresia_nombre, m.costo
                        FROM empresas e
                        LEFT JOIN membresias m ON e.membresia_id = m.id
                        WHERE e.id = ?
                        AND e.activo = 1 
                        AND e.fecha_renovacion BETWEEN ? AND ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$user['empresa_id'], $start, $end]);
            } else {
                $stmt = null;
            }
        }
        
        if ($stmt) {
            $renovaciones = $stmt->fetchAll();
            
            foreach ($renovaciones as $renovacion) {
                // Color según días hasta vencimiento
                $dias = (strtotime($renovacion['fecha_renovacion']) - time()) / 86400;
                $color = $dias < 0 ? '#EF4444' : ($dias <= 30 ? '#F59E0B' : '#F97316'); // Rojo, amarillo, naranja
                
                $events[] = [
                    'id' => 'renovacion_' . $renovacion['id'],
                    'title' => 'Renovación: ' . $renovacion['razon_social'],
                    'start' => $renovacion['fecha_renovacion'],
                    'allDay' => true,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'tipo' => 'RENOVACION',
                        'description' => 'Vencimiento de membresía - ' . $renovacion['membresia_nombre'],
                        'empresa_nombre' => $renovacion['razon_social'],
                        'costo' => $renovacion['costo'],
                        'url' => BASE_URL . '/empresas.php?action=view&id=' . $renovacion['id']
                    ]
                ];
            }
        }
    }
    
    // 3. Obtener reuniones (eventos tipo REUNION)
    if ($mostrar_reuniones) {
        if ($es_interno) {
            $sql = "SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion
                    FROM eventos 
                    WHERE activo = 1 
                    AND tipo = 'REUNION'
                    AND fecha_inicio BETWEEN ? AND ?
                    ORDER BY fecha_inicio";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            
            $reuniones = $stmt->fetchAll();
            
            foreach ($reuniones as $reunion) {
                $events[] = [
                    'id' => 'reunion_' . $reunion['id'],
                    'title' => $reunion['titulo'],
                    'start' => $reunion['fecha_inicio'],
                    'end' => $reunion['fecha_fin'],
                    'backgroundColor' => '#8B5CF6', // Púrpura
                    'borderColor' => '#8B5CF6',
                    'extendedProps' => [
                        'tipo' => 'REUNION',
                        'description' => $reunion['descripcion'],
                        'ubicacion' => $reunion['ubicacion'],
                        'url' => BASE_URL . '/eventos.php?action=view&id=' . $reunion['id']
                    ]
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar eventos: ' . $e->getMessage()
    ]);
}
