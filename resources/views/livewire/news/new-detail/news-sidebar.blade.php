{{--
    livewire/news/new-detail/news-sidebar.blade.php
    ────────────────────────────────────────────────────────────────────
    Recibe: $latestNews (array[]), se auto-refresca con wire:poll.10s

    SEGURIDAD:
    • {{ }} en toda salida de texto → XSS imposible.
    • e() en src de imágenes.
    • Links a noticias usan UUID del DTO — sin IDs enteros en el DOM.
    • El formulario de newsletter es sólo visual (fase actual).
    ────────────────────────────────────────────────────────────────────
--}}

{{-- wire:poll.10s recarga el sidebar cada 10 segundos --}}
<aside class="space-y-8" wire:poll.10s="refreshNews">

    {{-- ── Últimas noticias ──────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 shadow-lg">
        <h3 class="text-xl font-bold mb-5 text-gray-900 border-b border-gray-200 pb-3">
            Últimas Noticias
        </h3>

        @if (! empty($latestNews))
            <div class="space-y-5">
                @foreach ($latestNews as $news)
                    <a
                        href="{{ route('news.show', $news['uuid']) }}"
                        class="flex items-start gap-3 group"
                        aria-label="Ir a: {{ $news['title'] }}"
                    >
                        {{-- Miniatura si existe --}}
                        @if (! empty($news['coverUrl']))
                            <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden">
                                <img
                                    src="{{ e($news['coverUrl']) }}"
                                    alt="{{ $news['title'] }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                    loading="lazy"
                                />
                            </div>
                        @else
                            <div class="flex-shrink-0 w-16 h-16 rounded-lg bg-blue-50
                                        flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="w-6 h-6 text-blue-300"
                                     fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.5"
                                     aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <h4 class="font-medium text-gray-900 mb-1 line-clamp-2
                                       group-hover:text-blue-600 transition-colors leading-snug text-sm">
                                {{ $news['title'] }}
                            </h4>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <time datetime="{{ $news['publishedAtIso'] }}">
                                    {{ $news['publishedAt'] }}
                                </time>
                                @if (! empty($news['categoryName']))
                                    <span aria-hidden="true">&bull;</span>
                                    <span class="text-blue-600 font-medium">
                                        {{ $news['categoryName'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 text-sm">No hay noticias recientes disponibles.</p>
        @endif
    </div>

    {{-- ── Newsletter (sólo visual — fase futura) ─────────────────── --}}
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 shadow-lg">
        <div class="flex items-center gap-2 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-xl font-bold text-gray-900">Mantente informado</h3>
        </div>

        <p class="text-gray-700 mb-5 leading-relaxed text-sm">
            Recibe nuestras actualizaciones directamente en tu correo.
        </p>

        {{-- Formulario visual — funcionalidad en fase futura --}}
        <div class="space-y-3">
            <input
                type="email"
                placeholder="tu@email.com"
                disabled
                aria-label="Correo electrónico para newsletter"
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg
                       text-sm text-gray-400 cursor-not-allowed opacity-70"
            />
            <button
                type="button"
                disabled
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3
                       bg-blue-400 text-white font-medium rounded-lg cursor-not-allowed
                       opacity-70"
                title="Próximamente disponible"
            >
                Suscribirme
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </div>
        <p class="text-xs text-gray-500 mt-2">Esta función estará disponible próximamente.</p>
    </div>

    {{-- ── Donaciones (desactivado temporalmente) ─────────────────── --}}
    {{--
    <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800
                rounded-2xl p-8 shadow-lg text-white">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mb-4" viewBox="0 0 24 24"
             fill="currentColor" aria-hidden="true">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
        </svg>
        <h3 class="text-2xl font-bold mb-3">Apoya la Causa</h3>
        <p class="text-blue-100 mb-6 leading-relaxed text-sm">
            Tu donación nos ayuda a continuar protegiendo y restaurando la laguna.
        </p>
        <a
            href="#"
            class="block w-full text-center px-6 py-3 bg-white text-blue-700
                   font-semibold rounded-lg hover:bg-blue-50 transition-colors"
        >
            DONAR AHORA
        </a>
    </div>
    --}}

</aside>
