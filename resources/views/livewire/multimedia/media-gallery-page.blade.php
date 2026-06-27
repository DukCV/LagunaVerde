{{--
    livewire/multimedia/media-gallery-page.blade.php
    ─────────────────────────────────────────────────────────────────────────
    Recibe:
      $albums        → LengthAwarePaginator<AlbumDto>
      $typeOptions   → array<string, string>   ['all'=>'Todos', ...]
      $totalResults  → int

    ARQUITECTURA DE INTERACTIVIDAD:
    • Búsqueda, filtro de tipo y paginación → Livewire (requieren consultar BD).
    • Expansión de álbum y modal de media   → Alpine.js puro (estado de
      presentación sobre datos ya presentes en el DOM, sin ida y vuelta al
      servidor). Esto evita el jank de red y permite x-transition fluidas.
    • Los items de cada álbum se exponen UNA sola vez por álbum vía @js() en
      su x-data — antes se reenviaba el álbum completo en cada miniatura.

    SEGURIDAD:
    • {{ }} en toda salida de usuario → XSS imposible.
    • @js() para todo valor de servidor embebido en una expresión Alpine →
      escapado seguro para contexto JavaScript (evita romper la cadena si el
      texto contiene comillas).
    • wire:model sincroniza filtros con variables PHP — sin input libre al DOM.
    • wire:key usa uuid del DTO — nunca el ID entero.
    • loading="lazy" en todas las imágenes → carga condicional de assets.
    • El iframe de video externo se monta con x-if (no x-show): no existe en
      el DOM, y por lo tanto no consume ancho de banda, hasta que el usuario
      abre explícitamente ese ítem en el modal. sandbox restringe su origen.
    ─────────────────────────────────────────────────────────────────────────
--}}

