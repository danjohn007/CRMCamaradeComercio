<?php
/**
 * Página pública de registro a eventos
 * Permite registro sin autenticación usando WhatsApp o RFC
 * Incluye captcha, términos y condiciones, y generación de boletos digitales con QR
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers/qrcode.php';
require_once __DIR__ . '/app/helpers/email.php';

$db = Database::getInstance()->getConnection();
$evento_id = $_GET['evento'] ?? null;
$error = '';
$success = '';
$empresa_data = null;
$search_performed = false;

// Generar captcha
if (!isset($_SESSION['captcha_evento_num1'])) {
    $_SESSION['captcha_evento_num1'] = rand(1, 10);
    $_SESSION['captcha_evento_num2'] = rand(1, 10);
}

// Obtener configuración
$config = getConfiguracion();
$max_boletos = intval($config['max_boletos_por_registro'] ?? 10);

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

// Buscar empresa por WhatsApp o RFC (incluye búsqueda en inscripciones)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buscar') {
    $search_performed = true;
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $rfc = sanitize($_POST['rfc'] ?? '');
    
    if (!empty($whatsapp) || !empty($rfc)) {
        $where_conditions = [];
        $params = [];
        
        // Buscar primero en empresas
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
        
        // Si no se encuentra en empresas, buscar en inscripciones previas
        if (!$empresa_data) {
            $where_inscripciones = [];
            $params_inscripciones = [];
            
            if (!empty($whatsapp)) {
                $where_inscripciones[] = "whatsapp_invitado = ?";
                $params_inscripciones[] = $whatsapp;
            }
            
            if (!empty($rfc)) {
                $where_inscripciones[] = "rfc_invitado = ?";
                $params_inscripciones[] = $rfc;
            }
            
            $where_insc_sql = implode(' OR ', $where_inscripciones);
            $stmt = $db->prepare("
                SELECT nombre_invitado, email_invitado, whatsapp_invitado, rfc_invitado, razon_social_invitado
                FROM eventos_inscripciones 
                WHERE ($where_insc_sql)
                ORDER BY fecha_inscripcion DESC 
                LIMIT 1
            ");
            $stmt->execute($params_inscripciones);
            $inscripcion_previa = $stmt->fetch();
            
            if ($inscripcion_previa) {
                // Usar datos de inscripción previa
                $empresa_data = [
                    'representante' => $inscripcion_previa['nombre_invitado'],
                    'email' => $inscripcion_previa['email_invitado'],
                    'whatsapp' => $inscripcion_previa['whatsapp_invitado'],
                    'rfc' => $inscripcion_previa['rfc_invitado'],
                    'razon_social' => $inscripcion_previa['razon_social_invitado'],
                    'id' => null // No es empresa afiliada
                ];
            }
        }
        
        if (!$empresa_data) {
            $error = 'No se encontró un registro previo con esos datos. Por favor complete el formulario manualmente.';
        }
    } else {
        $error = 'Debe ingresar al menos WhatsApp o RFC para buscar.';
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    try {
        $razon_social = sanitize($_POST['razon_social'] ?? '');
        $nombre = sanitize($_POST['nombre']);
        $email = sanitize($_POST['email']);
        $whatsapp = sanitize($_POST['whatsapp_registro']);
        $rfc = sanitize($_POST['rfc_registro'] ?? '');
        $boletos = intval($_POST['boletos_solicitados'] ?? 1);
        $es_invitado = isset($_POST['es_invitado']) ? 1 : 0;
        $empresa_id = !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null;
        $captcha_respuesta = intval($_POST['captcha_respuesta'] ?? 0);
        $terminos = isset($_POST['terminos']);
        
        // Validar captcha
        if ($captcha_respuesta != ($_SESSION['captcha_evento_num1'] + $_SESSION['captcha_evento_num2'])) {
            throw new Exception('La respuesta del captcha es incorrecta');
        }
        
        // Validar términos y condiciones
        if (!$terminos) {
            throw new Exception('Debe aceptar los términos y condiciones');
        }
        
        // Validar campos obligatorios
        if (empty($nombre) || empty($email) || empty($whatsapp)) {
            throw new Exception('Nombre, email y WhatsApp son obligatorios');
        }
        
        // Validar WhatsApp (10 dígitos)
        if (strlen($whatsapp) != 10 || !is_numeric($whatsapp)) {
            throw new Exception('El WhatsApp debe tener exactamente 10 dígitos');
        }
        
        // Validar RFC si NO es invitado
        if (!$es_invitado && empty($rfc)) {
            throw new Exception('El RFC es obligatorio para no invitados');
        }
        
        // Validar RFC si se proporciona
        if (!empty($rfc) && !validarRFC($rfc)) {
            throw new Exception('El RFC no tiene un formato válido');
        }
        
        // Validar email
        if (!validarEmail($email)) {
            throw new Exception('El email no tiene un formato válido');
        }
        
        // Validar número de boletos
        if ($boletos < 1 || $boletos > $max_boletos) {
            throw new Exception("El número de boletos debe estar entre 1 y {$max_boletos}");
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
        
        // Generar código QR único
        $codigo_qr = QRCodeGenerator::generateUniqueCode();
        
        // Determinar el precio efectivo del evento (preventa o regular)
        $precio_efectivo = $evento['costo'];
        $ahora = new DateTime();
        
        // Si hay precio de preventa y aún no pasó la fecha límite, usar precio de preventa
        if (isset($evento['precio_preventa']) && $evento['precio_preventa'] > 0 && 
            !empty($evento['fecha_limite_preventa'])) {
            $fecha_limite = new DateTime($evento['fecha_limite_preventa']);
            if ($ahora <= $fecha_limite) {
                $precio_efectivo = $evento['precio_preventa'];
            }
        }
        
        // Determinar si el evento requiere pago
        $requiere_pago = false;
        $es_boleto_gratis = false;
        $boletos_gratis = 0;
        $boletos_a_pagar = $boletos; // Por defecto, todos los boletos requieren pago
        
        if ($precio_efectivo > 0) {
            // Si la empresa está activa (afiliada), solo 1 boleto es gratis
            if ($empresa_id) {
                $stmt = $db->prepare("SELECT activo FROM empresas WHERE id = ? AND activo = 1");
                $stmt->execute([$empresa_id]);
                $empresa_activa = $stmt->fetch();
                if ($empresa_activa !== false) {
                    $boletos_gratis = 1; // Solo 1 boleto gratis para empresas activas
                    $es_boleto_gratis = true;
                }
            }
            
            // Calcular boletos que requieren pago
            $boletos_a_pagar = max(0, $boletos - $boletos_gratis);
            $requiere_pago = ($boletos_a_pagar > 0);
        } else {
            // Si el evento es gratuito, no se requiere pago
            $boletos_a_pagar = 0;
        }
        
        // Calcular monto a pagar (solo los boletos adicionales si la empresa está activa)
        $monto_total = $requiere_pago ? ($precio_efectivo * $boletos_a_pagar) : 0;
        
        // Determinar estado inicial de pago
        $estado_pago_inicial = $requiere_pago ? 'PENDIENTE' : 'SIN_PAGO';
        
        // Registrar inscripción
        $stmt = $db->prepare("
            INSERT INTO eventos_inscripciones 
            (evento_id, usuario_id, empresa_id, nombre_invitado, razon_social_invitado, email_invitado, 
             whatsapp_invitado, rfc_invitado, boletos_solicitados, es_invitado, codigo_qr, estado, estado_pago, monto_pagado) 
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CONFIRMADO', ?, ?)
        ");
        
        $stmt->execute([
            $evento_id, $empresa_id, $nombre, $razon_social, $email, 
            $whatsapp, $rfc, $boletos, $es_invitado, $codigo_qr, $estado_pago_inicial, $monto_total
        ]);
        
        $inscripcion_id = $db->lastInsertId();
        
        // Actualizar contador de inscritos
        $stmt = $db->prepare("UPDATE eventos SET inscritos = inscritos + ? WHERE id = ?");
        $stmt->execute([$boletos, $evento_id]);
        
        // Obtener datos completos de la inscripción
        $stmt = $db->prepare("SELECT * FROM eventos_inscripciones WHERE id = ?");
        $stmt->execute([$inscripcion_id]);
        $inscripcion = $stmt->fetch();
        
        // Generar y guardar imagen QR
        $qrCodePath = QRCodeGenerator::saveQRImage(
            BASE_URL . '/boleto_digital.php?codigo=' . $codigo_qr,
            $codigo_qr
        );
        
        // Enviar email de confirmación según el estado del pago
        try {
            if (!$requiere_pago) {
                // Evento gratuito o empresa afiliada con 1 boleto - enviar confirmación con boleto
                EmailHelper::sendEventRegistrationConfirmation($inscripcion, $evento, false, 0, $qrCodePath);
                
                // Actualizar que el boleto fue enviado
                $stmt = $db->prepare("UPDATE eventos_inscripciones SET boleto_enviado = 1, fecha_envio_boleto = NOW() WHERE id = ?");
                $stmt->execute([$inscripcion_id]);
            } else {
                // Requiere pago - enviar confirmación con link de pago
                // Si es empresa afiliada y tiene boleto gratis, enviar el primer boleto también
                $qr_path_for_email = ($es_boleto_gratis && $boletos_gratis > 0) ? $qrCodePath : null;
                EmailHelper::sendEventRegistrationConfirmation($inscripcion, $evento, true, $monto_total, $qr_path_for_email);
            }
        } catch (Exception $e) {
            // Log error but don't fail registration
            error_log("Error sending confirmation email: " . $e->getMessage());
        }
        
        // Regenerar captcha
        $_SESSION['captcha_evento_num1'] = rand(1, 10);
        $_SESSION['captcha_evento_num2'] = rand(1, 10);
        
        // Mensaje de éxito según si requiere pago o no
        if ($requiere_pago) {
            if ($es_boleto_gratis && $boletos_gratis > 0) {
                $success = "¡Registro exitoso! Como empresa afiliada, tu primer boleto es gratuito y se ha enviado a tu correo. Para obtener " . ($boletos - $boletos_gratis) . " boleto(s) adicional(es), por favor realiza el pago de <strong>\$" . number_format($monto_total, 2) . " MXN</strong> usando el botón de PayPal a continuación.";
            } else {
                $success = "¡Registro exitoso! Se ha enviado un correo de confirmación. Para completar tu inscripción y obtener tus {$boletos} boleto(s), por favor realiza el pago de <strong>\$" . number_format($monto_total, 2) . " MXN</strong> usando el botón de PayPal a continuación.";
            }
            
            // Guardar inscripcion_id para mostrar botón de pago
            $_SESSION['pending_payment_inscripcion_id'] = $inscripcion_id;
            $_SESSION['pending_payment_monto'] = $monto_total;
            $_SESSION['pending_payment_codigo_qr'] = $codigo_qr;
        } else {
            if ($es_boleto_gratis) {
                if ($boletos > 1) {
                    $success = "¡Registro exitoso! Como empresa afiliada, tu primer boleto es gratuito. Se han confirmado {$boletos} boleto(s) y se ha enviado un correo de confirmación con tu boleto digital (puede llegar a spam).";
                } else {
                    $success = "¡Registro exitoso! Como empresa afiliada, tu boleto es gratuito. Se ha enviado un correo de confirmación con tu boleto digital (puede llegar a spam).";
                }
            } else {
                $success = "¡Registro exitoso! Este evento es gratuito. Se han confirmado {$boletos} boleto(s) y recibirás un correo de confirmación con tu boleto digital (puede llegar a spam).";
            }
            
            // Agregar enlace para imprimir boleto
            $success .= " <a href='" . BASE_URL . "/boleto_digital.php?codigo={$codigo_qr}' target='_blank' class='underline font-bold'>Imprimir Boleto Ahora</a>";
        }
        
        // Limpiar formulario
        $empresa_data = null;
        $search_performed = false;
        
    } catch (Exception $e) {
        $error = 'Error al registrar: ' . $e->getMessage();
        // Regenerar captcha en caso de error
        $_SESSION['captcha_evento_num1'] = rand(1, 10);
        $_SESSION['captcha_evento_num2'] = rand(1, 10);
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
                <div class="relative">
                    <img src="<?php echo BASE_URL . '/public/uploads/' . e($evento['imagen']); ?>" 
                         alt="<?php echo e($evento['titulo']); ?>" 
                         class="w-full h-auto object-contain max-h-96">
                    <!-- Iconos de guardar y compartir -->
                    <div class="absolute top-4 right-4 flex gap-2">
                        <button onclick="saveImage()" 
                                class="bg-white/90 hover:bg-white p-3 rounded-full shadow-lg transition-all duration-200 hover:scale-110"
                                title="Guardar imagen">
                            <i class="fas fa-download text-gray-700"></i>
                        </button>
                        <button onclick="shareEvent()" 
                                class="bg-white/90 hover:bg-white p-3 rounded-full shadow-lg transition-all duration-200 hover:scale-110"
                                title="Compartir evento">
                            <i class="fas fa-share-alt text-gray-700"></i>
                        </button>
                    </div>
                </div>
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
                            <?php
                            // Determinar el precio efectivo (preventa o regular)
                            $precio_display = $evento['costo'];
                            $es_preventa = false;
                            $ahora_display = new DateTime();
                            
                            if (isset($evento['precio_preventa']) && $evento['precio_preventa'] > 0 && 
                                !empty($evento['fecha_limite_preventa'])) {
                                $fecha_limite_display = new DateTime($evento['fecha_limite_preventa']);
                                if ($ahora_display <= $fecha_limite_display) {
                                    $precio_display = $evento['precio_preventa'];
                                    $es_preventa = true;
                                }
                            }
                            ?>
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-<?php echo $es_preventa ? 'tag' : 'dollar-sign'; ?> mr-3 text-green-600"></i>
                                <div>
                                    <span class="font-semibold"><?php echo $es_preventa ? '¡PREVENTA! ' : ''; ?>$<?php echo number_format($precio_display, 2); ?> MXN</span>
                                    <?php if ($es_preventa): ?>
                                        <br><span class="text-sm text-gray-500 line-through">$<?php echo number_format($evento['costo'], 2); ?> MXN</span>
                                    <?php endif; ?>
                                </div>
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
                    <div class="flex items-center mb-3">
                        <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                        <div class="text-green-700 font-semibold"><?php echo $success; /* Contains safe HTML link */ ?></div>
                    </div>
                    
                    <?php if (isset($_SESSION['pending_payment_inscripcion_id'])): ?>
                        <!-- PayPal Payment Button -->
                        <div class="mt-4 p-4 bg-white rounded-lg border-2 border-blue-300">
                            <h3 class="text-lg font-bold text-gray-800 mb-3">
                                <i class="fab fa-paypal text-blue-600 mr-2"></i>
                                Pagar con PayPal
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Haz clic en el botón de PayPal para completar tu pago de forma segura. 
                                Una vez completado el pago, recibirás tu boleto digital por correo electrónico.
                            </p>
                            <div id="paypal-button-container"></div>
                            <p class="text-xs text-gray-500 mt-3">
                                <i class="fas fa-lock mr-1"></i>
                                Pago seguro procesado por PayPal
                            </p>
                        </div>
                        
                        <script src="https://www.paypal.com/sdk/js?client-id=<?php 
                            require_once __DIR__ . '/app/helpers/paypal.php';
                            echo PayPalHelper::getClientId();
                        ?>&currency=MXN&locale=es_MX"></script>
                        
                        <script>
                        paypal.Buttons({
                            createOrder: function(data, actions) {
                                // Mostrar loading
                                document.getElementById('paypal-button-container').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="text-sm text-gray-600 mt-2">Procesando...</p></div>';
                                
                                // Crear orden en el servidor
                                return fetch('<?php echo BASE_URL; ?>/api/crear_orden_paypal_evento.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        inscripcion_id: <?php echo $_SESSION['pending_payment_inscripcion_id']; ?>
                                    })
                                })
                                .then(function(res) {
                                    return res.json();
                                })
                                .then(function(orderData) {
                                    if (orderData.success) {
                                        return orderData.order_id;
                                    } else {
                                        throw new Error(orderData.error || 'Error al crear la orden');
                                    }
                                })
                                .catch(function(err) {
                                    alert('Error: ' + err.message);
                                    location.reload();
                                });
                            },
                            onApprove: function(data, actions) {
                                // Redirigir a la página de éxito
                                window.location.href = '<?php echo BASE_URL; ?>/api/paypal_success_evento.php?token=' + data.orderID + '&inscripcion_id=<?php echo $_SESSION['pending_payment_inscripcion_id']; ?>';
                            },
                            onCancel: function(data) {
                                alert('Pago cancelado. Puedes intentar nuevamente en cualquier momento.');
                                location.reload();
                            },
                            onError: function(err) {
                                console.error('Error de PayPal:', err);
                                alert('Ocurrió un error al procesar el pago. Por favor intenta nuevamente.');
                                location.reload();
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
                        
                        <?php 
                        // Limpiar la sesión después de mostrar el botón
                        // No lo hacemos aquí para que el botón siga disponible si recargan la página
                        ?>
                    <?php endif; ?>
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
                            <label class="block text-gray-700 font-semibold mb-2">WhatsApp (10 dígitos)</label>
                            <input type="text" name="whatsapp" 
                                   placeholder="Ej: 4421234567"
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">RFC</label>
                            <input type="text" name="rfc" 
                                   placeholder="Ej: ABC123456XYZ"
                                   maxlength="13"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 uppercase">
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                        <i class="fas fa-search mr-2"></i>Buscar Empresa o Registro Previo
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
                
                <form method="POST" class="space-y-6" id="registroForm">
                    <input type="hidden" name="action" value="registrar">
                    <input type="hidden" name="empresa_id" value="<?php echo $empresa_data['id'] ?? ''; ?>">
                    
                    <!-- Empresa/Razón Social -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Empresa / Razón Social *</label>
                        <input type="text" name="razon_social" required
                               value="<?php echo e($empresa_data['razon_social'] ?? ''); ?>"
                               placeholder="Nombre de la empresa o razón social"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
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
                    
                    <!-- WhatsApp obligatorio, 10 dígitos -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">WhatsApp * (10 dígitos)</label>
                        <input type="text" name="whatsapp_registro" required
                               value="<?php echo e($empresa_data['whatsapp'] ?? ''); ?>"
                               placeholder="4421234567"
                               maxlength="10"
                               pattern="[0-9]{10}"
                               title="Ingrese exactamente 10 dígitos"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Solo números, sin espacios ni guiones</p>
                    </div>
                    
                    <!-- Checkbox de invitado -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="es_invitado" id="es_invitado" 
                                   class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-3 text-gray-700 font-semibold">
                                Asisto como invitado (no tengo RFC empresarial)
                            </span>
                        </label>
                        <p class="text-sm text-gray-600 mt-2 ml-8">
                            Si eres invitado personal, marca esta casilla. El RFC no será obligatorio.
                        </p>
                    </div>
                    
                    <!-- RFC condicional -->
                    <div id="rfc_container">
                        <label class="block text-gray-700 font-semibold mb-2">
                            RFC <span id="rfc_required">*</span>
                        </label>
                        <input type="text" name="rfc_registro" id="rfc_registro"
                               value="<?php echo e($empresa_data['rfc'] ?? ''); ?>"
                               placeholder="ABC123456XYZ"
                               maxlength="13"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 uppercase">
                        <p class="text-sm text-gray-500 mt-1" id="rfc_help">Obligatorio para empresas</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            Número de Boletos Solicitados
                            <?php if ($empresa_data): ?>
                            <span class="text-sm font-normal text-gray-500">(para colaboradores)</span>
                            <?php endif; ?>
                        </label>
                        <input type="number" name="boletos_solicitados" min="1" max="<?php echo $max_boletos; ?>" value="1"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Máximo <?php echo $max_boletos; ?> boletos por registro</p>
                    </div>
                    
                    <!-- Captcha -->
                    <div class="bg-gray-50 border border-gray-300 rounded-lg p-4">
                        <label class="block text-gray-700 font-semibold mb-2">Verificación Anti-Spam *</label>
                        <p class="text-gray-600 mb-3">
                            Por favor resuelve: 
                            <span class="font-bold text-xl text-blue-600">
                                <?php echo $_SESSION['captcha_evento_num1']; ?> + <?php echo $_SESSION['captcha_evento_num2']; ?> = ?
                            </span>
                        </p>
                        <input type="number" name="captcha_respuesta" required
                               placeholder="Ingrese el resultado"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Términos y condiciones -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <label class="flex items-start cursor-pointer">
                            <input type="checkbox" name="terminos" required 
                                   class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mt-1">
                            <span class="ml-3 text-gray-700">
                                Acepto los 
                                <a href="<?php echo BASE_URL; ?>/terminos.php" target="_blank" class="text-blue-600 hover:underline font-semibold">
                                    Términos y Condiciones
                                </a> 
                                y la 
                                <a href="<?php echo BASE_URL; ?>/privacidad.php" target="_blank" class="text-blue-600 hover:underline font-semibold">
                                    Política de Privacidad
                                </a> *
                            </span>
                        </label>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-green-600 text-white py-4 rounded-lg hover:bg-green-700 font-bold text-lg transition">
                        <i class="fas fa-check mr-2"></i>Confirmar Registro
                    </button>
                </form>
                
                <!-- Script para hacer RFC condicional -->
                <script>
                    document.getElementById('es_invitado').addEventListener('change', function() {
                        const rfcInput = document.getElementById('rfc_registro');
                        const rfcRequired = document.getElementById('rfc_required');
                        const rfcHelp = document.getElementById('rfc_help');
                        
                        if (this.checked) {
                            rfcInput.removeAttribute('required');
                            rfcRequired.style.display = 'none';
                            rfcHelp.textContent = 'Opcional para invitados';
                            rfcInput.parentElement.classList.add('opacity-75');
                        } else {
                            rfcInput.setAttribute('required', 'required');
                            rfcRequired.style.display = 'inline';
                            rfcHelp.textContent = 'Obligatorio para empresas';
                            rfcInput.parentElement.classList.remove('opacity-75');
                        }
                    });
                </script>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="mt-8 text-center text-gray-600">
                <p>¿Tiene problemas? Contáctenos al correo: <?php echo htmlspecialchars($config['email_sistema'] ?? 'info@camaraqro.com'); ?></p>
            </div>
        </div>
    </div>
    
    <script>
    // Función para guardar la imagen del evento
    function saveImage() {
        const imgElement = document.querySelector('img[alt="<?php echo addslashes(e($evento['titulo'])); ?>"]');
        if (imgElement) {
            const link = document.createElement('a');
            link.href = imgElement.src;
            link.download = '<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $evento['titulo']); ?>.jpg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    // Función para compartir el evento
    function shareEvent() {
        const eventUrl = window.location.href;
        const eventTitle = '<?php echo addslashes(e($evento['titulo'])); ?>';
        const eventText = 'Te invito a este evento: ' + eventTitle;
        
        // Intentar usar la Web Share API si está disponible
        if (navigator.share) {
            navigator.share({
                title: eventTitle,
                text: eventText,
                url: eventUrl
            }).catch((error) => console.log('Error sharing:', error));
        } else {
            // Fallback: copiar al portapapeles
            const textarea = document.createElement('textarea');
            textarea.value = eventText + '\n' + eventUrl;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('¡Enlace copiado al portapapeles! Puedes compartirlo por WhatsApp, Email, etc.');
            } catch (err) {
                alert('No se pudo copiar automáticamente. URL del evento: ' + eventUrl);
            }
            document.body.removeChild(textarea);
        }
    }
    </script>
</body>
</html>
