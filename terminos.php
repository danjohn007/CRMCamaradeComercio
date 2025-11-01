<?php
/**
 * Página de términos y condiciones
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$config = getConfiguracion();
$terminos = $config['terminos_condiciones'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8 px-4">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-file-contract mr-2 text-blue-600"></i>
                    Términos y Condiciones
                </h1>
                
                <?php if ($terminos): ?>
                    <div class="prose max-w-none text-gray-700">
                        <?php echo nl2br(htmlspecialchars($terminos)); ?>
                    </div>
                <?php else: ?>
                    <div class="prose max-w-none text-gray-700">
                        <h2>1. Aceptación de los Términos</h2>
                        <p>Al acceder y utilizar este sistema, usted acepta estar sujeto a estos términos y condiciones de uso.</p>
                        
                        <h2>2. Uso del Servicio</h2>
                        <p>El usuario se compromete a utilizar el servicio de manera responsable y de acuerdo con las leyes aplicables.</p>
                        
                        <h2>3. Registro de Eventos</h2>
                        <p>Al registrarse para un evento, acepta proporcionar información veraz y actualizada. La Cámara de Comercio se reserva el derecho de cancelar registros fraudulentos.</p>
                        
                        <h2>4. Boletos Digitales</h2>
                        <p>Los boletos digitales son personales e intransferibles. Debe presentar su código QR para acceder al evento.</p>
                        
                        <h2>5. Privacidad</h2>
                        <p>Nos comprometemos a proteger su información personal de acuerdo con nuestra Política de Privacidad.</p>
                        
                        <h2>6. Modificaciones</h2>
                        <p>La Cámara de Comercio se reserva el derecho de modificar estos términos en cualquier momento.</p>
                        
                        <h2>7. Contacto</h2>
                        <p>Para cualquier pregunta sobre estos términos, contáctenos a través de los canales oficiales.</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        Última actualización: <?php echo date('d/m/Y'); ?>
                    </p>
                    <div class="text-center mt-4">
                        <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
