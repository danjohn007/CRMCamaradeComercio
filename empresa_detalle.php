<?php
/**
 * Página de detalles de empresa
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();
$config = getConfiguracion();
$empresa_id = intval($_GET['id'] ?? 0);

if ($empresa_id <= 0) {
    header('Location: ' . BASE_URL . '/directorio_publico.php');
    exit;
}

// Obtener datos de la empresa
$stmt = $db->prepare("
    SELECT e.*, s.nombre as sector_nombre, c.nombre as categoria_nombre,
           m.nombre as membresia_nombre
    FROM empresas e
    LEFT JOIN sectores s ON e.sector_id = s.id
    LEFT JOIN categorias c ON e.categoria_id = c.id
    LEFT JOIN membresias m ON e.membresia_id = m.id
    WHERE e.id = ? AND e.activo = 1
");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: ' . BASE_URL . '/directorio_publico.php');
    exit;
}

// Obtener imágenes de la empresa
$stmt = $db->prepare("SELECT * FROM empresa_imagenes WHERE empresa_id = ? ORDER BY orden ASC");
$stmt->execute([$empresa_id]);
$imagenes = $stmt->fetchAll();

// Obtener calificaciones recientes
$stmt = $db->prepare("
    SELECT ec.*, u.nombre as usuario_nombre
    FROM empresa_calificaciones ec
    LEFT JOIN usuarios u ON ec.usuario_id = u.id
    WHERE ec.empresa_id = ?
    ORDER BY ec.created_at DESC
    LIMIT 10
");
$stmt->execute([$empresa_id]);
$calificaciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($empresa['razon_social']); ?> - <?php echo htmlspecialchars($config['nombre_sitio'] ?? APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <style>
        .header-bg { background: <?php echo $config['color_primario'] ?? '#1E40AF'; ?>; }
        .swiper { width: 100%; height: 400px; }
        .swiper-slide { display: flex; align-items: center; justify-content: center; }
        .swiper-slide img { max-width: 100%; max-height: 100%; object-fit: contain; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="header-bg text-white py-4 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <a href="<?php echo BASE_URL; ?>/directorio_publico.php" class="text-white hover:text-gray-200">
                    <i class="fas fa-arrow-left mr-2"></i>Volver al Directorio
                </a>
                <a href="<?php echo BASE_URL; ?>/login.php" class="text-white hover:text-gray-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                </a>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Image Gallery -->
            <?php if (!empty($imagenes)): ?>
            <div class="swiper empresa-detail-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($imagenes as $imagen): ?>
                    <div class="swiper-slide bg-gray-100">
                        <img src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($imagen['ruta_imagen']); ?>" 
                             alt="<?php echo htmlspecialchars($imagen['descripcion'] ?? $empresa['razon_social']); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
            <?php elseif (!empty($empresa['logo'])): ?>
            <div class="h-96 bg-gray-100 flex items-center justify-center p-8">
                <img src="<?php echo BASE_URL . '/public/uploads/' . htmlspecialchars($empresa['logo']); ?>" 
                     alt="<?php echo htmlspecialchars($empresa['razon_social']); ?>"
                     class="max-h-full max-w-full object-contain">
            </div>
            <?php endif; ?>

            <div class="p-8">
                <!-- Company Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <?php echo htmlspecialchars($empresa['razon_social']); ?>
                    </h1>
                    
                    <?php if ($empresa['sector_nombre']): ?>
                    <span class="inline-block px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                        <?php echo htmlspecialchars($empresa['sector_nombre']); ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Rating Display -->
                    <?php if ($empresa['total_calificaciones'] > 0): ?>
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex text-yellow-500 text-xl">
                            <?php
                            $rating = floatval($empresa['calificacion_promedio']);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= floor($rating)) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i - 0.5 <= $rating) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span class="text-gray-600">
                            <?php echo number_format($rating, 1); ?> 
                            (<?php echo $empresa['total_calificaciones']; ?> calificaciones)
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if (!empty($empresa['descripcion'])): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Descripción</h2>
                    <p class="text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($empresa['descripcion'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Services/Products -->
                <?php if (!empty($empresa['servicios_productos'])): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-tags mr-2"></i>Productos y Servicios
                    </h2>
                    <p class="text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($empresa['servicios_productos'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Contact Information -->
                <div class="mb-6 grid md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Información de Contacto</h2>
                        <div class="space-y-3">
                            <?php if ($empresa['telefono']): ?>
                            <div class="flex items-center">
                                <i class="fas fa-phone w-6 text-gray-400"></i>
                                <a href="tel:<?php echo htmlspecialchars($empresa['telefono']); ?>" 
                                   class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($empresa['telefono']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($empresa['whatsapp']): ?>
                            <div class="flex items-center">
                                <i class="fab fa-whatsapp w-6 text-green-500"></i>
                                <a href="https://wa.me/52<?php echo htmlspecialchars($empresa['whatsapp']); ?>" 
                                   target="_blank"
                                   class="text-green-600 hover:underline">
                                    <?php echo htmlspecialchars($empresa['whatsapp']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($empresa['email']): ?>
                            <div class="flex items-center">
                                <i class="fas fa-envelope w-6 text-gray-400"></i>
                                <a href="mailto:<?php echo htmlspecialchars($empresa['email']); ?>" 
                                   class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($empresa['email']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($empresa['sitio_web']): ?>
                            <div class="flex items-center">
                                <i class="fas fa-globe w-6 text-gray-400"></i>
                                <a href="<?php echo htmlspecialchars($empresa['sitio_web']); ?>" 
                                   target="_blank"
                                   class="text-blue-600 hover:underline">
                                    Visitar Sitio Web
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Social Media -->
                        <?php if ($empresa['facebook'] || $empresa['instagram']): ?>
                        <div class="mt-4">
                            <h3 class="font-semibold text-gray-700 mb-2">Redes Sociales</h3>
                            <div class="flex gap-3">
                                <?php if ($empresa['facebook']): ?>
                                <a href="<?php echo htmlspecialchars($empresa['facebook']); ?>" 
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-700 text-2xl">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($empresa['instagram']): ?>
                                <a href="<?php echo htmlspecialchars($empresa['instagram']); ?>" 
                                   target="_blank"
                                   class="text-pink-600 hover:text-pink-700 text-2xl">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Ubicación</h2>
                        <?php if ($empresa['direccion_comercial']): ?>
                        <p class="text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            <?php echo htmlspecialchars($empresa['direccion_comercial']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($empresa['ciudad']): ?>
                        <p class="text-gray-700 mb-2">
                            <?php echo htmlspecialchars($empresa['ciudad']); ?>, <?php echo htmlspecialchars($empresa['estado']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($empresa['codigo_postal']): ?>
                        <p class="text-gray-700">
                            C.P. <?php echo htmlspecialchars($empresa['codigo_postal']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Calificaciones y Comentarios -->
                <?php if (!empty($calificaciones)): ?>
                <div class="border-t pt-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Calificaciones y Comentarios</h2>
                    <div class="space-y-4">
                        <?php foreach ($calificaciones as $cal): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($cal['usuario_nombre'] ?? 'Usuario'); ?>
                                    </span>
                                    <div class="flex text-yellow-500 text-sm">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo $i <= $cal['calificacion'] ? 'fas' : 'far'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span class="text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($cal['created_at'])); ?>
                                </span>
                            </div>
                            <?php if (!empty($cal['comentario'])): ?>
                            <p class="text-gray-700">
                                <?php echo htmlspecialchars($cal['comentario']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        <?php if (!empty($imagenes)): ?>
        new Swiper('.empresa-detail-swiper', {
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
                delay: 4000,
                disableOnInteraction: false,
            },
        });
        <?php endif; ?>
    </script>
</body>
</html>
