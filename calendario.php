<?php
/**
 * Calendario global de eventos y renovaciones
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Determinar si es usuario interno o externo
$roles_internos = ['PRESIDENCIA', 'DIRECCION', 'CONSEJERO', 'AFILADOR', 'CAPTURISTA'];
$es_interno = in_array($user['rol'], $roles_internos);

include __DIR__ . '/app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <?php echo $es_interno ? 'Calendario Global' : 'Mi Calendario'; ?>
        </h1>
        <p class="text-gray-600 mt-2">
            <?php echo $es_interno 
                ? 'Eventos y renovaciones de todas las empresas' 
                : 'Mis eventos y renovaciones'; ?>
        </p>
    </div>

    <!-- Filtros de calendario -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center space-x-2">
                <input type="checkbox" id="mostrarEventos" checked 
                       class="form-checkbox h-5 w-5 text-blue-600">
                <span class="text-gray-700">Eventos</span>
            </label>
            
            <?php if ($es_interno): ?>
            <label class="flex items-center space-x-2">
                <input type="checkbox" id="mostrarRenovaciones" checked 
                       class="form-checkbox h-5 w-5 text-orange-600">
                <span class="text-gray-700">Renovaciones</span>
            </label>
            <?php endif; ?>
            
            <label class="flex items-center space-x-2">
                <input type="checkbox" id="mostrarReuniones" checked 
                       class="form-checkbox h-5 w-5 text-purple-600">
                <span class="text-gray-700">Reuniones</span>
            </label>
        </div>
    </div>

    <!-- Leyenda -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-6">
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-blue-500 rounded"></div>
                <span class="text-sm text-gray-700">Eventos Públicos</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-green-500 rounded"></div>
                <span class="text-sm text-gray-700">Eventos Internos</span>
            </div>
            <?php if ($es_interno): ?>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-orange-500 rounded"></div>
                <span class="text-sm text-gray-700">Renovaciones</span>
            </div>
            <?php endif; ?>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-purple-500 rounded"></div>
                <span class="text-sm text-gray-700">Reuniones</span>
            </div>
        </div>
    </div>

    <!-- Calendario -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal para detalles del evento -->
<div id="modalEvento" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-800" id="eventoTitulo"></h3>
            <button onclick="cerrarModalEvento()" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div id="eventoContenido" class="space-y-4">
            <!-- Se llenará con JavaScript -->
        </div>
        
        <div class="flex justify-end mt-6">
            <button onclick="cerrarModalEvento()" 
                    class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const esInterno = <?php echo $es_interno ? 'true' : 'false'; ?>;
    const empresaId = <?php echo $user['empresa_id'] ?? 'null'; ?>;
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
            list: 'Lista'
        },
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            cargarEventos(info.start, info.end, successCallback, failureCallback);
        },
        eventClick: function(info) {
            mostrarDetalleEvento(info.event);
        },
        eventDidMount: function(info) {
            // Agregar tooltip
            info.el.title = info.event.extendedProps.description || info.event.title;
        }
    });
    
    calendar.render();
    
    // Event listeners para filtros
    document.getElementById('mostrarEventos').addEventListener('change', function() {
        calendar.refetchEvents();
    });
    
    <?php if ($es_interno): ?>
    document.getElementById('mostrarRenovaciones').addEventListener('change', function() {
        calendar.refetchEvents();
    });
    <?php endif; ?>
    
    document.getElementById('mostrarReuniones').addEventListener('change', function() {
        calendar.refetchEvents();
    });
    
    async function cargarEventos(start, end, successCallback, failureCallback) {
        const mostrarEventos = document.getElementById('mostrarEventos').checked;
        const mostrarRenovaciones = esInterno && document.getElementById('mostrarRenovaciones').checked;
        const mostrarReuniones = document.getElementById('mostrarReuniones').checked;
        
        try {
            const url = new URL('<?php echo BASE_URL; ?>/api/calendario_eventos.php');
            url.searchParams.append('start', start.toISOString().split('T')[0]);
            url.searchParams.append('end', end.toISOString().split('T')[0]);
            url.searchParams.append('mostrar_eventos', mostrarEventos ? '1' : '0');
            url.searchParams.append('mostrar_renovaciones', mostrarRenovaciones ? '1' : '0');
            url.searchParams.append('mostrar_reuniones', mostrarReuniones ? '1' : '0');
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                successCallback(data.events);
            } else {
                failureCallback(data.error);
            }
        } catch (error) {
            console.error('Error al cargar eventos:', error);
            failureCallback(error);
        }
    }
    
    function mostrarDetalleEvento(event) {
        const modal = document.getElementById('modalEvento');
        const titulo = document.getElementById('eventoTitulo');
        const contenido = document.getElementById('eventoContenido');
        
        titulo.textContent = event.title;
        
        let html = '<div class="space-y-3">';
        
        if (event.extendedProps.description) {
            html += `<p class="text-gray-700">${event.extendedProps.description}</p>`;
        }
        
        html += `
            <div class="flex items-center text-gray-700">
                <i class="fas fa-calendar-alt w-6"></i>
                <span>${formatDate(event.start)}</span>
            </div>
        `;
        
        if (event.extendedProps.ubicacion) {
            html += `
                <div class="flex items-center text-gray-700">
                    <i class="fas fa-map-marker-alt w-6"></i>
                    <span>${event.extendedProps.ubicacion}</span>
                </div>
            `;
        }
        
        if (event.extendedProps.tipo === 'RENOVACION' && event.extendedProps.empresa_nombre) {
            html += `
                <div class="flex items-center text-gray-700">
                    <i class="fas fa-building w-6"></i>
                    <span>${event.extendedProps.empresa_nombre}</span>
                </div>
            `;
        }
        
        if (event.extendedProps.costo && event.extendedProps.costo > 0) {
            html += `
                <div class="flex items-center text-gray-700">
                    <i class="fas fa-dollar-sign w-6"></i>
                    <span>$${parseFloat(event.extendedProps.costo).toFixed(2)}</span>
                </div>
            `;
        }
        
        if (event.extendedProps.cupo_maximo) {
            html += `
                <div class="flex items-center text-gray-700">
                    <i class="fas fa-users w-6"></i>
                    <span>${event.extendedProps.inscritos || 0} / ${event.extendedProps.cupo_maximo} inscritos</span>
                </div>
            `;
        }
        
        // Enlace para ver más detalles
        if (event.extendedProps.url) {
            html += `
                <div class="mt-4">
                    <a href="${event.extendedProps.url}" 
                       class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-external-link-alt mr-2"></i>Ver Detalles
                    </a>
                </div>
            `;
        }
        
        html += '</div>';
        
        contenido.innerHTML = html;
        modal.classList.remove('hidden');
    }
    
    function formatDate(date) {
        if (!date) return '';
        const d = new Date(date);
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return d.toLocaleDateString('es-MX', options);
    }
});

function cerrarModalEvento() {
    document.getElementById('modalEvento').classList.add('hidden');
}

// Cerrar modal al hacer clic fuera de él
document.getElementById('modalEvento').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalEvento();
    }
});
</script>

<?php include __DIR__ . '/app/views/layouts/footer.php'; ?>
