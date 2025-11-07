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
    // Capturar datos del formulario incluyendo datos de empresa
    $email = sanitize($_POST['email'] ?? '');
    $rfc = strtoupper(sanitize($_POST['rfc'] ?? ''));
    $razon_social = sanitize($_POST['razon_social'] ?? '');
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $telefono = sanitize($_POST['telefono'] ?? '');
    $representante = sanitize($_POST['representante'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $captcha_respuesta = intval($_POST['captcha_respuesta'] ?? 0);
    $terminos = isset($_POST['terminos']);
    $empresa_id_existente = intval($_POST['empresa_id_existente'] ?? 0);
    
    // Validaciones
    if (empty($email) || empty($rfc) || empty($razon_social) || empty($whatsapp) || empty($password)) {
        $error = 'Todos los campos obligatorios deben ser completados';
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
            
            // Verificar si el email ya existe en usuarios
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este email ya está registrado. Por favor inicia sesión.';
            } else {
                // Comenzar transacción
                $db->beginTransaction();
                
                try {
                    // Si existe una empresa con este RFC, verificar que coincida y actualizar sus datos
                    if ($empresa_id_existente > 0) {
                        // Verificar que el empresa_id corresponda al RFC proporcionado (prevenir manipulación)
                        $stmt = $db->prepare("SELECT id FROM empresas WHERE id = ? AND rfc = ?");
                        $stmt->execute([$empresa_id_existente, $rfc]);
                        $empresa_verificada = $stmt->fetch();
                        
                        if ($empresa_verificada) {
                            $stmt = $db->prepare("UPDATE empresas SET razon_social = ?, email = ?, telefono = ?, whatsapp = ?, representante = ? WHERE id = ? AND rfc = ?");
                            $stmt->execute([$razon_social, $email, $telefono, $whatsapp, $representante, $empresa_id_existente, $rfc]);
                            $empresa_id = $empresa_id_existente;
                        } else {
                            throw new Exception('Error de validación: empresa no corresponde al RFC proporcionado');
                        }
                    } else {
                        // Crear nueva empresa si no existe
                        $stmt = $db->prepare("INSERT INTO empresas (rfc, razon_social, email, telefono, whatsapp, representante, activo, verificado) VALUES (?, ?, ?, ?, ?, ?, 1, 0)");
                        $stmt->execute([$rfc, $razon_social, $email, $telefono, $whatsapp, $representante]);
                        $empresa_id = $db->lastInsertId();
                    }
                    
                    // Crear código de verificación
                    $codigo_verificacion = generateVerificationCode();
                    
                    // Hash de la contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar usuario vinculado a la empresa
                    $stmt = $db->prepare("INSERT INTO usuarios (email, password, rol, empresa_id, codigo_verificacion, activo, email_verificado) VALUES (?, ?, 'ENTIDAD_COMERCIAL', ?, ?, 0, 0)");
                    $stmt->execute([$email, $password_hash, $empresa_id, $codigo_verificacion]);
                    
                    // Confirmar transacción
                    $db->commit();
                    
                    // Enviar email de verificación (uses $nombre_sitio from page config)
                    $verify_link = BASE_URL . "/verify-email.php?code=" . $codigo_verificacion;
                    $email_body = "Hola,\n\nGracias por registrarte en " . $nombre_sitio . ".\n\nPor favor, verifica tu email haciendo clic en el siguiente enlace:\n\n" . $verify_link . "\n\nSi no te registraste, ignora este mensaje.";
                    
                    sendEmail($email, 'Verifica tu email - ' . $nombre_sitio, $email_body);
                    
                    // Redirigir a login con mensaje de verificación de email
                    redirect('/login.php?success=verify_email');
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
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
    <?php
    // Cargar colores personalizados y configuraciones
    try {
        $db_config = Database::getInstance()->getConnection();
        $stmt = $db_config->query("SELECT clave, valor FROM configuracion WHERE clave IN ('color_primario', 'color_secundario', 'logo_sistema', 'nombre_sitio', 'terminos_condiciones', 'politica_privacidad')");
        $custom_config = [];
        while ($row = $stmt->fetch()) {
            $custom_config[$row['clave']] = $row['valor'];
        }
        $color_primario = $custom_config['color_primario'] ?? '#10B981';
        $color_secundario = $custom_config['color_secundario'] ?? '#059669';
        $logo_sistema = $custom_config['logo_sistema'] ?? '';
        $nombre_sitio = $custom_config['nombre_sitio'] ?? APP_NAME;
        $terminos_condiciones = $custom_config['terminos_condiciones'] ?? '';
        $politica_privacidad = $custom_config['politica_privacidad'] ?? '';
    } catch (Exception $e) {
        $color_primario = '#10B981';
        $color_secundario = '#059669';
        $logo_sistema = '';
        $nombre_sitio = APP_NAME;
        $terminos_condiciones = '';
        $politica_privacidad = '';
    }
    ?>
    <style>
        :root {
            --color-primario: <?php echo $color_primario; ?>;
            --color-secundario: <?php echo $color_secundario; ?>;
        }
        .bg-green-600, .bg-green-500 {
            background-color: var(--color-primario) !important;
        }
        .text-green-600 {
            color: var(--color-primario) !important;
        }
        .hover\:bg-green-700:hover {
            background-color: var(--color-primario) !important;
            filter: brightness(0.9);
        }
        .focus\:ring-green-500:focus {
            --tw-ring-color: var(--color-primario) !important;
        }
        body {
            background: linear-gradient(135deg, <?php echo $color_primario; ?>15 0%, <?php echo $color_secundario; ?>15 100%);
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto">
            <!-- Encabezado -->
            <div class="text-center mb-8">
                <?php if (!empty($logo_sistema) && file_exists(ROOT_PATH . $logo_sistema)): ?>
                    <div class="mb-4">
                        <img src="<?php echo BASE_URL . $logo_sistema; ?>" alt="Logo" class="mx-auto max-h-24">
                    </div>
                <?php else: ?>
                    <div class="inline-block bg-green-600 text-white rounded-full p-4 mb-4">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <h1 class="text-3xl font-bold text-gray-800">Registro de Empresa</h1>
                <p class="text-gray-600 mt-2">Únete a <?php echo e($nombre_sitio); ?></p>
            </div>

            <!-- Formulario de Registro -->
            <div class="bg-white rounded-lg shadow-xl p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formRegistro">
                    <input type="hidden" id="empresa_id_existente" name="empresa_id_existente" value="">
                    
                    <!-- Mensaje informativo -->
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Importante:</strong> Ingresa el RFC de tu empresa primero. Si tu empresa ya está registrada en nuestro sistema, 
                            los datos se cargarán automáticamente y podrás editarlos antes de crear tu cuenta.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- RFC - Campo Principal -->
                        <div class="md:col-span-2">
                            <label for="rfc" class="block text-gray-700 font-semibold mb-2">
                                RFC de la Empresa *
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
                                oninput="this.value = this.value.toUpperCase(); buscarEmpresaPorRFC(this.value);"
                            >
                            <p class="text-xs text-gray-500 mt-1">12 o 13 caracteres</p>
                            <div id="rfc_mensaje" class="mt-2"></div>
                        </div>

                        <!-- Razón Social -->
                        <div class="md:col-span-2">
                            <label for="razon_social" class="block text-gray-700 font-semibold mb-2">
                                Razón Social *
                            </label>
                            <input 
                                type="text" 
                                id="razon_social" 
                                name="razon_social" 
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Nombre o razón social de la empresa"
                                value="<?php echo htmlspecialchars($razon_social ?? ''); ?>"
                            >
                        </div>

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

                        <!-- Teléfono -->
                        <div>
                            <label for="telefono" class="block text-gray-700 font-semibold mb-2">
                                Teléfono
                            </label>
                            <input 
                                type="tel" 
                                id="telefono" 
                                name="telefono" 
                                maxlength="10"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="4421234567"
                                value="<?php echo htmlspecialchars($telefono ?? ''); ?>"
                            >
                            <p class="text-xs text-gray-500 mt-1">10 dígitos sin espacios</p>
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

                        <!-- Representante Legal -->
                        <div class="md:col-span-2">
                            <label for="representante" class="block text-gray-700 font-semibold mb-2">
                                Representante Legal
                            </label>
                            <input 
                                type="text" 
                                id="representante" 
                                name="representante" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Nombre del representante legal"
                                value="<?php echo htmlspecialchars($representante ?? ''); ?>"
                            >
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
                                    <?php if (!empty($terminos_condiciones)): ?>
                                        <a href="<?php echo BASE_URL; ?>/terminos.php" target="_blank" class="text-green-600 hover:underline">términos y condiciones</a>
                                    <?php else: ?>
                                        <span class="text-green-600">términos y condiciones</span>
                                    <?php endif; ?>
                                    y la 
                                    <?php if (!empty($politica_privacidad)): ?>
                                        <a href="<?php echo BASE_URL; ?>/privacidad.php" target="_blank" class="text-green-600 hover:underline">política de privacidad</a>
                                    <?php else: ?>
                                        <span class="text-green-600">política de privacidad</span>
                                    <?php endif; ?>
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
            
            <!-- Footer con términos -->
            <div class="mt-6 text-center text-xs text-gray-600">
                <?php if (!empty($terminos_condiciones) || !empty($politica_privacidad)): ?>
                    <div class="flex justify-center space-x-4">
                        <?php if (!empty($terminos_condiciones)): ?>
                            <a href="<?php echo BASE_URL; ?>/terminos.php" target="_blank" class="hover:underline">
                                Términos y Condiciones
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($politica_privacidad)): ?>
                            <a href="<?php echo BASE_URL; ?>/privacidad.php" target="_blank" class="hover:underline">
                                Política de Privacidad
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Función para buscar empresa por RFC con rate limiting
    let buscarRFCTimeout;
    let ultimaBusqueda = 0;
    const MINIMO_INTERVALO = 1000; // Mínimo 1 segundo entre búsquedas
    
    async function buscarEmpresaPorRFC(rfc) {
        clearTimeout(buscarRFCTimeout);
        const mensajeDiv = document.getElementById('rfc_mensaje');
        
        // Limpiar si RFC es muy corto
        if (rfc.length < 12) {
            mensajeDiv.innerHTML = '';
            document.getElementById('empresa_id_existente').value = '';
            return;
        }
        
        // Esperar 800ms antes de buscar
        buscarRFCTimeout = setTimeout(async () => {
            // Rate limiting en cliente
            const ahora = Date.now();
            if (ahora - ultimaBusqueda < MINIMO_INTERVALO) {
                return; // Ignorar si es muy pronto
            }
            ultimaBusqueda = ahora;
            
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/buscar_empresa_publico.php?rfc=' + encodeURIComponent(rfc));
                
                // Validar content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Respuesta inválida del servidor');
                }
                
                const data = await response.json();
                
                if (response.status === 429) {
                    mensajeDiv.innerHTML = `
                        <div class="p-4 bg-orange-50 border-l-4 border-orange-500 rounded">
                            <p class="text-sm text-orange-700">
                                <i class="fas fa-exclamation-circle mr-2"></i>Demasiadas búsquedas. Espera un momento e intenta nuevamente.
                            </p>
                        </div>
                    `;
                    return;
                }
                
                if (data.success && data.empresa) {
                    const emp = data.empresa;
                    
                    // Mostrar mensaje de éxito
                    mensajeDiv.innerHTML = `
                        <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded">
                            <p class="text-sm text-green-700 font-semibold mb-2">
                                <i class="fas fa-check-circle mr-2"></i>¡Empresa encontrada en el sistema!
                            </p>
                            <p class="text-xs text-gray-600">Los datos se han cargado automáticamente. Puedes editarlos antes de crear tu cuenta.</p>
                        </div>
                    `;
                    
                    // Guardar ID de empresa existente
                    document.getElementById('empresa_id_existente').value = emp.id;
                    
                    // Llenar campos automáticamente - sanitizar para prevenir XSS
                    const sanitize = (str) => {
                        const div = document.createElement('div');
                        div.textContent = str || '';
                        return div.innerHTML;
                    };
                    
                    document.getElementById('razon_social').value = sanitize(emp.razon_social);
                    document.getElementById('email').value = sanitize(emp.email);
                    document.getElementById('telefono').value = sanitize(emp.telefono);
                    document.getElementById('whatsapp').value = sanitize(emp.whatsapp);
                    document.getElementById('representante').value = sanitize(emp.representante);
                    
                } else {
                    // RFC no encontrado
                    mensajeDiv.innerHTML = `
                        <div class="p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-2"></i>RFC no encontrado en el sistema. Completa el formulario para registrar tu empresa.
                            </p>
                        </div>
                    `;
                    document.getElementById('empresa_id_existente').value = '';
                }
            } catch (error) {
                console.error('Error al buscar empresa:', error);
                mensajeDiv.innerHTML = `
                    <div class="p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded">
                        <p class="text-sm text-yellow-700">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Error de conexión. Puedes continuar con el registro manualmente.
                        </p>
                    </div>
                `;
            }
        }, 800);
    }
    </script>
</body>
</html>
