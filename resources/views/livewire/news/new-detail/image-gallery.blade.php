{{--
    livewire/news/new-detail/image-gallery.blade.php
    ────────────────────────────────────────────────────────────────────
    Recibe: $items (array de MediaItemDto serializados), $newsTitle

    SEGURIDAD:
    • Las URLs de imágenes/videos vienen del DTO (sanitizadas).
    • e() en atributos src dentro de x-bind → previene XSS.
    • Alpine.js maneja el carrusel client-side → sin roundtrips al servidor.
    • @js() codifica el array PHP de forma segura para Alpine.

    REPRODUCCIÓN DE VIDEO (sin fugas en segundo plano):
    • mediaCarousel() (resources/js/media/media-carousel.js, Alpine.data()
      registrado en app.js) pausa y reinicia cualquier <video> de este
      carrusel ANTES de cambiar de diapositiva — x-show solo oculta con
      'display:none', nunca destruye el <video>, así que sin esto seguiría
      sonando de fondo tras avanzar a la siguiente miniatura/diapositiva.
    ────────────────────────────────────────────────────────────────────
--}}

@if (! empty($items))
<div
    x-data="mediaCarousel(@js($items))"
    class="rounded-2xl overflow-hidden bg-gray-900"
>
    {{-- ── Visor principal ────────────────────────────────────────── --}}
    <div class="relative aspect-video">

        <template x-for="(item, index) in items" :key="index">
            <div x-show="currentIndex === index" class="absolute inset-0">

                {{-- Imagen --}}
                <template x-if="item.isImage">
                    <img
                        :src="item.url"
                        :alt="item.alt || ('{{ addslashes($newsTitle) }} - imagen ' + (index + 1))"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                </template>

                {{-- Video --}}
                <template x-if="item.isVideo">
                    <video
                        :src="item.url"
                        controls
                        preload="metadata"
                        class="w-full h-full object-contain bg-black"
                        :aria-label="item.title || 'Video ' + (index + 1)"
                    ></video>
                </template>

            </div>
        </template>

        {{-- Controles de navegación (solo si hay más de 1 ítem) --}}
        <template x-if="total() > 1">
            <div>
                <button
                    @click="prev()"
                    class="absolute left-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12
                           bg-white/90 hover:bg-white rounded-full flex items-center
                           justify-center shadow-lg transition-all hover:scale-110"
                    aria-label="Anterior"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-800"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <button
                    @click="next()"
                    class="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12
                           bg-white/90 hover:bg-white rounded-full flex items-center
                           justify-center shadow-lg transition-all hover:scale-110"
                    aria-label="Siguiente"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-800"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                {{-- Contador --}}
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-4 py-2
                            bg-black/60 text-white rounded-full text-sm select-none"
                     aria-live="polite">
                    <span x-text="currentIndex + 1"></span> / <span x-text="total()"></span>
                </div>
            </div>
        </template>

    </div>

    {{-- ── Miniaturas ──────────────────────────────────────────────── --}}
    <template x-if="total() > 1">
        <div class="flex gap-2 p-4 bg-gray-800 overflow-x-auto">
            <template x-for="(item, index) in items" :key="'thumb-' + index">
                <button
                    @click="goTo(index)"
                    :class="currentIndex === index
                        ? 'ring-4 ring-blue-500 scale-105'
                        : 'opacity-60 hover:opacity-100'"
                    class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden
                           transition-all bg-gray-700"
                    :aria-label="'Ir a elemento ' + (index + 1)"
                    :aria-pressed="currentIndex === index"
                >
                    {{-- Miniatura imagen --}}
                    <template x-if="item.isImage">
                        <img :src="item.url" :alt="item.alt"
                             class="w-full h-full object-cover" loading="lazy" />
                    </template>

                    {{-- Miniatura video (icono play) --}}
                    <template x-if="item.isVideo">
                        <div class="w-full h-full flex items-center justify-center bg-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white"
                                 fill="currentColor" viewBox="0 0 24 24">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                    </template>

                </button>
            </template>
        </div>
    </template>

</div>
@endif
