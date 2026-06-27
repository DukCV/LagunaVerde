{{--
    livewire/news/news-index.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe:
      $featured      → NewsCardDto|null
      $newsPaginator → LengthAwarePaginator<NewsCardDto>
      $totalResults  → int
      $categories    → array<string, string>

    SEGURIDAD EN VISTA:
    • {{ }} en toda salida → auto-escaping XSS de Blade.
    • $search impreso en pantalla pasa por {{ }} → seguro ante XSS.
    • wire:key usa $article->uuid → sin IDs enteros en el DOM.
    • El input de búsqueda es type="search" con maxlength para limitar en cliente.
    • Las options del select provienen del servicio (valores de BD) — no del DOM.
    ─────────────────────────────────────────────────────────────────────
--}}

<div>

    {{-- ══════════════════════════════════════════════════════════════
         HERO HEADER
    ══════════════════════════════════════════════════════════════ --}}
    <section class="relative bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 py-16 lg:py-24 overflow-hidden">
        {{-- Patrón de fondo sutil --}}
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:20px_20px]"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-block px-4 py-2 bg-white/20 backdrop-blur-sm text-white
                            rounded-full mb-6 text-sm font-medium tracking-wide animate-fade-in-up">
                    Mantente Informado
                </div>
                <h1 class="text-white text-4xl lg:text-6xl font-bold mb-6 leading-tight animate-fade-in-up" style="animation-delay: 100ms;">
                    Noticias y Actualidad
                </h1>
                <p class="text-blue-100 text-lg lg:text-xl max-w-2xl mx-auto leading-relaxed animate-fade-in-up" style="animation-delay: 200ms;">
                    Mantente informado sobre las acciones para rescatar nuestra laguna.
                </p>
            </div>
        </div>

        {{-- Separador SVG (Wave) --}}
        <div class="absolute bottom-0 w-full leading-none translate-y-px">
            <svg class="block w-full h-10 lg:h-20 text-gray-50 drop-shadow-sm" fill="currentColor" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V95.8C52.16,112.44,112.08,125.8,165.4,124.2,228.16,122.3,277.6,90,321.39,56.44Z"></path>
            </svg>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         TOOLBAR (sticky)
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b sticky top-0 z-30 shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">

                {{-- Búsqueda --}}
                <div class="relative w-full md:w-96">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        wire:model.live.debounce.400ms="search"
                        type="search"
                        maxlength="100"
                        placeholder="Buscar por palabras clave..."
                        autocomplete="off"
                        aria-label="Buscar noticias"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm"
                    />
                </div>

                {{-- Filtro de categoría --}}
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5 text-gray-400 hidden md:block shrink-0"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    <select
                        wire:model.live="category"
                        aria-label="Filtrar por categoría"
                        class="w-full md:w-64 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm text-gray-700 appearance-none cursor-pointer"
                    >
                        {{--
                            Las opciones vienen del servicio (valores de BD validados),
                            no del input del usuario → sin posibilidad de inyección.
                        --}}
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Contador de resultados --}}
            <p class="mt-3 text-sm text-gray-500" aria-live="polite" aria-atomic="true">
                <span class="font-medium text-gray-700">{{ $totalResults }}</span>
                {{ $totalResults === 1 ? 'resultado' : 'resultados' }}
                {{-- $search ya pasa por {{ }} → XSS imposible --}}
                @if ($search)
                    para <span class="italic">"{{ $search }}"</span>
                @endif
            </p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         GRID DE NOTICIAS
    ══════════════════════════════════════════════════════════════ --}}
    <section class="py-12 lg:py-16 bg-gray-50 min-h-[40vh]">
        <div class="container mx-auto px-4">

            @if ($totalResults === 0)

                {{-- ── Estado vacío ──────────────────────────────── --}}
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mb-4"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"
                         aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <p class="text-gray-500 text-lg">
                        No se encontraron noticias que coincidan con tu búsqueda.
                    </p>
                    <button
                        wire:click="$set('search', '')"
                        class="mt-4 text-blue-600 hover:underline text-sm"
                    >
                        Limpiar búsqueda
                    </button>
                </div>

            @else

                {{-- ── Noticia destacada (la más reciente) ─────── --}}
                @if ($featured)
                    <div class="mb-12">
                        {{--
                            wire:key usa UUID del DTO → sin IDs enteros en el DOM.
                            El DTO implementa Wireable; Livewire lo serializa de forma segura.
                        --}}
                        <livewire:news.news-card
                            :article="$featured"
                            :featured="true"
                            :wire:key="'featured-' . $featured->uuid"
                        />
                    </div>
                @endif

                {{-- ── Grid de noticias restantes ──────────────── --}}
                <div wire:loading.class="opacity-50 pointer-events-none transition-opacity duration-300" class="w-full relative">
                    {{-- Spinner de carga centrado --}}
                    <div wire:loading.flex class="absolute inset-0 z-10 hidden items-center justify-center">
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-full shadow-lg">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>

                    @if ($newsPaginator->count() > 0)
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                            @foreach ($newsPaginator as $article)
                                <livewire:news.news-card
                                    :article="$article"
                                    :featured="false"
                                    :wire:key="'card-' . $article->uuid"
                                />
                            @endforeach
                        </div>
                    @endif
                </div>

            @endif

        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         PAGINACIÓN
    ══════════════════════════════════════════════════════════════ --}}
    @if ($newsPaginator->hasPages())
        <section class="pb-16 bg-gray-50">
            <div class="container mx-auto px-4">
                <div class="flex flex-col items-center gap-4">

                    {{-- Paginador nativo de Laravel (Eloquent real, no manual) --}}
                    {{ $newsPaginator->links() }}

                    <p class="text-sm text-gray-500">
                        Página
                        <span class="font-medium text-gray-700">{{ $newsPaginator->currentPage() }}</span>
                        de
                        <span class="font-medium text-gray-700">{{ $newsPaginator->lastPage() }}</span>
                        &middot;
                        {{ $newsPaginator->total() }}
                        {{ $newsPaginator->total() === 1 ? 'artículo' : 'artículos' }}
                    </p>

                </div>
            </div>
        </section>
    @endif

</div>
