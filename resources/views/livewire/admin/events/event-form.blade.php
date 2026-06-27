{{--
    Vista: Formulario de Creación/Edición de Eventos
    Componente: App\Livewire\Admin\Events\EventForm

    ARQUITECTURA: el estado vive en 4 Form objects (generalInfo, schedule,
    location, registration) — wire:model usa rutas con punto
    (ej. "generalInfo.name") y @error usa la misma ruta con punto.

    UBICACIÓN: 'location' es un input de texto simple (ver
    form/_location-modality.blade.php), sin mapa interactivo ni dependencias
    de terceros — el texto se reutiliza tal cual en el iframe de Google Maps
    de la vista pública del evento.

    SEGURIDAD:
     - Toda variable de usuario se renderiza con {{ }} → escape XSS automático.
     - wire:click solo invoca métodos explícitos del componente.
--}}

<div class="space-y-6">

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO CON NAVEGACIÓN                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3">
        <button
            wire:click="abrirModal('cancelar')"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
            aria-label="Volver a la lista de eventos"
        >
            <x-admin-icon name="chevron-left" class="w-5 h-5 text-gray-600" />
        </button>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $modo === 'editar' ? 'Editar Evento' : 'Crear Nuevo Evento' }}
            </h1>
            <p class="text-sm text-gray-500">
                {{ $modo === 'editar'
                    ? 'Modifica los detalles y guarda los cambios'
                    : 'Completa el formulario para crear y publicar un evento' }}
            </p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- LAYOUT PRINCIPAL: formulario izquierda + acciones derecha          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-[1fr_300px] gap-6 items-start">

        {{-- ── Columna principal ─────────────────────────────────────── --}}
        <div class="space-y-6 min-w-0">
            @include('livewire.admin.events.form._general-info')
            @include('livewire.admin.events.form._schedule')
            {{-- "Colaboradores Invitados" reubicado justo antes de "Capacidad
                 e Inscripciones": el contenedor padre usa space-y-6, por lo
                 que el espaciado vertical se recalcula solo y no requiere
                 márgenes propios en ninguna de las dos secciones. --}}
            @include('livewire.admin.events.form._collaborators')
            @include('livewire.admin.events.form._capacity-registration')
            @include('livewire.admin.events.form._location-modality')
            @include('livewire.admin.events.form._media-slider')
        </div>

        {{-- ── Panel lateral de acciones (sticky) + modales ───────────── --}}
        @include('livewire.admin.events.form._action-panel')

    </div>

</div>
