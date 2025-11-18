<?php
/**
 * Script CLI para probar la funcionalidad de importación
 * Este script simula la importación sin necesidad de servidor web
 */

// Configurar para mostrar todos los errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== Test de Importación de Empresas ===\n\n";

// Simular sesión
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_rol'] = 'PRESIDENCIA';
$_SESSION['user_nombre'] = 'Test User';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Conexión a base de datos exitosa\n\n";
} catch (Exception $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar estructura de base de datos
echo "=== Verificando Estructura de Base de Datos ===\n";

// Verificar FK actual
$stmt = $db->query("
    SELECT 
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'empresas'
        AND COLUMN_NAME = 'vendedor_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
");
$fk = $stmt->fetch();

if ($fk) {
    echo "FK actual: {$fk['CONSTRAINT_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}\n";
    if ($fk['REFERENCED_TABLE_NAME'] === 'vendedores') {
        echo "⚠ ADVERTENCIA: FK apunta a 'vendedores', se recomienda aplicar migración\n";
        echo "   Archivo: database/migrations/20251118_fix_vendedor_fk_to_usuarios.sql\n";
    } else if ($fk['REFERENCED_TABLE_NAME'] === 'usuarios') {
        echo "✓ FK apunta correctamente a 'usuarios'\n";
    }
} else {
    echo "ℹ No se encontró FK para vendedor_id\n";
}

echo "\n=== Verificando Catálogos ===\n";

// Verificar sectores
$stmt = $db->query("SELECT COUNT(*) as count FROM sectores");
$count = $stmt->fetch()['count'];
echo "Sectores disponibles: $count\n";
if ($count > 0) {
    $stmt = $db->query("SELECT nombre FROM sectores");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['nombre']}\n";
    }
}

// Verificar membresías
$stmt = $db->query("SELECT COUNT(*) as count FROM membresias");
$count = $stmt->fetch()['count'];
echo "\nMembresías disponibles: $count\n";
if ($count > 0) {
    $stmt = $db->query("SELECT nombre FROM membresias WHERE activo = 1");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['nombre']}\n";
    }
}

// Verificar afiliadores
$stmt = $db->query("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'AFILADOR' AND activo = 1");
$count = $stmt->fetch()['count'];
echo "\nAfiliadores disponibles: $count\n";
if ($count > 0) {
    $stmt = $db->query("SELECT id, nombre FROM usuarios WHERE rol = 'AFILADOR' AND activo = 1");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['nombre']} (ID: {$row['id']})\n";
    }
}

echo "\n=== Simulando Importación ===\n";

// Leer archivo CSV
$archivo_csv = __DIR__ . '/test_import.csv';
if (!file_exists($archivo_csv)) {
    echo "✗ Archivo test_import.csv no encontrado\n";
    exit(1);
}

echo "Leyendo archivo: $archivo_csv\n";

$datos = [];
if (($handle = fopen($archivo_csv, 'r')) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ',');
    echo "Columnas encontradas: " . count($headers) . "\n";
    
    $row_num = 0;
    while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
        if (count($row) === count($headers)) {
            $datos[] = array_combine($headers, $row);
            $row_num++;
        }
    }
    fclose($handle);
    echo "Filas de datos: $row_num\n\n";
}

echo "=== Procesando Filas ===\n";

$importados = 0;
$duplicados = 0;
$errores = 0;

