<?php
/**
 * Módulo de configuración del sistema
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requirePermission('PRESIDENCIA');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Obtener configuración actual ANTES de procesar POST
// Nota: No usamos getConfiguracion() aquí porque tiene caché estático
// y no reflejaría cambios guardados en el mismo request
try {
    $stmt = $db->query("SELECT clave, valor FROM configuracion");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {
    $config = [];
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Procesar logo si se subió
        $logo_path = $config['logo_sistema'] ?? '';
        if (isset($_FILES['logo_sistema']) && $_FILES['logo_sistema']['error'] === UPLOAD_ERR_OK) {
            // Validar tamaño de archivo (máximo 2MB)
            $max_size = 2 * 1024 * 1024; // 2MB en bytes
            if ($_FILES['logo_sistema']['size'] > $max_size) {
                throw new Exception('El archivo es demasiado grande. Tamaño máximo: 2MB');
            }
            
            $upload_dir = UPLOAD_PATH . '/logo/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['logo_sistema']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Generar nombre único usando timestamp y uniqid para evitar colisiones
                $new_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['logo_sistema']['tmp_name'], $upload_path)) {
                    $logo_path = '/public/uploads/logo/' . $new_filename;
                    
                    // Eliminar logo anterior si existe
                    if (!empty($config['logo_sistema']) && file_exists(ROOT_PATH . $config['logo_sistema'])) {
                        unlink(ROOT_PATH . $config['logo_sistema']);
                    }
                }
            } else {
                throw new Exception('Formato de archivo no permitido. Use: JPG, PNG, GIF o SVG');
            }
        }
        
        $configuraciones = [
            'nombre_sitio' => sanitize($_POST['nombre_sitio'] ?? ''),
            'email_sistema' => sanitize($_POST['email_sistema'] ?? ''),
            'whatsapp_chatbot' => sanitize($_POST['whatsapp_chatbot'] ?? ''),
            'telefono_contacto' => sanitize($_POST['telefono_contacto'] ?? ''),
            'horario_atencion' => sanitize($_POST['horario_atencion'] ?? ''),
            'paypal_account' => sanitize($_POST['paypal_account'] ?? ''),
            'paypal_client_id' => sanitize($_POST['paypal_client_id'] ?? ''),
            'paypal_secret' => sanitize($_POST['paypal_secret'] ?? ''),
            'paypal_mode' => sanitize($_POST['paypal_mode'] ?? 'sandbox'),
            'paypal_plan_id_monthly' => sanitize($_POST['paypal_plan_id_monthly'] ?? ''),
            'paypal_plan_id_annual' => sanitize($_POST['paypal_plan_id_annual'] ?? ''),
            'paypal_webhook_url' => sanitize($_POST['paypal_webhook_url'] ?? ''),
            'dias_aviso_renovacion' => sanitize($_POST['dias_aviso_renovacion'] ?? '30,15,5'),
            'max_boletos_por_registro' => intval($_POST['max_boletos_por_registro'] ?? 10),
            'color_primario' => sanitize($_POST['color_primario'] ?? '#1E40AF'),
            'color_secundario' => sanitize($_POST['color_secundario'] ?? '#10B981'),
            'color_terciario' => sanitize($_POST['color_terciario'] ?? '#6366F1'),
            'color_acento1' => sanitize($_POST['color_acento1'] ?? '#F59E0B'),
            'color_acento2' => sanitize($_POST['color_acento2'] ?? '#EC4899'),
            'color_header' => sanitize($_POST['color_header'] ?? '#1E40AF'),
            'color_sidebar' => sanitize($_POST['color_sidebar'] ?? '#1F2937'),
            'color_footer' => sanitize($_POST['color_footer'] ?? '#111827'),
            'terminos_condiciones' => $_POST['terminos_condiciones'] ?? '',
            'politica_privacidad' => $_POST['politica_privacidad'] ?? '',
            'logo_sistema' => $logo_path,
            // SMTP Configuration
            'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
            'smtp_port' => sanitize($_POST['smtp_port'] ?? '587'),
            'smtp_user' => sanitize($_POST['smtp_user'] ?? ''),
            'smtp_pass' => sanitize($_POST['smtp_pass'] ?? ''),
            'smtp_secure' => sanitize($_POST['smtp_secure'] ?? 'tls'),
            'smtp_from_name' => sanitize($_POST['smtp_from_name'] ?? ''),
            // QR Code API Configuration
            'qr_api_provider' => sanitize($_POST['qr_api_provider'] ?? 'google'),
            'qr_size' => intval($_POST['qr_size'] ?? 400),
            // Shelly Relay API
            'shelly_api_enabled' => isset($_POST['shelly_api_enabled']) ? '1' : '0',
            'shelly_api_url' => sanitize($_POST['shelly_api_url'] ?? ''),
            'shelly_api_channel' => sanitize($_POST['shelly_api_channel'] ?? '0'),
        ];

        foreach ($configuraciones as $clave => $valor) {
            $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$clave, $valor, $valor]);
        }

        // Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada) VALUES (?, 'UPDATE_CONFIG', 'configuracion')");
        $stmt->execute([$user['id']]);

        $success = 'Configuración actualizada exitosamente';
        
        // Recargar configuración para mostrar valores actualizados
        $stmt = $db->query("SELECT clave, valor FROM configuracion");
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['clave']] = $row['valor'];
        }
    } catch (Exception $e) {
        $error = 'Error al guardar la configuración: ' . $e->getMessage();
    }
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">
        <i class="fas fa-cog mr-2"></i>Configuración del Sistema
    </h1>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Información General -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Información General</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Nombre del Sitio</label>
                    <input type="text" name="nombre_sitio" 
                           value="<?php echo e($config['nombre_sitio'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="CRM Cámara de Comercio">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Email del Sistema</label>
                    <input type="email" name="email_sistema" 
                           value="<?php echo e($config['email_sistema'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="contacto@camara.com">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">WhatsApp del Chatbot</label>
                    <input type="text" name="whatsapp_chatbot" 
                           value="<?php echo e($config['whatsapp_chatbot'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="4421234567">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Teléfono de Contacto</label>
                    <input type="text" name="telefono_contacto" 
                           value="<?php echo e($config['telefono_contacto'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="442-123-4567">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Horario de Atención</label>
                    <input type="text" name="horario_atencion" 
                           value="<?php echo e($config['horario_atencion'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Lunes a Viernes 9:00 AM - 6:00 PM">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Máximo de Boletos por Registro</label>
                    <input type="number" name="max_boletos_por_registro" min="1" max="100"
                           value="<?php echo e($config['max_boletos_por_registro'] ?? '10'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="10">
                    <p class="text-sm text-gray-500 mt-1">Número máximo de boletos que se pueden solicitar por registro en eventos</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-image mr-2"></i>Logotipo del Sistema
                    </label>
                    <?php if (!empty($config['logo_sistema'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo BASE_URL . e($config['logo_sistema']); ?>" 
                                 alt="Logo actual" 
                                 class="max-h-20 border rounded p-2">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logo_sistema" 
                           accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Formatos: JPG, PNG, GIF, SVG (máx. 2MB)</p>
                </div>
            </div>
        </div>

        <!-- Configuración SMTP -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-envelope mr-2"></i>Configuración de Correo SMTP
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Servidor SMTP</label>
                    <input type="text" name="smtp_host" 
                           value="<?php echo e($config['smtp_host'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="smtp.gmail.com">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Puerto SMTP</label>
                    <input type="number" name="smtp_port" 
                           value="<?php echo e($config['smtp_port'] ?? '587'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="587">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Usuario SMTP</label>
                    <input type="text" name="smtp_user" 
                           value="<?php echo e($config['smtp_user'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="usuario@gmail.com">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Contraseña SMTP</label>
                    <input type="password" name="smtp_pass" 
                           value="<?php echo e($config['smtp_pass'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Seguridad</label>
                    <select name="smtp_secure" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="tls" <?php echo ($config['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($config['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Nombre del Remitente</label>
                    <input type="text" name="smtp_from_name" 
                           value="<?php echo e($config['smtp_from_name'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="CRM Cámara de Comercio">
                </div>
            </div>
        </div>

        <!-- Configuración de PayPal -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fab fa-paypal text-blue-600 mr-3"></i>
                Configuración de PayPal
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Cuenta Principal de PayPal -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Cuenta Principal de PayPal</label>
                    <input type="email" name="paypal_account" 
                           value="<?php echo e($config['paypal_account'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="webmaster@impactosdigitales.com">
                    <p class="text-sm text-gray-500 mt-1">Cuenta de PayPal para recibir los pagos del sistema</p>
                </div>

                <!-- Entorno de PayPal -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Entorno de PayPal</label>
                    <select name="paypal_mode" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="sandbox" <?php echo ($config['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Pruebas)</option>
                        <option value="live" <?php echo ($config['paypal_mode'] ?? '') === 'live' ? 'selected' : ''; ?>>Live (Producción)</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Entorno para las transacciones de PayPal</p>
                </div>

                <!-- Client ID -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Client ID (ID de Cliente)</label>
                    <input type="text" name="paypal_client_id" 
                           value="<?php echo e($config['paypal_client_id'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                           placeholder="AZd7a_Vpfv6vuzd1CKF3e5a1OPu4jcKOGzLkwe0QYfwNVdEzjUnOZKnf0Oz">
                    <p class="text-sm text-gray-500 mt-1">ID de cliente de la aplicación PayPal</p>
                </div>

                <!-- Client Secret -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Client Secret (Secreto del Cliente)</label>
                    <input type="password" name="paypal_secret" 
                           value="<?php echo e($config['paypal_secret'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                           placeholder="••••••••••••••••••••••••••••••••••••">
                    <p class="text-sm text-gray-500 mt-1">Secreto del cliente de la aplicación PayPal</p>
                </div>

                <!-- PayPal Plan ID - Mensual -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">PayPal Plan ID - Mensual</label>
                    <input type="text" name="paypal_plan_id_monthly" 
                           value="<?php echo e($config['paypal_plan_id_monthly'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                           placeholder="P-XXXXXXXXXXXX">
                    <p class="text-sm text-gray-500 mt-1">ID del plan de suscripción mensual en PayPal</p>
                </div>

                <!-- PayPal Plan ID - Anual -->
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">PayPal Plan ID - Anual</label>
                    <input type="text" name="paypal_plan_id_annual" 
                           value="<?php echo e($config['paypal_plan_id_annual'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                           placeholder="P-YYYYYYYYYYYY">
                    <p class="text-sm text-gray-500 mt-1">ID del plan de suscripción anual en PayPal</p>
                </div>

                <!-- Webhook URL -->
                <div class="md:col-span-2">
                    <label class="block text-gray-700 font-semibold mb-2">Webhook URL</label>
                    <input type="url" name="paypal_webhook_url" 
                           value="<?php echo e($config['paypal_webhook_url'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="<?php echo BASE_URL; ?>/webhook/paypal">
                    <p class="text-sm text-gray-500 mt-1">
                        URL para recibir notificaciones de PayPal sobre cambios en suscripciones<br>
                        <strong>Nota:</strong> La Webhook URL debe ser: <code class="bg-gray-100 px-2 py-1 rounded"><?php echo BASE_URL; ?>/webhook/paypal</code>
                    </p>
                </div>
            </div>

            <!-- Info Box -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800 mb-3">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Nota:</strong> Para obtener las credenciales de PayPal, accede al 
                    <a href="https://developer.paypal.com/dashboard/" target="_blank" class="underline font-semibold">
                        Dashboard de Desarrolladores de PayPal
                    </a> 
                    y crea una aplicación. Usa el modo Sandbox para pruebas y Live para producción.
                </p>
                <p class="text-sm text-blue-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Importante:</strong> Asegúrate de usar las credenciales correctas según el entorno seleccionado:
                    <br>• <strong>Sandbox:</strong> Las credenciales deben ser de una aplicación de prueba (sandbox)
                    <br>• <strong>Live:</strong> Las credenciales deben ser de una aplicación de producción (live)
                    <br>Las credenciales de un entorno NO funcionarán en el otro.
                </p>
            </div>
        </div>

        <!-- Configuración de Notificaciones -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Notificaciones Automáticas</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Días de Aviso de Renovación</label>
                    <input type="text" name="dias_aviso_renovacion" 
                           value="<?php echo e($config['dias_aviso_renovacion'] ?? '30,15,5'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="30,15,5">
                    <p class="text-sm text-gray-500 mt-1">Separados por comas (ej: 30,15,5)</p>
                </div>
            </div>
        </div>

        <!-- Personalización de Diseño -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-palette mr-2"></i>Personalización de Diseño
            </h2>
            
            <h3 class="text-md font-semibold text-gray-700 mb-4">Colores Principales</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Primario</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_primario" id="color_primario"
                               value="<?php echo e($config['color_primario'] ?? '#1E40AF'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_primario_text"
                               value="<?php echo e($config['color_primario'] ?? '#1E40AF'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Color principal de botones y elementos</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Secundario</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_secundario" id="color_secundario"
                               value="<?php echo e($config['color_secundario'] ?? '#10B981'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_secundario_text"
                               value="<?php echo e($config['color_secundario'] ?? '#10B981'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Color para elementos secundarios y acentos</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Terciario</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_terciario" id="color_terciario"
                               value="<?php echo e($config['color_terciario'] ?? '#6366F1'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_terciario_text"
                               value="<?php echo e($config['color_terciario'] ?? '#6366F1'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Color terciario para elementos complementarios</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Acento 1</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_acento1" id="color_acento1"
                               value="<?php echo e($config['color_acento1'] ?? '#F59E0B'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_acento1_text"
                               value="<?php echo e($config['color_acento1'] ?? '#F59E0B'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Primer color de acento para destacar</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Acento 2</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_acento2" id="color_acento2"
                               value="<?php echo e($config['color_acento2'] ?? '#EC4899'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_acento2_text"
                               value="<?php echo e($config['color_acento2'] ?? '#EC4899'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Segundo color de acento para elementos especiales</p>
                </div>
            </div>

            <h3 class="text-md font-semibold text-gray-700 mb-4 mt-6 border-t pt-4">Colores por Sección</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Header (Top)</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_header" id="color_header"
                               value="<?php echo e($config['color_header'] ?? '#1E40AF'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_header_text"
                               value="<?php echo e($config['color_header'] ?? '#1E40AF'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Color del encabezado superior</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Sidebar</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_sidebar" id="color_sidebar"
                               value="<?php echo e($config['color_sidebar'] ?? '#1F2937'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_sidebar_text"
                               value="<?php echo e($config['color_sidebar'] ?? '#1F2937'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Color de la barra lateral</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Footer (Bottom)</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_footer" id="color_footer"
                               value="<?php echo e($config['color_footer'] ?? '#111827'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" id="color_footer_text"
                               value="<?php echo e($config['color_footer'] ?? '#111827'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Color del pie de página</p>
                </div>
            </div>

            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Nota:</strong> Los colores personalizados se aplicarán mediante CSS en el sistema. 
                    Guarda la configuración para ver los cambios en toda la aplicación.
                </p>
            </div>
        </div>

        <!-- QR Code API Configuration -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-qrcode mr-2"></i>Configuración de Códigos QR
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">API para Generación de QR</label>
                    <select name="qr_api_provider" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="google" <?php echo ($config['qr_api_provider'] ?? 'google') === 'google' ? 'selected' : ''; ?>>Google Charts API (por defecto)</option>
                        <option value="qrserver" <?php echo ($config['qr_api_provider'] ?? '') === 'qrserver' ? 'selected' : ''; ?>>QR Server API</option>
                        <option value="quickchart" <?php echo ($config['qr_api_provider'] ?? '') === 'quickchart' ? 'selected' : ''; ?>>QuickChart API</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Seleccione el proveedor de API para generar códigos QR</p>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Tamaño de QR (píxeles)</label>
                    <input type="number" name="qr_size" min="200" max="1000" step="50"
                           value="<?php echo e($config['qr_size'] ?? '400'); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="400">
                    <p class="text-sm text-gray-500 mt-1">Tamaño del código QR para impresión (recomendado: 400px)</p>
                </div>
            </div>

            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Nota:</strong> La configuración de API de QR permite cambiar el proveedor de generación de códigos QR.
                    Un tamaño mayor mejora la calidad de impresión.
                </p>
            </div>
        </div>

        <!-- Shelly Relay API Configuration -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-plug mr-2"></i>Shelly Relay API - Control de Acceso
            </h2>
            
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="shelly_api_enabled" value="1"
                           <?php echo ($config['shelly_api_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                    <span class="text-gray-700 font-semibold">Habilitar integración con Shelly Relay API</span>
                </label>
                <p class="text-sm text-gray-500 mt-1 ml-6">
                    Permite controlar el acceso a eventos mediante dispositivos Shelly Relay
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">URL de la API de Shelly</label>
                    <input type="url" name="shelly_api_url" 
                           value="<?php echo e($config['shelly_api_url'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="http://192.168.1.100/relay">
                    <p class="text-sm text-gray-500 mt-1">Ejemplo: http://192.168.1.100/relay</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Canal del Relay</label>
                    <select name="shelly_api_channel" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="0" <?php echo ($config['shelly_api_channel'] ?? '0') === '0' ? 'selected' : ''; ?>>Canal 0</option>
                        <option value="1" <?php echo ($config['shelly_api_channel'] ?? '') === '1' ? 'selected' : ''; ?>>Canal 1</option>
                        <option value="2" <?php echo ($config['shelly_api_channel'] ?? '') === '2' ? 'selected' : ''; ?>>Canal 2</option>
                        <option value="3" <?php echo ($config['shelly_api_channel'] ?? '') === '3' ? 'selected' : ''; ?>>Canal 3</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Seleccione el canal del relay a controlar</p>
                </div>
            </div>

            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Importante:</strong> Asegúrese de que la URL del dispositivo Shelly sea accesible desde el servidor. 
                    Esta función permite activar el relay para el acceso físico a eventos.
                </p>
            </div>
        </div>

        <!-- Términos y Condiciones -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Términos y Condiciones</h2>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Texto de Términos y Condiciones</label>
                <textarea name="terminos_condiciones" rows="8"
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Ingresa los términos y condiciones del servicio..."><?php echo e($config['terminos_condiciones'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Política de Privacidad -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Política de Privacidad</h2>
            
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Texto de Política de Privacidad</label>
                <textarea name="politica_privacidad" rows="8"
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Ingresa la política de privacidad..."><?php echo e($config['politica_privacidad'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="flex justify-end space-x-4">
            <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold transition">
                <i class="fas fa-save mr-2"></i>Guardar Configuración
            </button>
        </div>
    </form>

    <!-- Información del Sistema -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Información del Sistema</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-600 mb-1">Versión de PHP</p>
                <p class="font-semibold text-gray-800"><?php echo phpversion(); ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600 mb-1">Versión de MySQL</p>
                <p class="font-semibold text-gray-800">
                    <?php 
                    try {
                        $version = $db->query("SELECT VERSION()")->fetchColumn();
                        echo $version;
                    } catch (Exception $e) {
                        echo 'N/A';
                    }
                    ?>
                </p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600 mb-1">URL Base</p>
                <p class="font-semibold text-gray-800 text-sm"><?php echo BASE_URL; ?></p>
            </div>
            
            <div>
                <p class="text-sm text-gray-600 mb-1">Zona Horaria</p>
                <p class="font-semibold text-gray-800"><?php echo date_default_timezone_get(); ?></p>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-200">
            <h3 class="font-semibold text-gray-800 mb-4">Herramientas de Mantenimiento</h3>
            <div class="flex flex-wrap gap-4">
                <a href="test_connection.php" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check-circle mr-2"></i>Test de Conexión
                </a>
                <button onclick="alert('Función de respaldo en desarrollo')" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-database mr-2"></i>Respaldar Base de Datos
                </button>
                <button onclick="if(confirm('¿Estás seguro de limpiar la caché?')) alert('Caché limpiada')" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                    <i class="fas fa-broom mr-2"></i>Limpiar Caché
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Update color text fields when color picker changes
document.getElementById('color_primario')?.addEventListener('input', function(e) {
    document.getElementById('color_primario_text').value = e.target.value;
});

document.getElementById('color_secundario')?.addEventListener('input', function(e) {
    document.getElementById('color_secundario_text').value = e.target.value;
});

document.getElementById('color_terciario')?.addEventListener('input', function(e) {
    document.getElementById('color_terciario_text').value = e.target.value;
});

document.getElementById('color_acento1')?.addEventListener('input', function(e) {
    document.getElementById('color_acento1_text').value = e.target.value;
});

document.getElementById('color_acento2')?.addEventListener('input', function(e) {
    document.getElementById('color_acento2_text').value = e.target.value;
});

document.getElementById('color_header')?.addEventListener('input', function(e) {
    document.getElementById('color_header_text').value = e.target.value;
});

document.getElementById('color_sidebar')?.addEventListener('input', function(e) {
    document.getElementById('color_sidebar_text').value = e.target.value;
});

document.getElementById('color_footer')?.addEventListener('input', function(e) {
    document.getElementById('color_footer_text').value = e.target.value;
});
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
