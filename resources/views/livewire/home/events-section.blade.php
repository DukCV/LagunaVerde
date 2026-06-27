{{--
    livewire/home/events-section.blade.php
    ─────────────────────────────────────────────────────────────────────
    $this->currentEvent es un objeto EventCardDto — se accede con ->propiedad
    $this->events       es array[] de DTOs serializados — se itera con foreach
    $this->currentSlide es int — índice del slide activo
    ─────────────────────────────────────────────────────────────────────
--}}

<section id="eventos" class="py-20 bg-gradient-to-b from-gray-50 to-white">
    <div class="container mx-auto px-4">

        {{-- ── Encabezado ─────────────────────────────────────────────── --}}
        <div class="text-center max-w-3xl mx-auto mb-12">
            <div class="inline-block px-4 py-2 bg-green-100 text-green-700 rounded-full mb-4 text-sm">
                Próximas Actividades
            </div>
            <h2 class="text-gray-900 text-4xl lg:text-5xl mb-4">
                Eventos y Voluntariado
            </h2>
            <p class="text-gray-600 text-lg">
                Participa en nuestras actividades y sé parte del cambio.
            </p>

            <div class="mt-6">
                <x-buttons.view-all :href="route('events')" wire:navigate>
                    Ver todos los eventos
                </x-buttons.view-all>
            </div>
        </div>

        {{-- ── Sin eventos próximos ────────────────────────────────────── --}}
        @if (empty($this->events) || $this->currentEvent === null)
            <div class="max-w-xl mx-auto text-center py-16 px-4">
                <span class="text-6xl block mb-6" aria-hidden="true">🌿</span>
                <h3 class="text-xl font-semibold text-gray-800 mb-3">
                    No hay eventos próximos
                </h3>
                <p class="text-gray-500 leading-relaxed">
                    Pronto publicaremos nuevas actividades. Regresa en unos días.
                </p>
            </div>

        {{-- ── Slider ──────────────────────────────────────────────────── --}}
        @else

            @php
                /** @var \App\DTOs\Home\Events\EventCardDto $event */
                $event = $this->currentEvent;   // objeto EventCardDto — acceso con ->
            @endphp

            <div class="max-w-5xl mx-auto">

                {{-- ── Tarjeta principal ───────────────────────────────── --}}
                <div class="relative bg-white rounded-3xl shadow-2xl overflow-hidden">
                    <div class="grid lg:grid-cols-2 relative">

                        {{-- ── Imagen de portada ──────────────────────── --}}
                        <div class="relative h-96 lg:h-auto
                                    bg-gradient-to-br from-blue-50 to-green-50">

                            @if ($event->coverUrl)
                                <img
                                    src="{{ e($event->coverUrl) }}"
                                    alt="{{ $event->coverAlt }}"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                    width="640" height="480"
                                />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <span class="text-8xl opacity-20" aria-hidden="true">📅</span>
                                </div>
                            @endif

                            {{-- Categoría --}}
                            @if ($event->categoryName)
                                <div class="absolute top-4 left-4">
                                    <span class="px-4 py-2 bg-white/90 backdrop-blur-sm
                                                 text-gray-800 rounded-full text-sm font-medium shadow-sm">
                                        {{ $event->categoryName }}
                                    </span>
                                </div>
                            @endif

                            {{-- Cupo lleno --}}
                            @if ($event->isFull)
                                <div class="absolute top-4 right-4">
                                    <span class="px-3 py-1.5 bg-red-600 text-white
                                                 text-sm font-medium rounded-full shadow">
                                        Cupo lleno
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- ── Contenido ───────────────────────────────── --}}
                        <div class="p-8 lg:p-12 flex flex-col justify-between">
                            <div>
                                {{-- Título --}}
                                <h3 class="text-3xl text-gray-900 mb-6 leading-snug">
                                    {{ $event->title }}
                                </h3>

                                <div class="space-y-4 mb-8 text-gray-700">

                                    {{-- Fecha y horario --}}
                                    <div class="flex items-start gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="w-5 h-5 text-blue-600 mt-0.5 shrink-0"
                                             fill="none" viewBox="0 0 24 24"
                                             stroke="currentColor" stroke-width="1.75"
                                             aria-hidden="true">
                                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8"  y1="2" x2="8"  y2="6"/>
                                            <line x1="3"  y1="10" x2="21" y2="10"/>
                                        </svg>
                                        <div>
                                            <time datetime="{{ $event->startDateIso }}"
                                                  class="font-medium">
                                                {{ $event->startDate }}
                                            </time>
                                            <div class="text-sm text-gray-500 mt-0.5">
                                                {{ $event->startTime }}
                                                {{-- endTime es opcional --}}
                                                @if ($event->endTime)
                                                    <span aria-hidden="true">–</span>
                                                    {{ $event->endTime }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Barra de ocupación --}}
                                    <div>
                                        <div class="flex justify-between text-sm mb-2">
                                            <span>
                                                <span class="font-medium">{{ $event->registered }}</span>
                                                / {{ $event->capacityTotal }} inscritos
                                            </span>
                                            <span class="font-medium
                                                {{ $event->occupancyPct >= 90
                                                    ? 'text-red-600'
                                                    : ($event->occupancyPct >= 70
                                                        ? 'text-amber-600'
                                                        : 'text-gray-600') }}">
                                                {{ $event->occupancyPct }}%
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2"
                                             role="progressbar"
                                             aria-valuenow="{{ $event->occupancyPct }}"
                                             aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-2 rounded-full transition-all duration-500
                                                        {{ $event->occupancyPct >= 90
                                                            ? 'bg-red-500'
                                                            : ($event->occupancyPct >= 70
                                                                ? 'bg-amber-500'
                                                                : 'bg-blue-600') }}"
                                                 style="width: {{ $event->occupancyPct }}%">
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                {{-- Descripción --}}
                                <p class="text-gray-600 leading-relaxed mb-8 line-clamp-3">
                                    {{ $event->description }}
                                </p>
                            </div>

                            {{-- CTA — UUID en la URL, nunca el ID entero --}}
                            <a
                                href="{{ route('events.show', $event->uuid) }}"
                                class="block w-full text-center py-4 rounded-xl font-medium
                                       transition-colors
                                    {{ $event->isFull
                                        ? 'bg-gray-200 text-gray-500 cursor-not-allowed pointer-events-none'
                                        : 'bg-blue-600 text-white hover:bg-blue-700' }}"
                                aria-disabled="{{ $event->isFull ? 'true' : 'false' }}"
                            >
                                {{ $event->isFull ? 'Sin cupo disponible' : 'Ver detalles del evento' }}
                            </a>
                        </div>
                    </div>

                    {{-- ── Navegación (solo si hay > 1 evento) ─────────── --}}
                    @if (count($this->events) > 1)

                        <button wire:click="prev"
                                class="absolute left-0 top-0 h-full w-14 flex items-center
                                       justify-center bg-black/10 hover:bg-black/20 transition
                                       text-white text-3xl"
                                aria-label="Evento anterior">‹</button>

                        <button wire:click="next"
                                class="absolute right-0 top-0 h-full w-14 flex items-center
                                       justify-center bg-black/10 hover:bg-black/20 transition
                                       text-white text-3xl"
                                aria-label="Siguiente evento">›</button>

                        {{-- Dots --}}
                        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2
                                    px-4 py-2 rounded-full bg-black/30 backdrop-blur-sm"
                             role="tablist">
                            @foreach ($this->events as $index => $evt)
                                <button
                                    wire:click="goToSlide({{ $index }})"
                                    role="tab"
                                    aria-selected="{{ $index === $currentSlide ? 'true' : 'false' }}"
                                    aria-label="Evento {{ $index + 1 }}"
                                    class="h-2 rounded-full transition-all duration-300
                                        {{ $index === $currentSlide
                                            ? 'bg-white w-8'
                                            : 'bg-white/60 w-2 hover:bg-white' }}"
                                ></button>
                            @endforeach
                        </div>

                    @endif
                </div>

                {{-- ── Miniaturas de navegación ────────────────────────── --}}
                @if (count($this->events) > 1)
                    <div class="grid md:grid-cols-3 gap-4 mt-8" role="tablist">
                        @foreach ($this->events as $index => $evt)
                            @php
                                // Rehidratar el array serializado como objeto DTO
                                $evtDto = \App\DTOs\Home\Events\EventCardDto::fromLivewire($evt);
                            @endphp
                            <button
                                wire:click="goToSlide({{ $index }})"
                                role="tab"
                                aria-selected="{{ $index === $currentSlide ? 'true' : 'false' }}"
                                class="text-left p-4 rounded-xl border-2 transition-all
                                    {{ $index === $currentSlide
                                        ? 'bg-blue-50 border-blue-600'
                                        : 'bg-white border-gray-200 hover:border-gray-300' }}"
                            >
                                {{-- Acceso como objeto DTO --}}
                                <time datetime="{{ $evtDto->startDateIso }}"
                                      class="block text-sm text-gray-500 mb-2">
                                    {{ $evtDto->startDate }}
                                </time>
                                <span class="text-gray-900 line-clamp-2 text-sm font-medium">
                                    {{ $evtDto->title }}
                                </span>
                                @if ($evtDto->isFull)
                                    <span class="inline-block mt-2 text-xs text-red-600 font-medium">
                                        Cupo lleno
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

            </div>
        @endif

    </div>
</section>
