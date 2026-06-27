{{--
    livewire/events/featured-event-banner.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe (del componente FeaturedEventBanner):
      $event → array<string, mixed>  (FeaturedEventDto serializado)

    SEGURIDAD:
    • {{ }} en toda salida → XSS imposible.
    • e() en atributos src.
    • route() con UUID del DTO — nunca el ID entero.
    • endTime y coverUrl se renderizan condicionalmente.
    ─────────────────────────────────────────────────────────────────────
--}}

@php
    $isFull     = (bool) $event['isFull'];
    $occupancy  = (int)  $event['occupancyPct'];
@endphp

<div class="relative bg-gradient-to-br from-blue-600 to-blue-800
            rounded-3xl overflow-hidden shadow-2xl mb-12">
    <div class="grid lg:grid-cols-2">

        {{-- ── Imagen de portada ──────────────────────────────────────── --}}
        <div class="relative h-72 lg:h-auto overflow-hidden
                    bg-gradient-to-br from-blue-700 to-blue-900">

            @if ($event['coverUrl'])
                <img
                    src="{{ e($event['coverUrl']) }}"
                    alt="{{ $event['coverAlt'] }}"
                    loading="eager"
                    width="800"
                    height="500"
                    class="w-full h-full object-cover"
                    onerror="this.src='https://placehold.co/800x500/1d4ed8/ffffff?text=Evento';this.onerror=null;"
                />
                {{-- Gradiente sobre la imagen para legibilidad del contenido --}}
                <div class="absolute inset-0 bg-gradient-to-t lg:bg-gradient-to-r
                            from-blue-900/80 to-transparent"></div>
            @else
                {{-- Placeholder sin imagen --}}
                <div class="w-full h-full flex items-center justify-center">
                    <span class="text-9xl opacity-10 text-white" aria-hidden="true">📅</span>
                </div>
            @endif

            {{-- Badge de fecha —  prominente en la esquina inferior/superior --}}
            <div class="absolute bottom-6 left-6 lg:top-6 lg:left-6 lg:bottom-auto">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-24">
                    <div class="bg-yellow-400 text-gray-900 px-4 py-1.5 text-center">
                        <span class="text-xs font-semibold uppercase tracking-widest">
                            {{ $event['startMonth'] }}
                        </span>
                    </div>
                    <div class="px-4 py-3 text-center">
                        <span class="text-4xl font-bold text-gray-900 leading-none">
                            {{ $event['startDay'] }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Categoría --}}
            @if ($event['categoryName'])
                <div class="absolute top-6 right-6">
                    <span class="px-4 py-2 bg-blue-500/80 backdrop-blur-sm text-white
                                 text-sm font-medium rounded-full shadow-lg">
                        {{ $event['categoryName'] }}
                    </span>
                </div>
            @endif

        </div>

        {{-- ── Contenido del banner ────────────────────────────────────── --}}
        <div class="p-8 lg:p-12 flex flex-col justify-center text-white">

            {{-- Etiqueta "Próximo evento destacado" --}}
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-400
                        text-gray-900 rounded-full mb-6 self-start">
                <span class="w-2 h-2 bg-gray-900 rounded-full animate-pulse"
                      aria-hidden="true"></span>
                <span class="text-xs font-semibold uppercase tracking-wide">
                    Próximo Evento Destacado
                </span>
            </div>

            {{-- Título --}}
            <h2 class="text-3xl lg:text-4xl font-bold mb-4 leading-tight">
                {{ $event['title'] }}
            </h2>

            {{-- Descripción completa --}}
            <p class="text-lg text-blue-100 mb-8 leading-relaxed">
                {{ $event['description'] }}
            </p>

            {{-- Metadatos: fecha, horario y capacidad --}}
            <div class="space-y-3 mb-8">

                {{-- Fecha completa --}}
                <div class="flex items-center gap-3 text-blue-100">
                    <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center
                                justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                             aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8"  y1="2" x2="8"  y2="6"/>
                            <line x1="3"  y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <time datetime="{{ $event['startDateIso'] }}" class="text-base capitalize">
                        {{ $event['startDate'] }}
                    </time>
                </div>

                {{-- Horario: inicio y fin opcional --}}
                <div class="flex items-center gap-3 text-blue-100">
                    <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center
                                justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                             aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <span class="text-base">
                        {{ $event['startTime'] }}
                        {{-- endTime es opcional — solo se muestra si está definida --}}
                        @if ($event['endTime'])
                            <span aria-hidden="true">–</span>
                            {{ $event['endTime'] }}
                        @endif
                    </span>
                </div>

                {{-- Barra de capacidad --}}
                <div>
                    <div class="flex justify-between text-sm text-blue-200 mb-2">
                        <span>
                            {{ $event['registered'] }} / {{ $event['capacityTotal'] }} inscritos
                        </span>
                        <span class="{{ $event['occupancyPct'] >= 90 ? 'text-red-300 font-medium' : '' }}">
                            {{ $event['occupancyPct'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-2"
                         role="progressbar"
                         aria-valuenow="{{ $event['occupancyPct'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-label="Ocupación: {{ $event['occupancyPct'] }}%">
                        <div class="h-2 rounded-full transition-all
                                    {{ $event['occupancyPct'] >= 90 ? 'bg-red-400' : 'bg-yellow-400' }}"
                             style="width: {{ $event['occupancyPct'] }}%"></div>
                    </div>
                </div>

            </div>

            {{-- Botones de acción --}}
            <div class="flex flex-col sm:flex-row gap-4">

                {{-- Ver detalles — UUID en URL --}}
                <a
                    href="{{ route('events.show', $event['uuid']) }}"
                    class="inline-flex items-center justify-center gap-2 px-8 py-4
                           bg-white text-blue-600 font-semibold rounded-xl
                           hover:bg-blue-50 transition-all shadow-lg hover:shadow-xl group"
                    aria-label="Ver detalles del evento: {{ $event['title'] }}"
                >
                    <span>Ver Detalles</span>
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5 group-hover:translate-x-1 transition-transform"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                         aria-hidden="true">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>

                {{-- Inscribirse — deshabilitado si cupo lleno --}}
                @if ($isFull)
                    <span class="inline-flex items-center justify-center px-8 py-4
                                 border-2 border-white/40 text-white/50 font-semibold
                                 rounded-xl cursor-not-allowed"
                          aria-disabled="true">
                        Sin cupo disponible
                    </span>
                @else
                    <a
                        href="{{ route('events.register', $event['uuid']) }}"
                        class="inline-flex items-center justify-center gap-2 px-8 py-4
                               border-2 border-white text-white font-semibold rounded-xl
                               hover:bg-white hover:text-blue-600 transition-all"
                        aria-label="Inscribirse al evento: {{ $event['title'] }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                             aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8"  y1="2" x2="8"  y2="6"/>
                            <line x1="3"  y1="10" x2="21" y2="10"/>
                        </svg>
                        <span>Inscribirme Ahora</span>
                    </a>
                @endif

            </div>
        </div>

    </div>
</div>
