{{--
    Vista: Gestión de Socios Colaboradores del Panel de Administración
    Componente: App\Livewire\Admin\Partners\PartnersManagement

    SEGURIDAD:
     - Toda variable de usuario se renderiza con {{ }} → escape XSS automático de Blade.
     - Los enlaces (sitio web y redes) provienen del DTO: validados como http(s)
       al guardar (PartnerForm) y revalidados al leer (AdminPartnerItemDto::sanitizeUrl())
       — cualquier valor no http(s) llega como null y nunca se renderiza en un href.
     - Los botones de acción usan wire:click con métodos explícitos (sin eval).
     - Los logos de redes sociales son SVG inline (<x-social-icon>) — sin
       dependencias de red ni de iconos de marca externos.

    ACCESIBILIDAD:
     - Roles ARIA en formularios de filtro (search, combobox).
     - aria-label en todos los botones de acción.
     - Estado vacío descriptivo con instrucción para el usuario.
--}}
<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VISTA FORMULARIO: PartnerForm (crea o edita según socioIdEdicion)   --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if ($vista === 'formulario')
        <livewire:admin.partners.partner-form
            :socioId="$socioIdEdicion"
            :key="'partner-form-' . ($socioIdEdicion ?? 'new')"
        />
    @else

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO DE SECCIÓN                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 mb-1">Gestión de Socios Colaboradores</h1>
            <p class="text-gray-500 text-sm">
                Administra las organizaciones que aparecen como colaboradoras en el sitio público
            </p>
        </div>
        <a
            href="{{ url('/') }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 underline underline-offset-2 flex-shrink-0"
        >
            <x-admin-icon name="arrow-top-right-on-square" class="w-4 h-4" />
            Ver sección pública
        </a>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- BARRA DE CONTROLES                                                  --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">

        {{-- Fila superior: métricas + botón CTA ────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">

            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <x-admin-icon name="user-group" class="w-5 h-5 text-emerald-600" />
                </div>
                <div>
                    <div class="text-2xl font-semibold text-gray-900 leading-none">
                        {{ number_format($totalActivos) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">Socios activos en el sitio</div>
                </div>
            </div>

            <button
                wire:click="crearSocio"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                aria-label="Registrar nuevo socio colaborador"
            >
                <x-admin-icon name="plus" class="w-4 h-4" />
                <span>Nuevo socio</span>
            </button>
        </div>

        {{-- Fila de filtros: búsqueda + tipo + estado + orden ───────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

            <div class="lg:col-span-2">
                <label for="busqueda-socios" class="sr-only">Buscar socios</label>
                <div class="relative">
                    <x-admin-icon
                        name="magnifying-glass"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    />
                    <input
                        id="busqueda-socios"
                        type="search"
                        wire:model.live.debounce.400ms="busqueda"
                        placeholder="Buscar por nombre o descripción..."
                        maxlength="100"
                        autocomplete="off"
                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        aria-label="Buscar socios"
                        role="searchbox"
                    >
                </div>
            </div>

            <div>
                <label for="filtro-tipo" class="sr-only">Filtrar por tipo</label>
                <div class="relative">
                    <select
                        id="filtro-tipo"
                        wire:model.live="tipo"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Filtrar por tipo"
                    >
                        @foreach($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>

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
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>

            <div>
                <label for="orden-socios" class="sr-only">Ordenar por</label>
                <div class="relative">
                    <select
                        id="orden-socios"
                        wire:model.live="orden"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Ordenar socios"
                    >
                        <option value="recientes">Más recientes</option>
                        <option value="antiguos">Más antiguos</option>
                        <option value="nombre">Nombre (A-Z)</option>
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>
        </div>

        {{-- Indicador de filtros activos ──────────────────────────────── --}}
        @if($busqueda !== '' || $tipo !== 'todos' || $estado !== 'todos')
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <x-admin-icon name="funnel" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <span>
                    Mostrando <strong class="text-gray-900">{{ $socios->total() }}</strong>
                    {{ $socios->total() === 1 ? 'socio' : 'socios' }} con filtros activos
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
    {{-- GRID DE TARJETAS DE SOCIOS                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($socios->isNotEmpty())

        @php
            // Colores e ícono de respaldo por tipo de organización — mismo
            // criterio visual que el diseño original (.tsx), expresado como
            // utilidades de Tailwind. 'icon' se usa SOLO cuando el socio no
            // tiene logotipo registrado (ver bloque de logo más abajo).
            $coloresTipo = [
                'Corporativo'   => ['bg' => 'bg-blue-50',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500',    'icon' => 'building-office'],
                'Educativo'     => ['bg' => 'bg-purple-50',  'text' => 'text-purple-700',  'dot' => 'bg-purple-500',  'icon' => 'book-open'],
                'ONG'           => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'icon' => 'heart'],
                'Gubernamental' => ['bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500',   'icon' => 'scale'],
                'Tecnológico'   => ['bg' => 'bg-cyan-50',    'text' => 'text-cyan-700',    'dot' => 'bg-cyan-500',    'icon' => 'cpu-chip'],
                'Fundación'     => ['bg' => 'bg-rose-50',    'text' => 'text-rose-700',    'dot' => 'bg-rose-500',    'icon' => 'gift'],
                'Comunitario'   => ['bg' => 'bg-orange-50',  'text' => 'text-orange-700',  'dot' => 'bg-orange-500',  'icon' => 'user-group'],
                'Persona'       => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-700',  'dot' => 'bg-indigo-500',  'icon' => 'user'],
            ];

            // Redes sociales: logo de marca (vía <x-social-icon>) + color de
            // fondo/hover por plataforma. El logo se renderiza con SVG inline
            // (sin dependencias de red); el color sigue el mismo criterio que
            // resources/views/livewire/home/collaborators-section.blade.php.
            $redesSociales = [
                'website'   => ['label' => 'Sitio web',   'clase' => 'bg-gray-100 text-gray-600 hover:bg-gray-200'],
                'instagram' => ['label' => 'Instagram',   'clase' => 'bg-pink-50 text-pink-600 hover:bg-pink-100'],
                'facebook'  => ['label' => 'Facebook',    'clase' => 'bg-blue-50 text-blue-700 hover:bg-blue-100'],
                'twitter'   => ['label' => 'Twitter / X', 'clase' => 'bg-sky-50 text-sky-600 hover:bg-sky-100'],
                'linkedin'  => ['label' => 'LinkedIn',    'clase' => 'bg-blue-50 text-blue-800 hover:bg-blue-100'],
                'youtube'   => ['label' => 'YouTube',     'clase' => 'bg-red-50 text-red-600 hover:bg-red-100'],
            ];
        @endphp

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">

            @foreach($socios as $socio)
                @php
                    $estiloTipo = $coloresTipo[$socio->type] ?? ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'icon' => 'building-office'];
                    $enlaces = array_filter([
                        'website'   => $socio->website,
                        'instagram' => $socio->instagram,
                        'facebook'  => $socio->facebook,
                        'twitter'   => $socio->twitter,
                        'linkedin'  => $socio->linkedin,
                        'youtube'   => $socio->youtube,
                    ]);
                @endphp
                <article
                    wire:key="socio-{{ $socio->id }}"
                    class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col transition-all duration-200 hover:shadow-md {{ $socio->active ? '' : 'opacity-80' }}"
                    aria-label="Socio: {{ $socio->name }}"
                >
                    {{-- Banda superior de color por tipo --}}
                    <div class="h-1.5 w-full {{ $estiloTipo['dot'] }}"></div>

                    <div class="p-5 flex flex-col flex-1 gap-4">

                        {{-- Cabecera: logo + nombre + tipo + estado --}}
                        <div class="flex items-start gap-4">
                            <div class="relative flex-shrink-0">
                                <div class="w-16 h-16 rounded-2xl overflow-hidden border-2 {{ $socio->active ? 'border-emerald-200' : 'border-gray-200' }} shadow-sm bg-gray-50 flex items-center justify-center">
                                    @if($socio->logoUrl)
                                        <img
                                            src="{{ $socio->logoUrl }}"
                                            alt="Logo de {{ $socio->name }}"
                                            loading="lazy"
                                            decoding="async"
                                            class="w-full h-full object-cover"
                                        >
                                    @else
                                        {{-- Sin logotipo: ícono representativo según la categoría del socio --}}
                                        <x-admin-icon name="{{ $estiloTipo['icon'] }}" class="w-7 h-7 text-gray-300" />
                                    @endif
                                </div>
                                <span
                                    class="absolute -bottom-1 -right-1 rounded-full border-2 border-white shadow-sm {{ $socio->active ? 'bg-emerald-500' : 'bg-gray-400' }}"
                                    style="width: 18px; height: 18px;"
                                    title="{{ $socio->active ? 'Activo' : 'Inactivo' }}"
                                ></span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <h3 class="text-gray-900 leading-snug line-clamp-2 mb-1.5">{{ $socio->name }}</h3>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border {{ $estiloTipo['bg'] }} {{ $estiloTipo['text'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $estiloTipo['dot'] }}"></span>
                                        {{ $socio->type }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border {{ $socio->active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500 border-gray-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $socio->active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                        {{ $socio->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Fechas --}}
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-400">
                            <span class="flex items-center gap-1">
                                <x-admin-icon name="calendar-days" class="w-3.5 h-3.5 text-gray-300" />
                                Registro: <time datetime="{{ $socio->createdAt }}">{{ $socio->createdAt }}</time>
                            </span>
                            <span class="flex items-center gap-1">
                                <x-admin-icon name="arrow-path" class="w-3.5 h-3.5 text-gray-300" />
                                Actualizado: <time datetime="{{ $socio->updatedAt }}">{{ $socio->updatedAt }}</time>
                            </span>
                        </div>

                        {{-- Quiénes son --}}
                        <div class="bg-gray-50 rounded-xl p-3.5 space-y-1">
                            <p class="text-xs text-gray-400 flex items-center gap-1.5 mb-1">
                                <x-admin-icon name="building-office" class="w-3.5 h-3.5 text-blue-400" />
                                Quiénes son
                            </p>
                            <p class="text-sm text-gray-700 line-clamp-3 leading-relaxed">{{ $socio->whoTheyAre }}</p>
                        </div>

                        {{-- Cómo nos apoyan --}}
                        <div class="bg-blue-50/60 rounded-xl p-3.5 space-y-1">
                            <p class="text-xs text-blue-500 flex items-center gap-1.5 mb-1">
                                <x-admin-icon name="check-circle" class="w-3.5 h-3.5" />
                                Cómo nos apoyan
                            </p>
                            <p class="text-sm text-gray-700 line-clamp-3 leading-relaxed">{{ $socio->howTheySupport }}</p>
                        </div>

                        {{-- Redes sociales --}}
                        @if(! empty($enlaces))
                            <div>
                                <p class="text-xs text-gray-400 mb-2">Presencia digital</p>
                                <div class="flex items-center flex-wrap gap-1.5">
                                    @foreach($enlaces as $plataforma => $url)
                                        @php $red = $redesSociales[$plataforma]; @endphp
                                        <a
                                            href="{{ $url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            title="{{ $red['label'] }}"
                                            aria-label="{{ $red['label'] }} de {{ $socio->name }}"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center transition-all hover:scale-110 {{ $red['clase'] }}"
                                        >
                                            <x-social-icon name="{{ $plataforma }}" class="w-4 h-4" />
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Botones de acción --}}
                        <div class="flex flex-wrap gap-2 mt-auto pt-1 border-t border-gray-100">
                            <button
                                wire:click="editarSocio({{ $socio->id }})"
                                class="flex-1 min-w-[60px] flex items-center justify-center gap-1.5 px-3 py-2 text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-xl transition-colors border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1"
                                aria-label="Editar: {{ $socio->name }}"
                            >
                                <x-admin-icon name="pencil-square" class="w-3.5 h-3.5" />
                                Editar
                            </button>

                            <button
                                wire:click="confirmarToggleEstado({{ $socio->id }})"
                                class="flex-1 min-w-[80px] flex items-center justify-center gap-1.5 px-3 py-2 text-xs rounded-xl transition-colors border {{ $socio->active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 border-amber-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border-emerald-200' }}"
                                aria-label="{{ $socio->active ? 'Desactivar' : 'Activar' }}: {{ $socio->name }}"
                            >
                                <x-admin-icon name="{{ $socio->active ? 'eye-slash' : 'arrow-path' }}" class="w-3.5 h-3.5" />
                                {{ $socio->active ? 'Desactivar' : 'Activar' }}
                            </button>

                            <button
                                wire:click="confirmarEliminacion({{ $socio->id }})"
                                class="flex items-center justify-center px-3 py-2 text-xs bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors border border-red-200 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-1"
                                aria-label="Eliminar permanentemente: {{ $socio->name }}"
                            >
                                <x-admin-icon name="trash" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </article>
            @endforeach

        </div>

        @if($socios->hasPages())
            <div class="flex justify-center pt-2">
                {{ $socios->links() }}
            </div>
        @endif

    @else

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- ESTADO VACÍO                                                     --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl p-12 shadow-sm border border-gray-200 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <x-admin-icon name="user-group" class="w-8 h-8 text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-2">No se encontraron socios</h3>
            <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                @if($busqueda !== '' || $tipo !== 'todos' || $estado !== 'todos')
                    Intenta ajustar los filtros o realiza una búsqueda diferente.
                @else
                    Aún no hay socios registrados. Registra el primero para comenzar.
                @endif
            </p>
            @if($busqueda !== '' || $tipo !== 'todos' || $estado !== 'todos')
                <button
                    wire:click="limpiarFiltros"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-admin-icon name="arrow-path" class="w-4 h-4" />
                    Limpiar filtros
                </button>
            @else
                <button
                    wire:click="crearSocio"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-admin-icon name="plus" class="w-4 h-4" />
                    Registrar primer socio
                </button>
            @endif
        </div>

    @endif

    {{-- Toast de notificación: mismo patrón que news-management.blade.php --}}
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

    <div wire:loading.delay class="fixed bottom-4 right-4 bg-white border border-gray-200 rounded-lg shadow-lg px-4 py-2 flex items-center gap-2 text-sm text-gray-600 z-40">
        <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span>Cargando...</span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN: ACTIVAR / DESACTIVAR                        --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarModalToggle)
        <div
            wire:key="modal-toggle"
            wire:click.self="cancelarModal"
            wire:keydown.escape.window="cancelarModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="true"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-titulo-toggle"
                aria-describedby="modal-desc-toggle"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                <div class="h-1.5 w-full {{ $socioEstaActivo ? 'bg-amber-500' : 'bg-emerald-500' }}"></div>

                <div class="p-6 sm:p-7">
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center {{ $socioEstaActivo ? 'bg-amber-100' : 'bg-emerald-100' }}">
                            <x-admin-icon name="{{ $socioEstaActivo ? 'eye-slash' : 'arrow-path' }}" class="w-7 h-7 {{ $socioEstaActivo ? 'text-amber-600' : 'text-emerald-600' }}" />
                        </div>
                    </div>

                    <h2 id="modal-titulo-toggle" class="text-lg font-semibold text-gray-900 text-center mb-2">
                        {{ $socioEstaActivo ? '¿Desactivar este socio?' : '¿Activar este socio?' }}
                    </h2>

                    <p id="modal-desc-toggle" class="text-sm text-gray-500 text-center leading-relaxed mb-7">
                        @if($socioEstaActivo)
                            El socio <strong class="text-gray-700">dejará de aparecer</strong> en la sección pública del sitio.
                        @else
                            El socio <strong class="text-gray-700">volverá a aparecer</strong> en la sección pública del sitio.
                        @endif
                    </p>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            wire:click="cancelarModal"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Cancelar
                        </button>
                        <button
                            wire:click="ejecutarToggleEstado"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="ejecutarToggleEstado"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2
                                {{ $socioEstaActivo
                                    ? 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-400'
                                    : 'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 focus:ring-emerald-500' }}"
                            aria-label="{{ $socioEstaActivo ? 'Confirmar desactivación del socio' : 'Confirmar activación del socio' }}"
                        >
                            <span wire:loading.remove wire:target="ejecutarToggleEstado">
                                {{ $socioEstaActivo ? 'Desactivar' : 'Activar' }}
                            </span>
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
                <div class="h-1.5 w-full bg-red-600"></div>

                <div class="p-6 sm:p-7">
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center bg-red-100">
                            <x-admin-icon name="trash" class="w-7 h-7 text-red-600" />
                        </div>
                    </div>

                    <h2 id="modal-titulo-eliminar" class="text-lg font-semibold text-gray-900 text-center mb-2 wrap-break-word">
                        ¿Estás seguro de eliminar a {{ $socioNombreParaEliminar }}?
                    </h2>

                    <p id="modal-desc-eliminar" class="text-sm text-gray-500 text-center leading-relaxed mb-7">
                        Esta acción es <strong class="text-red-600">permanente e irreversible</strong>.
                        Se eliminará también su <strong class="text-gray-700">logotipo</strong>, incluyendo el archivo almacenado en el servidor.
                    </p>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            wire:click="cancelarModalEliminar"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Cancelar
                        </button>
                        <button
                            wire:click="ejecutarEliminacion"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="ejecutarEliminacion"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 active:bg-red-800 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            aria-label="Confirmar eliminación permanente del socio"
                        >
                            <span wire:loading.remove wire:target="ejecutarEliminacion">Aceptar</span>
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
