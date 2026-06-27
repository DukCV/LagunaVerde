{{--
    livewire/news/news-card.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe: $article (NewsCardDto), $featured (bool), $showDocs (bool)

    SEGURIDAD EN VISTA:
    • {{ }} en toda salida de texto → escaping XSS automático de Blade.
    • e() en atributos de imagen (src, href) → previene XSS en atributos.
    • El enlace "Leer más" usa $article->uuid en la URL; nunca el ID entero.
    • 'content' no existe en el DTO → es imposible mostrarlo por error.
    • <time datetime=""> expone fecha en ISO para accesibilidad.
    • download en documentos sin target="_blank" abierto → sin tab hijacking.
    ─────────────────────────────────────────────────────────────────────
--}}

<article
    wire:loading.class="opacity-50 scale-[0.98] blur-sm"
    class="group bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-2xl hover:border-blue-100
           transition-all duration-500 hover:-translate-y-2
           {{ $featured ? 'md:flex md:min-h-[400px]' : 'flex flex-col' }}"
>
    {{-- ── Imagen de portada ──────────────────────────────────────── --}}
    <div class="relative overflow-hidden
                {{ $featured ? 'md:w-2/3 h-64 md:h-auto shrink-0' : 'h-56' }}
                bg-gradient-to-br from-blue-50 to-green-50">

        @if ($article->coverUrl)
            <img
                src="{{ e($article->coverUrl) }}"
                alt="{{ $article->coverAlt }}"
                loading="lazy"
                width="800"
                height="400"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-out"
                onerror="this.src='https://placehold.co/800x400/e2e8f0/94a3b8?text=Sin+imagen';this.onerror=null;"
            />
        @else
            {{-- Placeholder accesible cuando no hay imagen ─────────── --}}
            <div class="w-full h-full flex items-center justify-center">
                <span class="text-6xl opacity-20" aria-hidden="true">🌿</span>
            </div>
        @endif

        {{-- Categoría --}}
        @if ($article->categoryName)
            <div class="absolute top-4 left-4">
                <span class="px-3 py-1.5 bg-white/90 backdrop-blur-sm text-gray-900
                             text-sm font-medium rounded-full shadow-sm">
                    {{ $article->categoryName }}
                </span>
            </div>
        @endif

        {{-- Badge "Destacado" --}}
        @if ($featured)
            <div class="absolute top-4 right-4">
                <span class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-full shadow">
                    Destacado
                </span>
            </div>
        @endif

    </div>

    {{-- ── Contenido ──────────────────────────────────────────────── --}}
    <div class="p-6 flex flex-col
                {{ $featured ? 'md:w-1/3 md:justify-center' : 'flex-1' }}">

        {{-- Meta: fecha de publicación --}}
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8"  y1="2" x2="8"  y2="6"/>
                <line x1="3"  y1="10" x2="21" y2="10"/>
            </svg>
            {{-- datetime en ISO para lectores de pantalla --}}
            <time datetime="{{ $article->publishedAtIso }}">
                {{ $article->publishedAt }}
            </time>

            @if ($article->authorName)
                <span aria-hidden="true">·</span>
                <span>{{ $article->authorName }}</span>
            @endif
        </div>

        {{-- Título — nunca se imprime 'content' aquí --}}
        <h3 class="text-gray-900 font-semibold mb-3 line-clamp-2
                   group-hover:text-blue-600 transition-colors leading-snug
                   {{ $featured ? 'text-2xl lg:text-3xl' : 'text-xl' }}">
            {{ $article->title }}
        </h3>

        {{-- Resumen corto (summary) --}}
        @if ($article->summary)
            <p class="text-gray-600 leading-relaxed mb-4
                      {{ $featured ? 'line-clamp-4 text-base lg:text-lg' : 'line-clamp-3 text-sm' }}">
                {{ $article->summary }}
            </p>
        @endif

        <div class="flex flex-col gap-3 mt-auto">

            {{--
                Enlace "Leer más":
                - Usa <a href> (no wire:click) → funciona sin JS, es indexable y
                  permite cmd+click para abrir en nueva pestaña.
                - UUID en la URL; el ID entero NUNCA aparece en el HTML.
                - rel="noopener" si se abriera en _blank (aquí es _self por defecto).
            --}}
            <a
                href="{{ route('news.show', $article->uuid) }}"
                class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800
                       font-semibold text-sm w-fit group/btn px-4 py-2 bg-blue-50 hover:bg-blue-100 rounded-full transition-colors duration-300"
                aria-label="Leer noticia completa: {{ $article->title }}"
            >
                <span>Leer más</span>
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4 group-hover/btn:translate-x-1 transition-transform"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </a>

            {{-- Documentos adjuntos --}}
            @if (! empty($article->documents))
                <div class="border-t pt-3">

                    <button
                        wire:click="toggleDocs"
                        class="flex items-center gap-2 text-gray-600 hover:text-blue-600
                               transition-colors text-sm"
                        aria-expanded="{{ $showDocs ? 'true' : 'false' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>Documentos adjuntos ({{ count($article->documents) }})</span>
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="w-4 h-4 transition-transform duration-200 {{ $showDocs ? 'rotate-180' : '' }}"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                             aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    @if ($showDocs)
                        <div class="mt-3 space-y-2" role="list">
                            @foreach ($article->documents as $doc)
                                <a
                                    href="{{ e($doc['url']) }}"
                                    download
                                    rel="noopener noreferrer"
                                    role="listitem"
                                    class="flex items-center justify-between p-2.5 bg-gray-50
                                           rounded-xl hover:bg-blue-50 hover:text-blue-700
                                           transition-colors text-sm group/doc"
                                >
                                    <span class="text-gray-700 group-hover/doc:text-blue-700
                                                 truncate pr-2">
                                        {{ $doc['name'] }}
                                    </span>
                                    <span class="text-gray-400 text-xs whitespace-nowrap">
                                        {{ $doc['size'] }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                </div>
            @endif

        </div>
    </div>
</article>
