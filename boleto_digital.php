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

// Obtener configuración del sistema
$config = [];
try {
    $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('logo_sistema', 'nombre_sitio', 'color_primario', 'color_secundario', 'email_sistema', 'telefono_contacto', 'politica_privacidad')");
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {
    // Valores por defecto
}

$logo_sistema = $config['logo_sistema'] ?? '';
$nombre_sitio = $config['nombre_sitio'] ?? APP_NAME;
$color_primario = $config['color_primario'] ?? '#1E40AF';
$color_secundario = $config['color_secundario'] ?? '#10B981';
$email_contacto = $config['email_sistema'] ?? 'info@camaraqro.com';
$telefono_contacto = $config['telefono_contacto'] ?? '';
$tiene_politica = !empty($config['politica_privacidad']);

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
    <title>Boleto Digital - <?php echo e($nombre_sitio); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primario: <?php echo $color_primario; ?>;
            --color-secundario: <?php echo $color_secundario; ?>;
        }
        @page {
            size: A4;
            margin: 10mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white; 
                margin: 0;
                padding: 0;
            }
            .ticket-container { 
                box-shadow: none !important;
                border: 3px solid var(--color-primario);
                page-break-inside: avoid;
                max-width: 100%;
                margin: 0 auto;
                padding: 20px !important;
            }
            .qr-code-container img { 
                width: 180px !important; 
                height: 180px !important;
                display: block !important;
                margin: 0 auto !important;
            }
            .logo-container img {
                max-height: 50px !important;
            }
        }
        .bg-primary {
            background-color: var(--color-primario);
        }
        .text-primary {
            color: var(--color-primario);
        }
        .border-primary {
            border-color: var(--color-primario);
        }
        .bg-secondary {
            background-color: var(--color-secundario);
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
            <a href="<?php echo BASE_URL; ?>" class="mt-6 inline-block bg-primary text-white px-6 py-3 rounded-lg hover:opacity-90">
                Volver al inicio
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="min-h-screen py-4 px-4">
        <div class="max-w-2xl mx-auto">
            <!-- Botones de acción -->
            <div class="no-print mb-4 flex justify-between items-center">
                <a href="<?php echo BASE_URL; ?>" class="text-primary hover:opacity-80">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
                <button onclick="window.print()" class="bg-primary text-white px-6 py-2 rounded-lg hover:opacity-90">
                    <i class="fas fa-print mr-2"></i>Imprimir Boleto
                </button>
            </div>
            
            <!-- Boleto Compacto -->
            <div class="ticket-container bg-white rounded-lg shadow-xl border-4 border-primary" style="border-color: var(--color-primario); padding: 30px;">
                <!-- Header con Logo -->
                <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-primary" style="border-color: var(--color-primario);">
                    <div class="logo-container">
                        <?php if (!empty($logo_sistema)): ?>
                        <img src="<?php echo BASE_URL . e($logo_sistema); ?>" alt="Logo" class="h-16 object-contain">
                        <?php else: ?>
                        <h1 class="text-2xl font-bold text-primary"><?php echo e($nombre_sitio); ?></h1>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-primary">BOLETO DE ACCESO</p>
                        <p class="text-xs text-gray-500">Personal e Intransferible</p>
                    </div>
                </div>
                
                <!-- Información del Evento (Compacto) -->
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-gray-800 mb-3 text-center bg-gray-50 p-2 rounded" style="background-color: rgba(30, 64, 175, 0.1);">
                        <?php echo e($evento['titulo']); ?>
                    </h2>
                    
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-calendar text-primary mr-2"></i>
                            <span><?php echo date('d/m/Y', strtotime($evento['fecha_inicio'])); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-primary mr-2"></i>
                            <span><?php echo date('H:i', strtotime($evento['fecha_inicio'])); ?> - <?php echo date('H:i', strtotime($evento['fecha_fin'])); ?></span>
                        </div>
                        <?php if ($evento['ubicacion']): ?>
                        <div class="col-span-2 flex items-start">
                            <i class="fas fa-map-marker-alt text-primary mr-2 mt-1"></i>
                            <span><?php echo e($evento['ubicacion']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Grid: Info Asistente + QR -->
                <div class="grid grid-cols-2 gap-6 mb-4 border-t border-b border-gray-200 py-4">
                    <!-- Información del Asistente -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-800 mb-2 text-primary">ASISTENTE</h3>
                        <div class="space-y-1 text-sm">
                            <div>
                                <span class="font-semibold text-gray-600">Nombre:</span>
                                <p class="text-gray-800"><?php echo e($inscripcion['nombre_invitado']); ?></p>
                            </div>
                            <?php if ($inscripcion['razon_social_invitado']): ?>
                            <div>
                                <span class="font-semibold text-gray-600">Empresa:</span>
                                <p class="text-gray-800"><?php echo e($inscripcion['razon_social_invitado']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="font-semibold text-gray-600">Boletos:</span>
                                <span class="text-gray-800 font-bold text-lg"><?php echo $inscripcion['boletos_solicitados']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Código QR Compacto -->
                    <div class="text-center">
                        <p class="text-xs text-gray-600 font-semibold mb-1">CÓDIGO QR</p>
                        <?php if ($inscripcion['codigo_qr']): ?>
                        <div class="qr-code-container inline-block p-2 bg-white border-2 border-primary rounded" style="border-color: var(--color-primario);">
                            <img src="<?php echo QRCodeGenerator::generateQRImageURL(BASE_URL . '/boleto_digital.php?codigo=' . $inscripcion['codigo_qr'], 300); ?>" 
                                 alt="Código QR" 
                                 crossorigin="anonymous"
                                 class="mx-auto"
                                 style="width: 180px; height: 180px; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
                        </div>
                        <?php endif; ?>
                        <p class="text-xs font-mono text-primary mt-1"><?php echo e($inscripcion['codigo_qr']); ?></p>
                    </div>
                </div>
                
                <!-- Footer Compacto con Contacto -->
                <div class="text-center text-xs text-gray-600">
                    <p class="mb-1">
                        <i class="fas fa-envelope mr-1"></i><?php echo e($email_contacto); ?>
                        <?php if ($telefono_contacto): ?>
                        <span class="mx-2">|</span>
                        <i class="fas fa-phone mr-1"></i><?php echo e($telefono_contacto); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($tiene_politica): ?>
                    <p>
                        <a href="<?php echo BASE_URL; ?>/privacidad.php" class="text-primary hover:underline">
                            <i class="fas fa-shield-alt mr-1"></i>Política de Privacidad
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Información adicional (no se imprime) -->
            <div class="no-print mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-bold text-blue-800 mb-2 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>Instrucciones
                </h3>
                <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside">
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
