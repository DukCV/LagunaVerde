{{--
    Vista: Gestión de Eventos del Panel de Administración
    Componente: App\Livewire\Admin\Events\EventsManagement

    SEGURIDAD:
     - Toda variable de usuario se renderiza con {{ }} → escape XSS automático de Blade.
     - Los botones de acción usan wire:click con métodos explícitos (sin eval).
     - "Ver en el sitio público" sigue siendo un marcador visual inactivo a
       propósito: esa página pública aún no existe en esta iteración.

    ACCESIBILIDAD:
     - Roles ARIA en formularios de filtro (search, combobox).
     - aria-label / aria-disabled en todos los botones de acción.
     - <time> con datetime ISO para fechas.
--}}
<div class="space-y-6">

@if ($vista === 'lista')

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO DE SECCIÓN                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 mb-1">Gestión de Eventos</h1>
            <div class="flex items-center gap-3 mt-2 flex-wrap">
                <span class="text-3xl font-semibold text-blue-600">{{ number_format(array_sum($totales)) }}</span>
                <span class="text-gray-500 text-sm">eventos registrados</span>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {{ $totales['published'] ?? 0 }} publicados
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-200">
                        {{ $totales['draft'] ?? 0 }} borradores
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-red-50 text-red-700 border border-red-200">
                        {{ $totales['cancelled'] ?? 0 }} cancelados
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 border border-gray-300">
                        {{ $totales['closed'] ?? 0 }} finalizados
                    </span>
                </div>
            </div>
        </div>

        {{-- CTA "Nuevo evento" — abre EventForm en modo creación --}}
        <button
            type="button"
            wire:click="crearEvento"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <x-admin-icon name="plus" class="w-4 h-4" />
            <span>Nuevo evento</span>
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- BARRA DE FILTROS                                                    --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">

        <div class="flex items-center gap-2 mb-3">
            <x-admin-icon name="funnel" class="w-4 h-4 text-gray-400" />
            <span class="text-sm text-gray-600">Filtrar eventos</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

            {{-- Búsqueda por texto: debounce 400ms para reducir requests --}}
            <div class="lg:col-span-2">
                <label for="busqueda-eventos" class="sr-only">Buscar eventos</label>
                <div class="relative">
                    <x-admin-icon
                        name="magnifying-glass"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    />
                    <input
                        id="busqueda-eventos"
                        type="search"
                        wire:model.live.debounce.400ms="busqueda"
                        placeholder="Buscar por nombre, descripción o lugar..."
                        maxlength="100"
                        autocomplete="off"
                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        aria-label="Buscar eventos"
                        role="searchbox"
                    >
                </div>
            </div>

            {{-- Filtro de categoría --}}
            <div>
                <label for="filtro-categoria-evento" class="sr-only">Filtrar por categoría</label>
                <div class="relative">
                    <select
                        id="filtro-categoria-evento"
                        wire:model.live="categoria"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Filtrar por categoría"
                    >
                        @foreach($categorias as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>

            {{-- Orden --}}
            <div>
                <label for="orden-eventos" class="sr-only">Ordenar por</label>
                <div class="relative">
                    <select
                        id="orden-eventos"
                        wire:model.live="orden"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Ordenar eventos"
                    >
                        @foreach($ordenes as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>
        </div>

        {{-- Tabs de estado --}}
        @php
            $tabsEstado = [
                ['id' => 'todos',     'label' => 'Todos',       'count' => array_sum($totales)],
                ['id' => 'published', 'label' => 'Publicados',  'count' => $totales['published'] ?? 0],
                ['id' => 'draft',     'label' => 'Borradores',  'count' => $totales['draft'] ?? 0],
                ['id' => 'cancelled', 'label' => 'Cancelados',  'count' => $totales['cancelled'] ?? 0],
                ['id' => 'closed',    'label' => 'Finalizados', 'count' => $totales['closed'] ?? 0],
            ];
        @endphp
        <div class="flex items-center gap-2 mt-4 flex-wrap">
            @foreach($tabsEstado as $tab)
                <button
                    type="button"
                    wire:click="$set('estado', '{{ $tab['id'] }}')"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors {{ $estado === $tab['id'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                    aria-pressed="{{ $estado === $tab['id'] ? 'true' : 'false' }}"
                >
                    {{ $tab['label'] }}
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs {{ $estado === $tab['id'] ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                        {{ $tab['count'] }}
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Indicador de filtros activos --}}
        @if($busqueda !== '' || $estado !== 'todos' || $categoria !== 'todas')
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 pt-4 border-t border-gray-100">
                <x-admin-icon name="funnel" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <span>
                    Mostrando <strong class="text-gray-900">{{ $eventos->total() }}</strong>
                    {{ $eventos->total() === 1 ? 'evento' : 'eventos' }} con filtros activos
                </span>
                <button
                    wire:click="limpiarFiltros"
                    class="ml-1 text-blue-600 hover:text-blue-700 underline underline-offset-2 focus:outline-none focus:ring-1 focus:ring-blue-400 rounded"
                    aria-label="Limpiar todos los filtros"
                >
                    Limpiar filtros
                </button>
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- GRID DE TARJETAS DE EVENTOS                                         --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($eventos->isNotEmpty())

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" wire:loading.class="opacity-60">

            @foreach($eventos as $evento)
                <article
                    wire:key="evento-{{ $evento->id }}"
                    class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col"
                    aria-label="Evento: {{ $evento->title }}"
                >
                    {{-- Imagen de portada --}}
                    <div class="relative h-44 bg-gray-100 flex-shrink-0">
                        @if($evento->coverUrl)
                            <img
                                src="{{ $evento->coverUrl }}"
                                alt="{{ $evento->title }}"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >
                            <div class="absolute inset-0 bg-gray-100 flex-col items-center justify-center gap-1 hidden">
                                <x-admin-icon name="calendar-days" class="w-10 h-10 text-gray-300" />
                                <span class="text-xs text-gray-400">Sin imagen</span>
                            </div>
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1">
                                <x-admin-icon name="calendar-days" class="w-10 h-10 text-gray-300" />
                                <span class="text-xs text-gray-400">Sin imagen</span>
                            </div>
                        @endif

                        {{-- Degradado para legibilidad de los badges --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>

                        {{-- Badge de categoría --}}
                        <div class="absolute top-2.5 left-2.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs bg-blue-600/90 text-white backdrop-blur-sm">
                                <x-admin-icon name="tag" class="w-3 h-3" />
                                {{ $evento->categoryName }}
                            </span>
                        </div>

                        {{-- Badge de estado --}}
                        <div class="absolute top-2.5 right-2.5">
                            @include('livewire.admin.events.partials.event-status-badge', ['status' => $evento->status, 'label' => $evento->statusLabel])
                        </div>
                    </div>

                    {{-- Contenido de la tarjeta --}}
                    <div class="p-4 flex flex-col flex-1 space-y-3">

                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 leading-snug mb-1">
                                {{ $evento->title }}
                            </h3>
                            <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed">
                                {{ $evento->excerpt }}
                            </p>
                        </div>

                        {{-- Fechas de publicación del registro --}}
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-400">
                            <span>Publicado: <time datetime="{{ $evento->createdAtLabel }}">{{ $evento->createdAtLabel }}</time></span>
                            <span>Actualizado: <time datetime="{{ $evento->updatedAtLabel }}">{{ $evento->updatedAtLabel }}</time></span>
                        </div>

                        {{-- Programación --}}
                        <div class="bg-gray-50 rounded-lg p-3 space-y-2">
                            {{-- Inicio --}}
                            <div class="flex items-start gap-2 text-sm">
                                <x-admin-icon name="calendar-days" class="w-3.5 h-3.5 text-blue-500 mt-0.5 flex-shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs text-gray-400 block">Inicio</span>
                                    <span class="text-gray-700 text-xs">
                                        {{ $evento->startDateLabel }} — {{ $evento->startTimeLabel }}
                                    </span>
                                </div>
                            </div>

                            {{-- Fin: lógica condicional según si es el mismo día --}}
                            <div class="flex items-start gap-2 text-sm">
                                <x-admin-icon name="clock" class="w-3.5 h-3.5 text-purple-500 mt-0.5 flex-shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs text-gray-400 block">
                                        {{ $evento->isSameDay ? 'Hora de fin' : 'Fin' }}
                                    </span>
                                    <span class="text-gray-700 text-xs">
                                        @if($evento->isSameDay)
                                            {{ $evento->endTimeLabel }}
                                        @else
                                            {{ $evento->endDateLabel }} — {{ $evento->endTimeLabel }}
                                        @endif
                                    </span>
                                </div>
                            </div>

                            {{-- Ubicación --}}
                            <div class="flex items-start gap-2 text-sm">
                                <x-admin-icon name="map-pin" class="w-3.5 h-3.5 text-emerald-500 mt-0.5 flex-shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs text-gray-400 block">Lugar</span>
                                    <span class="text-gray-700 text-xs truncate block">
                                        {{ $evento->location !== '' ? $evento->location : 'Sin ubicación especificada' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Capacidad / aforo --}}
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="flex items-center gap-1.5 mb-2">
                                <x-admin-icon name="users" class="w-3.5 h-3.5 text-gray-400" />
                                <span class="text-xs text-gray-500">Asistentes registrados</span>
                            </div>
                            @include('livewire.admin.events.partials.capacity-bar', [
                                'registrations' => $evento->registrations,
                                'capacityTotal' => $evento->capacityTotal,
                                'isUnlimited'   => $evento->isUnlimited,
                                'occupancyPct'  => $evento->occupancyPct,
                                'isFull'        => $evento->isFull,
                                'isAlmostFull'  => $evento->isAlmostFull,
                            ])
                        </div>

                        {{-- Botones de acción --}}
                        <div class="grid grid-cols-3 gap-1.5 pt-1">

                            {{-- Editar — abre EventForm en modo edición con este evento --}}
                            <button
                                type="button"
                                wire:click="editarEvento({{ $evento->id }})"
                                class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 active:bg-blue-200 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1"
                                aria-label="Editar: {{ $evento->title }}"
                            >
                                <x-admin-icon name="pencil-square" class="w-3.5 h-3.5 flex-shrink-0" />
                                <span class="hidden sm:inline">Editar</span>
                            </button>

                            {{--
                                Ver en sitio público — marcador visual inactivo a propósito.
                                REQUISITO: deshabilitado con mensaje "No disponible por el momento".
                            --}}
                            <button
                                type="button"
                                disabled
                                aria-disabled="true"
                                title="No disponible por el momento"
                                class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium border border-gray-200 text-gray-300 rounded-lg cursor-not-allowed"
                            >
                                <x-admin-icon name="arrow-top-right-on-square" class="w-3.5 h-3.5 shrink-0" />
                                <span class="hidden sm:inline">Ver</span>
                            </button>

                            {{--
                                Cancelar: solo disponible si el evento está en borrador o publicado.
                                Transición de un solo sentido — sin "reactivar" desde este panel.
                            --}}
                            @if(in_array($evento->status, ['draft', 'published'], true))
                                <button
                                    wire:click="confirmarCancelacion({{ $evento->id }})"
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium border border-amber-300 text-amber-600 rounded-lg hover:bg-amber-50 active:bg-amber-100 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-1"
                                    aria-label="Cancelar: {{ $evento->title }}"
                                >
                                    <x-admin-icon name="x-circle" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">Cancelar</span>
                                </button>
                            @else
                                <button
                                    disabled
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed"
                                    aria-label="Estado terminal, sin acciones disponibles"
                                    aria-disabled="true"
                                    title="Estado terminal"
                                >
                                    <x-admin-icon name="no-symbol" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">{{ $evento->statusLabel }}</span>
                                </button>
                            @endif
                        </div>

                        {{-- Eliminar permanentemente — siempre disponible --}}
                        <button
                            wire:click="confirmarEliminacion({{ $evento->id }})"
                            class="flex items-center justify-center gap-1.5 w-full px-2 py-2 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 active:bg-red-200 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                            aria-label="Eliminar permanentemente: {{ $evento->title }}"
                        >
                            <x-admin-icon name="trash" class="w-3.5 h-3.5 flex-shrink-0" />
                            <span>Eliminar permanentemente</span>
                        </button>

                    </div>
                </article>
            @endforeach

        </div>

        {{-- Paginación --}}
        @if($eventos->hasPages())
            <div class="flex justify-center pt-2">
                {{ $eventos->links() }}
            </div>
        @endif

    @else

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- ESTADO VACÍO                                                     --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl p-12 shadow-sm border border-gray-200 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <x-admin-icon name="calendar-days" class="w-8 h-8 text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-2">No se encontraron eventos</h3>
            <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                @if($busqueda !== '' || $estado !== 'todos' || $categoria !== 'todas')
                    Intenta ajustar los filtros o realiza una búsqueda diferente.
                @else
                    Aún no hay eventos registrados.
                @endif
            </p>
            @if($busqueda !== '' || $estado !== 'todos' || $categoria !== 'todas')
                <button
                    wire:click="limpiarFiltros"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-admin-icon name="arrow-path" class="w-4 h-4" />
                    Limpiar filtros
                </button>
            @endif
        </div>

    @endif

@endif {{-- /vista === 'lista' --}}

{{-- ── Vista formulario: EventForm (crea o edita según eventoIdEdicion) ── --}}
@if ($vista === 'formulario')
    <livewire:admin.events.event-form
        :event-id="$eventoIdEdicion"
        :key="'event-form-' . ($eventoIdEdicion ?? 'new')"
    />
@endif

    {{--
        Toast de notificación: escucha el evento 'notificacion' despachado desde el
        componente Livewire. Alpine.js lo muestra 3.5 s y luego lo oculta automáticamente.
    --}}
    <div
        x-data="{ mostrar: false, tipo: '', mensaje: '' }"
        x-on:notificacion.window="
            tipo    = $event.detail.tipo;
            mensaje = $event.detail.mensaje;
            mostrar = true;
            setTimeout(() => mostrar = false, 3500);
        "
        x-show="mostrar"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        :class="tipo === 'exito' ? 'bg-green-600' : 'bg-red-600'"
        class="fixed bottom-20 right-4 z-50 px-5 py-3 rounded-lg shadow-lg text-sm font-medium text-white min-w-max"
        style="display: none;"
        role="alert"
        aria-live="polite"
    >
        <span x-text="mensaje"></span>
    </div>

    {{-- Indicador de carga superpuesto durante peticiones Livewire --}}
    <div wire:loading.delay class="fixed bottom-4 right-4 bg-white border border-gray-200 rounded-lg shadow-lg px-4 py-2 flex items-center gap-2 text-sm text-gray-600 z-40">
        <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span>Cargando...</span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN DE CANCELACIÓN                               --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarModalCancelar)
        <div
            wire:key="modal-cancelar"
            wire:click.self="cancelarModalCancelar"
            wire:keydown.escape.window="cancelarModalCancelar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="true"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-titulo-cancelar"
                aria-describedby="modal-desc-cancelar"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                <div class="h-1.5 w-full bg-amber-500"></div>

                <div class="p-6 sm:p-7">
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center bg-amber-100">
                            <x-admin-icon name="x-circle" class="w-7 h-7 text-amber-600" />
                        </div>
                    </div>

                    <h2 id="modal-titulo-cancelar" class="text-lg font-semibold text-gray-900 text-center mb-2 wrap-break-word">
                        ¿Cancelar el evento {{ $eventoTituloParaCancelar }}?
                    </h2>

                    <p id="modal-desc-cancelar" class="text-sm text-gray-500 text-center leading-relaxed mb-7">
                        El evento será marcado como
                        <strong class="text-gray-700">cancelado</strong>
                        y dejará de aceptar inscripciones. Esta acción no puede revertirse desde este panel.
                    </p>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            wire:click="cancelarModalCancelar"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Volver
                        </button>

                        <button
                            wire:click="ejecutarCancelacion"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="ejecutarCancelacion"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600 active:bg-amber-700 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                            aria-label="Confirmar cancelación del evento"
                        >
                            <span wire:loading.remove wire:target="ejecutarCancelacion">Sí, cancelar evento</span>
                            <span wire:loading wire:target="ejecutarCancelacion" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Procesando...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN DE ELIMINACIÓN PERMANENTE                    --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarModalEliminar)
        <div
            wire:key="modal-eliminar"
            wire:click.self="cancelarModalEliminar"
            wire:keydown.escape.window="cancelarModalEliminar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="true"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-titulo-eliminar"
                aria-describedby="modal-desc-eliminar"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                <div class="h-1.5 w-full bg-red-600"></div>

                <div class="p-6 sm:p-7">
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center bg-red-100">
                            <x-admin-icon name="trash" class="w-7 h-7 text-red-600" />
                        </div>
                    </div>

                    <h2 id="modal-titulo-eliminar" class="text-lg font-semibold text-gray-900 text-center mb-2 wrap-break-word">
                        ¿Eliminar permanentemente el evento {{ $eventoTituloParaEliminar }}?
                    </h2>

                    <p id="modal-desc-eliminar" class="text-sm text-gray-500 text-center leading-relaxed mb-7">
                        Esta acción es
                        <strong class="text-red-600">permanente e irreversible</strong>.
                        Se eliminarán también sus
                        <strong class="text-gray-700">inscripciones, comentarios e imágenes</strong>,
                        incluyendo los archivos almacenados en el servidor.
                    </p>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            wire:click="cancelarModalEliminar"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Cancelar
                        </button>

                        <button
                            wire:click="ejecutarEliminacion"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="ejecutarEliminacion"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 active:bg-red-800 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            aria-label="Confirmar eliminación permanente del evento"
                        >
                            <span wire:loading.remove wire:target="ejecutarEliminacion">Sí, eliminar</span>
                            <span wire:loading wire:target="ejecutarEliminacion" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Eliminando...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
