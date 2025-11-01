<?php
/**
 * Página pública de registro a eventos
 * Permite registro sin autenticación usando WhatsApp o RFC
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();
$evento_id = $_GET['evento'] ?? null;
$error = '';
$success = '';
$empresa_data = null;
$search_performed = false;

// Verificar que el evento existe y está activo
if (!$evento_id) {
    die('Evento no encontrado');
}

$stmt = $db->prepare("SELECT * FROM eventos WHERE id = ? AND activo = 1");
$stmt->execute([$evento_id]);
$evento = $stmt->fetch();

if (!$evento) {
    die('Evento no encontrado o inactivo');
}

// Buscar empresa por WhatsApp o RFC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buscar') {
    $search_performed = true;
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $rfc = sanitize($_POST['rfc'] ?? '');
    
    if (!empty($whatsapp) || !empty($rfc)) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($whatsapp)) {
            $where_conditions[] = "whatsapp = ?";
            $params[] = $whatsapp;
        }
        
        if (!empty($rfc)) {
            $where_conditions[] = "rfc = ?";
            $params[] = $rfc;
        }
        
        $where_sql = implode(' OR ', $where_conditions);
        $stmt = $db->prepare("SELECT * FROM empresas WHERE ($where_sql) AND activo = 1 LIMIT 1");
        $stmt->execute($params);
        $empresa_data = $stmt->fetch();
        
        if (!$empresa_data) {
            $error = 'No se encontró una empresa registrada con esos datos. Por favor registre sus datos manualmente.';
        }
    } else {
        $error = 'Debe ingresar al menos WhatsApp o RFC para buscar.';
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    try {
        $nombre = sanitize($_POST['nombre']);
        $email = sanitize($_POST['email']);
        $telefono = sanitize($_POST['telefono']);
        $whatsapp = sanitize($_POST['whatsapp_registro']);
        $rfc = sanitize($_POST['rfc_registro']);
        $boletos = intval($_POST['boletos_solicitados'] ?? 1);
        $empresa_id = !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null;
        
        // Validar campos obligatorios
        if (empty($nombre) || empty($email)) {
            throw new Exception('Nombre y email son obligatorios');
        }
        
        // Verificar cupo disponible
        if ($evento['cupo_maximo'] && ($evento['inscritos'] + $boletos) > $evento['cupo_maximo']) {
            throw new Exception('No hay suficiente cupo disponible para la cantidad de boletos solicitados');
        }
        
        // Verificar si ya está registrado con ese email
        $stmt = $db->prepare("SELECT id FROM eventos_inscripciones WHERE evento_id = ? AND email_invitado = ?");
        $stmt->execute([$evento_id, $email]);
        if ($stmt->fetch()) {
            throw new Exception('Este email ya está registrado para este evento');
        }
        
        // Registrar inscripción
        $stmt = $db->prepare("
            INSERT INTO eventos_inscripciones 
            (evento_id, usuario_id, empresa_id, nombre_invitado, email_invitado, telefono_invitado, 
             whatsapp_invitado, rfc_invitado, boletos_solicitados, es_invitado, estado) 
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 1, 'CONFIRMADO')
        ");
        
        $stmt->execute([
            $evento_id, $empresa_id, $nombre, $email, $telefono, 
            $whatsapp, $rfc, $boletos
        ]);
        
        // Actualizar contador de inscritos
        $stmt = $db->prepare("UPDATE eventos SET inscritos = inscritos + ? WHERE id = ?");
        $stmt->execute([$boletos, $evento_id]);
        
        $success = "Registro exitoso! Se han confirmado $boletos boleto(s) para el evento. Recibirá un correo de confirmación.";
        
        // Limpiar formulario
        $empresa_data = null;
        $search_performed = false;
        
    } catch (Exception $e) {
        $error = 'Error al registrar: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($evento['titulo']); ?> - Registro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Encabezado del evento -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
                <?php if ($evento['imagen']): ?>
                <img src="<?php echo BASE_URL . '/public/uploads/' . e($evento['imagen']); ?>" 
                     alt="<?php echo e($evento['titulo']); ?>" 
                     class="w-full h-64 object-cover">
                <?php endif; ?>
                
                <div class="p-8">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">
                        <?php echo e($evento['titulo']); ?>
                    </h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-calendar mr-3 text-blue-600"></i>
                            <span><?php echo date('d/m/Y', strtotime($evento['fecha_inicio'])); ?></span>
                        </div>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-clock mr-3 text-blue-600"></i>
                            <span><?php echo date('H:i', strtotime($evento['fecha_inicio'])); ?> - <?php echo date('H:i', strtotime($evento['fecha_fin'])); ?></span>
                        </div>
                        <?php if ($evento['ubicacion']): ?>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-map-marker-alt mr-3 text-blue-600"></i>
                            <span><?php echo e($evento['ubicacion']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($evento['costo'] > 0): ?>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-dollar-sign mr-3 text-green-600"></i>
                            <span class="font-semibold">$<?php echo number_format($evento['costo'], 2); ?> MXN</span>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-gift mr-3 text-green-600"></i>
                            <span class="font-semibold">Evento Gratuito</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($evento['cupo_maximo']): ?>
                        <div class="flex items-center text-gray-700">
                            <i class="fas fa-users mr-3 text-purple-600"></i>
                            <span>Cupo: <?php echo $evento['inscritos']; ?> / <?php echo $evento['cupo_maximo']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="prose max-w-none">
                        <p class="text-gray-700 whitespace-pre-line"><?php echo e($evento['descripcion']); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-6 mb-6 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                        <p class="text-green-700 font-semibold"><?php echo e($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-6 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3"></i>
                        <p class="text-red-700"><?php echo e($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulario de búsqueda -->
            <?php if (!$empresa_data && !$success): ?>
            <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-search mr-2 text-blue-600"></i>
                    Buscar por WhatsApp o RFC
                </h2>
                <p class="text-gray-600 mb-6">
                    Si su empresa ya está registrada en nuestro sistema, puede buscarla por WhatsApp o RFC para autocompletar los datos.
                </p>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="buscar">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">WhatsApp</label>
                            <input type="text" name="whatsapp" 
                                   placeholder="Ej: 4421234567"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">RFC</label>
                            <input type="text" name="rfc" 
                                   placeholder="Ej: ABC123456XYZ"
                                   maxlength="13"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                        <i class="fas fa-search mr-2"></i>Buscar Empresa
                    </button>
                </form>
                
                <?php if ($search_performed && !$empresa_data): ?>
                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        No encontramos su empresa. Por favor complete el formulario de registro manual a continuación.
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Formulario de registro -->
            <?php if ($empresa_data || $search_performed || $success === ''): ?>
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-user-plus mr-2 text-green-600"></i>
                    Formulario de Registro
                </h2>
                
                <?php if ($empresa_data): ?>
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-semibold mb-2">
                        <i class="fas fa-check-circle mr-2"></i>Empresa encontrada:
                    </p>
                    <p class="text-blue-700"><?php echo e($empresa_data['razon_social']); ?></p>
                    <p class="text-sm text-blue-600">Los datos se han autocompletado. Puede modificarlos si es necesario.</p>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="registrar">
                    <input type="hidden" name="empresa_id" value="<?php echo $empresa_data['id'] ?? ''; ?>">
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Nombre Completo *</label>
                        <input type="text" name="nombre" required
                               value="<?php echo e($empresa_data['representante'] ?? ''); ?>"
                               placeholder="Nombre de la persona que asistirá"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Email *</label>
                        <input type="email" name="email" required
                               value="<?php echo e($empresa_data['email'] ?? ''); ?>"
                               placeholder="correo@ejemplo.com"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Teléfono</label>
                            <input type="text" name="telefono"
                                   value="<?php echo e($empresa_data['telefono'] ?? ''); ?>"
                                   placeholder="4421234567"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">WhatsApp</label>
                            <input type="text" name="whatsapp_registro"
                                   value="<?php echo e($empresa_data['whatsapp'] ?? ''); ?>"
                                   placeholder="4421234567"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">RFC</label>
                        <input type="text" name="rfc_registro"
                               value="<?php echo e($empresa_data['rfc'] ?? ''); ?>"
                               placeholder="ABC123456XYZ"
                               maxlength="13"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            Número de Boletos Solicitados
                            <?php if ($empresa_data): ?>
                            <span class="text-sm font-normal text-gray-500">(para colaboradores con el mismo RFC)</span>
                            <?php endif; ?>
                        </label>
                        <input type="number" name="boletos_solicitados" min="1" max="10" value="1"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Máximo 10 boletos por registro</p>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-green-600 text-white py-4 rounded-lg hover:bg-green-700 font-bold text-lg">
                        <i class="fas fa-check mr-2"></i>Confirmar Registro
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="mt-8 text-center text-gray-600">
                <p>¿Tiene problemas? Contáctenos al correo: info@camaraqro.com</p>
            </div>
        </div>
    </div>
</body>
</html>
