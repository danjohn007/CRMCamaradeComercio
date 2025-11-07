<?php
/**
 * Página de verificación de email
 * Verifica el código de verificación enviado por email
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';
$code = $_GET['code'] ?? '';
$db = null;

// Intentar obtener conexión a la base de datos
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $error = 'Error de conexión a la base de datos.';
}

if (empty($code)) {
    $error = 'Código de verificación no proporcionado.';
} elseif ($db) {
    try {
        // Buscar usuario con este código de verificación
        $stmt = $db->prepare("SELECT id, email, email_verificado FROM usuarios WHERE codigo_verificacion = ?");
        $stmt->execute([$code]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Código de verificación inválido o expirado.';
        } elseif ($user['email_verificado']) {
            $success = 'Tu email ya ha sido verificado. Puedes iniciar sesión.';
        } else {
            // Actualizar usuario para marcar email como verificado y activar cuenta
            $stmt = $db->prepare("UPDATE usuarios SET email_verificado = 1, activo = 1, fecha_verificacion = NOW(), codigo_verificacion = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $success = '¡Email verificado correctamente! Ya puedes iniciar sesión en el sistema.';
        }
    } catch (Exception $e) {
        $error = 'Error al verificar el email: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
    // Cargar colores personalizados
    $color_primario = '#1E40AF';
    $color_secundario = '#10B981';
    if ($db) {
        try {
            $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('color_primario', 'color_secundario')");
            $custom_colors = [];
            while ($row = $stmt->fetch()) {
                $custom_colors[$row['clave']] = $row['valor'];
            }
            $color_primario = $custom_colors['color_primario'] ?? '#1E40AF';
            $color_secundario = $custom_colors['color_secundario'] ?? '#10B981';
        } catch (Exception $e) {
            // Use defaults
        }
    }
    ?>
    <style>
        :root {
            --color-primario: <?php echo $color_primario; ?>;
            --color-secundario: <?php echo $color_secundario; ?>;
        }
        .bg-blue-600 {
            background-color: var(--color-primario) !important;
        }
        .text-blue-600 {
            color: var(--color-primario) !important;
        }
        .hover\:bg-blue-700:hover {
            background-color: var(--color-primario) !important;
            filter: brightness(0.9);
        }
        body {
            background: linear-gradient(135deg, <?php echo $color_primario; ?>15 0%, <?php echo $color_secundario; ?>15 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <!-- Logo y Título -->
            <div class="text-center mb-8">
                <?php
                // Obtener logo del sistema desde configuración
                $logo_sistema = '';
                $nombre_sitio = APP_NAME;
                if ($db) {
                    try {
                        $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('logo_sistema', 'nombre_sitio')");
                        while ($row = $stmt->fetch()) {
                            if ($row['clave'] === 'logo_sistema') {
                                $logo_sistema = $row['valor'];
                            } elseif ($row['clave'] === 'nombre_sitio' && !empty($row['valor'])) {
                                $nombre_sitio = $row['valor'];
                            }
                        }
                    } catch (Exception $e) {}
                }
                
                if (!empty($logo_sistema) && file_exists(ROOT_PATH . $logo_sistema)):
                ?>
                    <div class="mb-4">
                        <img src="<?php echo BASE_URL . $logo_sistema; ?>" alt="Logo" class="mx-auto max-h-24">
                    </div>
                <?php else: ?>
                    <div class="inline-block bg-blue-600 text-white rounded-full p-4 mb-4">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo e($nombre_sitio); ?></h1>
                <p class="text-gray-600 mt-2">Verificación de Email</p>
            </div>

            <!-- Mensaje -->
            <div class="bg-white rounded-lg shadow-xl p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <p class="text-red-700"><?php echo e($error); ?></p>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="<?php echo BASE_URL; ?>/login.php" 
                           class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200">
                            Ir a Iniciar Sesión
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-green-700 font-semibold"><?php echo e($success); ?></p>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="<?php echo BASE_URL; ?>/login.php" 
                           class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200">
                            Iniciar Sesión
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
