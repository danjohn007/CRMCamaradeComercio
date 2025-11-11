<?php
/**
 * Página pública del directorio de empresas
 * Permite búsqueda pública sin autenticación
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

// Obtener configuración
$config = getConfiguracion();

// Parámetros de búsqueda
$query = $_GET['q'] ?? '';
$sector = $_GET['sector'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$ciudad = $_GET['ciudad'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Empresas por página

// Construir consulta SQL
$where_conditions = ["e.activo = 1"];
$params = [];

if (!empty($query)) {
    $where_conditions[] = "(e.razon_social LIKE ? OR e.servicios_productos LIKE ? OR e.palabras_clave LIKE ? OR e.descripcion LIKE ?)";
    $search_term = "%$query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($sector)) {
    $where_conditions[] = "e.sector_id = ?";
    $params[] = $sector;
}

if (!empty($categoria)) {
    $where_conditions[] = "e.categoria_id = ?";
    $params[] = $categoria;
}

if (!empty($ciudad)) {
    $where_conditions[] = "e.ciudad LIKE ?";
    $params[] = "%$ciudad%";
}

$where_sql = implode(' AND ', $where_conditions);

// Contar total de resultados
$count_sql = "SELECT COUNT(*) as total 
              FROM empresas e 
              WHERE $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_results = $stmt->fetch()['total'];
$total_pages = ceil($total_results / $per_page);

// Obtener resultados paginados
$offset = ($page - 1) * $per_page;
$sql = "SELECT e.*, s.nombre as sector_nombre, c.nombre as categoria_nombre
        FROM empresas e
        LEFT JOIN sectores s ON e.sector_id = s.id
        LEFT JOIN categorias c ON e.categoria_id = c.id
        WHERE $where_sql
        ORDER BY e.razon_social ASC
        LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$empresas = $stmt->fetchAll();

// Obtener filtros disponibles
$sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();
$ciudades = $db->query("SELECT DISTINCT ciudad FROM empresas WHERE activo = 1 AND ciudad IS NOT NULL AND ciudad != '' ORDER BY ciudad")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Empresas - <?php echo htmlspecialchars($config['nombre_sitio'] ?? APP_NAME); ?></title>
    <meta name="description" content="Directorio público de empresas afiliadas a la Cámara de Comercio. Busca productos y servicios de empresas locales.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-bg {
            background: <?php echo $config['color_primario'] ?? '#1E40AF'; ?>;
        }
        .btn-primary {
            background: <?php echo $config['color_primario'] ?? '#1E40AF'; ?>;
        }
        .btn-primary:hover {
            background: <?php echo $config['color_secundario'] ?? '#1E3A8A'; ?>;
        }
        .text-primary {
            color: <?php echo $config['color_primario'] ?? '#1E40AF'; ?>;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="header-bg text-white py-6 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <?php if (!empty($config['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="Logo" class="h-16 w-auto mr-4">
                    <?php endif; ?>
                    <div>
                        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($config['nombre_sitio'] ?? APP_NAME); ?></h1>
                        <p class="text-sm opacity-90">Directorio de Empresas Afiliadas</p>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>/login.php" class="bg-white text-blue-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                </a>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- Formulario de búsqueda -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <form method="GET" action="" class="space-y-4">
                <!-- Búsqueda principal -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-search mr-2"></i>Buscar empresas
                    </label>
                    <input type="text" 
                           name="q" 
                           value="<?php echo htmlspecialchars($query); ?>"
                           placeholder="Buscar por nombre, productos, servicios o palabras clave..."
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <!-- Filtros -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Sector</label>
                        <select name="sector" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Todos los sectores</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $sector == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Categoría</label>
                        <select name="categoria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $categoria == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Ciudad</label>
                        <select name="ciudad" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Todas las ciudades</option>
                            <?php foreach ($ciudades as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['ciudad']); ?>" <?php echo $ciudad === $c['ciudad'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['ciudad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition font-semibold flex-1">
                        <i class="fas fa-search mr-2"></i>Buscar
                    </button>
                    <a href="<?php echo BASE_URL; ?>/directorio_publico.php" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <?php if (!empty($query) || !empty($sector) || !empty($categoria) || !empty($ciudad)): ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    Resultados de búsqueda
                    <span class="text-primary">(<?php echo $total_results; ?> empresa<?php echo $total_results != 1 ? 's' : ''; ?>)</span>
                </h2>
            </div>
        <?php else: ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    Todas las empresas
                    <span class="text-primary">(<?php echo $total_results; ?>)</span>
                </h2>
            </div>
        <?php endif; ?>

        <?php if (!empty($empresas)): ?>
            <!-- Grid de empresas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($empresas as $empresa): ?>
                <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition overflow-hidden">
                    <!-- Logo de la empresa -->
                    <?php if (!empty($empresa['logo'])): ?>
                    <div class="h-48 bg-gray-100 flex items-center justify-center p-4">
                        <img src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($empresa['logo']); ?>" 
                             alt="<?php echo htmlspecialchars($empresa['razon_social']); ?>"
                             class="max-h-full max-w-full object-contain">
                    </div>
                    <?php else: ?>
                    <div class="h-48 bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center">
                        <i class="fas fa-building text-white text-6xl opacity-50"></i>
                    </div>
                    <?php endif; ?>

                    <!-- Contenido -->
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2 line-clamp-2">
                            <?php echo htmlspecialchars($empresa['razon_social']); ?>
                        </h3>
                        
                        <?php if ($empresa['sector_nombre']): ?>
                        <div class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm mb-3">
                            <?php echo htmlspecialchars($empresa['sector_nombre']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($empresa['descripcion'])): ?>
                        <p class="text-gray-600 mb-4 line-clamp-3">
                            <?php echo htmlspecialchars(substr($empresa['descripcion'], 0, 150)); ?>...
                        </p>
                        <?php endif; ?>

                        <!-- Servicios/Productos -->
                        <?php if (!empty($empresa['servicios_productos'])): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500 font-semibold mb-1">
                                <i class="fas fa-tags mr-1"></i>Productos/Servicios:
                            </p>
                            <p class="text-sm text-gray-700 line-clamp-2">
                                <?php echo htmlspecialchars(substr($empresa['servicios_productos'], 0, 100)); ?>...
                            </p>
                        </div>
                        <?php endif; ?>

                        <!-- Información de contacto -->
                        <div class="border-t pt-4 space-y-2">
                            <?php if ($empresa['ciudad']): ?>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                <?php echo htmlspecialchars($empresa['ciudad']); ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($empresa['telefono']): ?>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-phone mr-2 text-gray-400"></i>
                                <a href="tel:<?php echo htmlspecialchars($empresa['telefono']); ?>" class="hover:text-blue-600">
                                    <?php echo htmlspecialchars($empresa['telefono']); ?>
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if ($empresa['whatsapp']): ?>
                            <div class="text-sm text-gray-600">
                                <i class="fab fa-whatsapp mr-2 text-green-500"></i>
                                <a href="https://wa.me/52<?php echo htmlspecialchars($empresa['whatsapp']); ?>" 
                                   target="_blank"
                                   class="hover:text-green-600">
                                    <?php echo htmlspecialchars($empresa['whatsapp']); ?>
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if ($empresa['email']): ?>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                <a href="mailto:<?php echo htmlspecialchars($empresa['email']); ?>" class="hover:text-blue-600">
                                    <?php echo htmlspecialchars($empresa['email']); ?>
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if ($empresa['sitio_web']): ?>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-globe mr-2 text-gray-400"></i>
                                <a href="<?php echo htmlspecialchars($empresa['sitio_web']); ?>" 
                                   target="_blank" 
                                   class="hover:text-blue-600">
                                    Sitio Web
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Redes sociales -->
                        <?php if ($empresa['facebook'] || $empresa['instagram']): ?>
                        <div class="border-t mt-4 pt-4 flex gap-3">
                            <?php if ($empresa['facebook']): ?>
                            <a href="<?php echo htmlspecialchars($empresa['facebook']); ?>" 
                               target="_blank"
                               class="text-blue-600 hover:text-blue-700 text-xl">
                                <i class="fab fa-facebook"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($empresa['instagram']): ?>
                            <a href="<?php echo htmlspecialchars($empresa['instagram']); ?>" 
                               target="_blank"
                               class="text-pink-600 hover:text-pink-700 text-xl">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center gap-2">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg <?php echo $i == $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- No hay resultados -->
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">No se encontraron empresas</h3>
                <p class="text-gray-600 mb-6">Intenta con otros términos de búsqueda o filtros</p>
                <a href="<?php echo BASE_URL; ?>/directorio_publico.php" class="btn-primary text-white px-6 py-3 rounded-lg inline-block">
                    Ver todas las empresas
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p class="mb-2">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['nombre_sitio'] ?? APP_NAME); ?>. Todos los derechos reservados.</p>
            <?php if (!empty($config['email_sistema'])): ?>
            <p class="text-sm text-gray-400">
                Contacto: <?php echo htmlspecialchars($config['email_sistema']); ?>
                <?php if (!empty($config['telefono_contacto'])): ?>
                | Tel: <?php echo htmlspecialchars($config['telefono_contacto']); ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <p class="text-sm text-gray-400 mt-2">
                <a href="<?php echo BASE_URL; ?>/register.php" class="hover:text-white">¿Quieres afiliar tu empresa?</a>
            </p>
        </div>
    </footer>
</body>
</html>
