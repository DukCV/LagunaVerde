<section
    id="inicio"
    class="relative -mt-24 min-h-screen flex items-center pt-24 sm:pt-28"
>
    {{-- Background --}}
    <div class="absolute inset-0">
        <img
            src="https://www.yosoypuebla.com/wp-content/uploads/2024/07/chignahuapan-portada-puebla-magicos.jpg"
            alt="Laguna hermosa"
            class="w-full h-full object-cover"
            loading="eager"
        />

        <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/30"></div>
    </div>

    {{-- Content --}}
    <div class="container mx-auto px-4 relative z-10">
        {{-- Encabezado (izquierda) + tarjeta destacada (derecha); apilados en móvil --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-10 lg:gap-12">

            <div class="max-w-3xl">
                <h1 class="text-white text-4xl sm:text-5xl md:text-6xl lg:text-7xl mb-4 sm:mb-6 leading-tight font-bold">
                    Salvemos Nuestra Laguna Juntos
                </h1>

                <p class="text-white/90 text-base sm:text-lg lg:text-2xl mb-6 sm:mb-8 leading-relaxed max-w-2xl">
                    Protegemos y restauramos el ecosistema de la laguna para las generaciones futuras.
                    Cada acción cuenta, cada donación marca la diferencia.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    {{-- Donar (desactivado temporalmente) --}}
                    {{--
                    <button
                        wire:click="donate"
                        class="flex items-center justify-center gap-3 px-8 py-4 bg-blue-600 text-white rounded-lg
                               hover:bg-blue-700 transition-all shadow-2xl
                               hover:shadow-blue-500/50 hover:scale-105"
                    >
                        ❤️
                        <span class="text-lg">Donar Ahora</span>
                    </button>
                    --}}

                    {{-- Conoce más --}}
                    <a
                        href="#quienes-somos"
                        class="flex items-center justify-center gap-3 px-8 py-4
                               bg-white/10 backdrop-blur-sm text-white
                               border-2 border-white/30 rounded-lg
                               hover:bg-white/20 transition-all"
                    >
                        <span class="text-lg">Conoce Más</span>
                        →
                    </a>
                </div>

                {{-- Estadísticas de impacto en tiempo real --}}
                <div class="mt-8 sm:mt-12 pt-6 sm:pt-8 border-t border-white/30">
                    <livewire:shared.impact-stats />
                </div>
            </div>

            {{-- Tarjeta destacada: última noticia o próximo evento, al azar --}}
            @if ($spotlight)
                <a
                    href="{{ $spotlight->url }}"
                    aria-label="Ver detalle: {{ $spotlight->titulo }}"
                    class="group block w-full max-w-sm shrink-0 bg-white/10 backdrop-blur-md
                           border border-white/20 rounded-2xl overflow-hidden shadow-2xl
                           hover:bg-white/15 hover:-translate-y-1 transition-all duration-300"
                >
                    @if ($spotlight->coverUrl)
                        <img
                            src="{{ $spotlight->coverUrl }}"
                            alt="{{ $spotlight->coverAlt ?? $spotlight->titulo }}"
                            loading="lazy"
                            class="w-full h-40 object-cover"
                        >
                    @endif

                    <div class="p-5">
                        <span class="inline-block px-3 py-1 bg-blue-500/90 text-white text-xs
                                     font-semibold rounded-full mb-3">
                            {{ $spotlight->tipoLabel }}
                        </span>

                        <h3 class="text-white font-semibold text-lg leading-snug mb-1 line-clamp-2">
                            {{ $spotlight->titulo }}
                        </h3>

                        <p class="text-white/70 text-sm mb-4">{{ $spotlight->fecha }}</p>

                        <span class="inline-flex items-center gap-2 px-4 py-2 bg-white text-blue-700
                                     text-sm font-semibold rounded-lg group-hover:gap-3 transition-all">
                            Ver detalle
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </div>
                </a>
            @endif

        </div>
    </div>

    {{-- Scroll Indicator --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
        <div class="w-6 h-10 border-2 border-white/50 rounded-full flex items-start justify-center p-2">
            <div class="w-1.5 h-1.5 bg-white/70 rounded-full"></div>
        </div>
    </div>
</section>
