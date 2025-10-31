<?php
/**
 * Archivo de prueba de conexión y verificación de URL base
 * Ejecutar este archivo para verificar la configuración del sistema
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Conexión - CRM Cámara de Comercio</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">
                🔍 Test de Conexión y Configuración
            </h1>
            
            <!-- Verificación de URL Base -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center">
                    <span class="text-2xl mr-2">🌐</span> URL Base
                </h2>
                <div class="bg-blue-50 p-4 rounded">
                    <p class="text-sm text-gray-600 mb-2">URL Base detectada:</p>
                    <p class="text-lg font-mono text-blue-600"><?php echo BASE_URL; ?></p>
                </div>
            </div>

            <!-- Verificación de Rutas -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center">
                    <span class="text-2xl mr-2">📁</span> Rutas del Sistema
                </h2>
                <div class="space-y-2">
                    <?php
                    $paths = [
                        'ROOT_PATH' => ROOT_PATH,
                        'APP_PATH' => APP_PATH,
                        'PUBLIC_PATH' => PUBLIC_PATH,
                        'UPLOAD_PATH' => UPLOAD_PATH
                    ];
                    
                    foreach ($paths as $name => $path) {
                        $exists = file_exists($path);
                        $color = $exists ? 'green' : 'red';
                        $icon = $exists ? '✓' : '✗';
                        echo "<div class='flex items-center justify-between p-3 bg-gray-50 rounded'>";
                        echo "<span class='font-mono text-sm'>{$name}</span>";
                        echo "<span class='text-{$color}-600'>{$icon} " . ($exists ? 'OK' : 'No existe') . "</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Verificación de Base de Datos -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center">
                    <span class="text-2xl mr-2">💾</span> Conexión a Base de Datos
                </h2>
                <?php
                try {
                    $db = Database::getInstance()->getConnection();
                    echo "<div class='bg-green-50 p-4 rounded'>";
                    echo "<p class='text-green-700 font-semibold'>✓ Conexión exitosa</p>";
                    echo "<p class='text-sm text-gray-600 mt-2'>Host: " . DB_HOST . "</p>";
                    echo "<p class='text-sm text-gray-600'>Database: " . DB_NAME . "</p>";
                    
                    // Verificar tablas
                    $stmt = $db->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (count($tables) > 0) {
                        echo "<p class='text-sm text-gray-600 mt-2'>Tablas encontradas: " . count($tables) . "</p>";
                        echo "<details class='mt-2'>";
                        echo "<summary class='cursor-pointer text-sm text-blue-600'>Ver tablas</summary>";
                        echo "<ul class='mt-2 ml-4 text-sm text-gray-600'>";
                        foreach ($tables as $table) {
                            echo "<li>• {$table}</li>";
                        }
                        echo "</ul>";
                        echo "</details>";
                    } else {
                        echo "<p class='text-yellow-600 mt-2'>⚠ No se encontraron tablas. Ejecuta el archivo schema.sql</p>";
                    }
                    echo "</div>";
                } catch (Exception $e) {
                    echo "<div class='bg-red-50 p-4 rounded'>";
                    echo "<p class='text-red-700 font-semibold'>✗ Error de conexión</p>";
                    echo "<p class='text-sm text-red-600 mt-2'>" . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "<div class='mt-3 text-sm text-gray-600'>";
                    echo "<p class='font-semibold'>Verifica:</p>";
                    echo "<ul class='list-disc ml-5 mt-1'>";
                    echo "<li>MySQL está instalado y funcionando</li>";
                    echo "<li>Las credenciales en config/config.php son correctas</li>";
                    echo "<li>La base de datos '" . DB_NAME . "' existe</li>";
                    echo "</ul>";
                    echo "</div>";
                    echo "</div>";
                }
                ?>
            </div>

            <!-- Verificación de PHP -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center">
                    <span class="text-2xl mr-2">🐘</span> Configuración PHP
                </h2>
                <div class="space-y-2">
                    <?php
                    $phpInfo = [
                        'Versión PHP' => phpversion(),
                        'PDO MySQL' => extension_loaded('pdo_mysql') ? 'Instalado ✓' : 'No instalado ✗',
                        'JSON' => extension_loaded('json') ? 'Instalado ✓' : 'No instalado ✗',
                        'Session' => extension_loaded('session') ? 'Instalado ✓' : 'No instalado ✗',
                        'FileInfo' => extension_loaded('fileinfo') ? 'Instalado ✓' : 'No instalado ✗',
                        'Zona Horaria' => date_default_timezone_get()
                    ];
                    
                    foreach ($phpInfo as $key => $value) {
                        echo "<div class='flex items-center justify-between p-3 bg-gray-50 rounded'>";
                        echo "<span class='font-semibold'>{$key}</span>";
                        echo "<span class='font-mono text-sm'>{$value}</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Verificación de Permisos -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center">
                    <span class="text-2xl mr-2">🔐</span> Permisos de Escritura
                </h2>
                <div class="space-y-2">
                    <?php
                    $writablePaths = [
                        'Uploads' => UPLOAD_PATH,
                        'Session' => session_save_path()
                    ];
                    
                    foreach ($writablePaths as $name => $path) {
                        $writable = is_writable($path);
                        $color = $writable ? 'green' : 'red';
                        $icon = $writable ? '✓' : '✗';
                        echo "<div class='flex items-center justify-between p-3 bg-gray-50 rounded'>";
                        echo "<span class='font-semibold'>{$name}</span>";
                        echo "<span class='text-{$color}-600'>{$icon} " . ($writable ? 'Escribible' : 'No escribible') . "</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Instrucciones -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">📋 Próximos pasos</h3>
                <ol class="list-decimal ml-5 space-y-1 text-sm text-blue-700">
                    <li>Si la conexión a la base de datos falló, crea la base de datos y ejecuta schema.sql</li>
                    <li>Para datos de ejemplo, ejecuta sample_data.sql</li>
                    <li>Accede al sistema en: <a href="<?php echo BASE_URL; ?>" class="underline font-semibold"><?php echo BASE_URL; ?></a></li>
                    <li>Usuario por defecto: admin@camaraqro.com / password</li>
                </ol>
            </div>

            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                    Ir al Sistema
                </a>
            </div>
        </div>
    </div>
</body>
</html>
