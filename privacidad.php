<?php
/**
 * Página de política de privacidad
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$config = getConfiguracion();
$privacidad = $config['politica_privacidad'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8 px-4">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                    Política de Privacidad
                </h1>
                
                <?php if ($privacidad): ?>
                    <div class="prose max-w-none text-gray-700">
                        <?php echo nl2br(htmlspecialchars($privacidad)); ?>
                    </div>
                <?php else: ?>
                    <div class="prose max-w-none text-gray-700">
                        <h2>1. Información que Recopilamos</h2>
                        <p>Recopilamos información personal como nombre, correo electrónico, número de WhatsApp, RFC y datos de la empresa cuando se registra en nuestro sistema o en eventos.</p>
                        
                        <h2>2. Uso de la Información</h2>
                        <p>Utilizamos su información para:</p>
                        <ul>
                            <li>Gestionar su registro en eventos</li>
                            <li>Enviar boletos digitales y confirmaciones</li>
                            <li>Comunicar información relevante de la Cámara de Comercio</li>
                            <li>Mejorar nuestros servicios</li>
                        </ul>
                        
                        <h2>3. Protección de Datos</h2>
                        <p>Implementamos medidas de seguridad para proteger su información personal contra acceso no autorizado, alteración o divulgación.</p>
                        
                        <h2>4. Compartir Información</h2>
                        <p>No vendemos ni compartimos su información personal con terceros, excepto cuando sea necesario para operar nuestros servicios o cuando la ley lo requiera.</p>
                        
                        <h2>5. Cookies</h2>
                        <p>Utilizamos cookies para mejorar su experiencia en nuestro sitio web y mantener su sesión activa.</p>
                        
                        <h2>6. Sus Derechos</h2>
                        <p>Tiene derecho a:</p>
                        <ul>
                            <li>Acceder a su información personal</li>
                            <li>Corregir datos inexactos</li>
                            <li>Solicitar la eliminación de sus datos</li>
                            <li>Oponerse al procesamiento de su información</li>
                        </ul>
                        
                        <h2>7. Cambios a esta Política</h2>
                        <p>Podemos actualizar esta política de privacidad ocasionalmente. Le notificaremos sobre cambios significativos.</p>
                        
                        <h2>8. Contacto</h2>
                        <p>Si tiene preguntas sobre nuestra política de privacidad, contáctenos a: <?php echo e($config['email_sistema'] ?? 'info@camaraqro.com'); ?></p>
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
