<?php
/**
 * Helper para envío de emails con soporte de HTML y adjuntos
 */

class EmailHelper {
    
    /**
     * Enviar email con formato HTML
     * @param string $to - Destinatario
     * @param string $subject - Asunto
     * @param string $htmlBody - Cuerpo del mensaje en HTML
     * @param array $attachments - Archivos adjuntos [['path' => '', 'name' => '']]
     * @return bool
     */
    public static function sendHTML($to, $subject, $htmlBody, $attachments = []) {
        $config = getConfiguracion();
        $from = $config['email_sistema'] ?? 'noreply@camaraqro.com';
        $fromName = $config['smtp_from_name'] ?? APP_NAME;
        
        // Separador único para MIME
        $separator = md5(time());
        
        // Headers
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if (empty($attachments)) {
            // Email simple HTML
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message = $htmlBody;
        } else {
            // Email con adjuntos
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$separator}\"\r\n";
            
            $message = "--{$separator}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            
            // Agregar adjuntos
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $filename = $attachment['name'] ?? basename($attachment['path']);
                    $content = file_get_contents($attachment['path']);
                    $content = chunk_split(base64_encode($content));
                    
                    $message .= "--{$separator}\r\n";
                    $message .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
                    $message .= $content . "\r\n\r\n";
                }
            }
            
            $message .= "--{$separator}--";
        }
        
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Plantilla de email para boleto de evento
     */
    public static function sendEventTicket($inscripcion, $evento, $qrCodePath) {
        $config = getConfiguracion();
        $nombre_sitio = $config['nombre_sitio'] ?? APP_NAME;
        $nombre = $inscripcion['nombre_invitado'] ?? 'Asistente';
        $email = $inscripcion['email_invitado'];
        $razon_social = $inscripcion['razon_social_invitado'] ?? '';
        $boletos = $inscripcion['boletos_solicitados'] ?? 1;
        $codigo_qr = $inscripcion['codigo_qr'];
        $es_empresa_afiliada = !empty($inscripcion['empresa_id']);
        
        // Obtener colores del sistema
        $color_primario = $config['color_primario'] ?? '#1E40AF';
        $color_secundario = $config['color_secundario'] ?? '#10B981';
        $color_acento = $config['color_acento1'] ?? '#F59E0B';
        $logo_url = !empty($config['logo_sistema']) ? BASE_URL . $config['logo_sistema'] : '';
        
        $subject = "Confirmación de Boleto - " . $evento['titulo'];
        
        // Construir HTML del email
        $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$color_primario}; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .logo { max-width: 150px; height: auto; margin-bottom: 10px; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .ticket { background: white; padding: 20px; margin: 20px 0; border: 2px dashed {$color_primario}; border-radius: 5px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .qr-code img { max-width: 250px; border: 2px solid {$color_primario}; padding: 10px; background: white; }
        .info-row { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 3px; }
        .info-label { font-weight: bold; color: {$color_primario}; }
        .button { display: inline-block; padding: 12px 30px; background: {$color_secundario}; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; }
        .invitation-box { background: #fef3c7; border: 2px solid {$color_acento}; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>" . 
            ($logo_url ? "<img src='{$logo_url}' alt='Logo' class='logo'>" : "") . "
            <h1>¡Boleto Confirmado!</h1>
            <p>" . htmlspecialchars($nombre_sitio) . "</p>
        </div>
        
        <div class='content'>
            <h2>Hola {$nombre},</h2>
            <p>Tu registro para el evento <strong>" . htmlspecialchars($evento['titulo']) . "</strong> ha sido confirmado exitosamente.</p>
            
            <div class='ticket'>
                <h3 style='color: #1E40AF; text-align: center;'>Boleto Digital</h3>
                
                <div class='info-row'>
                    <span class='info-label'>Evento:</span> " . htmlspecialchars($evento['titulo']) . "
                </div>
                <div class='info-row'>
                    <span class='info-label'>Fecha:</span> " . date('d/m/Y', strtotime($evento['fecha_inicio'])) . "
                </div>
                <div class='info-row'>
                    <span class='info-label'>Hora:</span> " . date('H:i', strtotime($evento['fecha_inicio'])) . " - " . date('H:i', strtotime($evento['fecha_fin'])) . "
                </div>";
        
        if ($evento['ubicacion']) {
            $html .= "
                <div class='info-row'>
                    <span class='info-label'>Ubicación:</span> " . htmlspecialchars($evento['ubicacion']) . "
                </div>";
        }
        
        $html .= "
                <div class='info-row'>
                    <span class='info-label'>Nombre:</span> {$nombre}
                </div>";
        
        if ($razon_social) {
            $html .= "
                <div class='info-row'>
                    <span class='info-label'>Empresa/Razón Social:</span> {$razon_social}
                </div>";
        }
        
        $html .= "
                <div class='info-row'>
                    <span class='info-label'>Boletos:</span> {$boletos}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Código:</span> <strong>{$codigo_qr}</strong>
                </div>
                
                <div class='qr-code'>
                    <p><strong>Presenta este código QR en el evento:</strong></p>
                    <img src='" . htmlspecialchars(BASE_URL . $qrCodePath, ENT_QUOTES, 'UTF-8') . "' alt='Código QR'>
                </div>
            </div>
            
            <p style='text-align: center;'>
                <a href='" . BASE_URL . "/boleto_digital.php?codigo={$codigo_qr}' class='button'>
                    🖨️ Imprimir Boleto
                </a>
            </p>";
        
        // Agregar invitación a afiliarse si no es empresa afiliada
        if (!$es_empresa_afiliada) {
            $html .= "
            <div class='invitation-box'>
                <h3 style='color: #f59e0b; margin-top: 0;'>¡Únete a la Cámara de Comercio!</h3>
                <p>Notamos que aún no eres empresa afiliada a nuestra Cámara de Comercio.</p>
                <p><strong>Beneficios de afiliarse:</strong></p>
                <ul>
                    <li>Acceso a eventos exclusivos</li>
                    <li>Networking con empresarios de la región</li>
                    <li>Capacitaciones y talleres</li>
                    <li>Promoción de tu empresa</li>
                    <li>Asesoría empresarial</li>
                </ul>
                <p style='text-align: center;'>
                    <a href='" . BASE_URL . "/register.php' class='button' style='background: #f59e0b;'>
                        ✨ Afiliarme Ahora
                    </a>
                </p>
            </div>";
        }
        
        $html .= "
            <p><strong>Importante:</strong></p>
            <ul>
                <li>Guarda este correo y presenta tu código QR al ingresar al evento</li>
                <li>Puedes imprimir tu boleto usando el botón de arriba</li>
                <li>Llega con 15 minutos de anticipación</li>
            </ul>
            
            <p>¡Nos vemos en el evento!</p>
        </div>
        
        <div class='footer'>
            <p>" . htmlspecialchars($nombre_sitio) . "</p>
            <p>Contacto: " . htmlspecialchars($config['email_sistema'] ?? 'info@camaraqro.com') . "</p>
            <p>" . htmlspecialchars($config['telefono_contacto'] ?? '') . "</p>
        </div>
    </div>
</body>
</html>";
        
        return self::sendHTML($email, $subject, $html);
    }
    
    /**
     * Plantilla de email para confirmación inicial de registro (con o sin pago pendiente)
     */
    public static function sendEventRegistrationConfirmation($inscripcion, $evento, $requiere_pago = false, $monto_total = 0, $qrCodePath = null) {
        $config = getConfiguracion();
        $nombre_sitio = $config['nombre_sitio'] ?? APP_NAME;
        $nombre = $inscripcion['nombre_invitado'] ?? 'Asistente';
        $email = $inscripcion['email_invitado'];
        $razon_social = $inscripcion['razon_social_invitado'] ?? '';
        $boletos = $inscripcion['boletos_solicitados'] ?? 1;
        $codigo_qr = $inscripcion['codigo_qr'];
        $es_empresa_afiliada = !empty($inscripcion['empresa_id']);
        
        // Obtener colores del sistema
        $color_primario = $config['color_primario'] ?? '#1E40AF';
        $color_secundario = $config['color_secundario'] ?? '#10B981';
        $color_acento = $config['color_acento1'] ?? '#F59E0B';
        $logo_url = !empty($config['logo_sistema']) ? BASE_URL . $config['logo_sistema'] : '';
        
        $subject = "Registro Confirmado - " . $evento['titulo'];
        
        // Construir HTML del email
        $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$color_primario}; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .logo { max-width: 150px; height: auto; margin-bottom: 10px; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border: 2px solid {$color_primario}; border-radius: 5px; }
        .info-row { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 3px; }
        .info-label { font-weight: bold; color: {$color_primario}; }
        .button { display: inline-block; padding: 12px 30px; background: {$color_secundario}; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .button-payment { background: #2563EB; }
        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; }
        .warning-box { background: #fef3c7; border: 2px solid {$color_acento}; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success-box { background: #d1fae5; border: 2px solid {$color_secundario}; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>" . 
            ($logo_url ? "<img src='{$logo_url}' alt='Logo' class='logo'>" : "") . "
            <h1>¡Registro Exitoso!</h1>
            <p>" . htmlspecialchars($nombre_sitio) . "</p>
        </div>
        
        <div class='content'>
            <h2>Hola {$nombre},</h2>
            <p>Tu registro para el evento <strong>" . htmlspecialchars($evento['titulo']) . "</strong> ha sido recibido exitosamente.</p>
            
            <div class='info-box'>
                <h3 style='color: #1E40AF; text-align: center; margin-top: 0;'>Información del Evento</h3>
                
                <div class='info-row'>
                    <span class='info-label'>Evento:</span> " . htmlspecialchars($evento['titulo']) . "
                </div>
                <div class='info-row'>
                    <span class='info-label'>Fecha:</span> " . date('d/m/Y', strtotime($evento['fecha_inicio'])) . "
                </div>
                <div class='info-row'>
                    <span class='info-label'>Hora:</span> " . date('H:i', strtotime($evento['fecha_inicio'])) . " - " . date('H:i', strtotime($evento['fecha_fin'])) . "
                </div>";
        
        if ($evento['ubicacion']) {
            $html .= "
                <div class='info-row'>
                    <span class='info-label'>Ubicación:</span> " . htmlspecialchars($evento['ubicacion']) . "
                </div>";
        }
        
        $html .= "
                <div class='info-row'>
                    <span class='info-label'>Nombre:</span> {$nombre}
                </div>";
        
        if ($razon_social) {
            $html .= "
                <div class='info-row'>
                    <span class='info-label'>Empresa/Razón Social:</span> {$razon_social}
                </div>";
        }
        
        $html .= "
                <div class='info-row'>
                    <span class='info-label'>Boletos Solicitados:</span> {$boletos}
                </div>
            </div>";
        
        // Si requiere pago, mostrar información de pago
        if ($requiere_pago) {
            $html .= "
            <div class='warning-box'>
                <h3 style='color: #f59e0b; margin-top: 0;'>⚠️ Pago Pendiente</h3>
                <p><strong>Para completar tu registro y recibir " . ($es_empresa_afiliada ? "tus boletos adicionales" : "tus boletos") . ", debes realizar el pago de:</strong></p>
                <p style='text-align: center; font-size: 24px; font-weight: bold; color: #2563EB; margin: 20px 0;'>
                    \$" . number_format($monto_total, 2) . " MXN
                </p>";
            
            if ($es_empresa_afiliada) {
                $html .= "
                <p style='background: #d1fae5; padding: 10px; border-radius: 5px; border: 1px solid #10b981;'>
                    <strong>✓ Beneficio de Empresa Afiliada:</strong> Tu primer boleto es gratuito. 
                    Este pago es solo por " . ($boletos - 1) . " boleto(s) adicional(es).
                </p>";
            }
            
            $html .= "
                <p style='text-align: center; margin-top: 20px;'>
                    <a href='" . BASE_URL . "/boleto_digital.php?codigo={$codigo_qr}' class='button button-payment'>
                        💳 Realizar Pago Ahora
                    </a>
                </p>
                <p style='text-align: center; font-size: 12px; color: #666;'>
                    También puedes acceder al enlace de pago desde tu código de registro: <strong>{$codigo_qr}</strong>
                </p>
            </div>";
        } else {
            // Si NO requiere pago, mostrar boleto o confirmación
            if ($qrCodePath && $es_empresa_afiliada) {
                // Empresa afiliada - primer boleto gratis
                $html .= "
            <div class='success-box'>
                <h3 style='color: #10b981; margin-top: 0;'>✓ ¡Primer Boleto Confirmado!</h3>
                <p>Como empresa afiliada, tu <strong>primer boleto es gratuito</strong> y ya está confirmado.</p>
                
                <div style='text-align: center; margin: 20px 0;'>
                    <img src='" . htmlspecialchars(BASE_URL . $qrCodePath, ENT_QUOTES, 'UTF-8') . "' alt='Código QR' style='max-width: 200px; border: 2px solid #1E40AF; padding: 10px; background: white;'>
                    <p style='margin-top: 10px;'><strong>Código:</strong> {$codigo_qr}</p>
                </div>
                
                <p style='text-align: center;'>
                    <a href='" . BASE_URL . "/boleto_digital.php?codigo={$codigo_qr}' class='button'>
                        🖨️ Imprimir Boleto
                    </a>
                </p>
            </div>";
            } else {
                // Evento gratuito
                $html .= "
            <div class='success-box'>
                <h3 style='color: #10b981; margin-top: 0;'>✓ ¡Registro Completado!</h3>
                <p>Este evento es gratuito. Tu registro está confirmado para <strong>{$boletos} boleto(s)</strong>.</p>";
                
                if ($qrCodePath) {
                    $html .= "
                <div style='text-align: center; margin: 20px 0;'>
                    <img src='" . htmlspecialchars(BASE_URL . $qrCodePath, ENT_QUOTES, 'UTF-8') . "' alt='Código QR' style='max-width: 200px; border: 2px solid #1E40AF; padding: 10px; background: white;'>
                    <p style='margin-top: 10px;'><strong>Código:</strong> {$codigo_qr}</p>
                </div>";
                }
                
                $html .= "
                <p style='text-align: center;'>
                    <a href='" . BASE_URL . "/boleto_digital.php?codigo={$codigo_qr}' class='button'>
                        🖨️ Ver/Imprimir Boleto
                    </a>
                </p>
            </div>";
            }
        }
        
        $html .= "
            <p><strong>Importante:</strong></p>
            <ul>
                <li>Guarda este correo electrónico</li>";
        
        if ($requiere_pago) {
            $html .= "
                <li>Completa tu pago para recibir tus boletos digitales</li>
                <li>Una vez pagado, recibirás un correo con tus boletos</li>";
        } else {
            $html .= "
                <li>Presenta tu código QR al ingresar al evento</li>
                <li>Llega con 15 minutos de anticipación</li>";
        }
        
        $html .= "
            </ul>
            
            <p>¡Nos vemos en el evento!</p>
        </div>
        
        <div class='footer'>
            <p>" . htmlspecialchars($nombre_sitio) . "</p>
            <p>Contacto: " . htmlspecialchars($config['email_sistema'] ?? 'info@camaraqro.com') . "</p>
            <p>" . htmlspecialchars($config['telefono_contacto'] ?? '') . "</p>
        </div>
    </div>
</body>
</html>";
        
        return self::sendHTML($email, $subject, $html);
    }
    
    /**
     * Plantilla de email para boletos después del pago (boletos adicionales o todos)
     */
    public static function sendEventTicketAfterPayment($inscripcion, $evento, $qrCodePath, $boletos_enviados = null) {
        $config = getConfiguracion();
        $nombre_sitio = $config['nombre_sitio'] ?? APP_NAME;
        $nombre = $inscripcion['nombre_invitado'] ?? 'Asistente';
        $email = $inscripcion['email_invitado'];
        $razon_social = $inscripcion['razon_social_invitado'] ?? '';
        $boletos_total = $inscripcion['boletos_solicitados'] ?? 1;
        $codigo_qr = $inscripcion['codigo_qr'];
        $es_empresa_afiliada = !empty($inscripcion['empresa_id']);
        
        // Obtener colores del sistema
        $color_primario = $config['color_primario'] ?? '#1E40AF';
        $color_secundario = $config['color_secundario'] ?? '#10B981';
        $logo_url = !empty($config['logo_sistema']) ? BASE_URL . $config['logo_sistema'] : '';
        
        // Si no se especifica, enviar todos los boletos
        if ($boletos_enviados === null) {
            $boletos_enviados = $boletos_total;
        }
        
        $subject = "¡Pago Confirmado! - Boletos para " . $evento['titulo'];
        
        // Construir HTML del email
        $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$color_secundario}; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .logo { max-width: 150px; height: auto; margin-bottom: 10px; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .ticket { background: white; padding: 20px; margin: 20px 0; border: 2px dashed {$color_primario}; border-radius: 5px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .qr-code img { max-width: 250px; border: 2px solid {$color_primario}; padding: 10px; background: white; }
        .info-row { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 3px; }
        .info-label { font-weight: bold; color: {$color_primario}; }
        .button { display: inline-block; padding: 12px 30px; background: {$color_secundario}; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; }
        .success-banner { background: #d1fae5; border: 2px solid {$color_secundario}; padding: 15px; margin: 20px 0; border-radius: 5px; text-align: center; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>" . 
            ($logo_url ? "<img src='{$logo_url}' alt='Logo' class='logo'>" : "") . "
            <h1>✅ ¡Pago Confirmado!</h1>
            <p>" . htmlspecialchars($nombre_sitio) . "</p>
        </div>
        
        <div class='content'>
            <h2>¡Excelente, {$nombre}!</h2>
            <p>Tu pago ha sido confirmado exitosamente. Aquí " . ($boletos_enviados == 1 ? "está tu boleto" : "están tus {$boletos_enviados} boletos") . " para el evento:</p>
            
            <div class='success-banner'>
                <h3 style='color: #10b981; margin: 0;'>🎉 {$boletos_enviados} Boleto" . ($boletos_enviados > 1 ? "s" : "") . " Confirmado" . ($boletos_enviados > 1 ? "s" : "") . "</h3>";
        
        if ($es_empresa_afiliada && $boletos_total > $boletos_enviados) {
            $html .= "
                <p style='margin: 10px 0; color: #059669;'>
                    + 1 boleto gratuito (beneficio de empresa afiliada)
                </p>
                <p style='margin: 0; font-size: 18px; font-weight: bold;'>
                    Total: {$boletos_total} boletos
                </p>";
        }
        
        $html .= "
            </div>
            
            <div class='ticket'>
                <h3 style='color: #1E40AF; text-align: center;'>Boleto Digital</h3>
                
                <div class='info-row'>
                    <span class='info-label'>Evento:</span> " . htmlspecialchars($evento['titulo']) . "
                </div>
                <div class='info-row'>
                    <span class='info-label'>Fecha:</span> " . date('d/m/Y', strtotime($evento['fecha_inicio'])) . "
                </div>
                <div class='info-row'>
                    <span class='info-label'>Hora:</span> " . date('H:i', strtotime($evento['fecha_inicio'])) . " - " . date('H:i', strtotime($evento['fecha_fin'])) . "
                </div>";
        
        if ($evento['ubicacion']) {
            $html .= "
                <div class='info-row'>
                    <span class='info-label'>Ubicación:</span> " . htmlspecialchars($evento['ubicacion']) . "
                </div>";
        }
        
        $html .= "
                <div class='info-row'>
                    <span class='info-label'>Nombre:</span> {$nombre}
                </div>";
        
        if ($razon_social) {
            $html .= "
                <div class='info-row'>
                    <span class='info-label'>Empresa/Razón Social:</span> {$razon_social}
                </div>";
        }
        
        $html .= "
                <div class='info-row'>
                    <span class='info-label'>Total de Boletos:</span> <strong>{$boletos_total}</strong>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Código:</span> <strong>{$codigo_qr}</strong>
                </div>
                
                <div class='qr-code'>
                    <p><strong>Presenta este código QR en el evento:</strong></p>
                    <img src='" . htmlspecialchars(BASE_URL . $qrCodePath, ENT_QUOTES, 'UTF-8') . "' alt='Código QR'>
                </div>
            </div>
            
            <p style='text-align: center;'>
                <a href='" . BASE_URL . "/boleto_digital.php?codigo={$codigo_qr}' class='button'>
                    🖨️ Imprimir Boletos
                </a>
            </p>
            
            <p><strong>Importante:</strong></p>
            <ul>
                <li>Guarda este correo y presenta tu código QR al ingresar al evento</li>
                <li>Puedes imprimir tus boletos usando el botón de arriba</li>
                <li>Un solo código QR es válido para todos tus {$boletos_total} boletos</li>
                <li>Llega con 15 minutos de anticipación</li>
            </ul>
            
            <p>¡Nos vemos en el evento!</p>
        </div>
        
        <div class='footer'>
            <p>" . htmlspecialchars($nombre_sitio) . "</p>
            <p>Contacto: " . htmlspecialchars($config['email_sistema'] ?? 'info@camaraqro.com') . "</p>
            <p>" . htmlspecialchars($config['telefono_contacto'] ?? '') . "</p>
        </div>
    </div>
</body>
</html>";
        
        return self::sendHTML($email, $subject, $html);
    }
}
