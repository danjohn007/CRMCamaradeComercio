<?php
/**
 * Página de registro de nuevos usuarios (Entidades Comerciales)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';
$step = 1;

// Generar captcha
if (!isset($_SESSION['captcha_num1'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Paso 1: Registro inicial
    $email = sanitize($_POST['email'] ?? '');
    $rfc = strtoupper(sanitize($_POST['rfc'] ?? ''));
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $captcha_respuesta = intval($_POST['captcha_respuesta'] ?? 0);
    $terminos = isset($_POST['terminos']);
    
    // Validaciones
    if (empty($email) || empty($rfc) || empty($whatsapp) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (!validarEmail($email)) {
        $error = 'El email no es válido';
    } elseif (!validarRFC($rfc)) {
        $error = 'El RFC no tiene un formato válido';
    } elseif (strlen($whatsapp) != 10 || !is_numeric($whatsapp)) {
        $error = 'El WhatsApp debe tener 10 dígitos';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif ($captcha_respuesta != ($_SESSION['captcha_num1'] + $_SESSION['captcha_num2'])) {
        $error = 'La respuesta del captcha es incorrecta';
    } elseif (!$terminos) {
        $error = 'Debes aceptar los términos y condiciones';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verificar si el email ya existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este email ya está registrado';
            } else {
                // Verificar si el RFC ya existe
                $stmt = $db->prepare("SELECT id FROM empresas WHERE rfc = ?");
                $stmt->execute([$rfc]);
                if ($stmt->fetch()) {
                    $error = 'Este RFC ya está registrado';
                } else {
                    // Crear código de verificación
                    $codigo_verificacion = generateVerificationCode();
                    
                    // Hash de la contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar usuario
                    $stmt = $db->prepare("INSERT INTO usuarios (email, password, rol, codigo_verificacion, activo, email_verificado) VALUES (?, ?, 'ENTIDAD_COMERCIAL', ?, 0, 0)");
                    $stmt->execute([$email, $password_hash, $codigo_verificacion]);
                    
                    // Enviar email de verificación
                    $verify_link = BASE_URL . "/verify-email.php?code=" . $codigo_verificacion;
                    $email_body = "Hola,\n\nGracias por registrarte en " . APP_NAME . ".\n\nPor favor, verifica tu email haciendo clic en el siguiente enlace:\n\n" . $verify_link . "\n\nSi no te registraste, ignora este mensaje.";
                    
                    sendEmail($email, 'Verifica tu email - ' . APP_NAME, $email_body);
                    
                    // Redirigir a login con mensaje de éxito
                    redirect('/login.php?success=registered');
                }
            }
        } catch (Exception $e) {
            $error = 'Error al procesar el registro: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-green-50 to-green-100 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto">
            <!-- Encabezado -->
            <div class="text-center mb-8">
                <div class="inline-block bg-green-600 text-white rounded-full p-4 mb-4">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-800">Registro de Empresa</h1>
                <p class="text-gray-600 mt-2">Únete a la Cámara de Comercio de Querétaro</p>
            </div>

            <!-- Formulario de Registro -->
            <div class="bg-white rounded-lg shadow-xl p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Email -->
                        <div class="md:col-span-2">
                            <label for="email" class="block text-gray-700 font-semibold mb-2">
                                Email *
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="empresa@ejemplo.com"
                                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            >
                        </div>

                        <!-- RFC -->
                        <div>
                            <label for="rfc" class="block text-gray-700 font-semibold mb-2">
                                RFC *
                            </label>
                            <input 
                                type="text" 
                                id="rfc" 
                                name="rfc" 
                                required
                                maxlength="13"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="ABC123456XYZ"
                                value="<?php echo htmlspecialchars($rfc ?? ''); ?>"
                            >
                            <p class="text-xs text-gray-500 mt-1">12 o 13 caracteres</p>
                        </div>

                        <!-- WhatsApp -->
                        <div>
                            <label for="whatsapp" class="block text-gray-700 font-semibold mb-2">
                                WhatsApp *
                            </label>
                            <input 
                                type="tel" 
                                id="whatsapp" 
                                name="whatsapp" 
                                required
                                maxlength="10"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="4421234567"
                                value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>"
                            >
                            <p class="text-xs text-gray-500 mt-1">10 dígitos sin espacios</p>
                        </div>

                        <!-- Contraseña -->
                        <div>
                            <label for="password" class="block text-gray-700 font-semibold mb-2">
                                Contraseña *
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                minlength="8"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Mínimo 8 caracteres"
                            >
                        </div>

                        <!-- Confirmar Contraseña -->
                        <div>
                            <label for="password_confirm" class="block text-gray-700 font-semibold mb-2">
                                Confirmar Contraseña *
                            </label>
                            <input 
                                type="password" 
                                id="password_confirm" 
                                name="password_confirm" 
                                required
                                minlength="8"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Repite tu contraseña"
                            >
                        </div>

                        <!-- Captcha -->
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-semibold mb-2">
                                Verifica que eres humano *
                            </label>
                            <div class="flex items-center space-x-4">
                                <div class="bg-gray-100 px-6 py-3 rounded-lg font-mono text-xl">
                                    <?php echo $_SESSION['captcha_num1']; ?> + <?php echo $_SESSION['captcha_num2']; ?> = ?
                                </div>
                                <input 
                                    type="number" 
                                    id="captcha_respuesta" 
                                    name="captcha_respuesta" 
                                    required
                                    class="w-24 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                    placeholder="?"
                                >
                            </div>
                        </div>

                        <!-- Términos y Condiciones -->
                        <div class="md:col-span-2">
                            <label class="flex items-start">
                                <input 
                                    type="checkbox" 
                                    name="terminos" 
                                    required
                                    class="mt-1 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                >
                                <span class="ml-3 text-sm text-gray-600">
                                    Acepto los 
                                    <a href="#" class="text-green-600 hover:underline">términos y condiciones</a> 
                                    y la 
                                    <a href="#" class="text-green-600 hover:underline">política de privacidad</a>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Botón de registro -->
                    <div class="mt-8">
                        <button 
                            type="submit"
                            class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition duration-200"
                        >
                            Crear Cuenta
                        </button>
                    </div>
                </form>

                <!-- Link a login -->
                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        ¿Ya tienes cuenta? 
                        <a href="login.php" class="text-green-600 hover:underline font-semibold">
                            Inicia sesión aquí
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