<div class="min-h-screen bg-gray-50">

    {{-- ════════════════════════════════════════════════════════════════
         HERO HEADER — gradiente azul con título y descripción
    ════════════════════════════════════════════════════════════════ --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white py-20">
        <div class="container mx-auto px-4">
            <div class="max-w-3xl">

                {{-- Etiqueta de sección --}}
                <div class="inline-block px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full mb-4 text-sm font-medium tracking-wide">
                    Repositorio Visual
                </div>

                <h1 class="text-4xl lg:text-5xl font-bold mb-4 leading-tight">
                    Galería Multimedia
                </h1>

                <p class="text-blue-100 text-lg leading-relaxed max-w-2xl">
                    Explora nuestra colección de imágenes y videos organizados por noticias y eventos.
                    Cada álbum cuenta una historia de conservación y esfuerzo comunitario.
                </p>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         BARRA DE FILTROS — búsqueda en tiempo real y filtro por tipo
    ════════════════════════════════════════════════════════════════ --}}
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-md p-6 mb-8">
            <div class="flex flex-col sm:flex-row gap-4">

                {{-- Campo de búsqueda con debounce de 400ms --}}
                <div class="relative flex-1">
                    {{-- Icono de lupa (aria-hidden para accesibilidad) --}}
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
                        id="gallery-search"
                        maxlength="100"
                        placeholder="Buscar por título o descripción..."
                        autocomplete="off"
                        aria-label="Buscar en la galería multimedia"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm"
                    />
                    {{-- Indicador de carga durante la búsqueda --}}
                    <div wire:loading wire:target="search"
                         class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin">
                    </div>
                </div>

                {{-- Filtros de tipo como pills de selección --}}
                <div class="flex items-center gap-2 flex-wrap" role="group" aria-label="Filtrar por tipo de media">
                    @foreach($typeOptions as $value => $label)
                        <button
                            wire:click="$set('typeFilter', '{{ $value }}')"
                            id="filter-{{ $value }}"
                            aria-pressed="{{ $typeFilter === $value ? 'true' : 'false' }}"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 border
                                   {{ $typeFilter === $value
                                       ? 'bg-blue-600 text-white border-blue-600 shadow-md'
                                       : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-600' }}"
                        >
                            {{-- Icono según el tipo de filtro --}}
                            @if($value === 'image')
                                <span class="mr-1" aria-hidden="true">🖼️</span>
                            @elseif($value === 'video')
                                <span class="mr-1" aria-hidden="true">🎬</span>
                            @else
                                <span class="mr-1" aria-hidden="true">📁</span>
                            @endif
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Contador de resultados y botón limpiar filtros --}}
            <div class="mt-4 flex items-center justify-between flex-wrap gap-2">
                <p class="text-sm text-gray-500" aria-live="polite" aria-atomic="true">
                    <span class="font-semibold text-gray-700">{{ $totalResults }}</span>
                    {{ $totalResults === 1 ? 'álbum encontrado' : 'álbumes encontrados' }}
                    @if($search)
                        para <span class="italic text-gray-600">"{{ $search }}"</span>
                    @endif
                </p>

                {{-- Botón limpiar filtros — visible sólo cuando hay filtros activos --}}
                @if($search || $typeFilter !== 'all')
                    <button
                        wire:click="$set('search', ''); $set('typeFilter', 'all')"
                        class="text-sm text-blue-600 hover:underline hover:text-blue-700 transition-colors"
                    >
                        ✕ Limpiar filtros
                    </button>
                @endif
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             GRID DE ÁLBUMES
        ════════════════════════════════════════════════════════════ --}}

        @if($totalResults === 0)

            {{-- Estado vacío — sin álbumes que coincidan --}}
            <div class="flex flex-col items-center justify-center py-32 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-300"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"
                         aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-700 mb-2">
                    No se encontraron álbumes
                </h2>
                <p class="text-gray-500 text-sm mb-6 max-w-sm">
                    No hay contenido multimedia que coincida con los filtros seleccionados.
                </p>
                <button
                    wire:click="$set('search', ''); $set('typeFilter', 'all')"
                    class="text-blue-600 hover:underline text-sm font-medium"
                >
                    Limpiar filtros
                </button>
            </div>

        @else

            {{-- Grid de álbumes — comportamiento responsive --}}
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">

                @foreach($albums as $album)
                    {{--
                        x-data encapsula TODO el estado de presentación de este álbum:
                          - expanded:    si el grid de miniaturas está visible
                          - items:       los MediaAlbumItemDto del álbum, una sola vez
                                         (ya no se reenvía el álbum completo por miniatura)
                          - modalIndex:  índice del item abierto en el modal, o null
                        wire:key usa uuid del DTO — nunca el ID entero.
                    --}}
                    <div
                        wire:key="album-{{ $album->uuid }}"
                        x-data="{
                            expanded: false,
                            items: @js($album->mediaItems),
                            modalIndex: null,
                        }"
                        class="bg-white rounded-2xl shadow-md overflow-hidden transition-all duration-300"
                        :class="expanded ? 'md:col-span-2 lg:col-span-3' : ''"
                    >

                        {{-- ── Portada del álbum (clickable) ──────────────────────────── --}}
                        <div
                            role="button"
                            tabindex="0"
                            @click="expanded = !expanded"
                            @keydown.enter="expanded = !expanded"
                            @keydown.space.prevent="expanded = !expanded"
                            class="cursor-pointer group"
                            :aria-expanded="expanded ? 'true' : 'false'"
                            :aria-label="(expanded ? 'Contraer' : 'Expandir') + ' álbum: ' + @js($album->title)"
                        >
                            <div class="relative h-64 overflow-hidden">

                                {{-- Imagen de portada con lazy loading --}}
                                @if($album->coverImageUrl)
                                    <img
                                        src="{{ $album->coverImageUrl }}"
                                        alt="{{ $album->title }}"
                                        loading="lazy"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    />
                                @else
                                    {{-- Placeholder cuando no hay imagen de portada --}}
                                    <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-400"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                    </div>
                                @endif

                                {{-- Overlay gradiente para legibilidad del texto --}}
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>

                                {{-- Badge de categoría (noticia / evento) --}}
                                <div class="absolute top-4 left-4">
                                    <span class="px-3 py-1 bg-white/90 backdrop-blur-sm rounded-full text-xs font-semibold text-gray-800">
                                        {{ $album->category === 'noticia' ? '📰 Noticia' : '📅 Evento' }}
                                    </span>
                                </div>

                                {{-- Información en la parte inferior --}}
                                <div class="absolute bottom-0 left-0 right-0 p-5 text-white">

                                    {{-- Contador de archivos multimedia --}}
                                    <div class="flex items-center gap-1.5 text-xs text-white/80 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                        <span>{{ $album->mediaCount }} {{ $album->mediaCount === 1 ? 'archivo' : 'archivos' }} multimedia</span>
                                    </div>

                                    {{-- Título del álbum (truncado a 2 líneas) --}}
                                    <h2 class="text-lg font-bold mb-1.5 line-clamp-2 leading-snug">
                                        {{ $album->title }}
                                    </h2>

                                    {{-- Fecha del álbum --}}
                                    <p class="text-xs text-white/70">
                                        {{ $album->date }}
                                    </p>
                                </div>

                                {{-- Chevron único: rota 180° de forma animada según el estado --}}
                                <div class="absolute bottom-5 right-4">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="w-6 h-6 text-white transition-transform duration-300"
                                         :class="expanded ? 'rotate-180' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </div>

                            </div>
                        </div>

                        {{--
                            ── Grid de media expandida (Alpine, sin redirección) ────────
                            x-transition con sólo Alpine "core" (sin el plugin @alpinejs/collapse,
                            que no está instalado en este proyecto): fade + desplazamiento vertical
                            sutil, sin animar 'height' — evita layout shift/jank en móvil.
                        --}}
                        <div
                            x-show="expanded"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            class="p-6 border-t border-gray-100"
                        >

                            {{-- Descripción del álbum --}}
                            @if($album->description)
                                <p class="text-gray-600 text-sm leading-relaxed mb-6">
                                    {{ $album->description }}
                                </p>
                            @endif

                            {{-- Grid de miniaturas de los items de media --}}
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                                @foreach($album->mediaItems as $index => $mediaItem)
                                    <button
                                        type="button"
                                        @click="modalIndex = {{ $index }}"
                                        class="relative aspect-square rounded-xl overflow-hidden cursor-pointer group bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                                        aria-label="Ver {{ $mediaItem->isVideo ? 'video' : 'imagen' }}: {{ $mediaItem->alt ?: $mediaItem->title }}"
                                    >
                                        {{-- Miniatura del item --}}
                                        <img
                                            src="{{ $mediaItem->thumbnailUrl }}"
                                            alt="{{ $mediaItem->alt }}"
                                            loading="lazy"
                                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect width=%22100%22 height=%22100%22 fill=%22%23e5e7eb%22/%3E%3C/svg%3E'"
                                        />

                                        {{-- Overlay con icono de play para videos --}}
                                        @if($mediaItem->isVideo)
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/45 transition-colors">
                                                <div class="w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600 ml-0.5"
                                                         fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Overlay con descripción al hacer hover --}}
                                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-200
                                                    bg-gradient-to-t from-black/70 to-transparent">
                                            <div class="absolute bottom-0 left-0 right-0 p-2">
                                                <p class="text-white text-xs line-clamp-2 leading-tight">
                                                    {{ $mediaItem->alt ?: $mediaItem->title }}
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                        </div>

                        {{-- ── Modal de media — impulsado por modalIndex (Alpine) ─────── --}}
                        <template x-if="modalIndex !== null">
                            <div
                                x-show="modalIndex !== null"
                                x-transition.opacity.duration.200ms
                                @keydown.escape.window="modalIndex = null"
                                @click.self="modalIndex = null"
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="modal-title-{{ $album->uuid }}"
                                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
                            >
                                <div
                                    x-show="modalIndex !== null"
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-200"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    @click.stop
                                    class="relative bg-white rounded-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden shadow-2xl"
                                    role="document"
                                >

                                    {{-- Botón de cierre del modal --}}
                                    <button
                                        type="button"
                                        @click="modalIndex = null"
                                        class="absolute top-4 right-4 z-10 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full
                                               flex items-center justify-center hover:bg-white transition-colors shadow-lg
                                               focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        aria-label="Cerrar visor de media"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-900"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>

                                    <div class="grid lg:grid-cols-2 h-full">

                                        {{-- ── Panel izquierdo: preview de la media ──────────────────── --}}
                                        <div class="bg-gray-900 flex items-center justify-center p-8 min-h-[300px] lg:min-h-[400px]">

                                            {{--
                                                x-if (no x-show) para el video: el <iframe> NUNCA existe en
                                                el DOM —y por lo tanto no descarga nada— hasta que el usuario
                                                abre explícitamente ese ítem.
                                            --}}
                                            <template x-if="modalIndex !== null && items[modalIndex]?.isVideo">
                                                <iframe
                                                    :src="items[modalIndex]?.url"
                                                    :title="items[modalIndex]?.alt || items[modalIndex]?.title"
                                                    class="w-full aspect-video rounded-lg"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen
                                                    sandbox="allow-scripts allow-same-origin allow-presentation"
                                                    loading="lazy"
                                                ></iframe>
                                            </template>

                                            <template x-if="modalIndex !== null && !items[modalIndex]?.isVideo">
                                                <img
                                                    :src="items[modalIndex]?.url"
                                                    :alt="items[modalIndex]?.alt"
                                                    loading="lazy"
                                                    class="max-w-full max-h-[500px] object-contain rounded-lg"
                                                />
                                            </template>
                                        </div>

                                        {{-- ── Panel derecho: información del álbum y CTA ────────────── --}}
                                        <div class="p-8 flex flex-col overflow-y-auto">
                                            <div class="flex-1">

                                                {{-- Badge de categoría — estático, no depende del ítem seleccionado --}}
                                                <div class="inline-block px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold mb-4">
                                                    {{ $album->category === 'noticia' ? '📰 Noticia' : '📅 Evento' }}
                                                </div>

                                                {{-- Título del álbum padre --}}
                                                <h2 id="modal-title-{{ $album->uuid }}" class="text-xl lg:text-2xl font-bold text-gray-900 mb-3 leading-snug">
                                                    {{ $album->title }}
                                                </h2>

                                                {{-- Descripción del álbum --}}
                                                @if($album->description)
                                                    <p class="text-gray-600 text-sm leading-relaxed mb-6">
                                                        {{ $album->description }}
                                                    </p>
                                                @endif

                                                {{-- Meta información del item de media (depende de modalIndex) --}}
                                                <div class="space-y-2.5 border-t border-gray-100 pt-4">

                                                    {{-- Descripción o título del archivo --}}
                                                    <div class="flex items-start gap-2.5 text-gray-700" x-show="items[modalIndex]?.alt || items[modalIndex]?.title">
                                                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 flex-shrink-0"></div>
                                                        <span class="text-sm" x-text="items[modalIndex]?.alt || items[modalIndex]?.title"></span>
                                                    </div>

                                                    {{-- Fecha del álbum — estática --}}
                                                    <div class="flex items-center gap-2.5 text-gray-700">
                                                        <div class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></div>
                                                        <span class="text-sm">
                                                            Publicado: {{ $album->date }}
                                                        </span>
                                                    </div>

                                                    {{-- Tipo de archivo (depende de modalIndex) --}}
                                                    <div class="flex items-center gap-2.5 text-gray-700">
                                                        <div class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></div>
                                                        <span class="text-sm" x-text="items[modalIndex]?.isVideo ? '🎬 Video' : '🖼️ Imagen'"></span>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- CTA: Botón "Ver detalles completos" — enlace estático del álbum --}}
                                            <div class="mt-8">
                                                <a
                                                    href="{{ $album->detailRoute }}"
                                                    wire:navigate
                                                    class="w-full inline-flex items-center justify-center gap-2
                                                           bg-blue-600 text-white py-3.5 px-6 rounded-xl font-semibold
                                                           hover:bg-blue-700 transition-colors focus:outline-none
                                                           focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                                    aria-label="Ver detalles completos de {{ $album->title }}"
                                                >
                                                    <span>Ver detalles completos</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5"
                                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                                        <polyline points="12 5 19 12 12 19"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </template>

                    </div>
                @endforeach

            </div>

        @endif

        {{-- ════════════════════════════════════════════════════════════
             PAGINACIÓN
        ════════════════════════════════════════════════════════════ --}}
        @if($albums->hasPages())
            <div class="flex flex-col items-center gap-4 mt-12">
                {{ $albums->links() }}
                <p class="text-sm text-gray-500">
                    Página
                    <span class="font-semibold text-gray-700">{{ $albums->currentPage() }}</span>
                    de
                    <span class="font-semibold text-gray-700">{{ $albums->lastPage() }}</span>
                </p>
            </div>
        @endif

    </div>
    {{-- /container --}}

</div>
{{-- /root --}}
