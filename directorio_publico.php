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

// Check if user is logged in
$user = getCurrentUser();
$is_logged_in = isLoggedIn();

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

// Obtener resultados paginados con calificación promedio
$offset = ($page - 1) * $per_page;
$sql = "SELECT e.*, s.nombre as sector_nombre, c.nombre as categoria_nombre,
        e.calificacion_promedio, e.total_calificaciones
        FROM empresas e
        LEFT JOIN sectores s ON e.sector_id = s.id
        LEFT JOIN categorias c ON e.categoria_id = c.id
        WHERE $where_sql
        ORDER BY e.razon_social ASC
        LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$empresas = $stmt->fetchAll();

// Si el usuario está logueado, obtener sus favoritos
$favoritos = [];
if ($is_logged_in && $user) {
    $stmt = $db->prepare("SELECT empresa_id FROM empresa_favoritos WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $favoritos = array_column($stmt->fetchAll(), 'empresa_id');
}

// Obtener imágenes para cada empresa
$empresas_con_imagenes = [];
foreach ($empresas as $empresa) {
    $stmt = $db->prepare("SELECT * FROM empresa_imagenes WHERE empresa_id = ? ORDER BY orden ASC");
    $stmt->execute([$empresa['id']]);
    $empresa['imagenes'] = $stmt->fetchAll();
    $empresas_con_imagenes[] = $empresa;
}
$empresas = $empresas_con_imagenes;

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
    <!-- Swiper JS for slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
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
        .swiper {
            width: 100%;
            height: 100%;
        }
        .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .swiper-slide img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            cursor: zoom-in;
        }
        /* Modal para zoom de imagen */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        .image-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        .image-modal .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .action-icon {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .action-icon:hover {
            transform: scale(1.2);
        }
        .action-icon.active {
            color: #EF4444;
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
                <?php if ($is_logged_in): ?>
                <div class="flex items-center gap-4">
                    <span class="text-white text-sm">
                        <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($user['nombre']); ?>
                    </span>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition font-semibold text-sm">
                        <i class="fas fa-home mr-2"></i>Dashboard
                    </a>
                </div>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php" class="bg-white text-blue-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                </a>
                <?php endif; ?>

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
                    <!-- Imágenes de la empresa con slider -->
                    <?php if (!empty($empresa['imagenes']) && count($empresa['imagenes']) > 0): ?>
                    <div class="h-48 bg-gray-100 relative">
                        <div class="swiper empresa-swiper-<?php echo $empresa['id']; ?> h-full">
                            <div class="swiper-wrapper">
                                <?php foreach ($empresa['imagenes'] as $imagen): ?>
                                <div class="swiper-slide">
                                    <img src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($imagen['ruta_imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($imagen['descripcion'] ?? $empresa['razon_social']); ?>"
                                         class="zoom-image"
                                         data-full-src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($imagen['ruta_imagen']); ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($empresa['imagenes']) > 1): ?>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-pagination"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif (!empty($empresa['logo'])): ?>
                    <!-- Logo de la empresa si no tiene imágenes -->
                    <div class="h-48 bg-gray-100 flex items-center justify-center p-4">
                        <img src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($empresa['logo']); ?>" 
                             alt="<?php echo htmlspecialchars($empresa['razon_social']); ?>"
                             class="max-h-full max-w-full object-contain zoom-image cursor-pointer"
                             data-full-src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($empresa['logo']); ?>">
                    </div>
                    <?php else: ?>
                    <div class="h-48 bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center">
                        <i class="fas fa-building text-white text-6xl opacity-50"></i>
                    </div>
                    <?php endif; ?>

                    <!-- Contenido -->
                    <div class="p-6">
                        <!-- Action Icons Row -->
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-xl font-bold text-gray-800 line-clamp-2 flex-1">
                                <?php echo htmlspecialchars($empresa['razon_social']); ?>
                            </h3>
                            <div class="flex gap-2 ml-2">
                                <!-- Ver Detalles -->
                                <a href="<?php echo BASE_URL; ?>/empresa_detalle.php?id=<?php echo $empresa['id']; ?>" 
                                   class="action-icon text-blue-600 hover:text-blue-700"
                                   title="Ver Detalles">
                                    <i class="fas fa-eye text-lg"></i>
                                </a>
                                
                                <?php if ($is_logged_in): ?>
                                <!-- Favorito -->
                                <button class="action-icon text-gray-400 hover:text-red-500 toggle-favorito <?php echo in_array($empresa['id'], $favoritos) ? 'active' : ''; ?>"
                                        data-empresa-id="<?php echo $empresa['id']; ?>"
                                        title="<?php echo in_array($empresa['id'], $favoritos) ? 'Quitar de Favoritos' : 'Agregar a Favoritos'; ?>">
                                    <i class="<?php echo in_array($empresa['id'], $favoritos) ? 'fas' : 'far'; ?> fa-heart text-lg"></i>
                                </button>
                                
                                <!-- Calificar -->
                                <button class="action-icon text-gray-400 hover:text-yellow-500 open-rating-modal"
                                        data-empresa-id="<?php echo $empresa['id']; ?>"
                                        data-empresa-nombre="<?php echo htmlspecialchars($empresa['razon_social']); ?>"
                                        title="Calificar">
                                    <i class="fas fa-star text-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Rating Display -->
                        <?php if ($empresa['total_calificaciones'] > 0): ?>
                        <div class="mb-2 flex items-center gap-2">
                            <div class="flex text-yellow-500">
                                <?php
                                $rating = floatval($empresa['calificacion_promedio']);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($rating)) {
                                        echo '<i class="fas fa-star text-sm"></i>';
                                    } elseif ($i - 0.5 <= $rating) {
                                        echo '<i class="fas fa-star-half-alt text-sm"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-sm"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="text-xs text-gray-500">(<?php echo $empresa['total_calificaciones']; ?>)</span>
                        </div>
                        <?php endif; ?>
                        
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

    <!-- Modal para zoom de imagen -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="" alt="Imagen ampliada">
    </div>

    <!-- Modal para calificar empresa -->
    <div id="ratingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Calificar Empresa</h3>
                <button onclick="closeRatingModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <p id="ratingEmpresaNombre" class="text-gray-600 mb-4"></p>
            
            <form id="ratingForm">
                <input type="hidden" id="ratingEmpresaId" name="empresa_id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Calificación</label>
                    <div class="flex gap-2 text-3xl">
                        <i class="far fa-star rating-star cursor-pointer" data-rating="1"></i>
                        <i class="far fa-star rating-star cursor-pointer" data-rating="2"></i>
                        <i class="far fa-star rating-star cursor-pointer" data-rating="3"></i>
                        <i class="far fa-star rating-star cursor-pointer" data-rating="4"></i>
                        <i class="far fa-star rating-star cursor-pointer" data-rating="5"></i>
                    </div>
                    <input type="hidden" id="ratingValue" name="calificacion" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Comentario (opcional)</label>
                    <textarea name="comentario" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Enviar Calificación
                    </button>
                    <button type="button" onclick="closeRatingModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script>
        // Initialize all Swiper instances
        <?php foreach ($empresas as $empresa): ?>
        <?php if (!empty($empresa['imagenes']) && count($empresa['imagenes']) > 1): ?>
        new Swiper('.empresa-swiper-<?php echo $empresa['id']; ?>', {
            loop: true,
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
        });
        <?php endif; ?>
        <?php endforeach; ?>

        // Image zoom functionality
        document.querySelectorAll('.zoom-image').forEach(img => {
            img.addEventListener('click', function(e) {
                e.stopPropagation();
                const fullSrc = this.getAttribute('data-full-src') || this.src;
                document.getElementById('modalImage').src = fullSrc;
                document.getElementById('imageModal').classList.add('active');
            });
        });

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        // Close modal on click outside image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeRatingModal();
            }
        });

        <?php if ($is_logged_in): ?>
        // Toggle favorito
        document.querySelectorAll('.toggle-favorito').forEach(btn => {
            btn.addEventListener('click', async function() {
                const empresaId = this.getAttribute('data-empresa-id');
                const icon = this.querySelector('i');
                const isActive = this.classList.contains('active');
                
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/api/toggle_favorito.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ empresa_id: empresaId })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        if (data.action === 'added') {
                            this.classList.add('active');
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            this.title = 'Quitar de Favoritos';
                        } else {
                            this.classList.remove('active');
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            this.title = 'Agregar a Favoritos';
                        }
                    } else {
                        alert(data.message || 'Error al actualizar favorito');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al actualizar favorito');
                }
            });
        });

        // Rating modal
        let selectedRating = 0;
        
        document.querySelectorAll('.open-rating-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                const empresaId = this.getAttribute('data-empresa-id');
                const empresaNombre = this.getAttribute('data-empresa-nombre');
                
                document.getElementById('ratingEmpresaId').value = empresaId;
                document.getElementById('ratingEmpresaNombre').textContent = empresaNombre;
                document.getElementById('ratingModal').classList.remove('hidden');
                document.getElementById('ratingModal').classList.add('flex');
                
                // Reset stars
                selectedRating = 0;
                document.querySelectorAll('.rating-star').forEach(star => {
                    star.classList.remove('fas');
                    star.classList.add('far');
                    star.classList.remove('text-yellow-500');
                    star.classList.add('text-gray-400');
                });
            });
        });

        function closeRatingModal() {
            document.getElementById('ratingModal').classList.add('hidden');
            document.getElementById('ratingModal').classList.remove('flex');
            document.getElementById('ratingForm').reset();
            selectedRating = 0;
        }

        // Star rating interaction
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.getAttribute('data-rating'));
                document.getElementById('ratingValue').value = selectedRating;
                
                // Update stars display
                document.querySelectorAll('.rating-star').forEach((s, index) => {
                    if (index < selectedRating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                        s.classList.remove('text-gray-400');
                        s.classList.add('text-yellow-500');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                        s.classList.remove('text-yellow-500');
                        s.classList.add('text-gray-400');
                    }
                });
            });
            
            // Hover effect
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                document.querySelectorAll('.rating-star').forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('text-yellow-500');
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                document.querySelectorAll('.rating-star').forEach((s, index) => {
                    if (index >= selectedRating) {
                        s.classList.remove('text-yellow-500');
                        s.classList.add('text-gray-400');
                    }
                });
            });
        });

        // Submit rating form
        document.getElementById('ratingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (selectedRating === 0) {
                alert('Por favor selecciona una calificación');
                return;
            }
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/calificar_empresa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('¡Gracias por tu calificación!');
                    closeRatingModal();
                    location.reload(); // Reload to show updated rating
                } else {
                    alert(result.message || 'Error al enviar calificación');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al enviar calificación');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
