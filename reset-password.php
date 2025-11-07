<?php
/**
 * Página para restablecer contraseña con token
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valid_token = false;

// Obtener configuración del sistema una sola vez
$config = getConfiguracion();
$nombre_sitio = $config['nombre_sitio'] ?? APP_NAME;
$color_primario = $config['color_primario'] ?? '#1E40AF';
$color_secundario = $config['color_secundario'] ?? '#10B981';
$logo_sistema = $config['logo_sistema'] ?? '';

// Verificar token
if (!empty($token)) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, email, nombre 
            FROM usuarios 
            WHERE reset_token IS NOT NULL
            AND reset_token != ''
            AND reset_token = ? 
            AND reset_token_expiry > NOW() 
            AND activo = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $valid_token = true;
        } else {
            $error = 'El enlace de recuperación es inválido o ha expirado. Por favor, solicita uno nuevo.';
        }
    } catch (Exception $e) {
        $error = 'Error al verificar el token.';
        error_log("Error en reset-password: " . $e->getMessage());
    }
} else {
    $error = 'No se proporcionó un token de recuperación.';
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Por favor, ingresa una nueva contraseña';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Actualizar contraseña y limpiar token
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET password = ?, 
                    reset_token = NULL, 
                    reset_token_expiry = NULL,
                    intentos_login = 0,
                    bloqueado_hasta = NULL
                WHERE reset_token = ?
            ");
            $stmt->execute([$password_hash, $token]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Tu contraseña ha sido actualizada exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.';
                $valid_token = false; // Deshabilitar el formulario
            } else {
                $error = 'No se pudo actualizar la contraseña. Por favor, intenta nuevamente.';
            }
        } catch (Exception $e) {
            $error = 'Error al actualizar la contraseña.';
            error_log("Error en reset-password: " . $e->getMessage());
        }
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - <?php echo e($nombre_sitio); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .focus\:ring-blue-500:focus {
            --tw-ring-color: var(--color-primario) !important;
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
                <?php if (!empty($logo_sistema) && file_exists(ROOT_PATH . $logo_sistema)): ?>
                    <div class="mb-4">
                        <img src="<?php echo BASE_URL . $logo_sistema; ?>" alt="Logo" class="mx-auto max-h-24">
                    </div>
                <?php else: ?>
                    <div class="inline-block bg-blue-600 text-white rounded-full p-4 mb-4">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo e($nombre_sitio); ?></h1>
                <p class="text-gray-600 mt-2">Restablecer Contraseña</p>
            </div>

            <!-- Formulario de Cambio de Contraseña -->
            <div class="bg-white rounded-lg shadow-xl p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                        <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                    <div class="text-center">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200">
                            Ir a Iniciar Sesión
                        </a>
                    </div>
                <?php elseif ($valid_token): ?>
                <p class="text-gray-600 mb-6">
                    Ingresa tu nueva contraseña.
                </p>

                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 font-semibold mb-2">
                            Nueva Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            minlength="8"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Mínimo 8 caracteres"
                        >
                    </div>

                    <div class="mb-6">
                        <label for="password_confirm" class="block text-gray-700 font-semibold mb-2">
                            Confirmar Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            required
                            minlength="8"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Repite tu contraseña"
                        >
                    </div>

                    <button 
                        type="submit"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200"
                    >
                        Restablecer Contraseña
                    </button>
                </form>
                <?php else: ?>
                <div class="text-center">
                    <a href="<?php echo BASE_URL; ?>/forgot-password.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200">
                        Solicitar Nuevo Enlace
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
