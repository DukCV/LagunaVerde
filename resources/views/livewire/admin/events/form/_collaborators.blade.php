{{--
    Sección: Colaboradores Invitados.

    Recibe del componente (EventForm::render()):
      $colaboradoresDisponibles → array<App\DTOs\Admin\PartnerPickerItemDto>
      $tiposColaborador         → array<int, string> (Partner::TYPES)

    TOGGLE PRINCIPAL — POR QUÉ .live: a diferencia de los demás toggles del
    formulario (entangle() deferred, ver _capacity-registration.blade.php),
    activar "Con colaboradores" necesita un round-trip inmediato al
    servidor para poblar la grilla de búsqueda (EventForm::render() solo
    consulta la BD cuando withCollaborators ya es true). Por eso este toggle
    usa $wire.entangle(...).live en vez de la variante diferida.

    SEGURIDAD XSS: toda salida (nombre, detalles de participación, logo)
    pasa por {{ }} → escape automático de Blade. Las URLs de logo nunca son
    input directo del usuario: provienen de Media::url()/route('media.show', ...)
    resueltas en el servidor (ver AdminEventsFormService::resolverColaboradores()).
--}}
<div
    x-data="{ withCollaborators: $wire.entangle('collaboratorsFilter.withCollaborators').live }"
    class="bg-white rounded-xl p-6 shadow-sm border border-gray-200"
