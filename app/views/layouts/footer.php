        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-gray-600 text-sm mb-4 md:mb-0">
                    © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.
                </div>
                <div class="flex space-x-6 text-sm text-gray-600">
                    <a href="#" class="hover:text-blue-600">Términos y Condiciones</a>
                    <a href="#" class="hover:text-blue-600">Política de Privacidad</a>
                    <a href="#" class="hover:text-blue-600">Soporte</a>
                </div>
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
