<?php
/**
 * Página para recuperar contraseña
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor, ingresa tu email';
    } elseif (!validarEmail($email)) {
        $error = 'El email no es válido';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, email, nombre FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generar token de recuperación
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $stmt = $db->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $stmt->execute([$token, $expiry, $user['id']]);
                
                // Enviar email con el enlace de recuperación
                $config = getConfiguracion();
                $nombre_sitio = $config['nombre_sitio'] ?? APP_NAME;
                $reset_link = BASE_URL . "/reset-password.php?token=" . $token;
                
                $email_body = "Hola " . $user['nombre'] . ",\n\n";
                $email_body .= "Has solicitado restablecer tu contraseña en {$nombre_sitio}.\n\n";
                $email_body .= "Haz clic en el siguiente enlace para crear una nueva contraseña:\n\n";
                $email_body .= $reset_link . "\n\n";
                $email_body .= "Este enlace expirará en 1 hora.\n\n";
                $email_body .= "Si no solicitaste este cambio, ignora este mensaje.\n\n";
                $email_body .= "Saludos,\n";
                $email_body .= "{$nombre_sitio}";
                
                sendEmail($email, 'Recuperar Contraseña - ' . $nombre_sitio, $email_body);
                
                $success = 'Se ha enviado un correo con las instrucciones para recuperar tu contraseña. Por favor revisa tu bandeja de entrada y spam.';
            } else {
                // Por seguridad, mostrar el mismo mensaje aunque el email no exista
                $success = 'Se ha enviado un correo con las instrucciones para recuperar tu contraseña. Por favor revisa tu bandeja de entrada y spam.';
            }
        } catch (Exception $e) {
            $error = 'Error al procesar la solicitud. Por favor, intenta más tarde.';
            error_log("Error en forgot-password: " . $e->getMessage());
        }
    }
}

// Cargar configuración de colores y nombre del sitio
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('color_primario', 'color_secundario', 'logo_sistema', 'nombre_sitio')");
    $custom_config = [];
    while ($row = $stmt->fetch()) {
        $custom_config[$row['clave']] = $row['valor'];
    }
    $color_primario = $custom_config['color_primario'] ?? '#1E40AF';
    $color_secundario = $custom_config['color_secundario'] ?? '#10B981';
    $logo_sistema = $custom_config['logo_sistema'] ?? '';
    $nombre_sitio = $custom_config['nombre_sitio'] ?? APP_NAME;
} catch (Exception $e) {
    $color_primario = '#1E40AF';
    $color_secundario = '#10B981';
    $logo_sistema = '';
    $nombre_sitio = APP_NAME;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo e($nombre_sitio); ?></title>
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
                <p class="text-gray-600 mt-2">Recuperar Contraseña</p>
            </div>

            <!-- Formulario de Recuperación -->
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
                <?php endif; ?>

                <?php if (!$success): ?>
                <p class="text-gray-600 mb-6">
                    Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.
                </p>

                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="email" class="block text-gray-700 font-semibold mb-2">
                            Email
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="tu@email.com"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        >
                    </div>

                    <button 
                        type="submit"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200"
                    >
                        Enviar Enlace de Recuperación
                    </button>
                </form>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <a href="<?php echo BASE_URL; ?>/login.php" class="text-blue-600 hover:underline">
                        Volver al inicio de sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
