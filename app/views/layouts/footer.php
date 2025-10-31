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

    <!-- Script para el sidebar móvil -->
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('hidden');
                overlay.classList.toggle('hidden');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
            });
        }
    </script>
</body>
</html>
