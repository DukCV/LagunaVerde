{{--
    Vista: Formulario de Creación/Edición de Socios Colaboradores
    Componente: App\Livewire\Admin\Partners\PartnerForm

    MODOS:
     - 'crear': formulario vacío, botón "Registrar socio".
     - 'editar': formulario pre-rellenado, botón "Actualizar socio".

    A diferencia de NewsForm no existe modo "borrador": el socio siempre se
    valida por completo al guardar; 'activo' es solo la bandera de
    visibilidad pública, independiente de la validez de los datos.

    SEGURIDAD:
     - Toda variable de usuario renderizada con {{ }} → escape XSS automático de Blade.
     - wire:click solo invoca métodos explícitos del componente (sin eval).
     - Los enlaces se validan en el servidor contra esquema http(s) (ver PartnerForm::reglaEsquemaSeguro()).
     - guardar() ya aplica rate limiting por IP + por usuario (RL_GUARDAR_IP_MAX/
       RL_GUARDAR_USER_MAX) antes de tocar la BD — ver PartnerForm::guardar().
     - Los logos prepended en los campos de enlaces son SVG inline (<x-social-icon>),
       el mismo componente usado en la lista y en la tarjeta pública — sin
       dependencias de red.

    ACCESIBILIDAD:
     - Todos los inputs tienen <label> asociado.
     - Botones de acción tienen aria-label descriptivo.
     - role="dialog" + aria-modal en el modal de confirmación.
     - wire:loading.attr="disabled" en botones durante peticiones activas.
--}}
<div class="space-y-6">

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO CON NAVEGACIÓN                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3">
        <button
            wire:click="cancelar"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
            aria-label="Volver a la lista de socios"
        >
            <x-admin-icon name="chevron-left" class="w-5 h-5 text-gray-600" />
        </button>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $modo === 'editar' ? 'Editar Socio Colaborador' : 'Nuevo Socio Colaborador' }}
            </h1>
            <p class="text-sm text-gray-500">
                {{ $modo === 'editar'
                    ? 'Modifica los datos de la organización y guarda los cambios'
                    : 'Completa el formulario para registrar una nueva organización colaboradora' }}
            </p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- LAYOUT PRINCIPAL: formulario izquierda + acciones derecha          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-[1fr_300px] gap-6 items-start">

        {{-- ── Columna principal ─────────────────────────────────────── --}}
        <div class="space-y-6 min-w-0">

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Identidad — nombre, tipo, visibilidad      │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 space-y-5">

                {{-- ── Pertenece a un usuario ──────────────────────────── --}}
                <div>
                    <p class="block text-sm font-medium text-gray-700 mb-1.5">Pertenece a un usuario</p>
                    <button
                        type="button"
                        role="switch"
                        aria-checked="{{ $vincularUsuario ? 'true' : 'false' }}"
                        wire:click="$toggle('vincularUsuario')"
                        class="flex items-center gap-3 px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors w-full"
                    >
                        <span class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 {{ $vincularUsuario ? 'bg-blue-500' : 'bg-gray-300' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform duration-200 {{ $vincularUsuario ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </span>
                        <span class="text-sm {{ $vincularUsuario ? 'text-blue-700' : 'text-gray-500' }}">
                            {{ $vincularUsuario ? 'Vinculado a un usuario' : 'No vinculado a un usuario' }}
                        </span>
                    </button>

                    @if ($vincularUsuario)
                        <div class="mt-3 space-y-3">
                            @if ($usuarioVinculadoInfo !== null)
                                {{-- ── Tarjeta de usuario seleccionado ──────────── --}}
                                <div class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    @include('livewire.admin.partials.user-avatar-mini', [
                                        'avatarUrl' => $usuarioVinculadoInfo->avatarUrl,
                                        'initials'  => $usuarioVinculadoInfo->initials,
                                        'name'      => $usuarioVinculadoInfo->name,
                                        'size'      => 'w-10 h-10',
                                    ])
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $usuarioVinculadoInfo->name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ $usuarioVinculadoInfo->email }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="quitarUsuarioVinculado"
                                        class="flex-shrink-0 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors focus:outline-none focus:ring-2 focus:ring-red-400"
                                        aria-label="Quitar usuario vinculado"
                                    >
                                        Quitar
                                    </button>
                                </div>
                            @else
                                {{-- ── Buscador de usuarios ─────────────────────── --}}
                                <div class="relative">
                                    <x-admin-icon
                                        name="magnifying-glass"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                    />
                                    <input
                                        type="search"
                                        wire:model.live.debounce.500ms="busquedaUsuario"
                                        placeholder="Buscar por nombre, correo o teléfono..."
                                        maxlength="100"
                                        autocomplete="off"
                                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        aria-label="Buscar usuario a vincular"
                                        role="searchbox"
                                    >
                                </div>

                                {{-- ── Resultados ────────────────────────────────── --}}
                                <div
                                    class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-64 overflow-y-auto"
                                    wire:loading.class="opacity-50"
                                    wire:target="busquedaUsuario"
                                >
                                    @forelse ($resultadosUsuarios as $usuarioResultado)
                                        <button
                                            type="button"
                                            wire:click="seleccionarUsuario({{ $usuarioResultado->id }})"
                                            wire:key="usuario-resultado-{{ $usuarioResultado->id }}"
                                            class="w-full flex items-center gap-3 p-3 hover:bg-blue-50 text-left transition-colors focus:outline-none focus:bg-blue-50"
                                        >
                                            @include('livewire.admin.partials.user-avatar-mini', [
                                                'avatarUrl' => $usuarioResultado->avatarUrl,
                                                'initials'  => $usuarioResultado->initials,
                                                'name'      => $usuarioResultado->name,
                                                'size'      => 'w-9 h-9',
                                            ])
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $usuarioResultado->name }}</p>
                                                <p class="text-xs text-gray-500 truncate">{{ $usuarioResultado->email }}</p>
                                            </div>
                                        </button>
                                    @empty
                                        <p class="p-3 text-sm text-gray-400 text-center">No se encontraron usuarios.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Nombre --}}
                <div>
                    <label for="form-nombre" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Nombre de la organización <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="form-nombre"
                        wire:model="nombre"
                        placeholder="Ej. Fundación Agua Limpia"
                        maxlength="150"
                        autocomplete="off"
                        class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                               @error('nombre') border-red-400 bg-red-50 @enderror"
                        aria-required="true"
                    >
                    @error('nombre')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tipo + Visibilidad --}}
                <div class="grid sm:grid-cols-2 gap-5">

                    {{-- Tipo de organización --}}
                    <div>
                        <label for="form-tipo" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tipo de organización <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select
                                id="form-tipo"
                                wire:model="tipo"
                                class="w-full appearance-none px-4 py-2.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-900
                                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-9
                                       @error('tipo') border-red-400 bg-red-50 @enderror"
                                aria-required="true"
                            >
                                <option value="">— Selecciona un tipo —</option>
                                @foreach ($tipos as $opcion)
                                    <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $opcion }}</option>
                                @endforeach
                            </select>
                            <x-admin-icon name="chevron-down"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        </div>
                        @error('tipo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Visibilidad pública --}}
                    <div>
                        <p class="block text-sm font-medium text-gray-700 mb-1.5">Visibilidad pública</p>
                        <button
                            type="button"
                            role="switch"
                            aria-checked="{{ $activo ? 'true' : 'false' }}"
                            wire:click="$toggle('activo')"
                            class="flex items-center gap-3 px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors w-full"
                        >
                            <span class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 {{ $activo ? 'bg-emerald-500' : 'bg-gray-300' }}">
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform duration-200 {{ $activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </span>
                            <span class="text-sm {{ $activo ? 'text-emerald-700' : 'text-gray-500' }}">
                                {{ $activo ? 'Activo (visible en el sitio)' : 'Inactivo (oculto)' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Quiénes son / Cómo nos apoyan              │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 space-y-5">

                <div>
                    <label for="form-quienes-son" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Quiénes son <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="form-quienes-son"
                        wire:model="quienesSon"
                        placeholder="Descripción breve de la organización..."
                        maxlength="600"
                        rows="3"
                        class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg resize-y
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                               @error('quienesSon') border-red-400 bg-red-50 @enderror"
                        aria-required="true"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1">
                        @error('quienesSon')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @else
                            <span></span>
                        @enderror
                        <p class="text-xs text-gray-400 ml-auto">{{ mb_strlen($quienesSon) }} / 600</p>
                    </div>
                </div>

                <div>
                    <label for="form-como-apoyan" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Cómo nos apoyan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="form-como-apoyan"
                        wire:model="comoApoyan"
                        placeholder="Describe el tipo de apoyo que brinda esta organización..."
                        maxlength="600"
                        rows="3"
                        class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg resize-y
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                               @error('comoApoyan') border-red-400 bg-red-50 @enderror"
                        aria-required="true"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1">
                        @error('comoApoyan')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @else
                            <span></span>
                        @enderror
                        <p class="text-xs text-gray-400 ml-auto">{{ mb_strlen($comoApoyan) }} / 600</p>
                    </div>
                </div>
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Enlaces — sitio web y redes sociales       │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 space-y-5">
                <p class="text-sm font-medium text-gray-700">Enlaces <span class="text-gray-400 font-normal">(opcionales)</span></p>

                @php
                    // [propiedad => [etiqueta, icono, placeholder, color]] — recorrido único
                    // para evitar repetir el mismo bloque 6 veces (DRY). 'icono' es el nombre
                    // reconocido por <x-social-icon> (mismo componente usado en la lista y en
                    // la tarjeta pública); 'color' tiñe ese logo con el tono de marca correcto
                    // para dar claridad visual inmediata sobre qué red representa cada campo.
                    $campos = [
                        'sitioWeb'     => ['Sitio web',   'website',   'https://ejemplo.com',                 'text-gray-400'],
                        'redInstagram' => ['Instagram',   'instagram', 'https://instagram.com/usuario',        'text-pink-500'],
                        'redFacebook'  => ['Facebook',    'facebook',  'https://facebook.com/pagina',          'text-blue-600'],
                        'redTwitter'   => ['Twitter / X', 'twitter',   'https://twitter.com/usuario',          'text-sky-500'],
                        'redLinkedin'  => ['LinkedIn',    'linkedin',  'https://linkedin.com/company/empresa', 'text-blue-700'],
                        'redYoutube'   => ['YouTube',     'youtube',   'https://youtube.com/canal',            'text-red-500'],
                    ];
                @endphp

                <div class="grid sm:grid-cols-2 gap-5">
                    @foreach ($campos as $propiedad => [$etiqueta, $icono, $placeholder, $color])
                        <div>
                            <label for="form-{{ $propiedad }}" class="block text-sm font-medium text-gray-700 mb-1.5">
                                {{ $etiqueta }}
                            </label>
                            <div class="relative">
                                <x-social-icon name="{{ $icono }}" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 {{ $color }} pointer-events-none" />
                                <input
                                    type="url"
                                    id="form-{{ $propiedad }}"
                                    wire:model="{{ $propiedad }}"
                                    placeholder="{{ $placeholder }}"
                                    maxlength="255"
                                    autocomplete="off"
                                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg
                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                                           @error($propiedad) border-red-400 bg-red-50 @enderror"
                                >
                            </div>
                            @error($propiedad)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Logotipo                                   │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-3">Logotipo</p>

                @php
                    $previewLogoUrl = $logo
                        ? $logo->temporaryUrl()
                        : (($logoActualUrl && ! $eliminarLogo) ? $logoActualUrl : null);
                    $esLogoNuevo = (bool) $logo;
                @endphp

                @if ($previewLogoUrl)
                    <div class="mb-4 rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-center" style="max-height: 200px; min-height: 120px;">
                            <img
                                src="{{ $previewLogoUrl }}"
                                alt="{{ $esLogoNuevo ? 'Nuevo logotipo seleccionado' : 'Logotipo actual' }}"
                                class="max-w-full max-h-[200px] w-auto h-auto object-contain"
                            >
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <label
                            for="input-logo"
                            class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-300
                                   text-sm text-gray-700 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors
                                   focus-within:ring-2 focus-within:ring-blue-500"
                            aria-label="Cambiar logotipo"
                        >
                            <x-admin-icon name="arrow-path" class="w-4 h-4 text-gray-500" />
                            Cambiar logotipo
                        </label>

                        @if ($esLogoNuevo)
                            <button
                                type="button"
                                wire:click="$set('logo', null)"
                                class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2 bg-red-50 border border-red-200
                                       text-sm text-red-600 rounded-lg hover:bg-red-100 transition-colors
                                       focus:outline-none focus:ring-2 focus:ring-red-400"
                                aria-label="Quitar logotipo seleccionado"
                            >
                                <x-admin-icon name="x-mark" class="w-4 h-4" />
                                Quitar
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="toggleEliminarLogo"
                                class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2 bg-red-50 border border-red-200
                                       text-sm text-red-600 rounded-lg hover:bg-red-100 transition-colors
                                       focus:outline-none focus:ring-2 focus:ring-red-400"
                                aria-label="Eliminar logotipo actual"
                            >
                                <x-admin-icon name="trash" class="w-4 h-4" />
                                Quitar
                            </button>
                        @endif
                    </div>
                @endif

                @if (! $previewLogoUrl)
                    <label
                        for="input-logo"
                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300
                               rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all text-gray-400 hover:text-blue-500"
                    >
                        <x-admin-icon name="cloud-arrow-up" class="w-8 h-8 mb-2" />
                        <span class="text-sm">Haz clic para subir un logotipo</span>
                        <span class="text-xs mt-0.5">JPG, PNG, WEBP (máx. 2 MB)</span>
                    </label>
                @endif

                <div
                    x-data="{ subiendo: false }"
                    x-on:livewire:upload-start.window="subiendo = true"
                    x-on:livewire:upload-finish.window="subiendo = false"
                    x-on:livewire:upload-error.window="subiendo = false"
                >
                    <input
                        type="file"
                        id="input-logo"
                        wire:model="logo"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        aria-label="Subir logotipo"
                    >
                    <div x-show="subiendo" class="mt-2 flex items-center gap-2 text-xs text-blue-600">
                        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Subiendo logotipo...</span>
                    </div>
                </div>

                @error('logo')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </div>{{-- /columna principal --}}

        {{-- ── Columna de acciones (sticky en escritorio) ────────────── --}}
        <div class="space-y-4 lg:sticky lg:top-6">

            <div class="bg-white rounded-xl p-5 shadow-lg border border-gray-200">
                <p class="text-sm font-semibold text-gray-700 mb-4">Acciones</p>

                <button
                    wire:click="abrirModal('guardar')"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 cursor-not-allowed"
                    class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium
                           rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm hover:shadow-md
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    aria-label="{{ $modo === 'editar' ? 'Actualizar socio' : 'Registrar socio' }}"
                >
                    <x-admin-icon name="check" class="w-4 h-4" />
                    <span>{{ $modo === 'editar' ? 'Actualizar socio' : 'Registrar socio' }}</span>
                </button>

                <button
                    wire:click="abrirModal('cancelar')"
                    class="w-full flex items-center justify-center gap-2 px-5 py-2.5 mt-2 text-red-600 text-sm font-medium
                           rounded-lg hover:bg-red-50 transition-colors
                           focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2"
                    aria-label="Cancelar y volver a la lista"
                >
                    <x-admin-icon name="x-circle" class="w-4 h-4" />
                    <span>Cancelar</span>
                </button>

                <div class="pt-4 mt-2 border-t border-gray-100 space-y-2 text-xs text-gray-500">
                    <div class="flex items-start gap-1.5">
                        <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                        <p>Los campos marcados con <span class="text-red-500">*</span> son obligatorios</p>
                    </div>
                    <div class="flex items-start gap-1.5">
                        <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                        <p>Desactivar un socio lo oculta del sitio sin borrar sus datos</p>
                    </div>
                </div>
            </div>

            <div
                wire:loading.delay
                wire:target="guardar,logo"
                class="flex items-center gap-2 justify-center text-xs text-gray-500 bg-white rounded-lg border border-gray-200 py-2 px-3"
            >
                <svg class="animate-spin w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Procesando...</span>
            </div>
        </div>

    </div>{{-- /grid --}}

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN: guardar / cancelar                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if ($modalConfirmacion)
        <div
            wire:key="modal-confirmacion-socio"
            wire:click.self="cerrarModal"
            wire:keydown.escape.window="cerrarModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="false"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-form-titulo-socio"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                <div class="h-1.5 w-full {{ $modalConfirmacion === 'guardar' ? 'bg-blue-500' : 'bg-red-500' }}"></div>

                <div class="p-6 sm:p-7">
                    <div class="flex justify-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center {{ $modalConfirmacion === 'guardar' ? 'bg-blue-100' : 'bg-red-100' }}">
                            <x-admin-icon name="{{ $modalConfirmacion === 'guardar' ? 'check-circle' : 'exclamation-triangle' }}" class="w-6 h-6 {{ $modalConfirmacion === 'guardar' ? 'text-blue-600' : 'text-red-600' }}" />
                        </div>
                    </div>

                    <h2 id="modal-form-titulo-socio" class="text-lg font-semibold text-gray-900 text-center mb-2">
                        @if ($modalConfirmacion === 'guardar')
                            {{ $modo === 'editar' ? '¿Actualizar este socio?' : '¿Registrar este socio?' }}
                        @else
                            ¿Descartar los cambios?
                        @endif
                    </h2>

                    <p class="text-sm text-gray-500 text-center leading-relaxed mb-7">
                        @if ($modalConfirmacion === 'guardar')
                            {{ $activo
                                ? 'El socio será visible de inmediato en la sección pública del sitio.'
                                : 'El socio se guardará como inactivo y no será visible en el sitio público.' }}
                        @else
                            Perderás cualquier cambio no guardado en este formulario.
                        @endif
                    </p>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            wire:click="cerrarModal"
                            autofocus
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Volver al formulario"
                        >
                            Volver
                        </button>
                        <button
                            wire:click="{{ $modalConfirmacion === 'guardar' ? 'guardar' : 'cancelar' }}"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="guardar,cancelar"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2
                                {{ $modalConfirmacion === 'guardar'
                                    ? 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800 focus:ring-blue-500'
                                    : 'bg-red-600 hover:bg-red-700 active:bg-red-800 focus:ring-red-500' }}"
                            aria-label="{{ $modalConfirmacion === 'guardar' ? 'Confirmar guardado' : 'Confirmar descarte de cambios' }}"
                        >
                            <span wire:loading.remove wire:target="guardar,cancelar">
                                {{ $modalConfirmacion === 'guardar' ? 'Confirmar' : 'Sí, descartar' }}
                            </span>
                            <span wire:loading wire:target="guardar,cancelar" class="inline-flex items-center gap-2">
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

</div>
