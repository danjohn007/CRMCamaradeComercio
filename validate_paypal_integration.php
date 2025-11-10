<?php
/**
 * Script de validación para la integración de PayPal
 * Ejecutar desde la línea de comandos: php validate_paypal_integration.php
 */

echo "=================================================\n";
echo "VALIDACIÓN DE INTEGRACIÓN DE PAYPAL\n";
echo "=================================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar que existan los archivos necesarios
echo "1. Verificando archivos...\n";

$required_files = [
    'app/helpers/paypal.php',
    'api/crear_orden_paypal_evento.php',
    'api/paypal_success_evento.php',
    'database/migration_paypal_configuration.sql',
    'configuracion.php',
    'evento_publico.php',
    'eventos.php',
    'boleto_digital.php'
];

foreach ($required_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $success[] = "✓ Archivo existe: $file";
    } else {
        $errors[] = "✗ Archivo faltante: $file";
    }
}

// 2. Verificar sintaxis de PHP
echo "\n2. Verificando sintaxis de PHP...\n";

foreach ($required_files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && file_exists(__DIR__ . '/' . $file)) {
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            $success[] = "✓ Sintaxis OK: $file";
        } else {
            $errors[] = "✗ Error de sintaxis en: $file";
            $errors[] = "  " . implode("\n  ", $output);
        }
    }
}

// 3. Verificar que se puedan cargar las clases
echo "\n3. Verificando clases PHP...\n";

try {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/app/helpers/paypal.php';
    
    if (class_exists('PayPalHelper')) {
        $success[] = "✓ Clase PayPalHelper cargada correctamente";
        
        // Verificar métodos públicos
        $methods = ['getClientId', 'getMode', 'isConfigured', 'createOrder', 'captureOrder', 'getOrderDetails'];
        foreach ($methods as $method) {
            if (method_exists('PayPalHelper', $method)) {
                $success[] = "✓ Método PayPalHelper::$method() existe";
            } else {
                $errors[] = "✗ Método PayPalHelper::$method() no encontrado";
            }
        }
    } else {
        $errors[] = "✗ No se pudo cargar la clase PayPalHelper";
    }
} catch (Exception $e) {
    $warnings[] = "⚠ No se pudo verificar clases: " . $e->getMessage();
}

// 4. Verificar conexión a base de datos (si es posible)
echo "\n4. Verificando configuración de base de datos...\n";

try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    if ($db) {
        $success[] = "✓ Conexión a base de datos exitosa";
        
        // Verificar si existe la tabla eventos_inscripciones
        $stmt = $db->query("SHOW TABLES LIKE 'eventos_inscripciones'");
        if ($stmt->rowCount() > 0) {
            $success[] = "✓ Tabla eventos_inscripciones existe";
            
            // Verificar columnas de pago
            $payment_columns = ['estado_pago', 'monto_pagado', 'fecha_pago', 'referencia_pago', 'paypal_order_id'];
            $stmt = $db->query("SHOW COLUMNS FROM eventos_inscripciones");
            $existing_columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing_columns[] = $row['Field'];
            }
            
            foreach ($payment_columns as $col) {
                if (in_array($col, $existing_columns)) {
                    $success[] = "✓ Columna eventos_inscripciones.$col existe";
                } else {
                    $warnings[] = "⚠ Columna eventos_inscripciones.$col no existe (ejecutar migración)";
                }
            }
        } else {
            $warnings[] = "⚠ Tabla eventos_inscripciones no existe";
        }
        
        // Verificar configuración de PayPal
        $stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'paypal%'");
        $config_keys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $config_keys[] = $row['clave'];
            if (!empty($row['valor'])) {
                $success[] = "✓ Configuración {$row['clave']} está definida";
            } else {
                $warnings[] = "⚠ Configuración {$row['clave']} está vacía";
            }
        }
        
        $required_config = ['paypal_account', 'paypal_client_id', 'paypal_secret', 'paypal_mode'];
        foreach ($required_config as $key) {
            if (!in_array($key, $config_keys)) {
                $warnings[] = "⚠ Configuración $key no existe en la base de datos (ejecutar migración)";
            }
        }
        
    } else {
        $warnings[] = "⚠ No se pudo conectar a la base de datos";
    }
} catch (Exception $e) {
    $warnings[] = "⚠ Error al verificar base de datos: " . $e->getMessage();
}

// 5. Verificar documentación
echo "\n5. Verificando documentación...\n";

$doc_files = [
    'PAYPAL_INTEGRATION.md',
    'IMPLEMENTATION_SUMMARY_PAYPAL.md'
];

foreach ($doc_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $success[] = "✓ Documentación existe: $file";
    } else {
        $warnings[] = "⚠ Documentación faltante: $file";
    }
}

// Resumen
echo "\n=================================================\n";
echo "RESUMEN DE VALIDACIÓN\n";
echo "=================================================\n\n";

echo "✓ ÉXITOS: " . count($success) . "\n";
foreach ($success as $msg) {
    echo "  $msg\n";
}

if (count($warnings) > 0) {
    echo "\n⚠ ADVERTENCIAS: " . count($warnings) . "\n";
    foreach ($warnings as $msg) {
        echo "  $msg\n";
    }
}

if (count($errors) > 0) {
    echo "\n✗ ERRORES: " . count($errors) . "\n";
    foreach ($errors as $msg) {
        echo "  $msg\n";
    }
}

echo "\n=================================================\n";

if (count($errors) > 0) {
    echo "ESTADO: ✗ FALLÓ LA VALIDACIÓN\n";
    echo "Por favor corrige los errores antes de continuar.\n";
    exit(1);
} elseif (count($warnings) > 0) {
    echo "ESTADO: ⚠ VALIDACIÓN CON ADVERTENCIAS\n";
    echo "La integración está lista pero hay algunos elementos pendientes.\n";
    echo "Ejecuta la migración de base de datos y configura PayPal.\n";
    exit(0);
} else {
    echo "ESTADO: ✓ VALIDACIÓN EXITOSA\n";
    echo "¡La integración de PayPal está completamente lista!\n";
    exit(0);
}
