        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12" style="background-color: var(--color-footer, #111827); color: white; border-color: rgba(255, 255, 255, 0.1);">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <?php
                // Obtener nombre del sitio desde configuración (usa caché estática)
                $nombre_sitio_footer = getConfiguracion('nombre_sitio') ?? APP_NAME;
                ?>
                <div class="text-sm mb-4 md:mb-0" style="color: rgba(255, 255, 255, 0.8);">
                    © <?php echo date('Y'); ?> <?php echo e($nombre_sitio_footer); ?>. Todos los derechos reservados.
                </div>
                <div class="flex space-x-6 text-sm">
                    <?php
                    // Obtener términos y privacidad desde configuración
                    try {
                        $db_footer = Database::getInstance()->getConnection();
                        $stmt = $db_footer->query("SELECT clave, valor FROM configuracion WHERE clave IN ('terminos_condiciones', 'politica_privacidad')");
                        $footer_config = [];
                        while ($row = $stmt->fetch()) {
                            $footer_config[$row['clave']] = $row['valor'];
                        }
                        $has_terminos = !empty($footer_config['terminos_condiciones']);
                        $has_privacidad = !empty($footer_config['politica_privacidad']);
                    } catch (Exception $e) {
                        $has_terminos = false;
                        $has_privacidad = false;
                    }
                    ?>
                    <?php if ($has_terminos): ?>
                        <a href="<?php echo BASE_URL; ?>/terminos.php" class="hover:text-blue-300" style="color: rgba(255, 255, 255, 0.8);">Términos y Condiciones</a>
                    <?php endif; ?>
                    <?php if ($has_privacidad): ?>
                        <a href="<?php echo BASE_URL; ?>/privacidad.php" class="hover:text-blue-300" style="color: rgba(255, 255, 255, 0.8);">Política de Privacidad</a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Estrategia Digital -->
            <div class="text-center mt-4 pt-4" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <p class="text-xs" style="color: rgba(255, 255, 255, 0.6);">
                    <?php
                    // Obtener configuración de footer link
                    try {
                        $stmt = $db_footer->query("SELECT clave, valor FROM configuracion WHERE clave IN ('footer_link_text', 'footer_link_url')");
                        $footer_link = ['text' => 'Estrategia Digital desarrollada por ID', 'url' => 'https://impactosdigitales.com'];
                        while ($row = $stmt->fetch()) {
                            if ($row['clave'] === 'footer_link_text' && !empty($row['valor'])) {
                                $footer_link['text'] = $row['valor'];
                            } elseif ($row['clave'] === 'footer_link_url' && !empty($row['valor'])) {
                                $footer_link['url'] = $row['valor'];
                            }
                        }
                        
                        // Reemplazar "ID" con un enlace
                        $text_parts = explode(' ID', $footer_link['text']);
                        if (count($text_parts) > 1) {
                            echo htmlspecialchars($text_parts[0]) . ' <a href="' . htmlspecialchars($footer_link['url']) . '" target="_blank" rel="noopener noreferrer" class="text-blue-300 hover:underline font-semibold">ID</a>' . htmlspecialchars($text_parts[1]);
                        } else {
                            // Si no tiene "ID", usar el enlace completo
                            echo '<a href="' . htmlspecialchars($footer_link['url']) . '" target="_blank" rel="noopener noreferrer" class="text-blue-300 hover:underline">' . htmlspecialchars($footer_link['text']) . '</a>';
                        }
                    } catch (Exception $e) {
                        echo 'Estrategia Digital desarrollada por <a href="https://impactosdigitales.com" target="_blank" rel="noopener noreferrer" class="text-blue-300 hover:underline font-semibold">ID</a>';
                    }
                    ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- Script para el sidebar móvil y dropdown de usuario -->
    <script>
        // Sidebar móvil
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (sidebarToggle && sidebar && overlay) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('hidden');
                overlay.classList.toggle('hidden');
                // Agregar clase para animación
                if (!sidebar.classList.contains('hidden')) {
                    sidebar.style.transform = 'translateX(0)';
                }
            });

            overlay.addEventListener('click', () => {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
            });

            // Cerrar sidebar en móvil al hacer clic en cualquier enlace del menú
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    // Solo en dispositivos móviles (menos de 1024px)
                    if (window.innerWidth < 1024) {
                        sidebar.classList.add('hidden');
                        overlay.classList.add('hidden');
                    }
                });
            });
        }

        // Dropdown de usuario (click en lugar de hover)
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');

        if (userMenuButton && userMenu) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenu.classList.toggle('show');
            });

            // Cerrar al hacer clic fuera
            document.addEventListener('click', (e) => {
                if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.remove('show');
                }
            });
        }
    </script>
</body>
</html>
