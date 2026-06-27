{{--
    livewire/events/events-index.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe:
      $featuredEvent   → array|null (FeaturedEventDto serializado)
      $eventsPaginator → LengthAwarePaginator<EventSummaryDto>
      $categories      → string[]
      $months          → array<{value, label}>
      $statuses        → array<string, string>
      $totalResults    → int

    SEGURIDAD:
    • {{ }} en toda salida → XSS imposible.
    • wire:model sincroniza filtros con variables PHP — sin input libre al DOM.
    • wire:key usa uuid del DTO — nunca el ID entero.
    • Los selects se populan desde el servicio (BD validada) — sin valores inyectados.
    ─────────────────────────────────────────────────────────────────────
--}}

{{--
    -mt-24 cancela el pt-24 del body (principal.blade.php) para que bg-gray-50
    cubra desde y=0 y elimine la franja oscura visible bajo la cabecera fija.
--}}
<div class="min-h-screen bg-gray-50 -mt-24 pt-24 pb-16">
    <div class="container mx-auto px-4">

        {{-- ── Encabezado ─────────────────────────────────────────────── --}}
        <div class="mb-12 text-center">
            <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 mb-4">
                Eventos y Actividades
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                Únete a nuestras jornadas, talleres y actividades para contribuir
                activamente a la conservación de la laguna.
            </p>
        </div>

        {{-- ── Banner del evento más próximo (condicional) ────────────── --}}
        @if ($featuredEvent !== null)
            <livewire:events.featured-event-banner
                :event="$featuredEvent"
                :wire:key="'featured-' . $featuredEvent['uuid']"
            />
        @endif

        {{-- ════════════════════════════════════════════════════════════
             BARRA DE FILTROS
        ════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl shadow-md p-6 mb-12">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">

                {{-- Búsqueda por texto --}}
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        wire:model.live.debounce.400ms="search"
                        type="search"
                        maxlength="100"
                        placeholder="Buscar eventos..."
                        autocomplete="off"
                        aria-label="Buscar eventos"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               text-gray-900 placeholder:text-gray-400
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm"
                    />
                </div>

                {{-- Filtro: categoría (valores de BD) --}}
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    <select
                        wire:model.live="category"
                        aria-label="Filtrar por categoría"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm text-gray-700 appearance-none cursor-pointer"
                    >
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro: mes/año (valores de BD) --}}
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8"  y1="2" x2="8"  y2="6"/>
                        <line x1="3"  y1="10" x2="21" y2="10"/>
                    </svg>
                    <select
                        wire:model.live="month"
                        aria-label="Filtrar por mes"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm text-gray-700 appearance-none cursor-pointer"
                    >
                        @foreach ($months as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro: estado (whitelist fija) --}}
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <select
                        wire:model.live="status"
                        aria-label="Filtrar por estado"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm text-gray-700 appearance-none cursor-pointer"
                    >
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Contador de resultados --}}
            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-gray-500" aria-live="polite" aria-atomic="true">
                    <span class="font-medium text-gray-700">{{ $totalResults }}</span>
                    {{ $totalResults === 1 ? 'evento encontrado' : 'eventos encontrados' }}
                    @if ($search)
                        para <span class="italic">"{{ $search }}"</span>
                    @endif
                </p>

                {{-- Limpiar filtros --}}
                @if ($search || $category !== 'Todas' || $month !== '' || $status !== 'all')
                    <button
                        wire:click="$set('search', ''); $set('category', 'Todas'); $set('month', ''); $set('status', 'all')"
                        class="text-sm text-blue-600 hover:underline"
                    >
                        Limpiar filtros
                    </button>
                @endif
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             GRID DE EVENTOS
        ════════════════════════════════════════════════════════════ --}}
        @if ($totalResults === 0)

            {{-- Estado vacío --}}
            <div class="flex flex-col items-center justify-center py-24 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mb-4"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"
                     aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8"  y1="2" x2="8"  y2="6"/>
                    <line x1="3"  y1="10" x2="21" y2="10"/>
                </svg>
                <p class="text-xl text-gray-500 mb-4">
                    No se encontraron eventos con los filtros seleccionados.
                </p>
                <button
                    wire:click="$set('search', ''); $set('category', 'Todas'); $set('month', ''); $set('status', 'all')"
                    class="text-blue-600 hover:underline text-sm"
                >
                    Limpiar filtros
                </button>
            </div>

        @else

            {{-- Grid escritorio: 3 columnas --}}
            <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                @foreach ($eventsPaginator as $eventDto)
                    {{-- wire:key usa uuid del DTO — nunca el ID entero --}}
                    <livewire:events.event-card
                        :event="$eventDto->toLivewire()"
                        :horizontal="false"
                        :wire:key="'desk-' . $eventDto->uuid"
                    />
                @endforeach
            </div>

            {{-- Listado móvil: tarjeta horizontal --}}
            <div class="md:hidden space-y-6 mb-12">
                @foreach ($eventsPaginator as $eventDto)
                    <livewire:events.event-card
                        :event="$eventDto->toLivewire()"
                        :horizontal="true"
                        :wire:key="'mob-' . $eventDto->uuid"
                    />
                @endforeach
            </div>

        @endif

        {{-- ════════════════════════════════════════════════════════════
             PAGINACIÓN
        ════════════════════════════════════════════════════════════ --}}
        @if ($eventsPaginator->hasPages())
            <div class="flex flex-col items-center gap-4">
                {{ $eventsPaginator->links() }}
                <p class="text-sm text-gray-500">
                    Página
                    <span class="font-medium text-gray-700">{{ $eventsPaginator->currentPage() }}</span>
                    de
                    <span class="font-medium text-gray-700">{{ $eventsPaginator->lastPage() }}</span>
                </p>
            </div>
        @endif

    </div>
</div>
