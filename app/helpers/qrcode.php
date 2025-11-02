<?php
/**
 * Simple QR Code generator using PHP GD library
 * Genera códigos QR usando una API pública o biblioteca simple
 */

class QRCodeGenerator {
    
    /**
     * Generar código QR único
     */
    public static function generateUniqueCode() {
        return uniqid('QR', true) . bin2hex(random_bytes(8));
    }
    
    /**
     * Generar imagen QR usando API configurada
     * @param string $data - Datos a codificar en el QR
     * @param int $size - Tamaño de la imagen
     * @return string - URL de la imagen QR
     */
    public static function generateQRImageURL($data, $size = 300) {
        $config = getConfiguracion();
        $provider = $config['qr_api_provider'] ?? 'google';
        $configuredSize = intval($config['qr_size'] ?? 400);
        
        // Use configured size if provided size is default
        if ($size == 300) {
            $size = $configuredSize;
        }
        
        $data = urlencode($data);
        
        switch ($provider) {
            case 'qrserver':
                // QR Server API - más robusto para impresión
                return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$data}";
            
            case 'quickchart':
                // QuickChart API - alternativa moderna
                return "https://quickchart.io/qr?text={$data}&size={$size}";
            
            case 'google':
            default:
                // Google Charts API (por defecto)
                return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$data}&choe=UTF-8";
        }
    }
    
    /**
     * Descargar imagen QR y guardarla localmente
     * @param string $data - Datos a codificar
     * @param string $filename - Nombre del archivo a guardar
     * @return bool|string - Ruta del archivo guardado o false
     */
    public static function saveQRImage($data, $filename) {
        $url = self::generateQRImageURL($data, 300);
        
        // Crear directorio si no existe
        $qr_dir = UPLOAD_PATH . '/qrcodes/';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $filepath = $qr_dir . $filename . '.png';
        
        // Descargar y guardar la imagen
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $imageData) {
            file_put_contents($filepath, $imageData);
            return '/public/uploads/qrcodes/' . $filename . '.png';
        }
        
        return false;
    }
    
    /**
     * Verificar código QR
     * @param string $code - Código a verificar
     * @param int $evento_id - ID del evento
     * @return array|false - Datos de la inscripción o false
     */
    public static function verifyQRCode($code, $evento_id = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $sql = "SELECT * FROM eventos_inscripciones WHERE codigo_qr = ?";
            $params = [$code];
            
            if ($evento_id) {
                $sql .= " AND evento_id = ?";
                $params[] = $evento_id;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}
