{{--
    livewire/public/collaborators/collaborators-index.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe:
      $partnersPaginator → LengthAwarePaginator<App\DTOs\PartnerCardDto>
      $activeCount       → int
      $categories        → array<string, string>
      $selectedPartner   → App\DTOs\PartnerCardDto|null (socio mostrado en el modal)

    SEGURIDAD EN VISTA:
    • {{ }} en toda salida → auto-escaping XSS de Blade.
    • $search impreso en pantalla pasa por {{ }} → seguro ante XSS.
    • Las options de los selects provienen del servicio (Partner::TYPES) — no del DOM.
    • wire:key usa el id del socio solo como clave interna de Livewire — Partner
      no tiene página de detalle público, por lo que no hay enumeración posible.
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
                {{-- Contador dinámico de socios activos --}}
                <div class="inline-block px-4 py-2 bg-white/20 backdrop-blur-sm text-white
                            rounded-full mb-6 text-sm font-medium tracking-wide">
                    {{ $activeCount }} {{ $activeCount === 1 ? 'colaborador activo' : 'colaboradores activos' }}
                </div>
                <h1 class="text-white text-4xl lg:text-6xl font-bold mb-6 leading-tight">
                    Colaboradores
                </h1>
                <p class="text-blue-100 text-lg lg:text-xl max-w-2xl mx-auto leading-relaxed">
                    Conoce a las organizaciones que hacen posible nuestra misión de conservar la laguna.
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
         TOOLBAR DE FILTROS (sticky)
    ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white border-b sticky top-0 z-30 shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">

                {{-- Búsqueda --}}
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5 pointer-events-none"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        wire:model.live.debounce.500ms="search"
                        type="search"
                        maxlength="100"
                        placeholder="Buscar colaborador..."
                        autocomplete="off"
                        aria-label="Buscar colaboradores"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm"
                    />
                </div>

                {{-- Filtro de categoría --}}
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5 text-gray-400 hidden md:block shrink-0"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    <select
                        wire:model.live="type"
                        aria-label="Filtrar por categoría"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm text-gray-700 appearance-none cursor-pointer"
                    >
                        {{--
                            Las opciones vienen del servicio (Partner::TYPES, lista blanca),
                            no del input del usuario → sin posibilidad de inyección.
                        --}}
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Ordenamiento --}}
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5 text-gray-400 hidden md:block shrink-0"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                         aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/>
                    </svg>
                    <select
                        wire:model.live="sort"
                        aria-label="Ordenar colaboradores"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               transition-all text-sm text-gray-700 appearance-none cursor-pointer"
                    >
                        <option value="recientes">Más recientes</option>
                        <option value="antiguos">Más antiguas</option>
                        <option value="nombre">De la A-Z</option>
                    </select>
                </div>
            </div>

            {{-- Contador de resultados + limpiar filtros --}}
            <div class="mt-3 flex items-center justify-between gap-4">
                <p class="text-sm text-gray-500" aria-live="polite" aria-atomic="true">
                    <span class="font-medium text-gray-700">{{ $partnersPaginator->total() }}</span>
                    {{ $partnersPaginator->total() === 1 ? 'resultado' : 'resultados' }}
                    {{-- $search ya pasa por {{ }} → XSS imposible --}}
                    @if ($search)
                        para <span class="italic">"{{ $search }}"</span>
                    @endif
                </p>

                {{-- Solo visible si algún filtro difiere de su valor por defecto --}}
                @if ($search || $type !== 'todos' || $sort !== 'recientes')
                    <button
                        wire:click="clearFilters"
                        type="button"
                        class="text-sm text-blue-600 hover:underline shrink-0"
                    >
                        Limpiar filtros
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         GRID DE COLABORADORES
    ══════════════════════════════════════════════════════════════ --}}
    <section class="py-12 lg:py-16 bg-gray-50 min-h-[40vh]">
        <div class="container mx-auto px-4">

            @if ($partnersPaginator->isEmpty())

                {{-- ── Estado vacío ──────────────────────────────── --}}
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-300 mb-4"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"
                         aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <p class="text-gray-500 text-lg">
                        No se encontraron colaboradores que coincidan con tu búsqueda.
                    </p>
                    <button
                        wire:click="$set('search', '')"
                        class="mt-4 text-blue-600 hover:underline text-sm"
                    >
                        Limpiar búsqueda
                    </button>
                </div>

            @else

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

                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        @foreach ($partnersPaginator as $partner)
                            <x-collaborators.card :partner="$partner" wire:key="partner-{{ $partner->id }}" />
                        @endforeach
                    </div>
                </div>

            @endif

        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         PAGINACIÓN
    ══════════════════════════════════════════════════════════════ --}}
    @if ($partnersPaginator->hasPages())
        <section class="pb-16 bg-gray-50">
            <div class="container mx-auto px-4">
                <div class="flex flex-col items-center gap-4">

                    {{-- Paginador nativo de Laravel (Eloquent real, no manual) --}}
                    {{ $partnersPaginator->links() }}

                    <p class="text-sm text-gray-500">
                        Página
                        <span class="font-medium text-gray-700">{{ $partnersPaginator->currentPage() }}</span>
                        de
                        <span class="font-medium text-gray-700">{{ $partnersPaginator->lastPage() }}</span>
                        &middot;
                        {{ $partnersPaginator->total() }}
                        {{ $partnersPaginator->total() === 1 ? 'colaborador' : 'colaboradores' }}
                    </p>

                </div>
            </div>
        </section>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         MODAL DE DETALLES
         $selectedPartner ya está cargado en memoria (página actual del
         paginador) → abrir/cerrar el modal no genera consultas a la BD.
    ══════════════════════════════════════════════════════════════ --}}
    @if ($selectedPartner)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="partner-modal-title"
            wire:key="partner-modal-{{ $selectedPartner->id }}"
        >
            {{-- wire:click.outside cierra el modal al hacer clic fuera del panel --}}
            <div
                wire:click.outside="closeDetails"
                class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
            >
                {{-- ── Logo / Imagen ────────────────────────────────── --}}
                <div class="relative aspect-video bg-gray-100">
                    @if ($selectedPartner->logoUrl)
                        <img
                            src="{{ e($selectedPartner->logoUrl) }}"
                            alt="Logo de {{ $selectedPartner->name }}"
                            class="w-full h-full object-cover rounded-t-2xl"
                            loading="lazy"
                        >
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-50 to-green-50 rounded-t-2xl">
                            <span class="text-6xl opacity-30" aria-hidden="true">🤝</span>
                        </div>
                    @endif

                    {{-- Botón cerrar --}}
                    <button
                        type="button"
                        wire:click="closeDetails"
                        aria-label="Cerrar"
                        class="absolute top-4 right-4 w-9 h-9 bg-white/90 backdrop-blur-sm rounded-full
                               flex items-center justify-center text-gray-700 hover:bg-white transition-colors shadow"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    {{-- Categoría --}}
                    <div class="absolute top-4 left-4">
                        <span class="px-3 py-1 bg-white/90 backdrop-blur-sm text-gray-900 rounded-full text-sm">
                            {{ $selectedPartner->type }}
                        </span>
                    </div>
                </div>

                {{-- ── Contenido ────────────────────────────────────── --}}
                <div class="p-6 lg:p-8 space-y-6">

                    <h2 id="partner-modal-title" class="text-2xl text-gray-900">
                        {{ $selectedPartner->name }}
                    </h2>

                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                            Quiénes son
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $selectedPartner->whoTheyAre }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                            Cómo ayudan
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $selectedPartner->howTheySupport }}
                        </p>
                    </div>

                    {{-- Redes sociales + sitio web --}}
                    @if ($selectedPartner->facebook || $selectedPartner->twitter || $selectedPartner->linkedin || $selectedPartner->instagram || $selectedPartner->website)
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-200">
                            @if ($selectedPartner->facebook)
                                <a href="{{ e($selectedPartner->facebook) }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="Facebook de {{ $selectedPartner->name }}"
                                   class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="facebook" class="w-4 h-4" />
                                </a>
                            @endif
                            @if ($selectedPartner->twitter)
                                <a href="{{ e($selectedPartner->twitter) }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="X (Twitter) de {{ $selectedPartner->name }}"
                                   class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="twitter" class="w-4 h-4" />
                                </a>
                            @endif
                            @if ($selectedPartner->linkedin)
                                <a href="{{ e($selectedPartner->linkedin) }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="LinkedIn de {{ $selectedPartner->name }}"
                                   class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="linkedin" class="w-4 h-4" />
                                </a>
                            @endif
                            @if ($selectedPartner->instagram)
                                <a href="{{ e($selectedPartner->instagram) }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="Instagram de {{ $selectedPartner->name }}"
                                   class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="instagram" class="w-4 h-4" />
                                </a>
                            @endif
                            @if ($selectedPartner->website)
                                <a href="{{ e($selectedPartner->website) }}" target="_blank" rel="noopener noreferrer"
                                   class="text-sm text-blue-600 hover:underline ml-2">
                                    Visitar sitio web
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- Fechas --}}
                    <div class="flex items-center justify-between gap-2 pt-4 border-t border-gray-200 text-xs text-gray-500">
                        <span>Registrado: {{ $selectedPartner->createdAt }}</span>
                        <span>Actualizado: {{ $selectedPartner->updatedAt }}</span>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
