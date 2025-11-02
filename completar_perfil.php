<?php
/**
 * Módulo "Completar mi Perfil" para usuarios externos
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Solo para usuarios ENTIDAD_COMERCIAL y EMPRESA_TRACTORA
if (!in_array($user['rol'], ['ENTIDAD_COMERCIAL', 'EMPRESA_TRACTORA'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Procesar asociación de empresa por RFC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'asociar_empresa') {
    $rfc_buscar = strtoupper(sanitize($_POST['rfc_buscar'] ?? ''));
    
    try {
        // Validar que se haya subido constancia fiscal
        if (!isset($_FILES['constancia_fiscal']) || $_FILES['constancia_fiscal']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('La Constancia de Situación Fiscal es obligatoria');
        }
        
        // Validar tamaño (máx 5MB)
        if ($_FILES['constancia_fiscal']['size'] > 5 * 1024 * 1024) {
            throw new Exception('El archivo es demasiado grande. Tamaño máximo: 5MB');
        }
        
        // Buscar empresa por RFC
        $stmt = $db->prepare("SELECT * FROM empresas WHERE rfc = ? AND activo = 1");
        $stmt->execute([$rfc_buscar]);
        $empresa_encontrada = $stmt->fetch();
        
        if (!$empresa_encontrada) {
            throw new Exception('No se encontró ninguna empresa con ese RFC');
        }
        
        // Subir constancia fiscal
        $upload_dir = UPLOAD_PATH . '/constancias/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['constancia_fiscal']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf'];
        
        // Validar extensión
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Solo se permiten archivos PDF para la constancia fiscal');
        }
        
        // Validar MIME type real del archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['constancia_fiscal']['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type !== 'application/pdf') {
            throw new Exception('El archivo debe ser un PDF válido');
        }
        
        $new_filename = 'constancia_' . $rfc_buscar . '_' . time() . '.pdf';
        $upload_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($_FILES['constancia_fiscal']['tmp_name'], $upload_path)) {
            throw new Exception('Error al subir el archivo');
        }
        
        $constancia_path = '/public/uploads/constancias/' . $new_filename;
        
        // Asociar empresa al usuario
        $stmt = $db->prepare("UPDATE usuarios SET empresa_id = ?, constancia_fiscal = ? WHERE id = ?");
        $stmt->execute([$empresa_encontrada['id'], $constancia_path, $user['id']]);
        
        // Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles) VALUES (?, 'ASOCIAR_EMPRESA', 'empresas', ?, ?)");
        $stmt->execute([$user['id'], $empresa_encontrada['id'], 'Usuario asociado a empresa: ' . $empresa_encontrada['razon_social']]);
        
        $success = 'Empresa asociada exitosamente';
        
        // Recargar usuario
        $_SESSION['empresa_id'] = $empresa_encontrada['id'];
        $user['empresa_id'] = $empresa_encontrada['id'];
        
    } catch (Exception $e) {
        $error = 'Error al asociar empresa: ' . $e->getMessage();
    }
}

// Verificar que el usuario tiene empresa asociada
if (!$user['empresa_id']) {
    $mostrar_asociar = true;
    $empresa = null;
    $completitud = null;
} else {
    $mostrar_asociar = false;
    // Obtener información de la empresa
    $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$user['empresa_id']]);
    $empresa = $stmt->fetch();
    
    // Obtener porcentaje de completitud
    $stmt = $db->prepare("SELECT * FROM perfil_completitud WHERE empresa_id = ?");
    $stmt->execute([$user['empresa_id']]);
    $completitud = $stmt->fetch();
    
    // Si no existe, calcular
    if (!$completitud) {
        $campos_totales = 20;
        $campos_completados = 0;
        
        if ($empresa['razon_social']) $campos_completados++;
        if ($empresa['rfc']) $campos_completados++;
        if ($empresa['email']) $campos_completados++;
        if ($empresa['telefono']) $campos_completados++;
        if ($empresa['whatsapp']) $campos_completados++;
        if ($empresa['representante']) $campos_completados++;
        if ($empresa['direccion_comercial']) $campos_completados++;
        if ($empresa['direccion_fiscal']) $campos_completados++;
        if ($empresa['colonia']) $campos_completados++;
        if ($empresa['ciudad']) $campos_completados++;
        if ($empresa['codigo_postal']) $campos_completados++;
        if ($empresa['sector_id']) $campos_completados++;
        if ($empresa['categoria_id']) $campos_completados++;
        if ($empresa['membresia_id']) $campos_completados++;
        if ($empresa['descripcion']) $campos_completados++;
        if ($empresa['servicios_productos']) $campos_completados++;
        if ($empresa['palabras_clave']) $campos_completados++;
        if ($empresa['sitio_web']) $campos_completados++;
        if ($empresa['facebook']) $campos_completados++;
        if ($empresa['instagram']) $campos_completados++;
        
        $porcentaje = ($campos_completados * 100) / $campos_totales;
        
        $completitud = [
            'campos_totales' => $campos_totales,
            'campos_completados' => $campos_completados,
            'porcentaje' => $porcentaje
        ];
    }
}

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $empresa) {
    $data = [
        'razon_social' => sanitize($_POST['razon_social'] ?? ''),
        'rfc' => strtoupper(sanitize($_POST['rfc'] ?? '')),
        'email' => sanitize($_POST['email'] ?? ''),
        'telefono' => sanitize($_POST['telefono'] ?? ''),
        'whatsapp' => sanitize($_POST['whatsapp'] ?? ''),
        'representante' => sanitize($_POST['representante'] ?? ''),
        'direccion_comercial' => sanitize($_POST['direccion_comercial'] ?? ''),
        'direccion_fiscal' => sanitize($_POST['direccion_fiscal'] ?? ''),
        'colonia' => sanitize($_POST['colonia'] ?? ''),
        'ciudad' => sanitize($_POST['ciudad'] ?? ''),
        'codigo_postal' => sanitize($_POST['codigo_postal'] ?? ''),
        'estado' => sanitize($_POST['estado'] ?? 'Querétaro'),
        'sector_id' => $_POST['sector_id'] ?: null,
        'categoria_id' => $_POST['categoria_id'] ?: null,
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'servicios_productos' => sanitize($_POST['servicios_productos'] ?? ''),
        'palabras_clave' => sanitize($_POST['palabras_clave'] ?? ''),
        'sitio_web' => sanitize($_POST['sitio_web'] ?? ''),
        'facebook' => sanitize($_POST['facebook'] ?? ''),
        'instagram' => sanitize($_POST['instagram'] ?? ''),
    ];

    try {
        $sql = "UPDATE empresas SET 
                razon_social = ?, rfc = ?, email = ?, telefono = ?, whatsapp = ?,
                representante = ?, direccion_comercial = ?, direccion_fiscal = ?,
                colonia = ?, ciudad = ?, codigo_postal = ?, estado = ?,
                sector_id = ?, categoria_id = ?, descripcion = ?,
                servicios_productos = ?, palabras_clave = ?, sitio_web = ?,
                facebook = ?, instagram = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['razon_social'], $data['rfc'], $data['email'], $data['telefono'], $data['whatsapp'],
            $data['representante'], $data['direccion_comercial'], $data['direccion_fiscal'],
            $data['colonia'], $data['ciudad'], $data['codigo_postal'], $data['estado'],
            $data['sector_id'], $data['categoria_id'], $data['descripcion'],
            $data['servicios_productos'], $data['palabras_clave'], $data['sitio_web'],
            $data['facebook'], $data['instagram'],
            $user['empresa_id']
        ]);
        
        // Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) 
                             VALUES (?, 'UPDATE_PERFIL_EMPRESA', 'empresas', ?)");
        $stmt->execute([$user['id'], $user['empresa_id']]);
        
        $success = 'Perfil actualizado exitosamente';
        
        // Recargar datos
        $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$user['empresa_id']]);
        $empresa = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT * FROM perfil_completitud WHERE empresa_id = ?");
        $stmt->execute([$user['empresa_id']]);
        $completitud = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Error al actualizar el perfil: ' . $e->getMessage();
    }
}

// Obtener catálogos
$sectores = $db->query("SELECT * FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Completar mi Perfil</h1>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <p class="text-red-700"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <p class="text-green-700"><?php echo e($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($mostrar_asociar): ?>
        <!-- Formulario para asociar empresa -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-building mr-2 text-blue-600"></i>Asociar Empresa
            </h2>
            <p class="text-gray-600 mb-6">
                Para completar tu perfil, primero debes asociar tu cuenta con una empresa registrada. 
                Busca tu empresa por RFC y adjunta tu Constancia de Situación Fiscal actualizada.
            </p>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="asociar_empresa">
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">
                        RFC de la Empresa *
                    </label>
                    <input type="text" name="rfc_buscar" id="rfc_buscar" required
                           maxlength="13" placeholder="ABC123456XYZ"
                           oninput="buscarEmpresaPorRFC(this.value)"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    <div id="empresa_info" class="mt-3"></div>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">
                        Constancia de Situación Fiscal Actualizada *
                        <span class="text-red-500 text-xs">(Obligatorio - Solo PDF)</span>
                    </label>
                    <input type="file" name="constancia_fiscal" required
                           accept="application/pdf"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Solo archivos PDF (máx. 5MB)</p>
                </div>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
                    <p class="text-sm text-yellow-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Importante:</strong> Una vez asociada la empresa, podrás editar la información 
                        excepto: RFC, Membresía, Vendedor/Afiliador, Tipo de Afiliación y Fecha de Renovación.
                    </p>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-link mr-2"></i>Asociar Empresa
                    </button>
                </div>
            </form>
        </div>
        
        <script>
        let buscarTimeout;
        function buscarEmpresaPorRFC(rfc) {
            clearTimeout(buscarTimeout);
            const infoDiv = document.getElementById('empresa_info');
            
            if (rfc.length < 12) {
                infoDiv.innerHTML = '';
                return;
            }
            
            buscarTimeout = setTimeout(async () => {
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/api/buscar_empresa.php?rfc=' + encodeURIComponent(rfc));
                    const data = await response.json();
                    
                    if (data.success && data.empresa) {
                        const emp = data.empresa;
                        infoDiv.innerHTML = `
                            <div class="p-4 bg-green-50 border-l-4 border-green-500 rounded">
                                <p class="text-sm text-green-700 font-semibold mb-2">
                                    <i class="fas fa-check-circle mr-2"></i>Empresa encontrada:
                                </p>
                                <p class="text-gray-700"><strong>Razón Social:</strong> ${emp.razon_social}</p>
                                <p class="text-gray-600 text-sm"><strong>Email:</strong> ${emp.email || 'No registrado'}</p>
                                <p class="text-gray-600 text-sm"><strong>Teléfono:</strong> ${emp.telefono || 'No registrado'}</p>
                            </div>
                        `;
                    } else {
                        infoDiv.innerHTML = `
                            <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded">
                                <p class="text-sm text-red-700">
                                    <i class="fas fa-times-circle mr-2"></i>No se encontró ninguna empresa con ese RFC
                                </p>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error al buscar empresa:', error);
                    infoDiv.innerHTML = `
                        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded">
                            <p class="text-sm text-yellow-700">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Error al buscar empresa
                            </p>
                        </div>
                    `;
                }
            }, 500);
        }
        </script>
        
    <?php elseif ($empresa && $completitud): ?>
        
        <!-- Indicador de progreso -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Progreso de Completitud</h2>
                <span class="text-3xl font-bold text-blue-600">
                    <?php echo number_format($completitud['porcentaje'], 0); ?>%
                </span>
            </div>
            
            <div class="w-full bg-gray-200 rounded-full h-6">
                <div class="bg-gradient-to-r from-blue-500 to-green-500 h-6 rounded-full transition-all duration-500"
                     style="width: <?php echo $completitud['porcentaje']; ?>%">
                    <span class="flex items-center justify-center h-full text-white text-sm font-semibold">
                        <?php echo $completitud['campos_completados']; ?> de <?php echo $completitud['campos_totales']; ?> campos
                    </span>
                </div>
            </div>
            
            <?php if ($completitud['porcentaje'] < 100): ?>
                <p class="text-gray-600 mt-3">
                    <i class="fas fa-info-circle mr-2"></i>
                    Completa todos los campos para mejorar la visibilidad de tu empresa
                </p>
            <?php else: ?>
                <p class="text-green-600 mt-3">
                    <i class="fas fa-check-circle mr-2"></i>
                    ¡Felicidades! Tu perfil está 100% completo
                </p>
            <?php endif; ?>
        </div>

        <!-- Formulario de perfil -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                
                <!-- Información Básica -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-building mr-2 text-blue-600"></i>Información Básica
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Razón Social *
                                <?php if (!$empresa['razon_social']): ?>
                                    <span class="text-red-500 text-xs">(Requerido)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="razon_social" required
                                   value="<?php echo e($empresa['razon_social']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                RFC
                                <?php if (!$empresa['rfc']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="rfc" maxlength="13"
                                   value="<?php echo e($empresa['rfc']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Email
                                <?php if (!$empresa['email']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="email" name="email"
                                   value="<?php echo e($empresa['email']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Teléfono
                                <?php if (!$empresa['telefono']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="tel" name="telefono"
                                   value="<?php echo e($empresa['telefono']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                WhatsApp
                                <?php if (!$empresa['whatsapp']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="tel" name="whatsapp"
                                   value="<?php echo e($empresa['whatsapp']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Representante Legal
                                <?php if (!$empresa['representante']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="representante"
                                   value="<?php echo e($empresa['representante']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Ubicación -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>Ubicación
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-semibold mb-2">
                                Dirección Comercial
                                <?php if (!$empresa['direccion_comercial']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="direccion_comercial"
                                   value="<?php echo e($empresa['direccion_comercial']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-semibold mb-2">
                                Dirección Fiscal
                                <?php if (!$empresa['direccion_fiscal']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="direccion_fiscal"
                                   value="<?php echo e($empresa['direccion_fiscal']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Colonia
                                <?php if (!$empresa['colonia']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="colonia"
                                   value="<?php echo e($empresa['colonia']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Ciudad
                                <?php if (!$empresa['ciudad']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="ciudad"
                                   value="<?php echo e($empresa['ciudad']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Código Postal
                                <?php if (!$empresa['codigo_postal']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="codigo_postal" maxlength="10"
                                   value="<?php echo e($empresa['codigo_postal']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Estado</label>
                            <input type="text" name="estado"
                                   value="<?php echo e($empresa['estado']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Clasificación -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-tags mr-2 text-blue-600"></i>Clasificación
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Sector
                                <?php if (!$empresa['sector_id']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <select name="sector_id" 
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($sectores as $sector): ?>
                                    <option value="<?php echo $sector['id']; ?>" 
                                            <?php echo $empresa['sector_id'] == $sector['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($sector['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Categoría
                                <?php if (!$empresa['categoria_id']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <select name="categoria_id" 
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" 
                                            <?php echo $empresa['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Información de Negocio -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-briefcase mr-2 text-blue-600"></i>Información de Negocio
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Descripción de la Empresa
                                <?php if (!$empresa['descripcion']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <textarea name="descripcion" rows="4"
                                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                      placeholder="Describe tu empresa, su historia y valores"><?php echo e($empresa['descripcion']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Servicios / Productos
                                <?php if (!$empresa['servicios_productos']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <textarea name="servicios_productos" rows="4"
                                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                      placeholder="Lista los servicios o productos que ofreces"><?php echo e($empresa['servicios_productos']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Palabras Clave
                                <?php if (!$empresa['palabras_clave']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="palabras_clave"
                                   value="<?php echo e($empresa['palabras_clave']); ?>"
                                   placeholder="Ej: construcción, arquitectura, diseño"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <p class="text-sm text-gray-500 mt-1">Separa las palabras con comas</p>
                        </div>
                    </div>
                </div>

                <!-- Redes Sociales -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-globe mr-2 text-blue-600"></i>Presencia en Línea
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Sitio Web
                                <?php if (!$empresa['sitio_web']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="url" name="sitio_web"
                                   value="<?php echo e($empresa['sitio_web']); ?>"
                                   placeholder="https://www.ejemplo.com"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Facebook
                                <?php if (!$empresa['facebook']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="url" name="facebook"
                                   value="<?php echo e($empresa['facebook']); ?>"
                                   placeholder="https://facebook.com/tuempresa"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                Instagram
                                <?php if (!$empresa['instagram']): ?>
                                    <span class="text-orange-500 text-xs">(Incompleto)</span>
                                <?php endif; ?>
                            </label>
                            <input type="url" name="instagram"
                                   value="<?php echo e($empresa['instagram']); ?>"
                                   placeholder="https://instagram.com/tuempresa"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end space-x-3 pt-4">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" 
                       class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
