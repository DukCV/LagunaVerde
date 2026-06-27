<div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-200">

    {{-- Cabecera: flex-wrap evita desbordamiento horizontal cuando el select no cabe
         en una sola línea — en móvil pasa a dos líneas sin romper el contenedor. --}}
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 mb-4 sm:mb-6">
        <h2 class="text-lg font-semibold text-gray-900 min-w-0">Noticias Recientes</h2>

        {{-- shrink-0 impide que el select se comprima hasta un ancho ilegible;
             text-xs en móvil reduce su ancho mínimo nativo. --}}
        <select
            wire:model.live="filtro"
            class="shrink-0 text-xs sm:text-sm border border-gray-300 rounded-lg
                   px-2.5 sm:px-3 py-1.5 text-gray-700 bg-white
                   focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label="Filtro de noticias"
        >
            <option value="recientes">Más recientes</option>
            <option value="mas-vistas">Más vistas</option>
            <option value="menos-vistas">Menos vistas</option>
        </select>
    </div>

    {{-- Lista de noticias --}}
    <div class="space-y-3">
        @forelse($noticias as $noticia)

            {{-- Ítem: fila flex con portada + contenido textual --}}
            <div class="widget-item-card flex gap-3 p-3 border border-gray-200 rounded-lg hover:border-blue-200 hover:bg-blue-50/30 transition-colors">

                {{-- Portada de la noticia — primera imagen de la relación media --}}
                @php
                    /** @var \App\Models\Media|null $portada */
                    $portada = $noticia->media->first();
                @endphp

                <div class="widget-item-thumbnail {{ $portada ? '' : 'widget-item-thumbnail--fallback' }}">
                    @if($portada)
                        {{-- URL escapada automáticamente por {{ }} — previene XSS --}}
                        <img
                            src="{{ $portada->url() }}"
                            alt="{{ $noticia->title }}"
                            loading="lazy"
                            decoding="async"
                        >
                    @else
                        {{-- Ícono de periódico como fallback cuando no hay portada --}}
                        <x-admin-icon name="newspaper" class="w-6 h-6 text-blue-300" />
                    @endif
                </div>

                {{-- Contenido textual: título + métricas + acciones --}}
                {{-- min-w-0 es indispensable: sin él un hijo flex no puede achicarse
                     más allá de su contenido, rompiendo el truncado de texto. --}}
                <div class="flex-1 min-w-0">

                    {{-- Título con truncado a 2 líneas --}}
                    <h3 class="text-sm font-medium text-gray-900 mb-1.5 line-clamp-2 leading-snug">
                        {{ $noticia->title }}
                    </h3>

                    {{-- Métricas: vistas y comentarios --}}
                    <div class="flex items-center gap-3 text-xs text-gray-500 mb-2">
                        <div class="flex items-center gap-1">
                            <x-admin-icon name="eye" class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ number_format($noticia->views_count) }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <x-admin-icon name="chat-bubble-oval-left" class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ number_format($noticia->comments_count) }}</span>
                        </div>
                        <span class="text-gray-400 truncate">
                            {{ $noticia->published_at->diffForHumans() }}
                        </span>
                    </div>

                    {{-- Acciones --}}
                    <div class="flex gap-1.5">
                        <a
                            href="{{ route('news.show', $noticia->uuid) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex-1 px-2.5 py-1 text-xs text-center bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition-colors font-medium"
                        >
                            Ver
                        </a>
                        <button
                            class="flex-1 px-2.5 py-1 text-xs border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors flex items-center justify-center gap-1"
                            title="Editar noticia"
                            type="button"
                        >
                            <x-admin-icon name="pencil-square" class="w-3 h-3 shrink-0" />
                            Editar
                        </button>
                    </div>

                </div>
            </div>

        @empty
            {{-- Estado vacío --}}
            <div class="text-center py-8">
                <x-admin-icon name="newspaper" class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                <p class="text-sm text-gray-500">No hay noticias publicadas aún.</p>
            </div>
        @endforelse
    </div>

</div>
