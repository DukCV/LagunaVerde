{{--
    livewire/news-section.blade.php
    ─────────────────────────────────────────────────────────────────────
    SEGURIDAD EN VISTA:
    • {{ }} escapa toda salida de texto → previene XSS.
    • e() en atributos de imagen → previene XSS en src/href.
    • wire:click recibe UUID desde el modelo PHP, nunca del DOM/usuario.
    • El ID entero ($article->id) NUNCA se imprime en el HTML.
    • 'content' no existe en $articles → select() lo excluye en el componente.
    • <time datetime=""> expone la fecha en formato ISO para accesibilidad.
    ─────────────────────────────────────────────────────────────────────
--}}

<section id="noticias" class="py-20 bg-white">
    <div class="container mx-auto px-4">

        {{-- ── Header ────────────────────────────────────────────────── --}}
        <div class="flex flex-col md:flex-row md:items-end md:justify-between mb-12">
            <div>
                <div class="inline-block px-4 py-2 bg-blue-100 text-blue-700 rounded-full mb-4 text-sm">
                    Últimas Actualizaciones
                </div>

                <h2 class="text-gray-900 text-4xl lg:text-5xl mb-4">
                    Noticias
                </h2>

                <p class="text-gray-600 text-lg max-w-2xl">
                    Mantente informado sobre nuestros avances, eventos y el impacto
                    positivo que estamos generando en la conservación de la laguna.
                </p>
            </div>

            <x-buttons.view-all :href="route('news')" class="mt-4 md:mt-0" wire:navigate>
                Ver todas las noticias
            </x-buttons.view-all>
        </div>

        {{-- ── Grid ──────────────────────────────────────────────────── --}}
        @if ($articles->isNotEmpty())

            <div class="grid md:grid-cols-3 gap-8">
                @foreach ($articles as $article)

                    @php
                        /** @var \App\Models\Media|null $cover */
                        $cover = $article->media->first(); // order=0 por la query
                    @endphp

                    <article
                        class="group bg-white rounded-2xl overflow-hidden shadow-lg
                               hover:shadow-2xl transition-all hover:-translate-y-1"
                    >
                        {{-- ── Imagen de portada ───────────────────── --}}
                        <div class="relative h-64 overflow-hidden bg-gray-100">

                            @if ($cover)
                                <img
                                    src="{{ e($cover->url()) }}"
                                    alt="{{ $cover->alt ?? $article->title }}"
                                    class="w-full h-full object-cover
                                           group-hover:scale-110 transition-transform duration-500"
                                    loading="lazy"
                                    width="640"
                                    height="256"
                                />
                            @else
                                {{-- Placeholder sin imagen registrada --}}
                                <div class="w-full h-full flex items-center justify-center
                                            bg-gradient-to-br from-blue-50 to-green-50">
                                    <span class="text-5xl opacity-30" aria-hidden="true">🌿</span>
                                </div>
                            @endif

                            {{-- Etiqueta de categoría --}}
                            @if ($article->category)
                                <div class="absolute top-4 left-4">
                                    <span class="px-3 py-1 bg-white/90 backdrop-blur-sm
                                                 text-gray-900 rounded-full text-sm">
                                        {{ $article->category->name }}
                                    </span>
                                </div>
                            @endif

                        </div>

                        {{-- ── Contenido ───────────────────────────── --}}
                        <div class="p-6">

                            {{-- Meta: fecha y autor --}}
                            <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">

                                @if ($article->published_at)
                                    <div class="flex items-center gap-1">
                                        <span aria-hidden="true">📅</span>
                                        {{-- datetime en ISO para lectores de pantalla --}}
                                        <time datetime="{{ $article->published_at->toDateString() }}">
                                            {{ $article->published_at->translatedFormat('d M Y') }}
                                        </time>
                                    </div>
                                @endif

                                @if ($article->author_name)
                                    <span aria-hidden="true">•</span>
                                    <span>{{ $article->author_name }}</span>
                                @endif

                            </div>

                            {{-- Título — escapado por {{ }} --}}
                            <h3 class="text-gray-900 text-xl mb-3 line-clamp-2
                                       group-hover:text-blue-600 transition-colors">
                                {{ $article->title }}
                            </h3>

                            {{-- Resumen corto — NUNCA se imprime 'content' aquí --}}
                            @if ($article->summary)
                                <p class="text-gray-600 mb-4 line-clamp-3 leading-relaxed">
                                    {{ $article->summary }}
                                </p>
                            @endif

                            {{--
                                wire:click recibe el UUID del modelo PHP.
                                El UUID nunca viene del DOM ni de input del usuario.
                                El ID entero jamás aparece en el HTML.
                            --}}
                            <button
                                wire:click="openNews('{{ $article->uuid }}')"
                                class="text-blue-600 hover:text-blue-700 flex items-center gap-2 group/btn"
                                aria-label="Leer noticia completa: {{ $article->title }}"
                            >
                                <span>Leer más</span>
                                <span class="group-hover/btn:translate-x-1 transition-transform"
                                      aria-hidden="true">→</span>
                            </button>

                        </div>
                    </article>

                @endforeach
            </div>

        @else

            {{-- Estado vacío --}}
            <div class="text-center py-16 text-gray-400">
                <span class="text-5xl block mb-4" aria-hidden="true">📰</span>
                <p class="text-lg">No hay noticias publicadas aún.</p>
            </div>

        @endif

    </div>
</section>
