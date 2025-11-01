<?php
/**
 * Módulo de búsqueda global
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$query = $_GET['q'] ?? '';
$tipo = $_GET['tipo'] ?? 'todos';
$sector = $_GET['sector'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$membresia = $_GET['membresia'] ?? '';
$ciudad = $_GET['ciudad'] ?? '';

$resultados = [];

if (!empty($query) || !empty($sector) || !empty($categoria) || !empty($membresia) || !empty($ciudad)) {
    try {
        // Registrar búsqueda para estadísticas
        $stmt = $db->prepare("INSERT INTO busquedas (usuario_id, termino, tipo, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $query, $tipo, $_SERVER['REMOTE_ADDR']]);
        
        // Buscar en empresas
        if ($tipo === 'todos' || $tipo === 'empresas') {
            $whereClauses = ["e.activo = 1"];
            $params = [];
            
            if (!empty($query)) {
                $whereClauses[] = "(e.razon_social LIKE ? OR e.rfc LIKE ? OR e.email LIKE ? OR e.representante LIKE ? OR e.descripcion LIKE ? OR e.servicios_productos LIKE ? OR e.palabras_clave LIKE ? OR e.whatsapp LIKE ?)";
                $searchTerm = "%$query%";
                $params = array_merge($params, array_fill(0, 8, $searchTerm));
            }
            
            if (!empty($sector)) {
                $whereClauses[] = "e.sector_id = ?";
                $params[] = $sector;
            }
            
            if (!empty($categoria)) {
                $whereClauses[] = "e.categoria_id = ?";
                $params[] = $categoria;
            }
            
            if (!empty($membresia)) {
                $whereClauses[] = "e.membresia_id = ?";
                $params[] = $membresia;
            }
            
            if (!empty($ciudad)) {
                $whereClauses[] = "e.ciudad LIKE ?";
                $params[] = "%$ciudad%";
            }
            
            $whereSQL = implode(' AND ', $whereClauses);
            
            $sql = "SELECT e.*, s.nombre as sector_nombre, c.nombre as categoria_nombre, m.nombre as membresia_nombre
                    FROM empresas e
                    LEFT JOIN sectores s ON e.sector_id = s.id
                    LEFT JOIN categorias c ON e.categoria_id = c.id
                    LEFT JOIN membresias m ON e.membresia_id = m.id
                    WHERE $whereSQL
                    ORDER BY e.razon_social ASC
                    LIMIT 50";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $empresas = $stmt->fetchAll();
            
            foreach ($empresas as $empresa) {
                $resultados[] = [
                    'tipo' => 'empresa',
                    'id' => $empresa['id'],
                    'titulo' => $empresa['razon_social'],
                    'descripcion' => $empresa['descripcion'] ?? 'Empresa afiliada a la Cámara de Comercio',
                    'metadata' => [
                        'RFC' => $empresa['rfc'],
                        'Sector' => $empresa['sector_nombre'],
                        'Categoría' => $empresa['categoria_nombre'],
                        'Ciudad' => $empresa['ciudad']
                    ],
                    'enlace' => 'empresas.php?action=view&id=' . $empresa['id']
                ];
            }
            
            // También buscar en inscripciones a eventos si se busca por WhatsApp o RFC
            if (!empty($query) && (preg_match('/^[0-9]{10}$/', $query) || preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i', $query))) {
                $sql = "SELECT DISTINCT ei.*, e.titulo as evento_titulo, e.fecha_inicio
                        FROM eventos_inscripciones ei
                        JOIN eventos e ON ei.evento_id = e.id
                        WHERE (ei.whatsapp_invitado LIKE ? OR ei.rfc_invitado LIKE ?)
                        ORDER BY ei.fecha_inscripcion DESC
                        LIMIT 10";
                
                $searchTerm = "%$query%";
                $stmt = $db->prepare($sql);
                $stmt->execute([$searchTerm, $searchTerm]);
                $inscripciones = $stmt->fetchAll();
                
                foreach ($inscripciones as $inscripcion) {
                    $resultados[] = [
                        'tipo' => 'inscripcion_evento',
                        'id' => $inscripcion['id'],
                        'titulo' => $inscripcion['razon_social_invitado'] ?: $inscripcion['nombre_invitado'],
                        'descripcion' => 'Inscripción al evento: ' . $inscripcion['evento_titulo'],
                        'metadata' => [
                            'Nombre' => $inscripcion['nombre_invitado'],
                            'WhatsApp' => $inscripcion['whatsapp_invitado'],
                            'RFC' => $inscripcion['rfc_invitado'],
                            'Evento' => $inscripcion['evento_titulo'],
                            'Fecha Evento' => date('d/m/Y', strtotime($inscripcion['fecha_inicio']))
                        ],
                        'enlace' => 'boleto_digital.php?codigo=' . $inscripcion['codigo_qr']
                    ];
                }
            }
        }
        
        // Buscar en eventos
        if (($tipo === 'todos' || $tipo === 'eventos') && !empty($query)) {
            $sql = "SELECT * FROM eventos 
                    WHERE activo = 1 AND (titulo LIKE ? OR descripcion LIKE ?)
                    ORDER BY fecha_inicio DESC
                    LIMIT 20";
            
            $searchTerm = "%$query%";
            $stmt = $db->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm]);
            $eventos = $stmt->fetchAll();
            
            foreach ($eventos as $evento) {
                $resultados[] = [
                    'tipo' => 'evento',
                    'id' => $evento['id'],
                    'titulo' => $evento['titulo'],
                    'descripcion' => substr($evento['descripcion'], 0, 200),
                    'metadata' => [
                        'Fecha' => formatDate($evento['fecha_inicio'], 'd/m/Y H:i'),
                        'Tipo' => $evento['tipo'],
                        'Ubicación' => $evento['ubicacion']
                    ],
                    'enlace' => 'eventos.php?action=view&id=' . $evento['id']
                ];
            }
        }
        
        // Buscar en requerimientos
        if (($tipo === 'todos' || $tipo === 'requerimientos') && !empty($query)) {
            $sql = "SELECT r.*, e.razon_social as empresa_nombre
                    FROM requerimientos r
                    LEFT JOIN empresas e ON r.empresa_solicitante_id = e.id
                    WHERE r.estado = 'ABIERTO' AND (r.titulo LIKE ? OR r.descripcion LIKE ? OR r.palabras_clave LIKE ?)
                    ORDER BY r.created_at DESC
                    LIMIT 20";
            
            $searchTerm = "%$query%";
            $stmt = $db->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $requerimientos = $stmt->fetchAll();
            
            foreach ($requerimientos as $req) {
                $resultados[] = [
                    'tipo' => 'requerimiento',
                    'id' => $req['id'],
                    'titulo' => $req['titulo'],
                    'descripcion' => substr($req['descripcion'], 0, 200),
                    'metadata' => [
                        'Empresa' => $req['empresa_nombre'],
                        'Estado' => $req['estado'],
                        'Prioridad' => $req['prioridad']
                    ],
                    'enlace' => 'requerimientos.php?action=view&id=' . $req['id']
                ];
            }
        }
        
        // Actualizar contador de resultados
        $stmt = $db->prepare("UPDATE busquedas SET resultados_count = ? WHERE id = LAST_INSERT_ID()");
        $stmt->execute([count($resultados)]);
        
    } catch (Exception $e) {
        $error = "Error en la búsqueda: " . $e->getMessage();
    }
}

// Obtener filtros disponibles
$sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();
$membresias = $db->query("SELECT * FROM membresias WHERE activo = 1 ORDER BY nombre")->fetchAll();
$ciudades = $db->query("SELECT DISTINCT ciudad FROM empresas WHERE activo = 1 AND ciudad IS NOT NULL ORDER BY ciudad")->fetchAll();

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Búsqueda Global</h1>

    <!-- Formulario de búsqueda -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form method="GET" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <!-- Búsqueda por texto -->
                <div class="lg:col-span-2">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Buscar por nombre, RFC, email, productos, servicios..."
                        value="<?php echo e($query); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>

                <!-- Tipo de búsqueda -->
                <div>
                    <select name="tipo" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="todos" <?php echo $tipo === 'todos' ? 'selected' : ''; ?>>Todo</option>
                        <option value="empresas" <?php echo $tipo === 'empresas' ? 'selected' : ''; ?>>Empresas</option>
                        <option value="eventos" <?php echo $tipo === 'eventos' ? 'selected' : ''; ?>>Eventos</option>
                        <option value="requerimientos" <?php echo $tipo === 'requerimientos' ? 'selected' : ''; ?>>Requerimientos</option>
                    </select>
                </div>
            </div>

            <!-- Filtros avanzados -->
            <details class="mb-4">
                <summary class="cursor-pointer text-blue-600 hover:text-blue-700 font-semibold">
                    <i class="fas fa-filter mr-2"></i>Filtros Avanzados
                </summary>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <!-- Sector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sector</label>
                        <select name="sector" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $sector == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($s['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Categoría -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                        <select name="categoria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $categoria == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($c['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Membresía -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Membresía</label>
                        <select name="membresia" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <?php foreach ($membresias as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $membresia == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($m['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ciudad -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                        <select name="ciudad" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <?php foreach ($ciudades as $c): ?>
                                <option value="<?php echo $c['ciudad']; ?>" <?php echo $ciudad === $c['ciudad'] ? 'selected' : ''; ?>>
                                    <?php echo e($c['ciudad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </details>

            <!-- Botón de búsqueda -->
            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-search mr-2"></i>Buscar
                </button>
                <a href="buscar.php" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Resultados -->
    <?php if (!empty($query) || !empty($sector) || !empty($categoria) || !empty($membresia) || !empty($ciudad)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    Resultados de la Búsqueda
                    <span class="text-blue-600">(<?php echo count($resultados); ?>)</span>
                </h2>
            </div>

            <?php if (!empty($resultados)): ?>
                <div class="space-y-4">
                    <?php foreach ($resultados as $resultado): 
                        $tipoColors = [
                            'empresa' => 'blue',
                            'evento' => 'green',
                            'requerimiento' => 'purple'
                        ];
                        $tipoIcons = [
                            'empresa' => 'fa-building',
                            'evento' => 'fa-calendar',
                            'requerimiento' => 'fa-file-alt'
                        ];
                        $color = $tipoColors[$resultado['tipo']] ?? 'gray';
                        $icon = $tipoIcons[$resultado['tipo']] ?? 'fa-circle';
                    ?>
                    <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="inline-block px-3 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 rounded text-xs font-semibold mr-3">
                                        <i class="fas <?php echo $icon; ?> mr-1"></i>
                                        <?php echo ucfirst($resultado['tipo']); ?>
                                    </span>
                                    <h3 class="text-xl font-semibold text-gray-800">
                                        <?php echo e($resultado['titulo']); ?>
                                    </h3>
                                </div>
                                <p class="text-gray-600 mb-3">
                                    <?php echo e($resultado['descripcion']); ?>
                                </p>
                                
                                <!-- Metadata -->
                                <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                                    <?php foreach ($resultado['metadata'] as $key => $value): ?>
                                        <?php if ($value): ?>
                                        <div>
                                            <span class="font-semibold"><?php echo $key; ?>:</span> <?php echo e($value); ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <a href="<?php echo e($resultado['enlace']); ?>" 
                               class="ml-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition whitespace-nowrap">
                                Ver Detalles
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 text-lg">No se encontraron resultados</p>
                    <p class="text-gray-500 mt-2">Intenta con otros términos de búsqueda o filtros</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Estado inicial - Sugerencias -->
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <i class="fas fa-search text-6xl text-blue-500 mb-6"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">¿Qué estás buscando?</h2>
            <p class="text-gray-600 mb-8">Ingresa un término de búsqueda o usa los filtros avanzados</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-3xl mx-auto">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-building text-3xl text-blue-500 mb-3"></i>
                    <h3 class="font-semibold text-gray-800 mb-2">Empresas</h3>
                    <p class="text-sm text-gray-600">Busca empresas afiliadas por nombre, RFC, productos o servicios</p>
                </div>
                <div class="p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-calendar text-3xl text-green-500 mb-3"></i>
                    <h3 class="font-semibold text-gray-800 mb-2">Eventos</h3>
                    <p class="text-sm text-gray-600">Encuentra eventos, capacitaciones y reuniones</p>
                </div>
                <div class="p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-file-alt text-3xl text-purple-500 mb-3"></i>
                    <h3 class="font-semibold text-gray-800 mb-2">Requerimientos</h3>
                    <p class="text-sm text-gray-600">Descubre oportunidades comerciales</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
