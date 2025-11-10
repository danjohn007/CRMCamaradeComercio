<?php
/**
 * Script CLI para verificar modo PayPal
 * Ejecutar: php verificar_paypal_cli.php
 */

// Configurar variables de servidor para CLI
if (php_sapi_name() === 'cli') {
    $_SERVER['SERVER_PORT'] = 80;
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['HTTPS'] = 'off';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['DOCUMENT_ROOT'] = __DIR__;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  VERIFICACIÓN DE MODO PAYPAL - Backend\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$db = Database::getInstance()->getConnection();

// Obtener configuración
$stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'paypal%'");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

$modo = $config['paypal_mode'] ?? 'sandbox';
$client_id = $config['paypal_client_id'] ?? '';
$secret = $config['paypal_secret'] ?? '';

echo "📋 CONFIGURACIÓN ACTUAL:\n";
echo "───────────────────────────────────────────────────────────────\n";
echo sprintf("  Modo configurado: %s\n", strtoupper($modo));
echo sprintf("  Client ID: %s\n", !empty($client_id) ? substr($client_id, 0, 20) . '...' . substr($client_id, -10) : '❌ NO CONFIGURADO');
echo sprintf("  Secret: %s\n", !empty($secret) ? '✓ Configurado (***oculto***)' : '❌ NO CONFIGURADO');
echo sprintf("  Cuenta: %s\n", $config['paypal_account'] ?? 'No configurada');
echo "\n";

// Determinar URL
$base_url = ($modo === 'live') 
    ? 'https://api-m.paypal.com' 
    : 'https://api-m.sandbox.paypal.com';

echo "🌐 SERVIDOR API:\n";
echo "───────────────────────────────────────────────────────────────\n";
echo sprintf("  URL Base: %s\n", $base_url);
echo sprintf("  Entorno: %s\n", $modo === 'live' ? '⚠️  PRODUCCIÓN (Pagos Reales)' : '✓ PRUEBAS (Sandbox)');
echo "\n";

// Intentar obtener token
if (!empty($client_id) && !empty($secret)) {
    echo "🔌 PRUEBA DE CONEXIÓN:\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo "  Intentando conectar...\n";
    
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
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $time_taken = round(($end_time - $start_time) * 1000, 2);
    
    curl_close($ch);
    
    echo sprintf("  URL utilizada: %s\n", $url_oauth);
    echo sprintf("  Tiempo de respuesta: %s ms\n", $time_taken);
    
    if ($curl_error) {
        echo "  Estado: ❌ ERROR\n";
        echo sprintf("  Error cURL: %s\n", $curl_error);
    } elseif ($http_code === 200) {
        $data = json_decode($response, true);
        echo "  Estado: ✅ CONECTADO\n";
        echo sprintf("  HTTP Status: %d OK\n", $http_code);
        echo sprintf("  Token obtenido: %s...\n", substr($data['access_token'] ?? '', 0, 30));
        echo sprintf("  Expira en: %d segundos\n", $data['expires_in'] ?? 0);
    } else {
        $data = json_decode($response, true);
        echo "  Estado: ❌ ERROR\n";
        echo sprintf("  HTTP Status: %d\n", $http_code);
        echo sprintf("  Error: %s\n", $data['error_description'] ?? $data['message'] ?? 'Desconocido');
        if (isset($data['error'])) {
            echo sprintf("  Código de error: %s\n", $data['error']);
        }
    }
    
    echo "\n";
    
    // Resumen
    echo "📊 RESUMEN:\n";
    echo "───────────────────────────────────────────────────────────────\n";
    
    if ($http_code === 200) {
        echo "  ✅ Las credenciales son VÁLIDAS\n";
        echo sprintf("  ✅ El backend está usando %s\n", $modo === 'live' ? 'PRODUCCIÓN' : 'SANDBOX');
        echo sprintf("  ✅ Conecta a: %s\n", $base_url);
        
        if ($modo === 'sandbox') {
            echo "\n  💡 RECOMENDACIONES PARA SANDBOX:\n";
            echo "     • Crear cuentas de prueba en developer.paypal.com\n";
            echo "     • Usar cuenta Personal de sandbox para pagar\n";
            echo "     • No se requiere tarjeta real\n";
            echo "     • Los pagos son simulados\n";
        } else {
            echo "\n  ⚠️  ADVERTENCIAS PARA LIVE:\n";
            echo "     • Los pagos serán REALES\n";
            echo "     • Se cobrarán a cuentas reales\n";
            echo "     • Requiere HTTPS en producción\n";
            echo "     • Verifica tu cuenta Business\n";
        }
    } else {
        echo "  ❌ ERROR en la conexión\n";
        echo "  ❌ Verifica las credenciales\n";
        echo "  ❌ Asegúrate de usar credenciales del modo correcto\n";
    }
    
} else {
    echo "⚠️  ADVERTENCIA:\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo "  Client ID o Secret no configurados\n";
    echo "  Por favor configura PayPal en el sistema\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n\n";

// Información adicional sobre cómo cambiar el modo
echo "💡 PARA CAMBIAR EL MODO:\n";
echo "───────────────────────────────────────────────────────────────\n";
echo "  1. Ve a Configuración → Configuración de PayPal\n";
echo "  2. Cambia 'Entorno de PayPal'\n";
echo "  3. Usa credenciales correspondientes al modo:\n";
echo "     • Sandbox: developer.paypal.com (Apps & Credentials → Sandbox)\n";
echo "     • Live: developer.paypal.com (Apps & Credentials → Live)\n";
echo "\n";

echo "🔗 ENLACES ÚTILES:\n";
echo "───────────────────────────────────────────────────────────────\n";
echo "  PayPal Developer: https://developer.paypal.com\n";
echo "  Cuentas Sandbox: https://developer.paypal.com/dashboard/accounts\n";
echo "  Mis Apps: https://developer.paypal.com/dashboard/applications\n";
echo "\n";
