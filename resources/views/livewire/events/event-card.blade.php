{{--
    livewire/events/event-card.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe (del componente EventCard):
      $event      → array<string, mixed>  (EventSummaryDto serializado)
      $horizontal → bool

    SEGURIDAD:
    • {{ }} en toda salida → escaping XSS automático.
    • e() en atributos src → protección adicional.
    • route() con UUID del DTO — nunca el ID entero en la URL.
    • Los estilos "finalizado" se calculan desde $event['isActive'] (PHP bool).
    ─────────────────────────────────────────────────────────────────────
--}}

@php
    // Acceso tipado al array del DTO para mayor claridad en el template
    $isActive    = (bool) $event['isActive'];
    $isFull      = (bool) $event['isFull'];
    $occupancy   = (int)  $event['occupancyPct'];
@endphp

<article
    class="group bg-white rounded-2xl overflow-hidden shadow-md transition-all duration-300 flex
           {{ $horizontal ? 'flex-row' : 'flex-col' }}
           {{ $isActive
               ? 'hover:shadow-2xl hover:-translate-y-1'
               : 'opacity-75 grayscale-[30%]' }}"
    aria-label="Evento: {{ $event['title'] }}"
>
    {{-- ── Imagen de portada ──────────────────────────────────────────── --}}
    <div class="relative overflow-hidden shrink-0
                {{ $horizontal ? 'w-1/3' : 'aspect-video w-full' }}
                {{ $isActive ? '' : 'bg-gray-200' }}">

        @if ($event['coverUrl'])
            <img
                src="{{ e($event['coverUrl']) }}"
                alt="{{ $event['coverAlt'] }}"
                loading="lazy"
                width="600"
                height="400"
                class="w-full h-full object-cover
                       {{ $isActive ? 'group-hover:scale-105 transition-transform duration-500' : '' }}"
                onerror="this.src='https://placehold.co/600x400/e2e8f0/94a3b8?text=Evento';this.onerror=null;"
            />
        @else
            {{-- Placeholder accesible --}}
            <div class="w-full h-full flex items-center justify-center bg-gray-100">
                <span class="text-5xl opacity-20" aria-hidden="true">📅</span>
            </div>
        @endif

        {{-- Badge de categoría --}}
        @if ($event['categoryName'])
            <div class="absolute top-3 left-3">
                <span class="px-3 py-1.5 text-xs font-semibold rounded-full shadow-md
                             {{ $isActive
                                 ? 'bg-blue-600 text-white'
                                 : 'bg-gray-500 text-white' }}">
                    {{ $event['categoryName'] }}
                </span>
            </div>
        @endif

        {{-- Badge de estado para eventos finalizados/cancelados --}}
        @if (! $isActive)
            <div class="absolute top-3 right-3">
                <span class="px-3 py-1.5 text-xs font-bold rounded-full shadow-md
                             bg-red-100 text-red-700 border border-red-200">
                    {{ $event['statusLabel'] }}
                </span>
            </div>
        @endif

        {{-- Badge de fecha (día / mes) --}}
        <div class="absolute bottom-3 left-3 bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-3 py-1 text-center {{ $isActive ? 'bg-blue-600' : 'bg-gray-500' }}">
                <span class="text-xs font-bold tracking-widest text-white">
                    {{ $event['startMonth'] }}
                </span>
            </div>
            <div class="px-3 py-1.5 text-center">
                <span class="text-2xl font-bold text-gray-900 leading-none">
                    {{ $event['startDay'] }}
                </span>
            </div>
        </div>
    </div>

    {{-- ── Contenido ────────────────────────────────────────────────────── --}}
    <div class="flex-1 p-5 flex flex-col">

        {{-- Título --}}
        <h3 class="text-lg font-semibold mb-2 line-clamp-2 leading-snug transition-colors
                   {{ $isActive
                       ? 'text-gray-900 group-hover:text-blue-600'
                       : 'text-gray-500' }}">
            {{ $event['title'] }}
        </h3>

        {{-- Descripción breve --}}
        <p class="text-sm mb-4 line-clamp-2 leading-relaxed flex-grow
                  {{ $isActive ? 'text-gray-500' : 'text-gray-400' }}">
            {{ $event['description'] }}
        </p>

        {{-- Metadatos: horario y capacidad --}}
        <div class="space-y-2 mb-4">

            {{-- Horario --}}
            <div class="flex items-center gap-2 text-sm
                        {{ $isActive ? 'text-gray-500' : 'text-gray-400' }}">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4 shrink-0 {{ $isActive ? 'text-blue-500' : 'text-gray-400' }}"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <time datetime="{{ $event['startDateIso'] }}">
                    {{ $event['startTime'] }}
                </time>
                {{-- endTime es opcional —  se oculta si no está definida --}}
                @if ($event['endTime'])
                    <span aria-hidden="true">–</span>
                    <span>{{ $event['endTime'] }}</span>
                @endif
            </div>

            {{-- Capacidad con barra de ocupación (solo en eventos activos) --}}
            @if ($isActive)
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $event['registered'] }} / {{ $event['capacityTotal'] }} inscritos</span>
                        <span class="{{ $occupancy >= 90 ? 'text-red-600 font-medium' : '' }}">
                            {{ $occupancy }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5"
                         role="progressbar"
                         aria-valuenow="{{ $occupancy }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                        <div class="h-1.5 rounded-full transition-all
                                    {{ $occupancy >= 90 ? 'bg-red-500' : 'bg-blue-500' }}"
                             style="width: {{ $occupancy }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Botones de acción --}}
        <div class="flex gap-3 {{ $horizontal ? 'flex-col sm:flex-row' : '' }}">

            {{-- Ver detalles — UUID en URL, nunca ID entero --}}
            <a
                href="{{ route('events.show', $event['uuid']) }}"
                class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg
                       transition-colors text-center
                       {{ $isActive
                           ? 'bg-blue-600 text-white hover:bg-blue-700'
                           : 'bg-gray-200 text-gray-500 hover:bg-gray-300' }}"
                aria-label="Ver detalles de: {{ $event['title'] }}"
            >
                Ver Detalles
            </a>

            {{-- Inscribirse — deshabilitado visualmente si finalizado o cupo lleno --}}
            @if ($isActive && ! $isFull)
                <a
                    href="{{ route('events.register', $event['uuid']) }}"
                    class="flex-1 px-4 py-2.5 border-2 border-blue-600 text-blue-600
                           text-sm font-semibold rounded-lg hover:bg-blue-50
                           transition-colors text-center"
                    aria-label="Inscribirse a: {{ $event['title'] }}"
                >
                    Inscribirme
                </a>
            @elseif ($isActive && $isFull)
                <span class="flex-1 px-4 py-2.5 border-2 border-gray-300 text-gray-400
                             text-sm font-semibold rounded-lg text-center cursor-not-allowed"
                      aria-disabled="true">
                    Cupo lleno
                </span>
            @else
                <span class="flex-1 px-4 py-2.5 border-2 border-red-200 text-red-400
                             text-sm font-semibold rounded-lg text-center cursor-not-allowed"
                      aria-disabled="true">
                    {{ $event['statusLabel'] }}
                </span>
            @endif

        </div>
    </div>
</article>
