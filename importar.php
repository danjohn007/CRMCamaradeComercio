<?php
/**
 * Módulo de importación de datos desde Excel/CSV
 * 
 * IMPORTANTE: El campo VENDEDOR debe coincidir con el nombre de un usuario
 * activo con rol AFILADOR en la tabla usuarios. El sistema intentará hacer
 * una búsqueda exacta primero y luego una búsqueda parcial si no encuentra
 * coincidencia exacta.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

// Solo PRESIDENCIA, Dirección y Afiladores pueden importar
$allowed_roles = ['PRESIDENCIA', 'DIRECCION', 'AFILADOR'];
if (!in_array($_SESSION['user_rol'], $allowed_roles)) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();
$message = '';
$error = '';
$results = [];
$show_results = false;

// Procesar archivo importado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo'];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo.";
    } else {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['csv', 'xlsx'])) {
            $error = "Formato de archivo no válido. Use CSV o XLSX.";
        } else {
            $datos = [];
            
            // Procesar CSV
            if ($extension === 'csv') {
                if (($handle = fopen($archivo['tmp_name'], 'r')) !== FALSE) {
                    $headers = fgetcsv($handle, 1000, ',');
                    
                    while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                        if (count($row) === count($headers)) {
                            $datos[] = array_combine($headers, $row);
                        }
                    }
                    fclose($handle);
                }
            }
            // Procesar XLSX (requiere librería)
            else if ($extension === 'xlsx') {
                // Nota: Para producción, usar PHPSpreadsheet o similar
                $error = "Formato XLSX requiere librería adicional. Use CSV por ahora.";
            }
            
            if (!empty($datos)) {
                $importados = 0;
                $duplicados = 0;
                $errores = 0;
                
                foreach ($datos as $fila) {
                    // Mapeo de columnas esperadas
                    $empresa = sanitize($fila['EMPRESA / RAZON SOCIAL'] ?? $fila['EMPRESA'] ?? '');
                    $rfc = sanitize($fila['RFC'] ?? '');
                    $email = sanitize($fila['EMAIL'] ?? '');
                    $telefono = sanitize($fila['TELÉFONO'] ?? $fila['TELEFONO'] ?? '');
                    $representante = sanitize($fila['REPRESENTANTE'] ?? '');
                    $direccion_comercial = sanitize($fila['DIRECCIÓN COMERCIAL'] ?? $fila['DIRECCION COMERCIAL'] ?? '');
                    $direccion_fiscal = sanitize($fila['DIRECCIÓN FISCAL'] ?? $fila['DIRECCION FISCAL'] ?? '');
                    $sector = sanitize($fila['SECTOR'] ?? 'Comercio');
                    $categoria = sanitize($fila['CATEGORÍA'] ?? $fila['CATEGORIA'] ?? '');
                    $membresia = sanitize($fila['MEMBRESÍA'] ?? $fila['MEMBRESIA'] ?? 'Básica');
                    $tipo_afiliacion = sanitize($fila['TIPO DE AFILIACIÓN'] ?? $fila['TIPO DE AFILIACION'] ?? 'Nueva');
                    $vendedor = sanitize($fila['VENDEDOR'] ?? '');
                    $fecha_renovacion = sanitize($fila['FECHA DE RENOVACIÓN'] ?? $fila['FECHA DE RENOVACION'] ?? date('Y-m-d', strtotime('+1 year')));
                    $no_recibo = sanitize($fila['No. DE RECIBO'] ?? '');
                    $no_factura = sanitize($fila['No. DE FACTURA'] ?? '');
                    $engomado = sanitize($fila['ENGOMADO'] ?? '');
                    
                    // Validación básica
                    if (empty($empresa) || empty($rfc)) {
                        $results[] = [
                            'empresa' => $empresa ?: 'Sin nombre',
                            'status' => 'error',
                            'mensaje' => 'Faltan datos obligatorios (Empresa o RFC)'
                        ];
                        $errores++;
                        continue;
                    }
                    
                    // Verificar duplicados por RFC
                    $stmt = $db->prepare("SELECT id FROM empresas WHERE rfc = ?");
                    $stmt->execute([$rfc]);
                    $existe = $stmt->fetch();
                    
                    if ($existe) {
                        $results[] = [
                            'empresa' => $empresa,
                            'status' => 'duplicado',
                            'mensaje' => "RFC ya existe en el sistema"
                        ];
                        $duplicados++;
                        continue;
                    }
                    
                    // Obtener IDs de catálogos
                    $warnings = [];
                    
                    $sector_id = null;
                    $stmt = $db->prepare("SELECT id FROM sectores WHERE nombre = ? LIMIT 1");
                    $stmt->execute([$sector]);
                    $result = $stmt->fetch();
                    if ($result) {
                        $sector_id = $result['id'];
                    } else if (!empty($sector)) {
                        $warnings[] = "Sector '$sector' no encontrado";
                    }
                    
                    $categoria_id = null;
                    if (!empty($categoria)) {
                        $stmt = $db->prepare("SELECT id FROM categorias WHERE nombre = ? LIMIT 1");
                        $stmt->execute([$categoria]);
                        $result = $stmt->fetch();
                        if ($result) {
                            $categoria_id = $result['id'];
                        } else {
                            $warnings[] = "Categoría '$categoria' no encontrada";
                        }
                    }
                    
                    $membresia_id = null;
                    $stmt = $db->prepare("SELECT id FROM membresias WHERE nombre = ? LIMIT 1");
                    $stmt->execute([$membresia]);
                    $result = $stmt->fetch();
                    if ($result) {
                        $membresia_id = $result['id'];
                    } else if (!empty($membresia)) {
                        $warnings[] = "Membresía '$membresia' no encontrada";
                    }
                    
                    // Obtener ID del vendedor/afiliador si se proporcionó nombre
                    // Buscar en la tabla usuarios con rol AFILADOR
                    $vendedor_id = null;
                    if (!empty($vendedor)) {
                        // Intentar búsqueda exacta primero
                        $stmt = $db->prepare("SELECT id FROM usuarios WHERE nombre = ? AND rol = 'AFILADOR' AND activo = 1 LIMIT 1");
                        $stmt->execute([$vendedor]);
                        $result = $stmt->fetch();
                        if ($result) {
                            $vendedor_id = $result['id'];
                        } else {
                            // Intentar búsqueda parcial (por si hay diferencias en el nombre)
                            $stmt = $db->prepare("SELECT id FROM usuarios WHERE nombre LIKE ? AND rol = 'AFILADOR' AND activo = 1 LIMIT 1");
                            $stmt->execute(['%' . $vendedor . '%']);
                            $result = $stmt->fetch();
                            if ($result) {
                                $vendedor_id = $result['id'];
                            } else {
                                $warnings[] = "Vendedor/Afiliador '$vendedor' no encontrado";
                            }
                        }
                    }
                    
                    // Insertar empresa
                    $stmt = $db->prepare("
                        INSERT INTO empresas (
                            razon_social, rfc, email, telefono, representante,
                            direccion_comercial, direccion_fiscal, sector_id, categoria_id,
                            membresia_id, tipo_afiliacion, vendedor_id, fecha_renovacion,
                            no_recibo, no_factura, engomado, activo, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    
                    try {
                        $stmt->execute([
                            $empresa, $rfc, $email, $telefono, $representante,
                            $direccion_comercial, $direccion_fiscal, $sector_id, $categoria_id,
                            $membresia_id, $tipo_afiliacion, $vendedor_id, $fecha_renovacion,
                            $no_recibo, $no_factura, $engomado
                        ]);
                        
                        $empresa_id = $db->lastInsertId();
                        
                        $mensaje_success = 'Importado correctamente';
                        if (!empty($warnings)) {
                            $mensaje_success .= ' (Advertencias: ' . implode(', ', $warnings) . ')';
                        }
                        
                        $results[] = [
                            'empresa' => $empresa,
                            'status' => 'success',
                            'mensaje' => $mensaje_success
                        ];
                        $importados++;
                        
                        // Registrar auditoría
                        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, descripcion) VALUES (?, 'IMPORT', 'empresas', ?, ?)");
                        $stmt_audit->execute([$user['id'], $empresa_id, "Empresa importada: $empresa"]);
                    } catch (Exception $e) {
                        $results[] = [
                            'empresa' => $empresa,
                            'status' => 'error',
                            'mensaje' => 'Error en la base de datos: ' . $e->getMessage()
                        ];
                        $errores++;
                    }
                }
                
                $show_results = true;
                $message = "Proceso completado: $importados importados, $duplicados duplicados, $errores errores.";
            } else {
                $error = "No se encontraron datos válidos en el archivo.";
            }
        }
    }
}

include 'app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Importar Empresas desde Excel/CSV</h1>
        <a href="empresas.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition">
            Volver a Empresas
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Formulario de importación -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Subir archivo</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">
                        Seleccionar archivo CSV o XLSX
                    </label>
                    <input type="file" name="archivo" accept=".csv,.xlsx" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2">
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    Importar Datos
                </button>
            </form>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">⚠️ Importante:</h3>
                <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                    <li>El archivo debe ser CSV o XLSX</li>
                    <li>La primera fila debe contener los encabezados</li>
                    <li>Se validarán duplicados por RFC</li>
                    <li>Campos obligatorios: Empresa y RFC</li>
                </ul>
            </div>
        </div>

        <!-- Formato esperado -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Formato del archivo</h2>
            
            <p class="text-gray-600 mb-4">
                El archivo debe contener las siguientes columnas (encabezados):
            </p>

            <div class="bg-gray-50 p-4 rounded-lg text-sm overflow-x-auto">
                <ul class="space-y-2">
                    <li><strong>EMPRESA / RAZON SOCIAL</strong> *</li>
                    <li><strong>RFC</strong> *</li>
                    <li><strong>EMAIL</strong></li>
                    <li><strong>TELÉFONO</strong></li>
                    <li><strong>REPRESENTANTE</strong></li>
                    <li><strong>DIRECCIÓN COMERCIAL</strong></li>
                    <li><strong>DIRECCIÓN FISCAL</strong></li>
                    <li><strong>SECTOR</strong> (Comercio/Servicios/Turismo)</li>
                    <li><strong>CATEGORÍA</strong></li>
                    <li><strong>MEMBRESÍA</strong></li>
                    <li><strong>TIPO DE AFILIACIÓN</strong></li>
                    <li><strong>VENDEDOR</strong></li>
                    <li><strong>FECHA DE RENOVACIÓN</strong> (YYYY-MM-DD)</li>
                    <li><strong>No. DE RECIBO</strong></li>
                    <li><strong>No. DE FACTURA</strong></li>
                    <li><strong>ENGOMADO</strong></li>
                </ul>
            </div>

            <p class="text-xs text-gray-500 mt-4">
                * Campos obligatorios
            </p>

            <div class="mt-4">
                <a href="<?= BASE_URL ?>/public/plantilla_importacion.csv" 
                   class="inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                    📥 Descargar plantilla CSV
                </a>
            </div>
        </div>
    </div>

    <?php if ($show_results && !empty($results)): ?>
        <!-- Resultados de la importación -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h2 class="text-xl font-semibold">Resultados de la Importación</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Empresa
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Mensaje
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $result): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= htmlspecialchars($result['empresa']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($result['status'] === 'success'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        ✓ Importado
                                    </span>
                                <?php elseif ($result['status'] === 'duplicado'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                        ⚠ Duplicado
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                        ✗ Error
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= htmlspecialchars($result['mensaje']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Instrucciones adicionales -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-800 mb-3">📋 Instrucciones de uso</h3>
        <ol class="list-decimal list-inside space-y-2 text-blue-700">
            <li>Descargue la plantilla CSV de ejemplo</li>
            <li>Complete los datos de las empresas en Excel o cualquier editor de hojas de cálculo</li>
            <li>Guarde el archivo en formato CSV (separado por comas)</li>
            <li>Suba el archivo usando el formulario de arriba</li>
            <li>Revise los resultados de la importación</li>
            <li>Los registros duplicados (por RFC) serán omitidos automáticamente</li>
        </ol>
    </div>
</div>

<?php include 'app/views/layouts/footer.php'; ?>
