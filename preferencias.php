<?php
/**
 * Configurar Preferencias del Usuario
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Procesar actualización de preferencias
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $preferencias = [
            'notificaciones_email' => isset($_POST['notificaciones_email']) ? 1 : 0,
            'notificaciones_whatsapp' => isset($_POST['notificaciones_whatsapp']) ? 1 : 0,
            'notificaciones_sistema' => isset($_POST['notificaciones_sistema']) ? 1 : 0,
            'idioma' => sanitize($_POST['idioma'] ?? 'es'),
            'zona_horaria' => sanitize($_POST['zona_horaria'] ?? 'America/Mexico_City'),
            'items_por_pagina' => intval($_POST['items_por_pagina'] ?? 20),
            'tema' => sanitize($_POST['tema'] ?? 'light'),
        ];

        // Actualizar preferencias del usuario
        $stmt = $db->prepare("UPDATE usuarios SET preferencias = ? WHERE id = ?");
        $stmt->execute([json_encode($preferencias), $user['id']]);

        // Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'UPDATE_PREFERENCES', 'usuarios', ?)");
        $stmt->execute([$user['id'], $user['id']]);

        $success = 'Preferencias actualizadas exitosamente';
    } catch (Exception $e) {
        $error = 'Error al guardar las preferencias: ' . $e->getMessage();
    }
}

// Obtener preferencias actuales
try {
    $stmt = $db->prepare("SELECT preferencias FROM usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    
    $preferencias = [];
    if ($result && !empty($result['preferencias'])) {
        $preferencias = json_decode($result['preferencias'], true) ?? [];
    }
    
    // Valores por defecto
    $preferencias = array_merge([
        'notificaciones_email' => 1,
        'notificaciones_whatsapp' => 1,
        'notificaciones_sistema' => 1,
        'idioma' => 'es',
        'zona_horaria' => 'America/Mexico_City',
        'items_por_pagina' => 20,
        'tema' => 'light',
    ], $preferencias);
} catch (Exception $e) {
    $error = 'Error al cargar las preferencias';
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">
        <i class="fas fa-sliders-h mr-2"></i>Configurar Preferencias
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
        <!-- Notificaciones -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-bell mr-2"></i>Notificaciones
            </h2>
            
            <div class="space-y-4">
                <label class="flex items-center">
                    <input type="checkbox" name="notificaciones_email" value="1"
                           <?php echo $preferencias['notificaciones_email'] ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                    <div>
                        <span class="text-gray-700 font-semibold block">Notificaciones por Email</span>
                        <span class="text-sm text-gray-500">Recibir notificaciones importantes por correo electrónico</span>
                    </div>
                </label>

                <label class="flex items-center">
                    <input type="checkbox" name="notificaciones_whatsapp" value="1"
                           <?php echo $preferencias['notificaciones_whatsapp'] ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                    <div>
                        <span class="text-gray-700 font-semibold block">Notificaciones por WhatsApp</span>
                        <span class="text-sm text-gray-500">Recibir alertas urgentes por WhatsApp</span>
                    </div>
                </label>

                <label class="flex items-center">
                    <input type="checkbox" name="notificaciones_sistema" value="1"
                           <?php echo $preferencias['notificaciones_sistema'] ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                    <div>
                        <span class="text-gray-700 font-semibold block">Notificaciones en el Sistema</span>
                        <span class="text-sm text-gray-500">Mostrar notificaciones dentro de la plataforma</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Interfaz -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-desktop mr-2"></i>Interfaz
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Tema</label>
                    <select name="tema" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="light" <?php echo $preferencias['tema'] === 'light' ? 'selected' : ''; ?>>Claro</option>
                        <option value="dark" <?php echo $preferencias['tema'] === 'dark' ? 'selected' : ''; ?>>Oscuro</option>
                        <option value="auto" <?php echo $preferencias['tema'] === 'auto' ? 'selected' : ''; ?>>Automático</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Tema de color de la interfaz</p>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Elementos por Página</label>
                    <select name="items_por_pagina" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="10" <?php echo $preferencias['items_por_pagina'] == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $preferencias['items_por_pagina'] == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $preferencias['items_por_pagina'] == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $preferencias['items_por_pagina'] == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Cantidad de registros a mostrar en las tablas</p>
                </div>
            </div>
        </div>

        <!-- Regional -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-globe mr-2"></i>Configuración Regional
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Idioma</label>
                    <select name="idioma" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="es" <?php echo $preferencias['idioma'] === 'es' ? 'selected' : ''; ?>>Español</option>
                        <option value="en" <?php echo $preferencias['idioma'] === 'en' ? 'selected' : ''; ?>>English</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Zona Horaria</label>
                    <select name="zona_horaria" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="America/Mexico_City" <?php echo $preferencias['zona_horaria'] === 'America/Mexico_City' ? 'selected' : ''; ?>>
                            Ciudad de México (GMT-6)
                        </option>
                        <option value="America/Tijuana" <?php echo $preferencias['zona_horaria'] === 'America/Tijuana' ? 'selected' : ''; ?>>
                            Tijuana (GMT-8)
                        </option>
                        <option value="America/Cancun" <?php echo $preferencias['zona_horaria'] === 'America/Cancun' ? 'selected' : ''; ?>>
                            Cancún (GMT-5)
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Información de cuenta -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-600 text-2xl mr-4 mt-1"></i>
                <div>
                    <h3 class="font-semibold text-blue-900 mb-2">Información de tu Cuenta</h3>
                    <div class="text-blue-800 text-sm space-y-1">
                        <p><strong>Nombre:</strong> <?php echo e($user['nombre']); ?></p>
                        <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
                        <p><strong>Rol:</strong> <?php echo e($user['rol']); ?></p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/perfil.php" class="text-blue-600 hover:text-blue-700 font-semibold text-sm mt-3 inline-block">
                        <i class="fas fa-user mr-1"></i>Editar Perfil
                    </a>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="flex justify-end space-x-4">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
                Cancelar
            </a>
            <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold transition">
                <i class="fas fa-save mr-2"></i>Guardar Preferencias
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
