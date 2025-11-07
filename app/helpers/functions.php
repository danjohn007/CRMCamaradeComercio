<?php
/**
 * Funciones auxiliares del sistema
 */

/**
 * Redireccionar a una URL
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Sanitizar entrada de datos
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar si el usuario está autenticado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'nombre' => $_SESSION['user_nombre'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'rol' => $_SESSION['user_rol'] ?? '',
            'empresa_id' => $_SESSION['empresa_id'] ?? null
        ];
    }
    return null;
}

/**
 * Verificar permisos por rol
 */
function hasPermission($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $roles = [
        'PRESIDENCIA' => 7,
        'DIRECCION' => 6,
        'CONSEJERO' => 5,
        'AFILADOR' => 4,
        'CAPTURISTA' => 3,
        'ENTIDAD_COMERCIAL' => 2,
        'EMPRESA_TRACTORA' => 1
    ];
    
    $userRole = $_SESSION['user_rol'] ?? '';
    $userLevel = $roles[$userRole] ?? 0;
    $requiredLevel = $roles[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Requerir autenticación
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/login.php');
    }
}

/**
 * Requerir permisos específicos
 */
function requirePermission($role) {
    requireLogin();
    if (!hasPermission($role)) {
        redirect('/dashboard.php?error=no_permission');
    }
}

/**
 * Generar token CSRF
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

/**
 * Formatear moneda
 */
function formatMoney($amount) {
    return '$' . number_format($amount, 2, '.', ',');
}

/**
 * Enviar email usando configuración del sistema
 * 
 * @param string $to Dirección de email del destinatario
 * @param string $subject Asunto del email
 * @param string $body Cuerpo del mensaje (texto plano)
 * @return bool True si el email fue enviado, false en caso contrario
 * 
 * NOTA: Esta función depende de que el servidor tenga configurado un servicio de correo
 * (sendmail, postfix, etc.). Si el servidor no tiene servicio de correo configurado,
 * la función retornará false. Se recomienda verificar los logs del servidor en caso
 * de fallos en el envío de emails.
 */
function sendEmail($to, $subject, $body) {
    $config = getConfiguracion();
    $from = $config['email_sistema'] ?? 'noreply@camaraqro.com';
    $fromName = $config['smtp_from_name'] ?? $config['nombre_sitio'] ?? APP_NAME;
    
    // Headers
    $headers = "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

/**
 * Enviar WhatsApp (placeholder - implementar con API de WhatsApp Business)
 */
function sendWhatsApp($phone, $message) {
    // TODO: Implementar envío de WhatsApp real
    // Por ahora solo simulamos el envío
    return true;
}

/**
 * Subir archivo
 */
function uploadFile($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Error en el archivo'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'El archivo es muy grande'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
    }
    
    $filename = uniqid() . '.' . $ext;
    $destination = UPLOAD_PATH . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Error al mover el archivo'];
    }
    
    return ['success' => true, 'filename' => $filename];
}

/**
 * Validar RFC mexicano
 */
function validarRFC($rfc) {
    $rfc = strtoupper(trim($rfc));
    // Formato básico: AAAA000000XXX (13 caracteres persona física) o AAA000000XXX (12 caracteres persona moral)
    return preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc);
}

/**
 * Validar email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generar código de verificación
 */
function generateVerificationCode() {
    return bin2hex(random_bytes(16));
}

/**
 * Calcular días hasta vencimiento
 */
function diasHastaVencimiento($fecha) {
    // Return null if fecha is null or empty to avoid DateTime deprecation warning
    if (empty($fecha)) {
        return null;
    }
    
    $hoy = new DateTime();
    $vencimiento = new DateTime($fecha);
    $diff = $hoy->diff($vencimiento);
    return $diff->invert ? -$diff->days : $diff->days;
}

/**
 * Obtener configuración del sistema
 */
function getConfiguracion($clave = null) {
    static $config = null;
    
    if ($config === null) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT clave, valor FROM configuracion");
            $config = [];
            while ($row = $stmt->fetch()) {
                $config[$row['clave']] = $row['valor'];
            }
        } catch (Exception $e) {
            $config = [];
        }
    }
    
    if ($clave === null) {
        return $config;
    }
    
    return $config[$clave] ?? null;
}

/**
 * Escapar output para HTML
 */
function e($string) {
    // Manejar valores null y vacíos para evitar deprecation warning en PHP 8.1+
    if ($string === null) {
        return '';
    }
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Debug helper
 */
function dd($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die();
}

/**
 * Obtener conexión MySQLi (para compatibilidad con código existente)
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        
        $conn->set_charset(DB_CHARSET);
        return $conn;
    } catch (Exception $e) {
        die("Error al conectar a la base de datos: " . $e->getMessage());
    }
}

/**
 * Registrar acción en auditoría
 */
function registrarAuditoria($conn, $usuario_id, $accion, $tabla = '', $registro_id = null, $descripcion = '') {
    try {
        // Si es conexión MySQLi
        if ($conn instanceof mysqli) {
            $stmt = $conn->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, descripcion, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            // i=integer, s=string - usuario_id(i), accion(s), tabla(s), registro_id(i), descripcion(s), ip(s), ua(s)
            $stmt->bind_param("isisss", $usuario_id, $accion, $tabla, $registro_id, $descripcion, $ip, $ua);
            $stmt->execute();
            $stmt->close();
        }
        // Si es conexión PDO
        else if ($conn instanceof PDO) {
            $stmt = $conn->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, descripcion, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt->execute([$usuario_id, $accion, $tabla, $registro_id, $descripcion, $ip, $ua]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error al registrar auditoría: " . $e->getMessage());
        return false;
    }
}
