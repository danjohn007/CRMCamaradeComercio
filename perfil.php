<?php
/**
 * Página de perfil de usuario
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'actualizar_perfil') {
        $nombre = sanitize($_POST['nombre'] ?? '');
        $telefono = sanitize($_POST['telefono'] ?? '');
        $whatsapp = sanitize($_POST['whatsapp'] ?? '');
        
        try {
            $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, telefono = ?, whatsapp = ? WHERE id = ?");
            $stmt->execute([$nombre, $telefono, $whatsapp, $user['id']]);
            
            $_SESSION['user_nombre'] = $nombre;
            $success = 'Perfil actualizado exitosamente';
            
            // Actualizar variable $user
            $user['nombre'] = $nombre;
        } catch (Exception $e) {
            $error = 'Error al actualizar el perfil: ' . $e->getMessage();
        }
    } elseif ($action === 'cambiar_password') {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nuevo = $_POST['password_nuevo'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        if (empty($password_actual) || empty($password_nuevo) || empty($password_confirmar)) {
            $error = 'Todos los campos son obligatorios';
        } elseif ($password_nuevo !== $password_confirmar) {
            $error = 'Las contraseñas nuevas no coinciden';
        } elseif (strlen($password_nuevo) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } else {
            try {
                // Verificar contraseña actual
                $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ?");
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch();
                
                if (password_verify($password_actual, $userData['password'])) {
                    // Actualizar contraseña
                    $password_hash = password_hash($password_nuevo, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $user['id']]);
                    
                    $success = 'Contraseña actualizada exitosamente';
                } else {
                    $error = 'La contraseña actual es incorrecta';
                }
            } catch (Exception $e) {
                $error = 'Error al cambiar la contraseña: ' . $e->getMessage();
            }
        }
    }
}

// Obtener datos del usuario
try {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch();
    
    // Si tiene empresa asociada, obtener datos de la empresa
    if ($userData['empresa_id']) {
        $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$userData['empresa_id']]);
        $empresa = $stmt->fetch();
    }
} catch (Exception $e) {
    $error = 'Error al cargar los datos: ' . $e->getMessage();
}

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">
        <i class="fas fa-user mr-2"></i>Mi Perfil
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna izquierda - Info general -->
        <div class="space-y-6">
            <!-- Avatar y datos básicos -->
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="w-24 h-24 bg-blue-600 text-white rounded-full flex items-center justify-center text-4xl font-bold mx-auto mb-4">
                    <?php echo strtoupper(substr($userData['nombre'] ?: 'U', 0, 1)); ?>
                </div>
                <h2 class="text-xl font-bold text-gray-800 mb-1"><?php echo e($userData['nombre']); ?></h2>
                <p class="text-gray-600"><?php echo e($userData['email']); ?></p>
                <p class="mt-2">
                    <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                        <?php echo e($userData['rol']); ?>
                    </span>
                </p>
                <?php if ($userData['email_verificado']): ?>
                <p class="mt-2 text-sm text-green-600">
                    <i class="fas fa-check-circle mr-1"></i>Email verificado
                </p>
                <?php endif; ?>
            </div>

            <!-- Empresa asociada -->
            <?php if (isset($empresa)): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-building mr-2 text-blue-600"></i>Empresa
                </h3>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="font-semibold text-gray-700"><?php echo e($empresa['razon_social']); ?></span>
                    </div>
                    <?php if ($empresa['rfc']): ?>
                    <div class="text-gray-600">
                        <i class="fas fa-id-card mr-2"></i><?php echo e($empresa['rfc']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($empresa['telefono']): ?>
                    <div class="text-gray-600">
                        <i class="fas fa-phone mr-2"></i><?php echo e($empresa['telefono']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="empresas.php?action=view&id=<?php echo $empresa['id']; ?>" 
                   class="block mt-4 text-center py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                    Ver Empresa
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna derecha - Formularios -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Actualizar información personal -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Información Personal</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="actualizar_perfil">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Nombre Completo *</label>
                            <input type="text" name="nombre" required
                                   value="<?php echo e($userData['nombre']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Teléfono</label>
                                <input type="tel" name="telefono"
                                       maxlength="10"
                                       value="<?php echo e($userData['telefono']); ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="10 dígitos">
                                <p class="text-xs text-gray-500 mt-1">10 dígitos sin espacios</p>
                            </div>

                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">WhatsApp</label>
                                <input type="tel" name="whatsapp"
                                       maxlength="10"
                                       value="<?php echo e($userData['whatsapp']); ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="10 dígitos">
                                <p class="text-xs text-gray-500 mt-1">10 dígitos sin espacios</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Email</label>
                            <input type="email" 
                                   value="<?php echo e($userData['email']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg bg-gray-50"
                                   readonly>
                            <p class="text-xs text-gray-500 mt-1">El email no se puede modificar</p>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>

            <!-- Cambiar contraseña -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Cambiar Contraseña</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="cambiar_password">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Contraseña Actual *</label>
                            <input type="password" name="password_actual" required
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Nueva Contraseña *</label>
                            <input type="password" name="password_nuevo" required minlength="8"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Mínimo 8 caracteres</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Confirmar Nueva Contraseña *</label>
                            <input type="password" name="password_confirmar" required minlength="8"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-key mr-2"></i>Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>

            <!-- Actividad reciente -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Actividad Reciente</h2>
                
                <div class="space-y-3">
                    <?php if ($userData['ultimo_acceso']): ?>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-sign-in-alt mr-3 text-blue-500"></i>
                        <div>
                            <p>Último acceso</p>
                            <p class="text-xs text-gray-500"><?php echo formatDate($userData['ultimo_acceso'], 'd/m/Y H:i'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-calendar-plus mr-3 text-green-500"></i>
                        <div>
                            <p>Cuenta creada</p>
                            <p class="text-xs text-gray-500"><?php echo formatDate($userData['created_at'], 'd/m/Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
