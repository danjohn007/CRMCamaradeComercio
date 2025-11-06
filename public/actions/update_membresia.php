<?php
/**
 * Acción: Actualizar Membresía de Empresa
 * Descripción: Endpoint seguro para procesar la actualización de membresía desde la UI
 *              para usuarios con rol ENTIDAD_COMERCIAL o roles administrativos.
 * 
 * Método: POST
 * Parámetros esperados:
 *   - csrf_token: Token CSRF para validación
 *   - empresa_id: ID de la empresa a actualizar
 *   - membresia_id: ID de la nueva membresía
 * 
 * Validaciones:
 *   - Token CSRF válido
 *   - Usuario autenticado con rol apropiado
 *   - Empresa existe y usuario tiene permisos para modificarla
 *   - Membresía solicitada existe y está activa
 * 
 * Acciones:
 *   - Actualiza empresas.membresia_id
 *   - Actualiza empresas.fecha_renovacion según vigencia_meses
 *   - Registra la acción en tabla auditoria
 *   - Redirige con mensaje de éxito/error
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Solo procesar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Método de solicitud no permitido';
    header('Location: ' . BASE_URL . '/mi_membresia.php');
    exit;
}

// Verificar autenticación
requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

try {
    // ==================================================================================
    // 1. VALIDACIÓN CSRF
    // ==================================================================================
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        throw new Exception('Token de seguridad inválido. Por favor, recargue la página e intente nuevamente.');
    }
    
    // ==================================================================================
    // 2. VALIDACIÓN DE PERMISOS DE USUARIO
    // ==================================================================================
    // Verificar que el usuario tiene rol ENTIDAD_COMERCIAL o roles administrativos
    $roles_permitidos = ['ENTIDAD_COMERCIAL', 'EMPRESA_TRACTORA', 'CAPTURISTA', 'AFILADOR', 'CONSEJERO', 'DIRECCION', 'PRESIDENCIA'];
    
    if (!in_array($user['rol'], $roles_permitidos)) {
        throw new Exception('No tiene permisos suficientes para actualizar membresías.');
    }
    
    // ==================================================================================
    // 3. OBTENER Y VALIDAR PARÁMETROS
    // ==================================================================================
    $empresa_id = intval($_POST['empresa_id'] ?? 0);
    $membresia_id = intval($_POST['membresia_id'] ?? 0);
    
    if ($empresa_id <= 0) {
        throw new Exception('ID de empresa inválido.');
    }
    
    if ($membresia_id <= 0) {
        throw new Exception('ID de membresía inválido.');
    }
    
    // ==================================================================================
    // 4. VALIDAR QUE LA EMPRESA EXISTE Y EL USUARIO PUEDE MODIFICARLA
    // ==================================================================================
    $stmt = $db->prepare("
        SELECT e.*, m.nombre as membresia_actual_nombre
        FROM empresas e
        LEFT JOIN membresias m ON e.membresia_id = m.id
        WHERE e.id = ?
    ");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        throw new Exception('La empresa especificada no existe.');
    }
    
    // Verificar permisos: usuarios ENTIDAD_COMERCIAL/EMPRESA_TRACTORA solo pueden actualizar su propia empresa
    $es_usuario_externo = in_array($user['rol'], ['ENTIDAD_COMERCIAL', 'EMPRESA_TRACTORA']);
    $es_su_empresa = ($user['empresa_id'] && intval($user['empresa_id']) === intval($empresa_id));
    
    if ($es_usuario_externo && !$es_su_empresa) {
        throw new Exception('Solo puede actualizar la membresía de su propia empresa.');
    }
    
    // ==================================================================================
    // 5. VALIDAR QUE LA MEMBRESÍA EXISTE Y ESTÁ ACTIVA
    // ==================================================================================
    $stmt = $db->prepare("
        SELECT * FROM membresias 
        WHERE id = ? AND activo = 1
    ");
    $stmt->execute([$membresia_id]);
    $membresia = $stmt->fetch();
    
    if (!$membresia) {
        throw new Exception('La membresía seleccionada no existe o no está disponible.');
    }
    
    // Verificar que no sea la misma membresía actual
    if ($empresa['membresia_id'] !== null && intval($empresa['membresia_id']) === intval($membresia_id)) {
        throw new Exception('La empresa ya tiene esta membresía activa.');
    }
    
    // ==================================================================================
    // 6. INICIAR TRANSACCIÓN Y ACTUALIZAR MEMBRESÍA
    // ==================================================================================
    $db->beginTransaction();
    
    try {
        // Calcular nueva fecha de renovación
        $vigencia_meses = intval($membresia['vigencia_meses'] ?? 12);
        
        // Actualizar empresas.membresia_id y fecha_renovacion
        $stmt = $db->prepare("
            UPDATE empresas 
            SET membresia_id = ?,
                fecha_renovacion = DATE_ADD(CURDATE(), INTERVAL ? MONTH),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$membresia_id, $vigencia_meses, $empresa_id]);
        
        // ==================================================================================
        // 7. REGISTRAR EN AUDITORÍA
        // ==================================================================================
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $datos_auditoria = json_encode([
            'empresa_id' => $empresa_id,
            'razon_social' => $empresa['razon_social'],
            'membresia_anterior_id' => $empresa['membresia_id'],
            'membresia_anterior_nombre' => $empresa['membresia_actual_nombre'],
            'membresia_nueva_id' => $membresia_id,
            'membresia_nueva_nombre' => $membresia['nombre'],
            'vigencia_meses' => $vigencia_meses,
            'fecha_renovacion_nueva' => date('Y-m-d', strtotime("+{$vigencia_meses} months")),
            'accion_origen' => 'update_membresia_form'
        ]);
        
        $stmt = $db->prepare("
            INSERT INTO auditoria 
            (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, ip_address, user_agent)
            VALUES (?, 'UPDATE_MEMBRESIA', 'empresas', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $empresa_id,
            $datos_auditoria,
            $ip_address,
            $user_agent
        ]);
        
        // Commit de la transacción
        $db->commit();
        
        // Establecer mensaje de éxito
        $_SESSION['success_message'] = sprintf(
            'Membresía actualizada exitosamente a "%s". Vigencia hasta %s.',
            htmlspecialchars($membresia['nombre']),
            formatDate(date('Y-m-d', strtotime("+{$vigencia_meses} months")))
        );
        
    } catch (Exception $e) {
        // Rollback en caso de error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Establecer mensaje de error
    $_SESSION['error_message'] = 'Error al actualizar membresía: ' . $e->getMessage();
}

// ==================================================================================
// 8. REDIRIGIR DE VUELTA A MI MEMBRESÍA
// ==================================================================================
header('Location: ' . BASE_URL . '/mi_membresia.php');
exit;
