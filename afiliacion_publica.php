<?php
/**
 * Módulo público de afiliación con pago de membresía
 * Permite a nuevas empresas registrarse y pagar su membresía sin necesidad de login
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers/email.php';

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';
$membresia_id = $_GET['membresia'] ?? null;
$paso = $_GET['paso'] ?? '1';

// Obtener configuración de PayPal
$config = getConfiguracion();
$paypal_client_id = $config['paypal_client_id'] ?? '';

// Si se especificó una membresía, obtener sus datos
$membresia = null;
if ($membresia_id) {
    $stmt = $db->prepare("SELECT * FROM membresias WHERE id = ? AND activo = 1");
    $stmt->execute([$membresia_id]);
    $membresia = $stmt->fetch();
}

// Obtener todas las membresías activas si no se especificó una
$membresias_activas = [];
if (!$membresia) {
    $stmt = $db->query("SELECT * FROM membresias WHERE activo = 1 ORDER BY nivel_orden ASC, costo ASC");
    $membresias_activas = $stmt->fetchAll();
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso === '1') {
    try {
        $data = [
            'membresia_id' => intval($_POST['membresia_id'] ?? 0),
            'razon_social' => sanitize($_POST['razon_social'] ?? ''),
            'rfc' => strtoupper(sanitize($_POST['rfc'] ?? '')),
            'giro' => sanitize($_POST['giro'] ?? ''),
            'direccion' => sanitize($_POST['direccion'] ?? ''),
            'telefono' => sanitize($_POST['telefono'] ?? ''),
            'whatsapp' => sanitize($_POST['whatsapp'] ?? ''),
            'representante' => sanitize($_POST['representante'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'sitio_web' => sanitize($_POST['sitio_web'] ?? ''),
        ];
        
        // Validar campos obligatorios
        if (empty($data['razon_social']) || empty($data['rfc']) || empty($data['representante']) || empty($data['email'])) {
            throw new Exception('Por favor complete todos los campos obligatorios');
        }
        
        // Validar RFC
        if (!validarRFC($data['rfc'])) {
            throw new Exception('El RFC no tiene un formato válido');
        }
        
        // Validar email
        if (!validarEmail($data['email'])) {
            throw new Exception('El email no tiene un formato válido');
        }
        
        // Verificar que el RFC no esté ya registrado
        $stmt = $db->prepare("SELECT id FROM empresas WHERE rfc = ?");
        $stmt->execute([$data['rfc']]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe una empresa registrada con este RFC');
        }
        
        // Verificar que el email no esté ya registrado
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe un usuario registrado con este email');
        }
        
        // Obtener datos de la membresía seleccionada
        $stmt = $db->prepare("SELECT * FROM membresias WHERE id = ? AND activo = 1");
        $stmt->execute([$data['membresia_id']]);
        $membresia_seleccionada = $stmt->fetch();
        
        if (!$membresia_seleccionada) {
            throw new Exception('Membresía no válida');
        }
        
        // Generar contraseña temporal (12 caracteres alfanuméricos)
        $password_temporal = bin2hex(random_bytes(12));
        $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);
        
        // Calcular fecha de renovación (fecha de vencimiento)
        $fecha_renovacion = date('Y-m-d', strtotime('+' . $membresia_seleccionada['vigencia_meses'] . ' months'));
        
        // Iniciar transacción
        $db->beginTransaction();
        
        try {
            // Crear empresa
            $stmt = $db->prepare("
                INSERT INTO empresas 
                (razon_social, rfc, giro, direccion, telefono, whatsapp, sitio_web, membresia_id, fecha_renovacion, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $data['razon_social'], $data['rfc'], $data['giro'], $data['direccion'],
                $data['telefono'], $data['whatsapp'], $data['sitio_web'], 
                $data['membresia_id'], $fecha_renovacion
            ]);
            
            $empresa_id = $db->lastInsertId();
            
            // Crear usuario representante
            $stmt = $db->prepare("
                INSERT INTO usuarios 
                (nombre, email, password, rol, empresa_id, activo, requiere_cambio_password) 
                VALUES (?, ?, ?, 'ENTIDAD_COMERCIAL', ?, 0, 1)
            ");
            $stmt->execute([
                $data['representante'], $data['email'], $password_hash, $empresa_id
            ]);
            
            $usuario_id = $db->lastInsertId();
            
            // Guardar datos en sesión para el pago
            $_SESSION['afiliacion_pendiente'] = [
                'empresa_id' => $empresa_id,
                'usuario_id' => $usuario_id,
                'membresia_id' => $data['membresia_id'],
                'membresia_nombre' => $membresia_seleccionada['nombre'],
                'membresia_costo' => $membresia_seleccionada['costo'],
                'password_temporal' => $password_temporal,
                'email' => $data['email'],
                'nombre' => $data['representante'],
                'razon_social' => $data['razon_social']
            ];
            
            $db->commit();
            
            // Redirigir a página de pago
            header('Location: ' . BASE_URL . '/afiliacion_publica.php?paso=2');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afiliación - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-handshake text-blue-600 mr-3"></i>
                    Únete a nuestra Cámara de Comercio
                </h1>
                <p class="text-xl text-gray-600">
                    Regístrate y obtén acceso a todos los beneficios de ser socio
                </p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-6 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3"></i>
                        <p class="text-red-700"><?php echo e($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-6 mb-6 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                        <div class="text-green-700"><?php echo $success; ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($paso === '1'): ?>
            <!-- Paso 1: Selección de membresía y datos -->
            
            <?php if (!$membresia && !empty($membresias_activas)): ?>
            <!-- Mostrar opciones de membresías -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Selecciona tu Membresía</h2>
                <div class="grid grid-cols-1 md:grid-cols-<?php echo min(count($membresias_activas), 3); ?> gap-6">
                    <?php foreach ($membresias_activas as $memb): ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 text-center">
                            <h3 class="text-2xl font-bold mb-2"><?php echo e($memb['nombre']); ?></h3>
                            <div class="text-4xl font-bold mb-2">
                                <?php echo formatMoney($memb['costo']); ?>
                            </div>
                            <p class="text-sm text-blue-100">
                                Vigencia: <?php echo $memb['vigencia_meses']; ?> meses
                            </p>
                        </div>
                        
                        <div class="p-6">
                            <p class="text-gray-600 mb-4"><?php echo e($memb['descripcion']); ?></p>
                            
                            <?php if ($memb['beneficios']): ?>
                            <div class="mb-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Beneficios:</h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <?php 
                                    $beneficios = explode("\n", $memb['beneficios']);
                                    foreach ($beneficios as $beneficio): 
                                        if (trim($beneficio)): 
                                    ?>
                                        <div class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                            <span><?php echo e(trim($beneficio)); ?></span>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <a href="?membresia=<?php echo $memb['id']; ?>" 
                               class="block w-full text-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold transition">
                                <i class="fas fa-arrow-right mr-2"></i>Seleccionar
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($membresia): ?>
            <!-- Formulario de registro -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <!-- Membresía seleccionada -->
                <div class="mb-8 p-6 bg-blue-50 border-2 border-blue-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-blue-900 mb-1">Membresía: <?php echo e($membresia['nombre']); ?></h3>
                            <p class="text-blue-700">Costo: <?php echo formatMoney($membresia['costo']); ?> - Vigencia: <?php echo $membresia['vigencia_meses']; ?> meses</p>
                        </div>
                        <a href="?" class="text-blue-600 hover:text-blue-800 underline">
                            <i class="fas fa-arrow-left mr-1"></i>Cambiar
                        </a>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-gray-800 mb-6">Datos de tu Empresa</h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="membresia_id" value="<?php echo $membresia['id']; ?>">
                    
                    <!-- Datos de la empresa -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-semibold mb-2">Razón Social *</label>
                            <input type="text" name="razon_social" required
                                   value="<?php echo e($_POST['razon_social'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Nombre legal de la empresa">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">RFC *</label>
                            <input type="text" name="rfc" required
                                   value="<?php echo e($_POST['rfc'] ?? ''); ?>"
                                   maxlength="13"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 uppercase"
                                   placeholder="ABC123456XYZ">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Giro / Sector</label>
                            <input type="text" name="giro"
                                   value="<?php echo e($_POST['giro'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Ej: Comercio, Servicios, Manufactura">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-semibold mb-2">Dirección</label>
                            <input type="text" name="direccion"
                                   value="<?php echo e($_POST['direccion'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Calle, número, colonia, ciudad">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Teléfono</label>
                            <input type="text" name="telefono"
                                   value="<?php echo e($_POST['telefono'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Teléfono de oficina">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">WhatsApp</label>
                            <input type="text" name="whatsapp"
                                   value="<?php echo e($_POST['whatsapp'] ?? ''); ?>"
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="10 dígitos">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-semibold mb-2">Sitio Web</label>
                            <input type="url" name="sitio_web"
                                   value="<?php echo e($_POST['sitio_web'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="https://www.ejemplo.com">
                        </div>
                    </div>

                    <!-- Datos del representante -->
                    <div class="border-t pt-6 mt-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Datos del Representante</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Nombre Completo *</label>
                                <input type="text" name="representante" required
                                       value="<?php echo e($_POST['representante'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="Nombre del representante legal">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Email *</label>
                                <input type="email" name="email" required
                                       value="<?php echo e($_POST['email'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="correo@empresa.com">
                                <p class="text-sm text-gray-500 mt-1">
                                    Se enviará una contraseña temporal a este correo
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <a href="?" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                            Continuar al Pago
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php elseif ($paso === '2' && isset($_SESSION['afiliacion_pendiente'])): ?>
            <!-- Paso 2: Pago -->
            <?php $afiliacion = $_SESSION['afiliacion_pendiente']; ?>
            
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                    <i class="fas fa-credit-card text-blue-600 mr-2"></i>
                    Completar Pago de Membresía
                </h2>

                <!-- Resumen -->
                <div class="mb-8 p-6 bg-gray-50 rounded-lg">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Resumen de Afiliación</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Empresa:</span>
                            <span class="font-semibold"><?php echo e($afiliacion['razon_social']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Representante:</span>
                            <span class="font-semibold"><?php echo e($afiliacion['nombre']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Membresía:</span>
                            <span class="font-semibold"><?php echo e($afiliacion['membresia_nombre']); ?></span>
                        </div>
                        <div class="flex justify-between border-t pt-2 mt-2">
                            <span class="text-lg font-bold text-gray-800">Total a Pagar:</span>
                            <span class="text-2xl font-bold text-blue-600">
                                <?php echo formatMoney($afiliacion['membresia_costo']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Botón de PayPal -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fab fa-paypal text-blue-600 mr-2"></i>
                        Pagar con PayPal
                    </h3>
                    <p class="text-gray-600 mb-4">
                        Complete su pago de forma segura con PayPal. Al finalizar el pago, 
                        su cuenta será activada automáticamente y recibirá sus credenciales de acceso por correo.
                    </p>
                    <div id="paypal-button-container" class="max-w-md mx-auto"></div>
                </div>

                <div class="text-center pt-6 border-t">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-lock mr-1"></i>
                        Pago seguro procesado por PayPal
                    </p>
                </div>
            </div>

            <!-- PayPal SDK -->
            <script src="https://www.paypal.com/sdk/js?client-id=<?php echo e($paypal_client_id); ?>&currency=MXN&locale=es_MX"></script>
            <script>
            paypal.Buttons({
                createOrder: async function(data, actions) {
                    try {
                        const response = await fetch('<?php echo BASE_URL; ?>/api/crear_orden_paypal_membresia.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                empresa_id: <?php echo $afiliacion['empresa_id']; ?>,
                                membresia_id: <?php echo $afiliacion['membresia_id']; ?>
                            })
                        });
                        
                        const orderData = await response.json();
                        
                        if (!orderData.success) {
                            throw new Error(orderData.error || 'Error al crear la orden');
                        }
                        
                        return orderData.order_id;
                        
                    } catch (err) {
                        console.error('Error en createOrder:', err);
                        alert('Error: ' + err.message);
                        throw err;
                    }
                },
                onApprove: function(data, actions) {
                    console.log('Pago aprobado:', data);
                    
                    document.getElementById('paypal-button-container').innerHTML = 
                        '<div class="text-center py-4">' +
                        '<i class="fas fa-spinner fa-spin text-4xl text-blue-600 mb-2"></i>' +
                        '<p class="text-lg font-semibold text-gray-700">Procesando pago...</p>' +
                        '</div>';
                    
                    setTimeout(function() {
                        window.location.href = '<?php echo BASE_URL; ?>/api/paypal_success_membresia.php?token=' + 
                            data.orderID + '&empresa_id=<?php echo $afiliacion['empresa_id']; ?>';
                    }, 1000);
                },
                onCancel: function(data) {
                    alert('Pago cancelado. Puedes intentar nuevamente en cualquier momento.');
                },
                onError: function(err) {
                    console.error('Error de PayPal:', err);
                    alert('Ocurrió un error al procesar el pago. Por favor intenta nuevamente.');
                },
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'pay',
                    height: 45
                }
            }).render('#paypal-button-container');
            </script>

            <?php else: ?>
            <!-- Error: sin datos en sesión -->
            <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-lg text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-3"></i>
                <p class="text-red-700 font-semibold mb-3">Sesión expirada</p>
                <a href="?" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Iniciar Nuevo Registro
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
