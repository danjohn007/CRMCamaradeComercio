<?php
/**
 * Script para actualizar el estado de empresas basado en la fecha de vencimiento de membresía
 * Este script debe ejecutarse diariamente vía cron
 * 
 * Cron example: 0 2 * * * /usr/bin/php /path/to/actualizar_estado_empresas.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance()->getConnection();

try {
    // Actualizar empresas cuya membresía ha vencido a INACTIVO (activo = 0)
    // Una empresa está vencida si: fecha_renovacion + vigencia_meses < HOY
    $sql = "
        UPDATE empresas e
        LEFT JOIN membresias m ON e.membresia_id = m.id
        SET e.activo = 0
        WHERE e.activo = 1
        AND e.fecha_renovacion IS NOT NULL
        AND m.vigencia_meses IS NOT NULL
        AND DATE_ADD(e.fecha_renovacion, INTERVAL m.vigencia_meses MONTH) < CURDATE()
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $empresas_inactivadas = $stmt->rowCount();
    
    echo date('Y-m-d H:i:s') . " - Empresas inactivadas por vencimiento: {$empresas_inactivadas}\n";
    
    // Log opcional: registrar las empresas que fueron inactivadas
    if ($empresas_inactivadas > 0) {
        $logFile = __DIR__ . '/../../logs/empresas_inactivadas.log';
        $logDir = dirname($logFile);
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(
            $logFile,
            date('Y-m-d H:i:s') . " - {$empresas_inactivadas} empresas inactivadas por vencimiento de membresía\n",
            FILE_APPEND
        );
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Error en actualizar_estado_empresas.php: " . $e->getMessage());
}
