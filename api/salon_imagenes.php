<?php
/**
 * API para gestión de imágenes de salones
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requirePermission('DIRECCION');

header('Content-Type: application/json');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'upload':
            // Subir una o varias imágenes para un salón
            $salon_id = intval($_POST['salon_id'] ?? 0);
            $descripcion = sanitize($_POST['descripcion'] ?? '');
            
            if (!$salon_id) {
                throw new Exception('ID de salón requerido');
            }
            
            // Verificar que el salón existe
            $stmt = $db->prepare("SELECT id FROM salones WHERE id = ?");
            $stmt->execute([$salon_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Salón no encontrado');
            }
            
            // Verificar que se subió un archivo
            if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al subir la imagen');
            }
            
            // Validar tipo de archivo
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception('Solo se permiten imágenes JPG, PNG o WEBP');
            }
            
            // Validar tamaño (máx 5MB)
            if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
                throw new Exception('La imagen es demasiado grande. Tamaño máximo: 5MB');
            }
            
            // Crear directorio si no existe
            $upload_dir = UPLOAD_PATH . '/salones/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generar nombre único
            $file_extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $new_filename = 'salon_' . $salon_id . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_path)) {
                throw new Exception('Error al guardar la imagen');
            }
            
            $ruta_imagen = '/public/uploads/salones/' . $new_filename;
            
            // Obtener el siguiente orden
            $stmt = $db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden FROM salon_imagenes WHERE salon_id = ?");
            $stmt->execute([$salon_id]);
            $orden = $stmt->fetch()['siguiente_orden'];
            
            // Guardar en base de datos
            $stmt = $db->prepare("INSERT INTO salon_imagenes (salon_id, ruta_imagen, descripcion, orden) VALUES (?, ?, ?, ?)");
            $stmt->execute([$salon_id, $ruta_imagen, $descripcion, $orden]);
            
            $imagen_id = $db->lastInsertId();
            
            // Si es la primera imagen, marcarla como principal
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM salon_imagenes WHERE salon_id = ?");
            $stmt->execute([$salon_id]);
            if ($stmt->fetch()['total'] == 1) {
                $stmt = $db->prepare("UPDATE salon_imagenes SET es_principal = 1 WHERE id = ?");
                $stmt->execute([$imagen_id]);
            }
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'UPLOAD_SALON_IMAGEN', 'salon_imagenes', ?)");
            $stmt->execute([$user['id'], $imagen_id]);
            
            $response = [
                'success' => true,
                'message' => 'Imagen subida exitosamente',
                'imagen_id' => $imagen_id,
                'ruta_imagen' => $ruta_imagen
            ];
            break;
            
        case 'delete':
            // Eliminar una imagen
            $imagen_id = intval($_POST['imagen_id'] ?? 0);
            
            if (!$imagen_id) {
                throw new Exception('ID de imagen requerido');
            }
            
            // Obtener información de la imagen
            $stmt = $db->prepare("SELECT * FROM salon_imagenes WHERE id = ?");
            $stmt->execute([$imagen_id]);
            $imagen = $stmt->fetch();
            
            if (!$imagen) {
                throw new Exception('Imagen no encontrada');
            }
            
            // Eliminar archivo físico
            $file_path = ROOT_PATH . $imagen['ruta_imagen'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Eliminar de base de datos
            $stmt = $db->prepare("DELETE FROM salon_imagenes WHERE id = ?");
            $stmt->execute([$imagen_id]);
            
            // Si era la imagen principal, asignar otra como principal
            if ($imagen['es_principal']) {
                $stmt = $db->prepare("SELECT id FROM salon_imagenes WHERE salon_id = ? ORDER BY orden ASC LIMIT 1");
                $stmt->execute([$imagen['salon_id']]);
                $nueva_principal = $stmt->fetch();
                
                if ($nueva_principal) {
                    $stmt = $db->prepare("UPDATE salon_imagenes SET es_principal = 1 WHERE id = ?");
                    $stmt->execute([$nueva_principal['id']]);
                }
            }
            
            // Registrar en auditoría
            $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'DELETE_SALON_IMAGEN', 'salon_imagenes', ?)");
            $stmt->execute([$user['id'], $imagen_id]);
            
            $response = [
                'success' => true,
                'message' => 'Imagen eliminada exitosamente'
            ];
            break;
            
        case 'set_principal':
            // Marcar una imagen como principal
            $imagen_id = intval($_POST['imagen_id'] ?? 0);
            
            if (!$imagen_id) {
                throw new Exception('ID de imagen requerido');
            }
            
            // Obtener información de la imagen
            $stmt = $db->prepare("SELECT salon_id FROM salon_imagenes WHERE id = ?");
            $stmt->execute([$imagen_id]);
            $imagen = $stmt->fetch();
            
            if (!$imagen) {
                throw new Exception('Imagen no encontrada');
            }
            
            // Quitar principal de todas las imágenes del salón
            $stmt = $db->prepare("UPDATE salon_imagenes SET es_principal = 0 WHERE salon_id = ?");
            $stmt->execute([$imagen['salon_id']]);
            
            // Marcar la seleccionada como principal
            $stmt = $db->prepare("UPDATE salon_imagenes SET es_principal = 1 WHERE id = ?");
            $stmt->execute([$imagen_id]);
            
            $response = [
                'success' => true,
                'message' => 'Imagen principal actualizada'
            ];
            break;
            
        case 'list':
            // Listar imágenes de un salón
            $salon_id = intval($_GET['salon_id'] ?? 0);
            
            if (!$salon_id) {
                throw new Exception('ID de salón requerido');
            }
            
            $stmt = $db->prepare("SELECT * FROM salon_imagenes WHERE salon_id = ? ORDER BY orden ASC");
            $stmt->execute([$salon_id]);
            $imagenes = $stmt->fetchAll();
            
            $response = [
                'success' => true,
                'imagenes' => $imagenes
            ];
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
