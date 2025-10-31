<?php
if (!isLoggedIn()) {
    redirect('/login.php');
}
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        @media (max-width: 768px) {
            .sidebar.hidden {
                transform: translateX(-100%);
            }
        }
        /* Dropdown menu fix */
        .dropdown-menu {
            display: none;
        }
        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navegación superior -->
    <nav class="bg-white shadow-lg fixed w-full top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <!-- Logo y nombre -->
                <div class="flex items-center space-x-4">
                    <button id="sidebarToggle" class="lg:hidden text-gray-600 hover:text-gray-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="flex items-center space-x-2">
                        <div class="bg-blue-600 text-white rounded-lg p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <span class="font-bold text-xl text-gray-800 hidden md:block"><?php echo APP_NAME; ?></span>
                    </a>
                </div>

                <!-- Menú derecha -->
                <div class="flex items-center space-x-4">
                    <!-- Búsqueda rápida -->
                    <div class="hidden md:block">
                        <form action="<?php echo BASE_URL; ?>/buscar.php" method="GET" class="relative">
                            <input 
                                type="text" 
                                name="q" 
                                placeholder="Buscar..."
                                class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </form>
                    </div>

                    <!-- Notificaciones -->
                    <a href="<?php echo BASE_URL; ?>/notificaciones.php" class="relative text-gray-600 hover:text-gray-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">3</span>
                    </a>

                    <!-- Usuario -->
                    <div class="relative dropdown">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-gray-900">
                            <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center font-semibold">
                                <?php echo strtoupper(substr($user['nombre'] ?: 'U', 0, 1)); ?>
                            </div>
                            <span class="hidden md:block"><?php echo e($user['nombre'] ?: 'Usuario'); ?></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50">
                            <a href="<?php echo BASE_URL; ?>/perfil.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Mi Perfil
                            </a>
                            <?php if (hasPermission('PRESIDENCIA')): ?>
                            <a href="<?php echo BASE_URL; ?>/configuracion.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i>Configuración
                            </a>
                            <?php endif; ?>
                            <hr class="my-2">
                            <a href="<?php echo BASE_URL; ?>/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenedor principal con sidebar -->
    <div class="flex pt-16">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar fixed lg:static w-64 h-screen bg-white shadow-lg overflow-y-auto z-40">
            <div class="p-4">
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600">Rol del usuario</p>
                    <p class="font-semibold text-blue-600"><?php echo e($user['rol']); ?></p>
                </div>

                <nav class="space-y-2">
                    <!-- Dashboard -->
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition">
                        <i class="fas fa-home w-5"></i>
                        <span>Dashboard</span>
                    </a>

                    <!-- Empresas -->
                    <?php if (hasPermission('CAPTURISTA')): ?>
                    <a href="<?php echo BASE_URL; ?>/empresas.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition">
                        <i class="fas fa-building w-5"></i>
                        <span>Empresas</span>
                    </a>
                    <?php endif; ?>

                    <!-- Eventos -->
                    <a href="<?php echo BASE_URL; ?>/eventos.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Eventos</span>
                    </a>

                    <!-- Requerimientos -->
                    <a href="<?php echo BASE_URL; ?>/requerimientos.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Requerimientos</span>
                    </a>

                    <!-- Reportes -->
                    <?php if (hasPermission('CONSEJERO')): ?>
                    <a href="<?php echo BASE_URL; ?>/reportes.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Reportes</span>
                    </a>
                    <?php endif; ?>

                    <!-- Catálogos -->
                    <?php if (hasPermission('DIRECCION')): ?>
                    <div class="pt-4 mt-4 border-t border-gray-200">
                        <p class="px-4 text-xs font-semibold text-gray-400 uppercase mb-2">Administración</p>
                        
                        <a href="<?php echo BASE_URL; ?>/catalogos/membresias.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition menu-link">
                            <i class="fas fa-tags w-5"></i>
                            <span>Membresías</span>
                        </a>
                        
                        <a href="<?php echo BASE_URL; ?>/catalogos/categorias.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition menu-link">
                            <i class="fas fa-list w-5"></i>
                            <span>Categorías</span>
                        </a>

                        <a href="<?php echo BASE_URL; ?>/usuarios.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition menu-link">
                            <i class="fas fa-users w-5"></i>
                            <span>Usuarios</span>
                        </a>

                        <a href="<?php echo BASE_URL; ?>/importar.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition menu-link">
                            <i class="fas fa-file-import w-5"></i>
                            <span>Importar Datos</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Configuración -->
                    <?php if (hasPermission('PRESIDENCIA')): ?>
                    <a href="<?php echo BASE_URL; ?>/configuracion.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition menu-link">
                        <i class="fas fa-cog w-5"></i>
                        <span>Configuración</span>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </aside>

        <!-- Contenido principal -->
        <main class="flex-1 min-h-screen lg:ml-0">
            <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>
