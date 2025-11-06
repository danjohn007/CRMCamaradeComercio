<?php
/**
 * Página de login del sistema
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
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, ingresa tu email y contraseña';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verificar si el email está verificado
                if (!$user['email_verificado']) {
                    $error = 'Verificación pendiente de tu correo. Por favor, revisa tu bandeja de entrada y spam para verificar tu email.';
                } elseif ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
                    // Verificar bloqueo por intentos fallidos
                    $error = 'Cuenta temporalmente bloqueada. Intenta más tarde.';
                } else {
                    // Verificar contraseña
                    if (password_verify($password, $user['password'])) {
                        // Login exitoso
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_nombre'] = $user['nombre'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_rol'] = $user['rol'];
                        $_SESSION['empresa_id'] = $user['empresa_id'];
                        
                        // Resetear intentos fallidos
                        $stmt = $db->prepare("UPDATE usuarios SET intentos_login = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Registrar en auditoría
                        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, ip_address, user_agent) VALUES (?, 'LOGIN', ?, ?)");
                        $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                        
                        redirect('/dashboard.php');
                    } else {
                        // Contraseña incorrecta
                        $intentos = $user['intentos_login'] + 1;
                        $bloqueado_hasta = null;
                        
                        // Bloquear después de 5 intentos fallidos
                        if ($intentos >= 5) {
                            $bloqueado_hasta = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                            $error = 'Demasiados intentos fallidos. Cuenta bloqueada por 15 minutos.';
                        } else {
                            $error = 'Email o contraseña incorrectos';
                        }
                        
                        $stmt = $db->prepare("UPDATE usuarios SET intentos_login = ?, bloqueado_hasta = ? WHERE id = ?");
                        $stmt->execute([$intentos, $bloqueado_hasta, $user['id']]);
                    }
                }
            } else {
                $error = 'Email o contraseña incorrectos';
            }
        } catch (Exception $e) {
            $error = 'Error al procesar la solicitud';
        }
    }
}

// Verificar mensaje de éxito (ej: después de registro)
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'verify_email') {
        $success = 'Valida tu correo para entrar al sistema. Revisa tu bandeja de entrada y spam.';
    } elseif ($_GET['success'] === 'registered') {
        $success = 'Registro exitoso. Por favor, inicia sesión.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
    // Cargar colores personalizados
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('color_primario', 'color_secundario')");
        $custom_colors = [];
        while ($row = $stmt->fetch()) {
            $custom_colors[$row['clave']] = $row['valor'];
        }
        $color_primario = $custom_colors['color_primario'] ?? '#1E40AF';
        $color_secundario = $custom_colors['color_secundario'] ?? '#10B981';
    } catch (Exception $e) {
        $color_primario = '#1E40AF';
        $color_secundario = '#10B981';
    }
    ?>
    <style>
        :root {
            --color-primario: <?php echo $color_primario; ?>;
            --color-secundario: <?php echo $color_secundario; ?>;
        }
        .bg-blue-600, .bg-gradient-to-br {
            background-color: var(--color-primario) !important;
        }
        .text-blue-600 {
            color: var(--color-primario) !important;
        }
        .hover\:bg-blue-700:hover, .hover\:underline:hover {
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
                <?php
                // Obtener logo del sistema desde configuración
                $logo_sistema = '';
                $nombre_sitio = APP_NAME;
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
                
                if (!empty($logo_sistema) && file_exists(ROOT_PATH . $logo_sistema)):
                ?>
                    <div class="mb-4">
                        <img src="<?php echo BASE_URL . $logo_sistema; ?>" alt="Logo" class="mx-auto max-h-24">
                    </div>
                <?php else: ?>
                    <div class="inline-block bg-blue-600 text-white rounded-full p-4 mb-4">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo e($nombre_sitio); ?></h1>
                <p class="text-gray-600 mt-2">Inicia sesión para acceder al sistema</p>
            </div>

            <!-- Formulario de Login -->
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

                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 font-semibold mb-2">
                            Contraseña
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="••••••••"
                        >
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-600">Recordarme</span>
                        </label>
                        <a href="forgot-password.php" class="text-sm text-blue-600 hover:underline">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <button 
                        type="submit"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200"
                    >
                        Iniciar Sesión
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        ¿No tienes cuenta? 
                        <a href="register.php" class="text-blue-600 hover:underline font-semibold">
                            Regístrate aquí
                        </a>
                    </p>
                </div>
            </div>

            <!-- Información adicional -->
            <div class="mt-6 text-center text-sm text-gray-600">
                <?php
                // Obtener información de contacto desde configuración
                $email_contacto = '';
                $telefono_contacto = '';
                $horario_atencion = '';
                try {
                    $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('email_contacto', 'telefono_contacto', 'horario_atencion')");
                    while ($row = $stmt->fetch()) {
                        if ($row['clave'] === 'email_contacto') {
                            $email_contacto = $row['valor'];
                        } elseif ($row['clave'] === 'telefono_contacto') {
                            $telefono_contacto = $row['valor'];
                        } elseif ($row['clave'] === 'horario_atencion') {
                            $horario_atencion = $row['valor'];
                        }
                    }
                } catch (Exception $e) {}
                ?>
                <p class="font-semibold text-gray-700 mb-2">Para más información, contacta con nosotros:</p>
                <?php if (!empty($email_contacto)): ?>
                <p class="mb-1">
                    <i class="fas fa-envelope mr-2"></i>
                    <a href="mailto:<?php echo e($email_contacto); ?>" class="text-blue-600 hover:underline">
                        <?php echo e($email_contacto); ?>
                    </a>
                </p>
                <?php endif; ?>
                <?php if (!empty($telefono_contacto)): ?>
                <p class="mb-1">
                    <i class="fas fa-phone mr-2"></i>
                    <span class="text-gray-700"><?php echo e($telefono_contacto); ?></span>
                </p>
                <?php endif; ?>
                <?php if (!empty($horario_atencion)): ?>
                <p class="mt-2 text-xs text-gray-500">
                    <i class="fas fa-clock mr-1"></i>
                    <?php echo e($horario_atencion); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
