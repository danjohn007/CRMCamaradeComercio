<?php
/**
 * Helper para integración con PayPal
 * Proporciona funciones para crear órdenes de pago y verificar transacciones
 */

class PayPalHelper {
    private static $client_id;
    private static $secret;
    private static $mode;
    private static $base_url;
    
    /**
     * Inicializar configuración de PayPal
     */
    private static function init() {
        if (self::$client_id === null) {
            $config = getConfiguracion();
            self::$client_id = $config['paypal_client_id'] ?? '';
            self::$secret = $config['paypal_secret'] ?? '';
            self::$mode = $config['paypal_mode'] ?? 'sandbox';
            
            // Set base URL according to mode
            self::$base_url = (self::$mode === 'live') 
                ? 'https://api-m.paypal.com' 
                : 'https://api-m.sandbox.paypal.com';
        }
    }
    
    /**
     * Obtener token de acceso de PayPal
     */
    private static function getAccessToken() {
        self::init();
        
        if (empty(self::$client_id) || empty(self::$secret)) {
            throw new Exception('PayPal credentials not configured');
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$base_url . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, self::$client_id . ':' . self::$secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("PayPal cURL Error: $curl_error");
            throw new Exception('PayPal connection error: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            error_log("PayPal OAuth Error: HTTP $http_code - Mode: " . self::$mode . " - Response: $response");
            $error_data = json_decode($response, true);
            $error_message = $error_data['error_description'] ?? $error_data['message'] ?? 'Failed to get PayPal access token';
            throw new Exception('PayPal authentication error: ' . $error_message);
        }
        
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            error_log("PayPal OAuth Error: No access token in response - $response");
            throw new Exception('Invalid PayPal response: no access token received');
        }
        
        return $data['access_token'];
    }
    
    /**
     * Crear una orden de pago en PayPal
     * 
     * @param string $description Descripción del pago
     * @param float $amount Monto del pago
     * @param string $currency Moneda (default: MXN)
     * @param string $return_url URL de retorno después del pago
     * @param string $cancel_url URL de cancelación
     * @return array Datos de la orden creada
     */
    public static function createOrder($description, $amount, $currency = 'MXN', $return_url = null, $cancel_url = null) {
        self::init();
        
        $access_token = self::getAccessToken();
        
        if (!$return_url) {
            $return_url = BASE_URL . '/api/paypal_success.php';
        }
        if (!$cancel_url) {
            $cancel_url = BASE_URL . '/api/paypal_cancel.php';
        }
        
        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'description' => $description,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ]
                ]
            ],
            'application_context' => [
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
                'brand_name' => getConfiguracion()['nombre_sitio'] ?? 'CRM Cámara de Comercio',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$base_url . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("PayPal cURL Error (Create Order): $curl_error");
            throw new Exception('PayPal connection error: ' . $curl_error);
        }
        
        if ($http_code !== 201) {
            error_log("PayPal Create Order Error: HTTP $http_code - Mode: " . self::$mode . " - Response: $response");
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'Failed to create PayPal order';
            throw new Exception('PayPal order creation error: ' . $error_message);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Capturar un pago autorizado
     * 
     * @param string $order_id ID de la orden de PayPal
     * @return array Datos de la captura
     */
    public static function captureOrder($order_id) {
        self::init();
        
        $access_token = self::getAccessToken();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$base_url . '/v2/checkout/orders/' . $order_id . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("PayPal cURL Error (Capture Order): $curl_error");
            throw new Exception('PayPal connection error: ' . $curl_error);
        }
        
        if ($http_code !== 201) {
            error_log("PayPal Capture Order Error: HTTP $http_code - Mode: " . self::$mode . " - Response: $response");
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'Failed to capture PayPal order';
            throw new Exception('PayPal capture error: ' . $error_message);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Obtener detalles de una orden
     * 
     * @param string $order_id ID de la orden de PayPal
     * @return array Detalles de la orden
     */
    public static function getOrderDetails($order_id) {
        self::init();
        
        $access_token = self::getAccessToken();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$base_url . '/v2/checkout/orders/' . $order_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("PayPal cURL Error (Get Order): $curl_error");
            throw new Exception('PayPal connection error: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            error_log("PayPal Get Order Error: HTTP $http_code - Mode: " . self::$mode . " - Response: $response");
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'Failed to get PayPal order details';
            throw new Exception('PayPal order retrieval error: ' . $error_message);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Verificar si PayPal está configurado
     * 
     * @return bool
     */
    public static function isConfigured() {
        self::init();
        return !empty(self::$client_id) && !empty(self::$secret);
    }
    
    /**
     * Obtener el client ID para uso en frontend
     * 
     * @return string
     */
    public static function getClientId() {
        self::init();
        return self::$client_id;
    }
    
    /**
     * Obtener el modo (sandbox o live)
     * 
     * @return string
     */
    public static function getMode() {
        self::init();
        return self::$mode;
    }
}
