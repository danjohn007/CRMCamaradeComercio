<?php
session_start();
require_once 'config/database.php';
require_once 'app/helpers/functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Solo PRESIDENCIA y Dirección pueden gestionar usuarios
if ($_SESSION['user_role'] !== 'PRESIDENCIA' && $_SESSION['user_role'] !== 'Dirección') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $nombre = sanitize($_POST['nombre']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $rol = sanitize($_POST['rol']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validar que no exista el email
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "El email ya está registrado en el sistema.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssi", $nombre, $email, $password_hash, $rol, $activo);
        
        if ($stmt->execute()) {
            registrarAuditoria($conn, $_SESSION['user_id'], 'CREATE', 'usuarios', $stmt->insert_id, 
                "Nuevo usuario: $nombre ($rol)");
            $message = "Usuario creado exitosamente.";
        } else {
            $error = "Error al crear usuario: " . $conn->error;
        }
    }
}

// Actualizar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $id = intval($_POST['id']);
    $nombre = sanitize($_POST['nombre']);
    $email = sanitize($_POST['email']);
    $rol = sanitize($_POST['rol']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validar que no exista el email en otro usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "El email ya está registrado en otro usuario.";
    } else {
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ?, activo = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $nombre, $email, $password_hash, $rol, $activo, $id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?");
            $stmt->bind_param("sssii", $nombre, $email, $rol, $activo, $id);
        }
        
        if ($stmt->execute()) {
            registrarAuditoria($conn, $_SESSION['user_id'], 'UPDATE', 'usuarios', $id, 
                "Usuario actualizado: $nombre");
            $message = "Usuario actualizado exitosamente.";
        } else {
            $error = "Error al actualizar usuario: " . $conn->error;
        }
    }
}

// Eliminar usuario
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // No permitir eliminar al usuario actual
    if ($id === $_SESSION['user_id']) {
        $error = "No puedes eliminar tu propio usuario.";
    } else {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            registrarAuditoria($conn, $_SESSION['user_id'], 'DELETE', 'usuarios', $id, 
                "Usuario eliminado ID: $id");
            $message = "Usuario eliminado exitosamente.";
        } else {
            $error = "Error al eliminar usuario: " . $conn->error;
        }
    }
    $action = 'list';
}

// Obtener usuario para editar
$usuario_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usuario_edit = $stmt->get_result()->fetch_assoc();
}

// Listar usuarios con filtros
$where = "1=1";
$params = [];
$types = "";

if (!empty($_GET['search'])) {
    $search = "%" . sanitize($_GET['search']) . "%";
    $where .= " AND (nombre LIKE ? OR email LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

if (!empty($_GET['rol'])) {
    $where .= " AND rol = ?";
    $params[] = sanitize($_GET['rol']);
    $types .= "s";
}

if (isset($_GET['activo']) && $_GET['activo'] !== '') {
    $where .= " AND activo = ?";
    $params[] = intval($_GET['activo']);
    $types .= "i";
}

$query = "SELECT * FROM usuarios WHERE $where ORDER BY fecha_registro DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$roles_disponibles = ['PRESIDENCIA', 'Dirección', 'Consejeros', 'Afiladores', 'Capturistas', 'Entidad Comercial', 'Empresa Tractora'];

include 'app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Gestión de Usuarios</h1>
        <?php if ($action === 'list'): ?>
        <a href="?action=new" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
            + Nuevo Usuario
        </a>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Filtros</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="search" placeholder="Buscar por nombre o email" 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       class="border border-gray-300 rounded-lg px-4 py-2">
                
                <select name="rol" class="border border-gray-300 rounded-lg px-4 py-2">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles_disponibles as $rol): ?>
                        <option value="<?= $rol ?>" <?= (($_GET['rol'] ?? '') === $rol) ? 'selected' : '' ?>>
                            <?= $rol ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="activo" class="border border-gray-300 rounded-lg px-4 py-2">
                    <option value="">Todos los estados</option>
                    <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>>Activos</option>
                    <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>>Inactivos</option>
                </select>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Filtrar
                </button>
            </form>
        </div>

        <!-- Tabla de usuarios -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Último acceso</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($usuarios as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900"><?= htmlspecialchars($user['nombre']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= htmlspecialchars($user['email']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($user['rol']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($user['activo']): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Activo</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('d/m/Y', strtotime($user['fecha_registro'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= $user['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($user['ultimo_acceso'])) : 'Nunca' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="?action=edit&id=<?= $user['id'] ?>" 
                               class="text-blue-600 hover:text-blue-900 mr-3">Editar</a>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <a href="?action=delete&id=<?= $user['id'] ?>" 
                               onclick="return confirm('¿Estás seguro de eliminar este usuario?')"
                               class="text-red-600 hover:text-red-900">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($usuarios)): ?>
                <div class="text-center py-8 text-gray-500">
                    No se encontraron usuarios
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($action === 'new' || $action === 'edit'): ?>
        <!-- Formulario de crear/editar usuario -->
        <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
            <h2 class="text-2xl font-semibold mb-6">
                <?= $action === 'new' ? 'Nuevo Usuario' : 'Editar Usuario' ?>
            </h2>

            <form method="POST" action="?action=<?= $action === 'new' ? 'create' : 'update' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $usuario_edit['id'] ?>">
                <?php endif; ?>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Nombre completo *</label>
                    <input type="text" name="nombre" required
                           value="<?= htmlspecialchars($usuario_edit['nombre'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Email *</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($usuario_edit['email'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">
                        Contraseña <?= $action === 'edit' ? '(dejar en blanco para no cambiar)' : '*' ?>
                    </label>
                    <input type="password" name="password" <?= $action === 'new' ? 'required' : '' ?>
                           class="w-full border border-gray-300 rounded-lg px-4 py-2">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Rol *</label>
                    <select name="rol" required class="w-full border border-gray-300 rounded-lg px-4 py-2">
                        <option value="">Seleccionar rol</option>
                        <?php foreach ($roles_disponibles as $rol): ?>
                            <option value="<?= $rol ?>" 
                                    <?= (($usuario_edit['rol'] ?? '') === $rol) ? 'selected' : '' ?>>
                                <?= $rol ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" value="1"
                               <?= ($usuario_edit['activo'] ?? 1) ? 'checked' : '' ?>
                               class="mr-2">
                        <span class="text-gray-700">Usuario activo</span>
                    </label>
                </div>

                <div class="flex gap-4">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        <?= $action === 'new' ? 'Crear Usuario' : 'Actualizar Usuario' ?>
                    </button>
                    <a href="usuarios.php" 
                       class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'app/views/layouts/footer.php'; ?>
