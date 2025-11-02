<?php
/**
 * Página para visualizar e imprimir boleto digital
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers/qrcode.php';

$db = Database::getInstance()->getConnection();
$codigo = $_GET['codigo'] ?? '';
$error = '';
$inscripcion = null;
$evento = null;

if (empty($codigo)) {
    $error = 'Código de boleto no válido';
} else {
    // Buscar inscripción
    $stmt = $db->prepare("
        SELECT ei.*, e.* 
        FROM eventos_inscripciones ei
        JOIN eventos e ON ei.evento_id = e.id
        WHERE ei.codigo_qr = ?
    ");
    $stmt->execute([$codigo]);
    $data = $stmt->fetch();
    
    if (!$data) {
        $error = 'Boleto no encontrado';
    } else {
        $inscripcion = $data;
        $evento = $data;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleto Digital - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .ticket-container { box-shadow: none; border: 2px solid #1E40AF; page-break-inside: avoid; }
            .qr-code-container img { 
                width: 200px !important; 
                height: 200px !important;
                display: block !important;
                margin: 0 auto !important;
            }
        }
        .qr-code-container img {
            max-width: 200px;
            max-height: 200px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php if ($error): ?>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full text-center">
            <i class="fas fa-exclamation-triangle text-red-500 text-6xl mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Error</h1>
            <p class="text-gray-600"><?php echo e($error); ?></p>
            <a href="<?php echo BASE_URL; ?>" class="mt-6 inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                Volver al inicio
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="min-h-screen py-8 px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Botones de acción -->
            <div class="no-print mb-6 flex justify-between items-center">
                <a href="<?php echo BASE_URL; ?>" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
                <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-print mr-2"></i>Imprimir Boleto
                </button>
            </div>
            
            <!-- Boleto -->
            <div class="ticket-container bg-white rounded-lg shadow-xl p-8 border-4 border-blue-600">
                <!-- Encabezado -->
                <div class="text-center mb-8 border-b-2 border-blue-600 pb-6">
                    <h1 class="text-3xl font-bold text-blue-600 mb-2"><?php echo APP_NAME; ?></h1>
                    <p class="text-xl font-semibold text-gray-800">Boleto de Acceso</p>
                </div>
                
                <!-- Información del evento -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center bg-blue-50 p-3 rounded">
                        <?php echo e($evento['titulo']); ?>
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                        <div class="bg-gray-50 p-4 rounded">
                            <p class="text-sm text-gray-600 font-semibold">FECHA</p>
                            <p class="text-lg text-gray-800">
                                <i class="fas fa-calendar mr-2 text-blue-600"></i>
                                <?php echo date('d/m/Y', strtotime($evento['fecha_inicio'])); ?>
                            </p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded">
                            <p class="text-sm text-gray-600 font-semibold">HORA</p>
                            <p class="text-lg text-gray-800">
                                <i class="fas fa-clock mr-2 text-blue-600"></i>
                                <?php echo date('H:i', strtotime($evento['fecha_inicio'])); ?> - <?php echo date('H:i', strtotime($evento['fecha_fin'])); ?>
                            </p>
                        </div>
                        <?php if ($evento['ubicacion']): ?>
                        <div class="bg-gray-50 p-4 rounded md:col-span-2">
                            <p class="text-sm text-gray-600 font-semibold">UBICACIÓN</p>
                            <p class="text-lg text-gray-800">
                                <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                                <?php echo e($evento['ubicacion']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Información del asistente -->
                <div class="mb-8 border-t-2 border-gray-200 pt-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Información del Asistente</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-700">Nombre:</span>
                            <span class="text-gray-800"><?php echo e($inscripcion['nombre_invitado']); ?></span>
                        </div>
                        <?php if ($inscripcion['razon_social_invitado']): ?>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-700">Empresa:</span>
                            <span class="text-gray-800"><?php echo e($inscripcion['razon_social_invitado']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-700">Email:</span>
                            <span class="text-gray-800"><?php echo e($inscripcion['email_invitado']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-700">Boletos:</span>
                            <span class="text-gray-800 font-bold"><?php echo $inscripcion['boletos_solicitados']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Código QR -->
                <div class="text-center border-t-2 border-gray-200 pt-6">
                    <p class="text-sm text-gray-600 font-semibold mb-2">CÓDIGO DE VERIFICACIÓN</p>
                    <p class="text-2xl font-bold text-blue-600 mb-4 tracking-wider"><?php echo e($inscripcion['codigo_qr']); ?></p>
                    
                    <?php if ($inscripcion['codigo_qr']): ?>
                    <div class="qr-code-container inline-block p-4 bg-white border-4 border-blue-600 rounded">
                        <img src="<?php echo QRCodeGenerator::generateQRImageURL(BASE_URL . '/boleto_digital.php?codigo=' . $inscripcion['codigo_qr'], 400); ?>" 
                             alt="Código QR" 
                             crossorigin="anonymous"
                             class="w-48 h-48 mx-auto"
                             style="image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-xs text-gray-600 mt-4">Presenta este código al ingresar al evento</p>
                </div>
                
                <!-- Footer -->
                <div class="mt-8 pt-6 border-t-2 border-gray-200 text-center text-sm text-gray-600">
                    <p>Este boleto es personal e intransferible</p>
                    <p class="mt-2">Para más información contacta: <?php echo htmlspecialchars(getConfiguracion('email_sistema') ?? 'info@camaraqro.com'); ?></p>
                </div>
            </div>
            
            <!-- Información adicional (no se imprime) -->
            <div class="no-print mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="font-bold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Instrucciones
                </h3>
                <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                    <li>Imprime este boleto o guárdalo en tu dispositivo móvil</li>
                    <li>Llega con 15 minutos de anticipación</li>
                    <li>Presenta tu código QR en la entrada del evento</li>
                    <li>Si tienes problemas, contacta al organizador</li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