>
    <div class="flex items-start justify-between gap-3 mb-1">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0">
                <x-admin-icon name="user-group" class="w-4.5 h-4.5 text-blue-600" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Colaboradores Invitados</h2>
                <p class="text-xs text-gray-500 mt-0.5">Socios u organizaciones que participan en este evento</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            <span class="text-xs text-gray-500 whitespace-nowrap" x-text="withCollaborators ? 'Activado' : 'Desactivado'"></span>
            <x-admin-toggle model="withCollaborators" id="toggle-con-colaboradores" />
        </div>
    </div>

    <div x-show="withCollaborators" x-cloak class="mt-5 space-y-5 pt-5 border-t border-gray-100">

        {{-- ══════════════════════════════════════════════════════════
             BUSCADOR + FILTRO DE CATEGORÍA
        ══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-admin-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    type="search"
                    wire:model.live.debounce.400ms="collaboratorsFilter.search"
                    placeholder="Buscar socio por nombre..."
                    maxlength="100"
                    autocomplete="off"
                    aria-label="Buscar colaboradores disponibles"
                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                >
            </div>
            <div class="relative sm:w-56 flex-shrink-0">
                <select
                    wire:model.live="collaboratorsFilter.type"
                    aria-label="Filtrar por categoría"
                    class="w-full appearance-none px-4 py-2.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-900
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-9"
                >
                    <option value="todos">Todas las categorías</option>
                    @foreach ($tiposColaborador as $tipo)
                        <option value="{{ $tipo }}">{{ $tipo }}</option>
                    @endforeach
                </select>
                <x-admin-icon name="chevron-down"
                    class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             GRILLA DE SOCIOS DISPONIBLES
        ══════════════════════════════════════════════════════════ --}}
        <div wire:loading.class="opacity-50" wire:target="collaboratorsFilter.search,collaboratorsFilter.type">
            @if (count($colaboradoresDisponibles) > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach ($colaboradoresDisponibles as $colaborador)
                        <div
                            wire:key="colab-disponible-{{ $colaborador->id }}"
                            class="flex flex-col items-center gap-2 p-3 border border-gray-200 rounded-xl hover:border-blue-300 hover:bg-blue-50/30 transition-colors text-center"
                        >
                            <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center flex-shrink-0">
                                @if ($colaborador->logoUrl)
                                    <img src="{{ $colaborador->logoUrl }}" alt="Logo de {{ $colaborador->name }}" class="w-full h-full object-cover">
                                @else
                                    <x-admin-icon name="building-office" class="w-5 h-5 text-gray-400" />
                                @endif
                            </div>
                            <p class="text-xs font-medium text-gray-700 line-clamp-2 leading-snug min-h-[2rem]" title="{{ $colaborador->name }}">
                                {{ $colaborador->name }}
                            </p>
                            <button
                                type="button"
                                wire:click="agregarColaborador({{ $colaborador->id }})"
                                wire:loading.attr="disabled"
                                wire:target="agregarColaborador({{ $colaborador->id }})"
                                class="w-full flex items-center justify-center gap-1 px-2 py-1.5 text-xs font-medium text-blue-600
                                       bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <x-admin-icon name="user-plus" class="w-3.5 h-3.5" />
                                Agregar
                            </button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400 border-2 border-dashed border-gray-100 rounded-lg p-5 text-center">
                    No se encontraron socios disponibles con estos filtros.
                </p>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             AGREGAR COLABORADOR EXTERNO (no vive en la tabla 'partners')
        ══════════════════════════════════════════════════════════ --}}
        <div class="border border-dashed border-gray-300 rounded-xl p-4 bg-gray-50/50">
            <p class="text-xs font-medium text-gray-600 mb-3">¿No está en la lista? Agrega un colaborador externo</p>

            @php
                $previewLogoPersonalizado = $customCollaboratorLogo && $customCollaboratorLogo->isPreviewable()
                    ? $customCollaboratorLogo->temporaryUrl()
                    : null;
            @endphp

            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                <div class="flex items-start gap-3 flex-1 w-full min-w-0">
                    <label
                        for="input-logo-colaborador-externo"
                        class="relative w-11 h-11 rounded-full overflow-hidden bg-white border-2 border-dashed border-gray-300
                               hover:border-blue-400 flex items-center justify-center flex-shrink-0 cursor-pointer transition-colors"
                        title="Logotipo (opcional)"
                    >
                        @if ($previewLogoPersonalizado)
                            <img src="{{ $previewLogoPersonalizado }}" alt="Vista previa del logotipo" class="w-full h-full object-cover">
                        @else
                            <x-admin-icon name="photo" class="w-4 h-4 text-gray-400" />
                        @endif
                    </label>
                    <input
                        type="file"
                        id="input-logo-colaborador-externo"
                        wire:model="customCollaboratorLogo"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        aria-label="Logotipo del colaborador externo (opcional)"
                    >

                    <div class="flex-1 min-w-0">
                        <input
                            type="text"
                            wire:model="customCollaboratorName"
                            placeholder="Nombre del colaborador externo"
                            maxlength="150"
                            autocomplete="off"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                                   @error('customCollaboratorName') border-red-400 bg-red-50 @enderror"
                        >
                        @error('customCollaboratorName')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        @error('customCollaboratorLogo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="agregarColaboradorPersonalizado"
                    wire:loading.attr="disabled"
                    wire:target="agregarColaboradorPersonalizado,customCollaboratorLogo"
                    class="w-full sm:w-auto flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-medium text-white
                           bg-gray-700 hover:bg-gray-800 rounded-lg transition-colors flex-shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <x-admin-icon name="plus" class="w-4 h-4" />
                    <span>Agregar externo</span>
                </button>
            </div>

            <div wire:loading wire:target="customCollaboratorLogo" class="mt-2 flex items-center gap-2 text-xs text-blue-600">
                <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Subiendo logotipo...</span>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             LISTA DE COLABORADORES SELECCIONADOS
        ══════════════════════════════════════════════════════════ --}}
        <div class="pt-2">
            <p class="text-sm font-medium text-gray-700 mb-3">
                Colaboradores seleccionados ({{ count($selectedCollaborators) }})
            </p>

            @if (count($selectedCollaborators) > 0)
                <div class="space-y-2">
                    @foreach ($selectedCollaborators as $item)
                        <div wire:key="colab-seleccionado-{{ $item['key'] }}">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 border border-gray-200 rounded-xl bg-gray-50/60">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="w-10 h-10 rounded-full overflow-hidden bg-white border border-gray-200 flex items-center justify-center flex-shrink-0">
                                        @if ($item['logoUrl'])
                                            <img src="{{ $item['logoUrl'] }}" alt="Logo de {{ $item['name'] }}" class="w-full h-full object-cover">
                                        @else
                                            <x-admin-icon name="building-office" class="w-4 h-4 text-gray-400" />
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $item['name'] }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ $item['source'] === 'partner' ? 'Socio registrado' : 'Colaborador externo' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 sm:w-72 flex-shrink-0">
                                    <input
                                        type="text"
                                        wire:model="selectedCollaborators.{{ $loop->index }}.participationDetails"
                                        placeholder="¿Cómo será su participación? (opcional)"
                                        maxlength="300"
                                        autocomplete="off"
                                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    >
                                    <button
                                        type="button"
                                        wire:click="quitarColaborador('{{ $item['key'] }}')"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0"
                                        aria-label="Quitar a {{ $item['name'] }} de la lista"
                                    >
                                        <x-admin-icon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            @error('selectedCollaborators.' . $loop->index . '.name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400 border-2 border-dashed border-gray-100 rounded-lg p-5 text-center">
                    Aún no se han agregado colaboradores a este evento.
                </p>
            @endif
        </div>
    </div>
</div>
