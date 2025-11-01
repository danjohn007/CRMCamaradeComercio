<?php
/**
 * Archivo de configuración principal del sistema CRM
 * Detección automática de URL base para instalación en cualquier directorio
 */

// Detección automática de URL base
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    
    // Obtener el path del directorio raíz del proyecto
    // Eliminar el nombre del archivo y cualquier subdirectorio hasta llegar a la raíz
    $path = dirname($script);
    
    // Si estamos en un subdirectorio (como /catalogos/), subir un nivel
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    $rootPath = dirname(__DIR__); // ROOT_PATH del proyecto
    
    // Calcular la ruta relativa desde document root
    $relativePath = str_replace($documentRoot, '', $rootPath);
    
    return $protocol . $host . $relativePath;
}

// Definir constantes del sistema
define('BASE_URL', rtrim(getBaseUrl(), '/'));
define('APP_NAME', 'CRM Cámara de Comercio');
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// Configuración de uploads
define('MAX_FILE_SIZE', 5242880); // 5MB en bytes

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'agenciae_canaco');
define('DB_USER', 'agenciae_canaco');
define('DB_PASS', 'Danjohn007!');
define('DB_CHARSET', 'utf8mb4');

// Configuración de sesión
define('SESSION_NAME', 'CRM_CAMARA_SESSION');
define('SESSION_LIFETIME', 7200); // 2 horas

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de errores (cambiar a 0 en producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Autoloader simple para clases
spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/models/' . $class . '.php',
        APP_PATH . '/controllers/' . $class . '.php',
        APP_PATH . '/helpers/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
});

// Cargar helpers
require_once APP_PATH . '/helpers/functions.php';
