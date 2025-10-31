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

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $configuraciones = [
            'nombre_sitio' => sanitize($_POST['nombre_sitio'] ?? ''),
            'email_sistema' => sanitize($_POST['email_sistema'] ?? ''),
            'whatsapp_chatbot' => sanitize($_POST['whatsapp_chatbot'] ?? ''),
            'telefono_contacto' => sanitize($_POST['telefono_contacto'] ?? ''),
            'horario_atencion' => sanitize($_POST['horario_atencion'] ?? ''),
            'paypal_account' => sanitize($_POST['paypal_account'] ?? ''),
            'dias_aviso_renovacion' => sanitize($_POST['dias_aviso_renovacion'] ?? '30,15,5'),
            'color_primario' => sanitize($_POST['color_primario'] ?? '#1E40AF'),
            'color_secundario' => sanitize($_POST['color_secundario'] ?? '#10B981'),
            'terminos_condiciones' => $_POST['terminos_condiciones'] ?? '',
            'politica_privacidad' => $_POST['politica_privacidad'] ?? '',
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
    } catch (Exception $e) {
        $error = 'Error al guardar la configuración: ' . $e->getMessage();
    }
}

// Obtener configuración actual
try {
    $stmt = $db->query("SELECT clave, valor FROM configuracion");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {
    $config = [];
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

    <form method="POST" class="space-y-6">
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
            </div>
        </div>

        <!-- Configuración de Pagos -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Configuración de Pagos</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Cuenta Principal de PayPal</label>
                    <input type="email" name="paypal_account" 
                           value="<?php echo e($config['paypal_account'] ?? ''); ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="pagos@camara.com">
                    <p class="text-sm text-gray-500 mt-1">Email de la cuenta de PayPal para recibir pagos</p>
                </div>
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
            <h2 class="text-xl font-bold text-gray-800 mb-6">Personalización de Diseño</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Primario</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_primario" 
                               value="<?php echo e($config['color_primario'] ?? '#1E40AF'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" 
                               value="<?php echo e($config['color_primario'] ?? '#1E40AF'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Color Secundario</label>
                    <div class="flex gap-2">
                        <input type="color" name="color_secundario" 
                               value="<?php echo e($config['color_secundario'] ?? '#10B981'); ?>"
                               class="w-16 h-10 border rounded cursor-pointer">
                        <input type="text" 
                               value="<?php echo e($config['color_secundario'] ?? '#10B981'); ?>"
                               class="flex-1 px-4 py-2 border rounded-lg bg-gray-50"
                               readonly>
                    </div>
                </div>
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

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
