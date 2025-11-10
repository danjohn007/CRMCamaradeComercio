<?php
/**
 * Script de diagnóstico para PayPal
 * Verifica la configuración y conexión con PayPal
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers/paypal.php';

requireLogin();

// Solo para admin
$user = getCurrentUser();
if ($user['rol'] !== 'PRESIDENCIA') {
    die('Acceso denegado');
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico PayPal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Diagnóstico de PayPal</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Configuración</h2>
            <?php
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'paypal%'");
            $config_paypal = [];
            while ($row = $stmt->fetch()) {
                $config_paypal[$row['clave']] = $row['valor'];
            }
            ?>
            
            <table class="w-full">
                <tr>
                    <td class="font-semibold py-2">Client ID:</td>
                    <td class="py-2">
                        <?php if (!empty($config_paypal['paypal_client_id'])): ?>
                            <span class="text-green-600">✓ Configurado</span>
                            <code class="text-xs"><?php echo substr($config_paypal['paypal_client_id'], 0, 20); ?>...</code>
                        <?php else: ?>
                            <span class="text-red-600">✗ No configurado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="font-semibold py-2">Secret:</td>
                    <td class="py-2">
                        <?php if (!empty($config_paypal['paypal_secret'])): ?>
                            <span class="text-green-600">✓ Configurado</span>
                        <?php else: ?>
                            <span class="text-red-600">✗ No configurado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="font-semibold py-2">Modo:</td>
                    <td class="py-2">
                        <span class="<?php echo ($config_paypal['paypal_mode'] ?? 'sandbox') === 'live' ? 'text-red-600' : 'text-blue-600'; ?>">
                            <?php echo strtoupper($config_paypal['paypal_mode'] ?? 'sandbox'); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="font-semibold py-2">Cuenta:</td>
                    <td class="py-2"><?php echo $config_paypal['paypal_account'] ?? 'No configurada'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Prueba de Conexión</h2>
            <?php
            try {
                if (PayPalHelper::isConfigured()) {
                    echo '<p class="text-green-600 font-semibold">✓ PayPal está configurado</p>';
                    
                    // Intentar obtener un token de acceso
                    try {
                        $reflection = new ReflectionClass('PayPalHelper');
                        $method = $reflection->getMethod('getAccessToken');
                        $method->setAccessible(true);
                        $token = $method->invoke(null);
                        
                        echo '<p class="text-green-600 mt-2">✓ Token de acceso obtenido exitosamente</p>';
                        echo '<p class="text-xs text-gray-600 mt-1">Token: ' . substr($token, 0, 30) . '...</p>';
                    } catch (Exception $e) {
                        echo '<p class="text-red-600 mt-2">✗ Error al obtener token: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                } else {
                    echo '<p class="text-red-600 font-semibold">✗ PayPal no está configurado correctamente</p>';
                    echo '<p class="text-gray-600 mt-2">Por favor, configure el Client ID y Secret en Configuración del Sistema</p>';
                }
            } catch (Exception $e) {
                echo '<p class="text-red-600">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Prueba de Botón PayPal</h2>
            <p class="text-gray-600 mb-4">Si el botón de PayPal aparece a continuación, la integración del SDK está funcionando:</p>
            
            <div id="paypal-test-button"></div>
            <div id="test-message" class="mt-4"></div>
        </div>
        
        <div class="mt-6">
            <a href="<?php echo BASE_URL; ?>/configuracion.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Ir a Configuración
            </a>
            <a href="<?php echo BASE_URL; ?>/mi_membresia.php" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 ml-2">
                Ver Mi Membresía
            </a>
        </div>
    </div>
    
    <?php if (!empty($config_paypal['paypal_client_id'])): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $config_paypal['paypal_client_id']; ?>&currency=MXN"></script>
    <script>
        if (typeof paypal !== 'undefined') {
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: '10.00',
                                currency_code: 'MXN'
                            },
                            description: 'Prueba de PayPal'
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    document.getElementById('test-message').innerHTML = 
                        '<div class="bg-green-50 border-l-4 border-green-500 p-4">' +
                        '<p class="text-green-700">✓ PayPal está funcionando correctamente (no se procesó el pago, solo prueba)</p>' +
                        '</div>';
                },
                onError: function(err) {
                    document.getElementById('test-message').innerHTML = 
                        '<div class="bg-red-50 border-l-4 border-red-500 p-4">' +
                        '<p class="text-red-700">✗ Error en PayPal: ' + err + '</p>' +
                        '</div>';
                }
            }).render('#paypal-test-button');
        } else {
            document.getElementById('test-message').innerHTML = 
                '<div class="bg-red-50 border-l-4 border-red-500 p-4">' +
                '<p class="text-red-700">✗ El SDK de PayPal no se cargó correctamente</p>' +
                '</div>';
        }
    </script>
    <?php else: ?>
    <script>
        document.getElementById('test-message').innerHTML = 
            '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">' +
            '<p class="text-yellow-700">⚠ PayPal no está configurado. Por favor configure el Client ID en Configuración.</p>' +
            '</div>';
    </script>
    <?php endif; ?>
</body>
</html>
