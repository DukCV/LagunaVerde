{{--
    livewire/news/new-detail/news-detail-page.blade.php
    ────────────────────────────────────────────────────────────────────
    Recibe: $article (NewsDetailDto), accesible via $this->article

    SEGURIDAD:
    • {{ }} en todo texto plano → XSS imposible.
    • {!! $article->content !!} SOLO para el campo content, que pasa
      por sanitizeHtml() en NewsDetailDto (strip_tags + regex).
    • e() en atributos de imagen (src).
    • El enlace "Volver" usa route() con nombre — sin concatenación manual.
    • Sub-componentes reciben datos del DTO — sin IDs enteros en el DOM.
    • Los sub-componentes solo se montan cuando tienen datos (condicional).
    ────────────────────────────────────────────────────────────────────
--}}

{{--
    -mt-24 cancela el pt-24 del body (principal.blade.php) para que bg-gray-50
    cubra desde y=0 y elimine la franja oscura visible bajo la cabecera fija.
    El pt-24 propio del contenedor sigue siendo el único desplazamiento de cabecera.
--}}
<div class="min-h-screen bg-gray-50 -mt-24 pt-24 pb-16">
    <div class="container mx-auto px-4">

        {{-- ── Volver ──────────────────────────────────────────────── --}}
        <a href="{{ route('news') }}"
           class="inline-flex items-center gap-2 text-gray-600 hover:text-blue-600
                  transition-colors mb-8 group">
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="w-5 h-5 group-hover:-translate-x-1 transition-transform"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                 aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            <span>Volver a noticias</span>
        </a>

        {{-- ── Layout principal ────────────────────────────────────── --}}
        <div class="grid lg:grid-cols-3 gap-12">

            {{-- ════════════════════════════════════════════════════════
                 COLUMNA ARTÍCULO
            ════════════════════════════════════════════════════════ --}}
            <article class="lg:col-span-2">

                {{-- Header del artículo --}}
                <header class="mb-8">
                    <div class="flex flex-wrap items-center gap-3 mb-4">

                        @if ($article->categoryName)
                            <span class="px-3 py-1.5 bg-blue-100 text-blue-700
                                         rounded-full text-sm font-medium">
                                {{ $article->categoryName }}
                            </span>
                        @endif

                        <div class="flex items-center gap-2 text-gray-500 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                 aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8"  y1="2" x2="8"  y2="6"/>
                                <line x1="3"  y1="10" x2="21" y2="10"/>
                            </svg>
                            <time datetime="{{ $article->publishedAtIso }}">
                                {{ $article->publishedAt }}
                            </time>
                        </div>
                    </div>

                    <h1 class="text-gray-900 font-bold text-4xl lg:text-5xl mb-6 leading-tight">
                        {{ $article->title }}
                    </h1>

                    @if ($article->authorName)
                        <div class="flex items-center gap-3 text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                 aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span class="font-semibold text-gray-900">{{ $article->authorName }}</span>
                        </div>
                    @endif
                </header>

                {{-- Imagen de portada --}}
                @if ($article->coverImage)
                    <div class="mb-8 rounded-2xl overflow-hidden shadow-lg">
                        <img
                            src="{{ e($article->coverImage->url) }}"
                            alt="{{ $article->coverImage->alt ?: $article->title }}"
                            class="w-full h-auto object-cover"
                            loading="eager"
                        />
                    </div>
                @endif

                {{-- Contenido completo (HTML sanitizado en NewsDetailDto::sanitizeHtml()) --}}
                {{-- .trix-content: mismos estilos que el editor Trix del panel admin (ver resources/css/app.css) --}}
                <div class="trix-content max-w-none">
                    {!! $article->content !!}
                </div>

                {{-- ── Galería (condicional) ───────────────────────── --}}
                @if (! empty($article->galleryImages) || ! empty($article->videos))
                    <div class="my-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">
                            Galería multimedia
                        </h2>
                        <livewire:news.new-detail.image-gallery
                            :items="array_merge($article->galleryImages, $article->videos)"
                            :newsTitle="$article->title"
                            :wire:key="'gallery-' . $article->uuid"
                        />
                    </div>
                @endif

                {{-- ── Documentos (condicional) ────────────────────── --}}
                @if (! empty($article->documents))
                    <div class="my-12 p-8 bg-white rounded-2xl shadow-lg">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">
                            Documentos adjuntos
                        </h2>
                        <div class="space-y-3">
                            @foreach ($article->documents as $doc)
                                <a
                                    href="{{ e($doc->url) }}"
                                    download
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between p-4 bg-gray-50
                                           hover:bg-blue-50 rounded-lg transition-colors group"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 group-hover:bg-blue-600
                                                    rounded-lg flex items-center justify-center
                                                    transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                 class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors"
                                                 fill="none" viewBox="0 0 24 24"
                                                 stroke="currentColor" stroke-width="2"
                                                 aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900
                                                       group-hover:text-blue-600 transition-colors">
                                                {{ $doc->title }}
                                            </p>
                                            <p class="text-sm text-gray-500">{{ $doc->size }}</p>
                                        </div>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ── ShareButtons ────────────────────────────────── --}}
                <div class="my-12 p-8 bg-white rounded-2xl shadow-lg">
                    <livewire:news.new-detail.share-buttons
                        :title="$article->title"
                        :shareUrl="url()->current()"
                        :wire:key="'share-' . $article->uuid"
                    />
                </div>

                {{-- ── CommentSection ──────────────────────────────── --}}
                <livewire:news.new-detail.comment-section
                    :newsUuid="$article->uuid"
                    :wire:key="'comments-' . $article->uuid"
                />

            </article>

            {{-- ════════════════════════════════════════════════════════
                 SIDEBAR
            ════════════════════════════════════════════════════════ --}}
            <div class="lg:col-span-1">
                <div class="sticky top-24">
                    <livewire:news.new-detail.news-sidebar
                        :currentUuid="$article->uuid"
                        :wire:key="'sidebar-' . $article->uuid"
                    />
                </div>
            </div>

        </div>
    </div>
</div>
