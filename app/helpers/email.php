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
        .header { background: #1E40AF; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .ticket { background: white; padding: 20px; margin: 20px 0; border: 2px dashed #1E40AF; border-radius: 5px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .qr-code img { max-width: 250px; border: 2px solid #1E40AF; padding: 10px; background: white; }
        .info-row { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 3px; }
        .info-label { font-weight: bold; color: #1E40AF; }
        .button { display: inline-block; padding: 12px 30px; background: #10B981; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; }
        .invitation-box { background: #fef3c7; border: 2px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
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
}
