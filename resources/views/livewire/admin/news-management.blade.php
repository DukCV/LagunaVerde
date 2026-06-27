{{--
    Vista: Gestión de Noticias del Panel de Administración
    Componente: App\Livewire\Admin\NewsManagement

    SEGURIDAD:
     - Toda variable de usuario se renderiza con {{ }} → escape XSS automático de Blade.
     - Las URLs de portada provienen del DTO sanitizado, no directamente del modelo.
     - Los botones de acción usan wire:click con métodos explícitos (sin eval).

    ACCESIBILIDAD:
     - Roles ARIA en formularios de filtro (search, combobox).
     - aria-label en todos los botones de acción.
     - <time> con datetime ISO para fechas.
     - Estado vacío descriptivo con instrucción para el usuario.
--}}
{{--
    Escucha el evento 'abrir-noticia-publica' para abrir el artículo en nueva pestaña.
    Se usa un evento del navegador porque window.open() no puede llamarse desde PHP.
--}}
<div
    class="space-y-6"
    x-on:abrir-noticia-publica.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')"
>
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VISTA FORMULARIO: NewsForm (crea o edita según noticiaIdEdicion)   --}}
    {{-- :key fuerza un componente nuevo al cambiar entre crear y editar.   --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($vista === 'formulario')
        <livewire:admin.news-form
            :newsId="$noticiaIdEdicion"
            :key="'news-form-' . ($noticiaIdEdicion ?? 'new')"
        />
    @else

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO DE SECCIÓN                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 mb-1">Gestión de Noticias</h1>
        <p class="text-gray-500 text-sm">
            Administra y publica contenido informativo sobre conservación y eventos
        </p>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- BARRA DE CONTROLES                                                  --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">

        {{-- Fila superior: métrica de publicadas + botón CTA ──────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">

            {{-- Métrica: total de noticias publicadas --}}
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <x-admin-icon name="newspaper" class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <div class="text-2xl font-semibold text-gray-900 leading-none">
                        {{-- Número escapado automáticamente por {{ }} --}}
                        {{ number_format($totalPublicadas) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">Noticias publicadas</div>
                </div>
            </div>

            {{-- Botón CTA: acción placeholder hasta implementación --}}
            <button
                wire:click="crearNoticia"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                aria-label="Crear nueva noticia"
            >
                <x-admin-icon name="plus" class="w-4 h-4" />
                <span>Crear nueva noticia</span>
            </button>
        </div>

        {{-- Fila de filtros: búsqueda + estado + categoría + orden ─────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

            {{-- Búsqueda por texto: debounce 400ms para reducir requests --}}
            <div class="lg:col-span-2">
                <label for="busqueda-noticias" class="sr-only">Buscar noticias</label>
                <div class="relative">
                    <x-admin-icon
                        name="magnifying-glass"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    />
                    <input
                        id="busqueda-noticias"
                        type="search"
                        wire:model.live.debounce.400ms="busqueda"
                        placeholder="Buscar por título, autor o contenido..."
                        maxlength="100"
                        autocomplete="off"
                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        aria-label="Buscar noticias"
                        role="searchbox"
                    >
                </div>
            </div>

            {{-- Filtro por estado --}}
            <div>
                <label for="filtro-estado" class="sr-only">Filtrar por estado</label>
                <div class="relative">
                    <select
                        id="filtro-estado"
                        wire:model.live="estado"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Filtrar por estado"
                    >
                        @foreach($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    {{-- Ícono de flecha para el select personalizado --}}
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>

            {{-- Filtro por categoría --}}
            <div>
                <label for="filtro-categoria" class="sr-only">Filtrar por categoría</label>
                <div class="relative">
                    <select
                        id="filtro-categoria"
                        wire:model.live="categoria"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Filtrar por categoría"
                    >
                        @foreach($categorias as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>

            {{-- Ordenamiento --}}
            <div>
                <label for="orden-noticias" class="sr-only">Ordenar por</label>
                <div class="relative">
                    <select
                        id="orden-noticias"
                        wire:model.live="orden"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Ordenar noticias"
                    >
                        <option value="recientes">Más recientes</option>
                        <option value="mas-vistas">Más vistas</option>
                        <option value="menos-vistas">Menos vistas</option>
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>
        </div>

        {{-- Indicador de filtros activos ──────────────────────────────── --}}
        @if($busqueda !== '' || $estado !== 'todas' || $categoria !== 'todas')
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <x-admin-icon name="funnel" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <span>
                    Mostrando <strong class="text-gray-900">{{ $noticias->total() }}</strong>
                    {{ $noticias->total() === 1 ? 'noticia' : 'noticias' }} con filtros activos
                </span>
                <button
                    wire:click="limpiarFiltros"
                    class="ml-1 text-blue-600 hover:text-blue-700 underline underline-offset-2 focus:outline-none focus:ring-1 focus:ring-blue-400 rounded"
                    aria-label="Limpiar todos los filtros"
                >
                    Limpiar filtros
                </button>
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- GRID DE TARJETAS DE NOTICIAS                                        --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($noticias->isNotEmpty())

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">

            @foreach($noticias as $noticia)
                {{--
                    wire:key provee identidad estable a cada elemento del loop.
                    Sin él, morphdom rastrea por posición DOM y puede ignorar
                    cambios de contenido cuando la lista se re-renderiza.
                --}}
                @php
                    // Marcadores de posición para borradores incompletos: un borrador
                    // puede guardarse sin título, portada o autor (validación flexible
                    // — ver NewsForm::reglasArchivos()), así que la tarjeta debe seguir
                    // siendo legible y mostrar un texto descriptivo en lugar de vacío.
                    $tituloMostrar = $noticia->title !== '' ? $noticia->title : 'Sin título';
                    $autorMostrar  = $noticia->authorName !== '' ? $noticia->authorName : 'Sin autor';
                @endphp
                <article
                    wire:key="noticia-{{ $noticia->id }}"
                    class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col"
                    aria-label="Noticia: {{ $tituloMostrar }}"
                >

                    {{-- Imagen de portada ──────────────────────────────── --}}
                    <div class="relative h-44 bg-gray-100 flex-shrink-0">

                        @if($noticia->coverUrl)
                            <img
                                src="{{ $noticia->coverUrl }}"
                                alt="{{ $tituloMostrar }}"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >
                            {{-- Fallback si la URL de imagen falla al cargar --}}
                            <div class="absolute inset-0 bg-gray-100 flex-col items-center justify-center gap-1 hidden">
                                <x-admin-icon name="newspaper" class="w-10 h-10 text-gray-300" />
                                <span class="text-xs text-gray-400">Sin imagen</span>
                            </div>
                        @else
                            {{-- Sin imagen de portada registrada (habitual en borradores) --}}
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1">
                                <x-admin-icon name="newspaper" class="w-10 h-10 text-gray-300" />
                                <span class="text-xs text-gray-400">Sin imagen</span>
                            </div>
                        @endif

                        {{-- Badge de estado (esquina superior izquierda) --}}
                        <div class="absolute top-2.5 left-2.5">
                            @include('livewire.admin.partials.news-status-badge', ['status' => $noticia->status, 'label' => $noticia->statusLabel])
                        </div>

                        {{-- Badge de categoría (esquina superior derecha) --}}
                        <div class="absolute top-2.5 right-2.5">
                            <span class="px-2 py-1 bg-white/90 backdrop-blur-sm rounded-full text-xs text-gray-700 font-medium shadow-sm">
                                {{ $noticia->categoryName }}
                            </span>
                        </div>
                    </div>

                    {{-- Contenido de la tarjeta ─────────────────────────── --}}
                    <div class="p-4 flex flex-col flex-1 space-y-3">

                        {{-- Título (máximo 2 líneas) — placeholder si el borrador aún no lo tiene --}}
                        <h3 class="text-sm font-semibold line-clamp-2 leading-snug {{ $noticia->title !== '' ? 'text-gray-900' : 'text-gray-400 italic' }}">
                            {{ $tituloMostrar }}
                        </h3>

                        {{-- Resumen breve (máximo 3 líneas) — vacío si no fue redactado --}}
                        @if($noticia->summary !== '')
                            <p class="text-xs text-gray-500 line-clamp-3 leading-relaxed">
                                {{ $noticia->summary }}
                            </p>
                        @endif

                        {{-- Metadatos: autor y fechas --}}
                        <div class="space-y-1.5 text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <x-admin-icon name="user" class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                                <span class="truncate {{ $noticia->authorName !== '' ? '' : 'italic text-gray-400' }}">{{ $autorMostrar }}</span>
                            </div>
                            <div class="flex items-start gap-1.5">
                                <x-admin-icon name="calendar-days" class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-px" />
                                <div class="space-y-0.5">
                                    <div>
                                        Creado:
                                        <time datetime="{{ $noticia->createdAt }}">{{ $noticia->createdAt }}</time>
                                    </div>
                                    <div class="text-gray-400">
                                        Actualizado:
                                        <time datetime="{{ $noticia->updatedAt }}">{{ $noticia->updatedAt }}</time>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Métricas de engagement --}}
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                            <div class="flex items-center gap-1">
                                <x-admin-icon name="eye" class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ number_format($noticia->viewsCount) }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-admin-icon name="chat-bubble-oval-left" class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ number_format($noticia->commentsCount) }}</span>
                            </div>
                        </div>

                        {{-- Contador de adjuntos por tipo --}}
                        <div class="flex items-center gap-3 pt-2.5 border-t border-gray-100 text-xs text-gray-500">
                            <div class="flex items-center gap-1" title="{{ $noticia->imagesCount }} imágenes">
                                <x-admin-icon name="photo" class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ $noticia->imagesCount }}</span>
                            </div>
                            <div class="flex items-center gap-1" title="{{ $noticia->videosCount }} videos">
                                <x-admin-icon name="video-camera" class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ $noticia->videosCount }}</span>
                            </div>
                            <div class="flex items-center gap-1" title="{{ $noticia->filesCount }} archivos">
                                <x-admin-icon name="document" class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ $noticia->filesCount }}</span>
                            </div>
                        </div>

                        {{-- Botones de acción --}}
                        <div class="grid grid-cols-3 gap-1.5 pt-1">

                            {{-- Editar (siempre disponible) --}}
                            <button
                                wire:click="editarNoticia({{ $noticia->id }})"
                                class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 active:bg-blue-200 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1"
                                aria-label="Editar: {{ $noticia->title }}"
                            >
                                <x-admin-icon name="pencil-square" class="w-3.5 h-3.5 flex-shrink-0" />
                                <span class="hidden sm:inline">Editar</span>
                            </button>

                            {{--
                                Ver en sitio público.
                                REGLA DE NEGOCIO: deshabilitado para borradores y noticias
                                programadas — en ambos casos la noticia no tiene URL pública
                                activa mientras no haya sido publicada (PublishedScope filtra
                                por status='published').
                            --}}
                            @if (in_array($noticia->status, ['draft', 'scheduled'], true))
                                <button
                                    disabled
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium border border-gray-200 text-gray-300 rounded-lg cursor-not-allowed"
                                    aria-label="No disponible: la noticia {{ $noticia->status === 'scheduled' ? 'está programada y aún no tiene página pública' : 'es un borrador y no tiene página pública' }}"
                                    aria-disabled="true"
                                    title="Solo disponible para noticias publicadas"
                                >
                                    <x-admin-icon name="arrow-top-right-on-square" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">Ver</span>
                                </button>
                            @else
                                <button
                                    wire:click="verNoticia('{{ $noticia->uuid }}')"
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-1"
                                    aria-label="Ver en sitio: {{ $noticia->title }}"
                                >
                                    <x-admin-icon name="arrow-top-right-on-square" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">Ver</span>
                                </button>
                            @endif

                            {{--
                                Toggle de estado: Deshabilitar / Republicar.

                                Estados terminales o sin acción:
                                  - archived → botón inactivo (estado terminal, sin toggle).
                                  - draft    → botón inactivo (un borrador no puede deshabilitarse;
                                               no es público y no requiere ocultarse).

                                Estados con acción:
                                  - disabled  → "Republicar" en ámbar (acción positiva).
                                  - published → "Deshabilitar" en rojo (acción destructiva).

                                SEGURIDAD: wire:click pasa SOLO el entero $noticia->id.
                                El estado real se lee desde la BD en el servidor (nunca del cliente).
                            --}}
                            @if ($noticia->status === 'archived' || $noticia->status === 'draft')
                                {{-- Estado terminal o borrador: sin toggle disponible --}}
                                <button
                                    disabled
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed"
                                    aria-label="{{ $noticia->status === 'draft' ? 'No disponible: la noticia es un borrador' : 'Noticia descontinuada, sin acciones disponibles' }}"
                                    aria-disabled="true"
                                    title="{{ $noticia->status === 'draft' ? 'Publica la noticia para habilitar esta acción' : 'Estado terminal' }}"
                                >
                                    <x-admin-icon name="{{ $noticia->status === 'draft' ? 'pencil' : 'x-circle' }}" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">
                                        {{ $noticia->status === 'draft' ? 'Borrador' : 'Inactivo' }}
                                    </span>
                                </button>

                            @elseif ($noticia->status === 'disabled')
                                {{-- Republicar: la noticia estaba deshabilitada y puede volver a publicarse --}}
                                <button
                                    wire:click="confirmarToggleEstado({{ $noticia->id }})"
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium border border-amber-300 text-amber-600 rounded-lg hover:bg-amber-50 active:bg-amber-100 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-1"
                                    aria-label="Republicar: {{ $noticia->title }}"
                                >
                                    <x-admin-icon name="arrow-path" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">Republicar</span>
                                </button>

                            @else
                                {{-- Deshabilitar: noticia publicada que puede deshabilitarse temporalmente --}}
                                <button
                                    wire:click="confirmarToggleEstado({{ $noticia->id }})"
                                    class="flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium border border-red-300 text-red-600 rounded-lg hover:bg-red-50 active:bg-red-100 transition-colors focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-1"
                                    aria-label="Deshabilitar: {{ $noticia->title }}"
                                >
                                    <x-admin-icon name="eye-slash" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="hidden sm:inline">Deshabilitar</span>
                                </button>
                            @endif

                        </div>

                        {{--
                            Eliminar permanentemente — SIEMPRE activo, sin importar
                            el estado actual (borrador, publicada o deshabilitada).
                            Se separa visualmente del resto por ser una acción
                            destructiva e irreversible (cascada + borrado físico).
                        --}}
                        <button
                            wire:click="confirmarEliminacion({{ $noticia->id }})"
                            class="flex items-center justify-center gap-1.5 w-full px-2 py-2 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 active:bg-red-200 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                            aria-label="Eliminar permanentemente: {{ $noticia->title }}"
                        >
                            <x-admin-icon name="trash" class="w-3.5 h-3.5 flex-shrink-0" />
                            <span>Eliminar permanentemente</span>
                        </button>

                    </div>
                </article>
            @endforeach

        </div>

        {{-- ── Paginación ────────────────────────────────────────────── --}}
        @if($noticias->hasPages())
            <div class="flex justify-center pt-2">
                {{ $noticias->links() }}
            </div>
        @endif

    @else

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- ESTADO VACÍO                                                     --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl p-12 shadow-sm border border-gray-200 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <x-admin-icon name="magnifying-glass" class="w-8 h-8 text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-2">No se encontraron noticias</h3>
            <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                @if($busqueda !== '' || $estado !== 'todas' || $categoria !== 'todas')
                    Intenta ajustar los filtros o realiza una búsqueda diferente.
                @else
                    Aún no hay noticias registradas. Crea la primera para comenzar.
                @endif
            </p>
            @if($busqueda !== '' || $estado !== 'todas' || $categoria !== 'todas')
                <button
                    wire:click="limpiarFiltros"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-admin-icon name="arrow-path" class="w-4 h-4" />
                    Limpiar filtros
                </button>
            @else
                <button
                    wire:click="crearNoticia"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-admin-icon name="plus" class="w-4 h-4" />
                    Crear primera noticia
                </button>
            @endif
        </div>

    @endif

    {{--
        Toast de notificación: escucha el evento 'notificacion' despachado desde el
        componente Livewire con $this->dispatch('notificacion', tipo:..., mensaje:...).
        Alpine.js lo muestra 3.5 s y luego lo oculta automáticamente.
        role="alert" + aria-live="polite" → lectores de pantalla lo anuncian.
    --}}
    <div
        x-data="{ mostrar: false, tipo: '', mensaje: '' }"
        x-on:notificacion.window="
            tipo    = $event.detail.tipo;
            mensaje = $event.detail.mensaje;
            mostrar = true;
            setTimeout(() => mostrar = false, 3500);
        "
        x-show="mostrar"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        :class="tipo === 'exito' ? 'bg-green-600' : 'bg-red-600'"
        class="fixed bottom-20 right-4 z-50 px-5 py-3 rounded-lg shadow-lg text-sm font-medium text-white min-w-max"
        style="display: none;"
        role="alert"
        aria-live="polite"
    >
        <span x-text="mensaje"></span>
    </div>

    {{-- Indicador de carga superpuesto durante peticiones Livewire --}}
    <div wire:loading.delay class="fixed bottom-4 right-4 bg-white border border-gray-200 rounded-lg shadow-lg px-4 py-2 flex items-center gap-2 text-sm text-gray-600 z-40">
        <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span>Cargando...</span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN DE TOGGLE DE ESTADO                          --}}
    {{--                                                                    --}}
    {{-- ACCESIBILIDAD:                                                     --}}
    {{--  - role="dialog" + aria-modal="true" → lectores de pantalla.       --}}
    {{--  - aria-labelledby / aria-describedby → contexto semántico.        --}}
    {{--  - wire:keydown.escape.window → cierre con teclado (Escape).       --}}
    {{--  - wire:click.self en el fondo → cierre al hacer clic fuera.       --}}
    {{--  - autofocus en el botón Cancelar → acción segura por defecto.     --}}
    {{-- SEGURIDAD:                                                         --}}
    {{--  - Toda variable usa {{ }} → escape XSS automático de Blade.       --}}
    {{--  - wire:loading.attr="disabled" → previene doble envío.            --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarModalToggle)
        {{--
            Fondo oscuro semitransparente — cierra el modal al hacer clic fuera.
            wire:key="modal-toggle": identidad DOM estable para morphdom.
            Sin wire:key, un re-render puede reutilizar el nodo equivocado y
            dejar el modal en un estado visualmente inconsistente entre renders.
        --}}
        <div
            wire:key="modal-toggle"
            wire:click.self="cancelarModal"
            wire:keydown.escape.window="cancelarModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="true"
        >
            {{-- Tarjeta del modal — tamaño máximo contenido, responsive --}}
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-titulo-toggle"
                aria-describedby="modal-desc-toggle"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                {{-- Franja de color superior: rojo (deshabilitar) o ámbar (republicar) --}}
                <div class="h-1.5 w-full {{ $noticiaEstaDeshabilitada ? 'bg-amber-500' : 'bg-red-500' }}"></div>

                <div class="p-6 sm:p-7">

                    {{-- Ícono central contextual --}}
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center {{ $noticiaEstaDeshabilitada ? 'bg-amber-100' : 'bg-red-100' }}">
                            @if($noticiaEstaDeshabilitada)
                                <x-admin-icon name="arrow-path" class="w-7 h-7 text-amber-600" />
                            @else
                                <x-admin-icon name="eye-slash" class="w-7 h-7 text-red-600" />
                            @endif
                        </div>
                    </div>

                    {{-- Título del modal --}}
                    <h2
                        id="modal-titulo-toggle"
                        class="text-lg font-semibold text-gray-900 text-center mb-2"
                    >
                        @if($noticiaEstaDeshabilitada)
                            ¿Republicar esta noticia?
                        @else
                            ¿Deshabilitar esta noticia?
                        @endif
                    </h2>

                    {{-- Descripción de consecuencias --}}
                    <p
                        id="modal-desc-toggle"
                        class="text-sm text-gray-500 text-center leading-relaxed mb-7"
                    >
                        @if($noticiaEstaDeshabilitada)
                            La noticia volverá a ser
                            <strong class="text-gray-700">visible para el público</strong>
                            y aparecerá nuevamente en el sitio web.
                        @else
                            La noticia
                            <strong class="text-gray-700">dejará de ser visible para el público</strong>
                            y no aparecerá en el sitio web hasta que sea republicada.
                        @endif
                    </p>

                    {{-- Botones: apilados en móvil, lado a lado desde sm --}}
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">

                        {{-- Cancelar — acción segura por defecto (autofocus) --}}
                        <button
                            wire:click="cancelarModal"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Cancelar
                        </button>

                        {{-- Confirmar acción — color dinámico según acción --}}
                        <button
                            wire:click="ejecutarToggleEstado"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="ejecutarToggleEstado"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2
                                {{ $noticiaEstaDeshabilitada
                                    ? 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-400'
                                    : 'bg-red-600 hover:bg-red-700 active:bg-red-800 focus:ring-red-500' }}"
                            aria-label="{{ $noticiaEstaDeshabilitada ? 'Confirmar republicación de la noticia' : 'Confirmar deshabilitación de la noticia' }}"
                        >
                            {{-- Texto normal --}}
                            <span wire:loading.remove wire:target="ejecutarToggleEstado">
                                {{ $noticiaEstaDeshabilitada ? 'Republicar' : 'Deshabilitar' }}
                            </span>

                            {{-- Estado de carga: spinner + texto mientras procesa --}}
                            <span wire:loading wire:target="ejecutarToggleEstado" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Procesando...</span>
                            </span>
                        </button>

                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN DE ELIMINACIÓN PERMANENTE                    --}}
    {{--                                                                    --}}
    {{-- ACCESIBILIDAD: mismo patrón que el modal de toggle (dialog, aria,  --}}
    {{--  escape, click-outside, autofocus en Cancelar como acción segura). --}}
    {{-- SEGURIDAD:                                                         --}}
    {{--  - El título se interpola exclusivamente con {{ }} → Blade aplica  --}}
    {{--    htmlspecialchars() automáticamente (escape XSS garantizado).    --}}
    {{--    El valor proviene de noticiaTituloParaEliminar, leído desde la  --}}
    {{--    BD por el servidor — nunca de un payload del cliente.           --}}
    {{--  - wire:loading.attr="disabled" → previene doble envío/doble borrado. --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarModalEliminar)
        <div
            wire:key="modal-eliminar"
            wire:click.self="cancelarModalEliminar"
            wire:keydown.escape.window="cancelarModalEliminar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="true"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-titulo-eliminar"
                aria-describedby="modal-desc-eliminar"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                {{-- Franja de color superior: rojo intenso → acción irreversible --}}
                <div class="h-1.5 w-full bg-red-600"></div>

                <div class="p-6 sm:p-7">

                    {{-- Ícono central de advertencia --}}
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center bg-red-100">
                            <x-admin-icon name="trash" class="w-7 h-7 text-red-600" />
                        </div>
                    </div>

                    {{-- Título del modal con el texto dinámico exacto solicitado --}}
                    <h2
                        id="modal-titulo-eliminar"
                        class="text-lg font-semibold text-gray-900 text-center mb-2 wrap-break-word"
                    >
                        ¿Estás seguro de eliminar la noticia {{ $noticiaTituloParaEliminar }}?
                    </h2>

                    {{-- Descripción de consecuencias — enfatiza la irreversibilidad --}}
                    <p
                        id="modal-desc-eliminar"
                        class="text-sm text-gray-500 text-center leading-relaxed mb-7"
                    >
                        Esta acción es
                        <strong class="text-red-600">permanente e irreversible</strong>.
                        Se eliminarán también sus
                        <strong class="text-gray-700">comentarios, imágenes, videos y documentos</strong>,
                        incluyendo los archivos almacenados en el servidor.
                    </p>

                    {{-- Botones: apilados en móvil, lado a lado desde sm --}}
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">

                        {{-- Cancelar — acción segura por defecto (autofocus) --}}
                        <button
                            wire:click="cancelarModalEliminar"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Cancelar
                        </button>

                        {{-- Confirmar eliminación — rojo intenso, refuerza la gravedad --}}
                        <button
                            wire:click="ejecutarEliminacion"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="ejecutarEliminacion"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 active:bg-red-800 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            aria-label="Confirmar eliminación permanente de la noticia"
                        >
                            {{-- Texto normal --}}
                            <span wire:loading.remove wire:target="ejecutarEliminacion">
                                Aceptar
                            </span>

                            {{-- Estado de carga: spinner + texto mientras procesa --}}
                            <span wire:loading wire:target="ejecutarEliminacion" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Eliminando...</span>
                            </span>
                        </button>

                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Fin bloque @else (vista lista) --}}
    @endif

</div>
