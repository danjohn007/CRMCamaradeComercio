<?php
/**
 * Script de verificación de modo PayPal (Sandbox vs Live)
 * Muestra información detallada sobre la configuración y conexión actual
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers/paypal.php';

// Requiere login como admin
requireLogin();
$user = getCurrentUser();
if (!in_array($user['rol'], ['PRESIDENCIA', 'DIRECCION'])) {
    die('Acceso denegado - Solo administradores');
}

header('Content-Type: text/html; charset=UTF-8');

$db = Database::getInstance()->getConnection();

// Obtener configuración de PayPal de la BD
$stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'paypal%'");
$config_paypal = [];
while ($row = $stmt->fetch()) {
    $config_paypal[$row['clave']] = $row['valor'];
}

// Información del modo
$modo = $config_paypal['paypal_mode'] ?? 'sandbox';
$client_id = $config_paypal['paypal_client_id'] ?? '';
$secret = $config_paypal['paypal_secret'] ?? '';

// Determinar URL base según el modo
$base_url = ($modo === 'live') 
    ? 'https://api-m.paypal.com' 
    : 'https://api-m.sandbox.paypal.com';

// Intentar obtener un token de acceso y verificar la URL real
$token_info = null;
$error_token = null;
$url_detectada = null;

if (!empty($client_id) && !empty($secret)) {
    try {
        $ch = curl_init();
        $url_oauth = $base_url . '/v1/oauth2/token';
        
        curl_setopt($ch, CURLOPT_URL, $url_oauth);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        // Activar verbose para capturar la URL real
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $url_efectiva = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        
        // Leer información verbose
        rewind($verbose);
        $verbose_log = stream_get_contents($verbose);
        
        curl_close($ch);
        fclose($verbose);
        
        $url_detectada = $url_efectiva;
        
        if ($curl_error) {
            $error_token = "cURL Error: " . $curl_error;
        } elseif ($http_code === 200) {
            $data = json_decode($response, true);
            $token_info = [
                'success' => true,
                'token_preview' => substr($data['access_token'] ?? '', 0, 30) . '...',
                'expires_in' => $data['expires_in'] ?? 0,
                'url_used' => $url_oauth,
                'http_code' => $http_code
            ];
        } else {
            $error_data = json_decode($response, true);
            $error_token = "HTTP $http_code - " . ($error_data['error_description'] ?? $error_data['message'] ?? 'Error desconocido');
            $token_info = [
                'success' => false,
                'url_used' => $url_oauth,
                'http_code' => $http_code,
                'response' => $response
            ];
        }
        
    } catch (Exception $e) {
        $error_token = $e->getMessage();
    }
}

// Detectar el tipo de credenciales basado en el formato
$tipo_credenciales = 'Desconocido';
if (!empty($client_id)) {
    // Las credenciales de sandbox suelen empezar con diferentes prefijos
    // pero lo más seguro es probar la conexión
    $tipo_credenciales = ($modo === 'live') ? 'Producción (Live)' : 'Prueba (Sandbox)';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Modo PayPal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold mb-6 flex items-center">
                <i class="fab fa-paypal text-blue-600 mr-3"></i>
                Verificación de Modo PayPal
            </h1>
            
            <!-- Resumen Principal -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-50 border-2 border-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-300 rounded-lg p-6 text-center">
                    <i class="fas fa-<?php echo $modo === 'live' ? 'globe' : 'flask'; ?> text-4xl text-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-600 mb-2"></i>
                    <h3 class="font-bold text-lg mb-1">Modo Actual</h3>
                    <p class="text-2xl font-bold text-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-700">
                        <?php echo strtoupper($modo); ?>
                    </p>
                    <p class="text-sm text-gray-600 mt-2">
                        <?php echo $modo === 'live' ? '⚠️ Producción' : '✓ Pruebas'; ?>
                    </p>
                </div>
                
                <div class="bg-gray-50 border-2 border-gray-300 rounded-lg p-6 text-center">
                    <i class="fas fa-server text-4xl text-gray-600 mb-2"></i>
                    <h3 class="font-bold text-lg mb-1">Servidor API</h3>
                    <p class="text-sm font-mono text-gray-700 break-all">
                        <?php echo $base_url; ?>
                    </p>
                </div>
                
                <div class="bg-<?php echo ($token_info && $token_info['success']) ? 'green' : 'red'; ?>-50 border-2 border-<?php echo ($token_info && $token_info['success']) ? 'green' : 'red'; ?>-300 rounded-lg p-6 text-center">
                    <i class="fas fa-<?php echo ($token_info && $token_info['success']) ? 'check-circle' : 'times-circle'; ?> text-4xl text-<?php echo ($token_info && $token_info['success']) ? 'green' : 'red'; ?>-600 mb-2"></i>
                    <h3 class="font-bold text-lg mb-1">Estado Conexión</h3>
                    <p class="text-lg font-bold text-<?php echo ($token_info && $token_info['success']) ? 'green' : 'red'; ?>-700">
                        <?php echo ($token_info && $token_info['success']) ? 'CONECTADO' : 'ERROR'; ?>
                    </p>
                </div>
            </div>
            
            <?php if ($modo === 'live'): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-red-800 font-bold text-lg mb-2">⚠️ ADVERTENCIA: Modo LIVE (Producción)</h3>
                        <p class="text-red-700">
                            Estás usando credenciales de <strong>PRODUCCIÓN</strong>. Los pagos procesados serán reales y se cobrarán a tarjetas reales.
                        </p>
                        <ul class="mt-3 text-red-600 space-y-1">
                            <li>✓ Los usuarios verán sus cuentas PayPal reales</li>
                            <li>✓ Se procesarán cargos reales</li>
                            <li>✓ Se requerirá HTTPS en producción</li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-6">
                <div class="flex">
                    <i class="fas fa-info-circle text-blue-500 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-blue-800 font-bold text-lg mb-2">ℹ️ Modo SANDBOX (Pruebas)</h3>
                        <p class="text-blue-700">
                            Estás usando credenciales de <strong>PRUEBA</strong>. Los pagos son simulados y no se cobran a tarjetas reales.
                        </p>
                        <ul class="mt-3 text-blue-600 space-y-1">
                            <li>✓ Usar cuentas de prueba de PayPal Developer</li>
                            <li>✓ No se procesarán cargos reales</li>
                            <li>✓ Ideal para desarrollo y testing</li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Detalles de Configuración -->
            <div class="bg-white border rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-cog text-gray-600 mr-2"></i>
                    Configuración Actual
                </h2>
                
                <table class="w-full">
                    <tr class="border-b">
                        <td class="py-3 font-semibold text-gray-700 w-1/3">Modo configurado:</td>
                        <td class="py-3">
                            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-100 text-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-800">
                                <?php echo strtoupper($modo); ?>
                            </span>
                        </td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-3 font-semibold text-gray-700">Client ID:</td>
                        <td class="py-3">
                            <?php if (!empty($client_id)): ?>
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo substr($client_id, 0, 20); ?>...<?php echo substr($client_id, -10); ?></code>
                                <span class="ml-2 text-green-600">✓ Configurado</span>
                            <?php else: ?>
                                <span class="text-red-600">✗ No configurado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-3 font-semibold text-gray-700">Secret:</td>
                        <td class="py-3">
                            <?php if (!empty($secret)): ?>
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">••••••••••••••••••••</code>
                                <span class="ml-2 text-green-600">✓ Configurado</span>
                            <?php else: ?>
                                <span class="text-red-600">✗ No configurado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-3 font-semibold text-gray-700">Cuenta PayPal:</td>
                        <td class="py-3">
                            <?php echo !empty($config_paypal['paypal_account']) ? htmlspecialchars($config_paypal['paypal_account']) : '<span class="text-gray-400">No configurada</span>'; ?>
                        </td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-3 font-semibold text-gray-700">URL Base API:</td>
                        <td class="py-3">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo $base_url; ?></code>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 font-semibold text-gray-700">Tipo de credenciales:</td>
                        <td class="py-3">
                            <span class="font-semibold text-<?php echo $modo === 'live' ? 'red' : 'blue'; ?>-700">
                                <?php echo $tipo_credenciales; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Prueba de Conexión -->
            <div class="bg-white border rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-plug text-gray-600 mr-2"></i>
                    Prueba de Conexión
                </h2>
                
                <?php if ($token_info): ?>
                    <?php if ($token_info['success']): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                                <div class="flex-1">
                                    <p class="text-green-800 font-bold">✓ Conexión exitosa con PayPal</p>
                                    <p class="text-green-700 text-sm mt-1">Las credenciales son válidas y funcionales</p>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-4 border-t border-green-200">
                                <table class="w-full text-sm">
                                    <tr>
                                        <td class="py-1 text-gray-700 font-semibold w-1/4">URL utilizada:</td>
                                        <td class="py-1"><code class="text-xs bg-white px-2 py-1 rounded"><?php echo $token_info['url_used']; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-700 font-semibold">HTTP Status:</td>
                                        <td class="py-1">
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono"><?php echo $token_info['http_code']; ?> OK</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-700 font-semibold">Token obtenido:</td>
                                        <td class="py-1"><code class="text-xs bg-white px-2 py-1 rounded"><?php echo $token_info['token_preview']; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-700 font-semibold">Expira en:</td>
                                        <td class="py-1"><?php echo $token_info['expires_in']; ?> segundos</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-blue-800 font-semibold mb-2">
                                <i class="fas fa-lightbulb mr-2"></i>Confirmación del Servidor:
                            </p>
                            <p class="text-blue-700">
                                El backend está conectando correctamente a 
                                <strong><?php echo $modo === 'live' ? 'PayPal PRODUCCIÓN' : 'PayPal SANDBOX'; ?></strong>
                                usando la URL: <code class="bg-white px-2 py-1 rounded text-xs"><?php echo $url_detectada ?? $base_url; ?></code>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-times-circle text-red-600 text-2xl mr-3"></i>
                                <div>
                                    <p class="text-red-800 font-bold">✗ Error de conexión</p>
                                    <p class="text-red-700 text-sm">No se pudo conectar con PayPal</p>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded p-3 mt-3">
                                <p class="text-sm text-gray-700 mb-2"><strong>URL intentada:</strong></p>
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded block"><?php echo $token_info['url_used']; ?></code>
                                
                                <p class="text-sm text-gray-700 mb-2 mt-3"><strong>HTTP Status:</strong></p>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-mono"><?php echo $token_info['http_code']; ?></span>
                                
                                <p class="text-sm text-gray-700 mb-2 mt-3"><strong>Error:</strong></p>
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded block"><?php echo htmlspecialchars($error_token); ?></code>
                                
                                <?php if (!empty($token_info['response'])): ?>
                                <p class="text-sm text-gray-700 mb-2 mt-3"><strong>Respuesta completa:</strong></p>
                                <pre class="text-xs bg-gray-100 p-2 rounded overflow-x-auto"><?php echo htmlspecialchars($token_info['response']); ?></pre>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                        <span class="text-yellow-800">No se pudo realizar la prueba de conexión. Verifica las credenciales.</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recomendaciones -->
            <div class="bg-white border rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-clipboard-list text-gray-600 mr-2"></i>
                    Recomendaciones
                </h2>
                
                <?php if ($modo === 'sandbox'): ?>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                        <div>
                            <p class="font-semibold">Crear cuentas de prueba</p>
                            <p class="text-sm text-gray-600">Ve a <a href="https://developer.paypal.com/dashboard/accounts" target="_blank" class="text-blue-600 hover:underline">PayPal Developer → Accounts</a> para crear cuentas Personal (comprador) y Business (vendedor)</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                        <div>
                            <p class="font-semibold">Iniciar sesión con cuenta de prueba</p>
                            <p class="text-sm text-gray-600">Al pagar, usa el email y password de la cuenta Personal de sandbox (NO tu cuenta real)</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-3 mt-1"></i>
                        <div>
                            <p class="font-semibold">No se requiere tarjeta real</p>
                            <p class="text-sm text-gray-600">Las cuentas de sandbox tienen saldo ficticio, no necesitas tarjeta</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3 mt-1"></i>
                        <div>
                            <p class="font-semibold text-red-700">Usar HTTPS en producción</p>
                            <p class="text-sm text-gray-600">PayPal requiere conexión segura (HTTPS) para procesar pagos reales</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3 mt-1"></i>
                        <div>
                            <p class="font-semibold text-red-700">Verificar cuenta de PayPal Business</p>
                            <p class="text-sm text-gray-600">Asegúrate de que tu cuenta Business esté verificada y pueda recibir pagos</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3 mt-1"></i>
                        <div>
                            <p class="font-semibold text-red-700">Los pagos son reales</p>
                            <p class="text-sm text-gray-600">Todos los cargos procesados serán reales y se cobrarán a las cuentas de los usuarios</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Acciones -->
            <div class="flex gap-4">
                <a href="<?php echo BASE_URL; ?>/configuracion.php" 
                   class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 inline-flex items-center">
                    <i class="fas fa-cog mr-2"></i>
                    Ir a Configuración
                </a>
                
                <a href="<?php echo BASE_URL; ?>/test_paypal.php" 
                   class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 inline-flex items-center">
                    <i class="fas fa-vial mr-2"></i>
                    Prueba Completa
                </a>
                
                <a href="<?php echo BASE_URL; ?>/mi_membresia.php" 
                   class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 inline-flex items-center">
                    <i class="fas fa-credit-card mr-2"></i>
                    Probar Pago
                </a>
                
                <button onclick="window.location.reload()" 
                        class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 inline-flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Recargar
                </button>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
            <p class="text-sm text-blue-800">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Nota:</strong> Este script verifica la configuración en el servidor (backend). 
                La conexión mostrada aquí es la que el sistema PHP está usando realmente para comunicarse con PayPal.
            </p>
        </div>
    </div>
</body>
</html>
