<?php
/**
 * Script simple de diagnóstico PayPal
 * No requiere login, solo muestra configuración básica
 */

// Mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Diagnóstico PayPal Simple</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;border-radius:8px;margin:10px 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:#16a34a;} .error{color:#dc2626;} .warning{color:#ea580c;}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:3px;}</style></head><body>";

echo "<h1>🔍 Diagnóstico Simple de PayPal</h1>";

try {
    // Cargar configuración de base de datos directamente
    require_once __DIR__ . '/config/database.php';
    
    echo "<div class='box'><h2>✅ Paso 1: Conexión a Base de Datos</h2>";
    
    $db = Database::getInstance()->getConnection();
    echo "<p class='success'>✓ Conectado a la base de datos correctamente</p>";
    
    // Leer configuración de PayPal
    echo "</div><div class='box'><h2>📋 Paso 2: Configuración de PayPal</h2>";
    
    $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'paypal%'");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
    
    $modo = $config['paypal_mode'] ?? 'No configurado';
    $client_id = $config['paypal_client_id'] ?? '';
    $secret = $config['paypal_secret'] ?? '';
    
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='border-bottom:1px solid #ddd;'><td style='padding:10px;'><strong>Modo:</strong></td><td style='padding:10px;'>";
    
    if ($modo === 'sandbox') {
        echo "<span class='success' style='background:#dcfce7;padding:4px 12px;border-radius:4px;'>🧪 SANDBOX (Pruebas)</span>";
    } elseif ($modo === 'live') {
        echo "<span class='error' style='background:#fee2e2;padding:4px 12px;border-radius:4px;'>⚠️ LIVE (Producción)</span>";
    } else {
        echo "<span class='warning'>No configurado</span>";
    }
    
    echo "</td></tr>";
    
    echo "<tr style='border-bottom:1px solid #ddd;'><td style='padding:10px;'><strong>Client ID:</strong></td><td style='padding:10px;'>";
    if (!empty($client_id)) {
        echo "<code>" . substr($client_id, 0, 20) . "..." . substr($client_id, -10) . "</code> ";
        echo "<span class='success'>✓</span>";
    } else {
        echo "<span class='error'>✗ No configurado</span>";
    }
    echo "</td></tr>";
    
    echo "<tr style='border-bottom:1px solid #ddd;'><td style='padding:10px;'><strong>Secret:</strong></td><td style='padding:10px;'>";
    if (!empty($secret)) {
        echo "<code>••••••••••••••••</code> <span class='success'>✓</span>";
    } else {
        echo "<span class='error'>✗ No configurado</span>";
    }
    echo "</td></tr>";
    
    echo "<tr><td style='padding:10px;'><strong>Cuenta PayPal:</strong></td><td style='padding:10px;'>";
    echo htmlspecialchars($config['paypal_account'] ?? 'No configurada');
    echo "</td></tr>";
    echo "</table>";
    
    // Determinar URL de API
    echo "</div><div class='box'><h2>🌐 Paso 3: Servidor API de PayPal</h2>";
    
    $base_url = ($modo === 'live') 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    echo "<p><strong>URL Base:</strong> <code>$base_url</code></p>";
    echo "<p><strong>Entorno:</strong> ";
    
    if ($modo === 'sandbox') {
        echo "<span class='success'>✓ Conectará a SANDBOX (entorno de pruebas)</span>";
    } elseif ($modo === 'live') {
        echo "<span class='error'>⚠️ Conectará a LIVE (entorno de producción)</span>";
    }
    
    echo "</p>";
    
    // Probar conexión
    if (!empty($client_id) && !empty($secret)) {
        echo "</div><div class='box'><h2>🔌 Paso 4: Prueba de Conexión</h2>";
        echo "<p>Intentando obtener token de acceso...</p>";
        
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $start = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        echo "<p><strong>URL utilizada:</strong> <code>$url_oauth</code></p>";
        echo "<p><strong>Tiempo de respuesta:</strong> {$duration} ms</p>";
        
        if ($curl_error) {
            echo "<p class='error'><strong>✗ Error de cURL:</strong> $curl_error</p>";
        } elseif ($http_code === 200) {
            $data = json_decode($response, true);
            echo "<p class='success'><strong>✅ CONEXIÓN EXITOSA</strong></p>";
            echo "<p>HTTP Status: <code>200 OK</code></p>";
            echo "<p>Token obtenido: <code>" . substr($data['access_token'] ?? '', 0, 30) . "...</code></p>";
            echo "<p>Expira en: " . ($data['expires_in'] ?? 0) . " segundos</p>";
            
            // Resumen final
            echo "</div><div class='box' style='background:#dcfce7;border:2px solid #16a34a;'>";
            echo "<h2 style='color:#16a34a;'>✅ Resumen: PayPal Configurado Correctamente</h2>";
            echo "<ul style='line-height:1.8;'>";
            echo "<li>✓ Base de datos conectada</li>";
            echo "<li>✓ Credenciales de PayPal válidas</li>";
            echo "<li>✓ Conecta a: <strong>$base_url</strong></li>";
            echo "<li>✓ Modo activo: <strong>" . strtoupper($modo) . "</strong></li>";
            
            if ($modo === 'sandbox') {
                echo "<li>💡 Usa cuentas de prueba de PayPal Developer para pagar</li>";
                echo "<li>💡 No se requiere tarjeta real</li>";
            } else {
                echo "<li>⚠️ Los pagos serán REALES</li>";
                echo "<li>⚠️ Requiere HTTPS en producción</li>";
            }
            
            echo "</ul></div>";
            
        } else {
            $data = json_decode($response, true);
            echo "<p class='error'><strong>✗ ERROR DE CONEXIÓN</strong></p>";
            echo "<p>HTTP Status: <code class='error'>$http_code</code></p>";
            
            if ($data) {
                echo "<p><strong>Error:</strong> " . htmlspecialchars($data['error_description'] ?? $data['message'] ?? 'Desconocido') . "</p>";
                if (isset($data['error'])) {
                    echo "<p><strong>Código:</strong> <code>" . htmlspecialchars($data['error']) . "</code></p>";
                }
            }
            
            echo "<p><strong>Respuesta completa:</strong></p>";
            echo "<pre style='background:#f3f4f6;padding:10px;border-radius:4px;overflow-x:auto;'>";
            echo htmlspecialchars($response);
            echo "</pre>";
            
            // Sugerencias
            echo "</div><div class='box' style='background:#fee2e2;border:2px solid #dc2626;'>";
            echo "<h2 style='color:#dc2626;'>❌ Problemas Detectados</h2>";
            echo "<p>Posibles causas:</p><ul style='line-height:1.8;'>";
            echo "<li>Las credenciales no son válidas</li>";
            echo "<li>Estás usando credenciales de <strong>SANDBOX</strong> con modo <strong>LIVE</strong> (o viceversa)</li>";
            echo "<li>La cuenta de PayPal no está activa o verificada</li>";
            echo "</ul>";
            echo "<p><strong>Solución:</strong> Ve a <a href='https://developer.paypal.com/dashboard/applications' target='_blank'>PayPal Developer</a> y verifica tus credenciales</p>";
            echo "</div>";
        }
        
    } else {
        echo "</div><div class='box' style='background:#fef3c7;border:2px solid #ea580c;'>";
        echo "<h2 style='color:#ea580c;'>⚠️ PayPal No Configurado</h2>";
        echo "<p>Client ID o Secret no están configurados en el sistema.</p>";
        echo "<p><strong>Acción requerida:</strong> Configura PayPal en el sistema</p>";
        echo "</div>";
    }
    
    // Enlaces útiles
    echo "<div class='box'><h2>🔗 Enlaces Útiles</h2>";
    echo "<ul style='line-height:2;'>";
    echo "<li><a href='https://developer.paypal.com' target='_blank'>PayPal Developer Dashboard</a></li>";
    echo "<li><a href='https://developer.paypal.com/dashboard/applications' target='_blank'>Mis Aplicaciones y Credenciales</a></li>";
    echo "<li><a href='https://developer.paypal.com/dashboard/accounts' target='_blank'>Cuentas de Prueba (Sandbox)</a></li>";
    echo "<li><a href='configuracion.php'>Configuración del Sistema</a></li>";
    echo "</ul></div>";
    
} catch (Exception $e) {
    echo "</div><div class='box' style='background:#fee2e2;border:2px solid #dc2626;'>";
    echo "<h2 style='color:#dc2626;'>❌ Error Fatal</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre style='background:#f3f4f6;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre></div>";
}

echo "</body></html>";
?>