foreach ($datos as $idx => $fila) {
    $num = $idx + 1;
    echo "\n--- Fila $num ---\n";
    
    $empresa = sanitize($fila['EMPRESA / RAZON SOCIAL'] ?? '');
    $rfc = sanitize($fila['RFC'] ?? '');
    $vendedor = sanitize($fila['VENDEDOR'] ?? '');
    
    echo "Empresa: $empresa\n";
    echo "RFC: $rfc\n";
    echo "Vendedor: " . ($vendedor ?: '(vacío)') . "\n";
    
    // Validación básica
    if (empty($empresa) || empty($rfc)) {
        echo "✗ ERROR: Faltan datos obligatorios\n";
        $errores++;
        continue;
    }
    
    // Verificar duplicados
    $stmt = $db->prepare("SELECT id FROM empresas WHERE rfc = ?");
    $stmt->execute([$rfc]);
    if ($stmt->fetch()) {
        echo "⚠ DUPLICADO: RFC ya existe\n";
        $duplicados++;
        continue;
    }
    
    // Buscar vendedor
    $vendedor_id = null;
    if (!empty($vendedor)) {
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE nombre = ? AND rol = 'AFILADOR' AND activo = 1 LIMIT 1");
        $stmt->execute([$vendedor]);
        $result = $stmt->fetch();
        if ($result) {
            $vendedor_id = $result['id'];
            echo "✓ Vendedor encontrado (ID: $vendedor_id)\n";
        } else {
            echo "⚠ Vendedor no encontrado, se importará sin vendedor\n";
        }
    }
    
    // Buscar otros catálogos
    $sector = sanitize($fila['SECTOR'] ?? 'Comercio');
    $stmt = $db->prepare("SELECT id FROM sectores WHERE nombre = ? LIMIT 1");
    $stmt->execute([$sector]);
    $sector_id = $stmt->fetch()['id'] ?? null;
    
    $membresia = sanitize($fila['MEMBRESÍA'] ?? $fila['MEMBRESIA'] ?? 'Básica');
    $stmt = $db->prepare("SELECT id FROM membresias WHERE nombre = ? LIMIT 1");
    $stmt->execute([$membresia]);
    $membresia_id = $stmt->fetch()['id'] ?? null;
    
    echo "Sector ID: " . ($sector_id ?? 'NULL') . "\n";
    echo "Membresía ID: " . ($membresia_id ?? 'NULL') . "\n";
    
    // Intentar insertar (simulación - comentar la siguiente línea para hacer la inserción real)
    echo "ℹ Simulación: No se insertará en la base de datos (quitar comentario para insertar)\n";
    
    /*
    try {
        $stmt = $db->prepare("
            INSERT INTO empresas (
                razon_social, rfc, email, telefono, representante,
                direccion_comercial, direccion_fiscal, sector_id, categoria_id,
                membresia_id, tipo_afiliacion, vendedor_id, fecha_renovacion,
                no_recibo, no_factura, engomado, activo, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $empresa,
            $rfc,
            sanitize($fila['EMAIL'] ?? ''),
            sanitize($fila['TELÉFONO'] ?? $fila['TELEFONO'] ?? ''),
            sanitize($fila['REPRESENTANTE'] ?? ''),
            sanitize($fila['DIRECCIÓN COMERCIAL'] ?? $fila['DIRECCION COMERCIAL'] ?? ''),
            sanitize($fila['DIRECCIÓN FISCAL'] ?? $fila['DIRECCION FISCAL'] ?? ''),
            $sector_id,
            null, // categoria_id
            $membresia_id,
            sanitize($fila['TIPO DE AFILIACIÓN'] ?? $fila['TIPO DE AFILIACION'] ?? 'Nueva'),
            $vendedor_id,
            sanitize($fila['FECHA DE RENOVACIÓN'] ?? $fila['FECHA DE RENOVACION'] ?? date('Y-m-d', strtotime('+1 year'))),
            sanitize($fila['No. DE RECIBO'] ?? ''),
            sanitize($fila['No. DE FACTURA'] ?? ''),
            sanitize($fila['ENGOMADO'] ?? '')
        ]);
        
        echo "✓ IMPORTADO exitosamente\n";
        $importados++;
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $errores++;
    }
    */
    
    $importados++; // Para simulación
}

echo "\n=== Resumen ===\n";
echo "Total procesado: " . count($datos) . "\n";
echo "Importados: $importados\n";
echo "Duplicados: $duplicados\n";
echo "Errores: $errores\n";

echo "\n=== Prueba Completada ===\n";
