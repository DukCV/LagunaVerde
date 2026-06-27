{{--
    Vista: Modal "Administrar rol" (Gestión de Usuarios)
    Componente: App\Livewire\Admin\UserRoleManager

    DISEÑO:
     - Un solo bloque de modal que cambia de contenido según el rol elegido
       (Alpine x-show), sin duplicar el HTML del modal en sí — cumple DRY.
     - El selector de rol y el cambio de sección viven enteramente en Alpine
       (x-data/x-show): cero peticiones a Livewire mientras se explora el
       formulario, solo al pulsar "Confirmar".

    SEGURIDAD:
     - La contraseña es estado local de Alpine (x-model), nunca una
       propiedad Livewire — viaja una sola vez, como argumento de
       $wire.confirmar(), nunca queda reflejada en el HTML.
     - Toda variable de usuario se renderiza con {{ }} → escape XSS automático.

    RESPONSIVE:
     - Modal con altura máxima (max-h-[90vh]) y cuerpo con scroll interno;
       cabecera y pie fijos — nunca se desborda en móvil.
--}}
<div
    x-data="{ rol: '{{ $rolInicial }}', password: '', procesando: false }"
    wire:keydown.escape.window="cancelar"
    wire:click.self="cancelar"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60 backdrop-blur-sm"
    aria-hidden="true"
>
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-gestor-rol"
        class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 max-h-[90vh] flex flex-col overflow-hidden"
    >
        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- CABECERA                                                       --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <h2 id="titulo-gestor-rol" class="text-lg font-semibold text-gray-900">Administrar rol</h2>
            <p class="text-sm text-gray-500 truncate">{{ $usuarioNombre }}</p>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- CUERPO — con scroll interno propio                              --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="px-6 py-5 overflow-y-auto flex-1 space-y-5">

            {{-- Selector de rol: radios estilizados, 100% Alpine ────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Selecciona el rol</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach($rolOpciones as $clave => $etiqueta)
                        @php
                            $colorActivo = match ($clave) {
                                'colaborador'   => 'border-purple-500 bg-purple-50 text-purple-700',
                                'administrador' => 'border-blue-500 bg-blue-50 text-blue-700',
                                default         => 'border-gray-500 bg-gray-50 text-gray-700',
                            };
                        @endphp
                        <button
                            type="button"
                            x-on:click="rol = '{{ $clave }}'"
                            x-bind:class="rol === '{{ $clave }}' ? '{{ $colorActivo }}' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="px-4 py-3 border-2 rounded-xl text-sm font-medium transition-colors text-center"
                            aria-pressed="{{ $rolInicial === $clave ? 'true' : 'false' }}"
                        >
                            {{ $etiqueta }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- ─── Sección: Usuario ──────────────────────────────────────── --}}
            <div x-show="rol === 'usuario'" x-cloak>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
                    <x-admin-icon name="information-circle" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-blue-800 leading-relaxed">
                        Como usuario podrá ver noticias, comentar, recibir notificaciones de la página e inscribirse a eventos.
                    </p>
                </div>
            </div>

            {{-- ─── Sección: Colaborador ──────────────────────────────────── --}}
            <div x-show="rol === 'colaborador'" x-cloak class="space-y-4">

                {{-- Logotipo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Logo</label>
                    <div class="flex items-center gap-4">
                        @if($colabLogo)
                            <img src="{{ $colabLogo->temporaryUrl() }}" alt="Vista previa del logo" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
                        @elseif($colabLogoActualUrl && ! $colabEliminarLogo)
                            <img src="{{ $colabLogoActualUrl }}" alt="Logo actual" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
                        @else
                            <div class="w-16 h-16 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center">
                                <x-admin-icon name="photo" class="w-7 h-7 text-gray-300" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <input
                                type="file"
                                wire:model="colabLogo"
                                accept="image/png,image/jpeg,image/webp"
                                class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100"
                                aria-label="Subir logo del colaborador"
                            >
                            <p wire:loading wire:target="colabLogo" class="text-xs text-gray-400 mt-1">Subiendo...</p>
                            @error('colabLogo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                            @if($colabLogoActualUrl)
                                <label class="flex items-center gap-2 text-xs text-gray-600 mt-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleEliminarLogo"
                                        @checked($colabEliminarLogo)
                                        class="rounded border-gray-300 text-red-600 focus:ring-red-400"
                                    >
                                    Quitar logo actual
                                </label>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Nombre y categoría --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="colab-nombre" class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
                        <input
                            type="text"
                            id="colab-nombre"
                            wire:model="colabNombre"
                            maxlength="150"
                            class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        >
                        @error('colabNombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="colab-tipo" class="block text-sm font-medium text-gray-700 mb-1.5">Categoría</label>
                        <select
                            id="colab-tipo"
                            wire:model="colabTipo"
                            class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        >
                            <option value="">Selecciona una categoría</option>
                            @foreach($tiposSocio as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                            @endforeach
                        </select>
                        @error('colabTipo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Quién es / Cómo apoya --}}
                <div>
                    <label for="colab-quienes-son" class="block text-sm font-medium text-gray-700 mb-1.5">Quién es</label>
                    <textarea
                        id="colab-quienes-son"
                        wire:model="colabQuienesSon"
                        rows="2"
                        maxlength="600"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                    ></textarea>
                    @error('colabQuienesSon') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="colab-como-apoya" class="block text-sm font-medium text-gray-700 mb-1.5">Cómo nos apoya</label>
                    <textarea
                        id="colab-como-apoya"
                        wire:model="colabComoApoyan"
                        rows="2"
                        maxlength="600"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                    ></textarea>
                    @error('colabComoApoyan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Redes sociales (opcional) --}}
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-1.5">Redes sociales <span class="text-gray-400 font-normal">(opcional)</span></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach([
                            'colabSitioWeb'     => 'Sitio web',
                            'colabRedInstagram' => 'Instagram',
                            'colabRedFacebook'  => 'Facebook',
                            'colabRedTwitter'   => 'Twitter / X',
                            'colabRedLinkedin'  => 'LinkedIn',
                            'colabRedYoutube'   => 'YouTube',
                        ] as $campo => $etiquetaCampo)
                            <div>
                                <input
                                    type="url"
                                    wire:model="{{ $campo }}"
                                    placeholder="{{ $etiquetaCampo }} (https://...)"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    aria-label="{{ $etiquetaCampo }}"
                                >
                                @error($campo) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ─── Sección: Administrador ────────────────────────────────── --}}
            <div x-show="rol === 'administrador'" x-cloak class="space-y-4">

                <div>
                    <label for="puesto-admin" class="block text-sm font-medium text-gray-700 mb-1.5">Puesto</label>
                    <input
                        type="text"
                        id="puesto-admin"
                        wire:model="puesto"
                        placeholder="Administrador"
                        maxlength="100"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    >
                    @error('puesto') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700 mb-2">Permisos granulares</p>
                    <div class="space-y-3">
                        @foreach($catalogoPermisos as $modulo => $permisosDelModulo)
                            <div class="bg-gray-50 rounded-lg p-3">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ $modulo }}</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    @foreach($permisosDelModulo as $clavePermiso => $etiquetaPermiso)
                                        <label class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-blue-50 cursor-pointer transition-colors">
                                            <input
                                                type="checkbox"
                                                wire:model="permisos"
                                                value="{{ $clavePermiso }}"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            >
                                            <span class="truncate">{{ $etiquetaPermiso }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- PIE — contraseña de confirmación + acciones                    --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="px-6 py-5 border-t border-gray-200 flex-shrink-0 space-y-3">

            <div>
                <label for="password-gestor-rol" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Tu contraseña
                </label>

                @if($errorPassword !== '')
                    <p class="text-sm text-red-600 mb-1.5" role="alert">{{ $errorPassword }}</p>
                @endif

                <input
                    type="password"
                    id="password-gestor-rol"
                    x-model="password"
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña actual"
                    class="w-full px-3 py-2.5 text-sm border rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-transparent transition-all {{ $errorPassword !== '' ? 'border-red-300 focus:ring-red-400' : 'border-gray-300 focus:ring-blue-500' }}"
                    aria-label="Tu contraseña"
                    aria-invalid="{{ $errorPassword !== '' ? 'true' : 'false' }}"
                >
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                <button
                    type="button"
                    wire:click="cancelar"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                    aria-label="Cancelar y cerrar el modal"
                >
                    Cancelar
                </button>

                {{--
                    Confirmar: deshabilitado hasta escribir algo en la contraseña
                    (x-bind:disabled) y mientras la petición está en curso
                    (procesando). El rol elegido y la contraseña se envían como
                    argumentos explícitos — ninguno de los dos es una propiedad
                    Livewire bound vía wire:model.
                --}}
                <button
                    type="button"
                    x-bind:disabled="password.length === 0 || procesando"
                    x-on:click="
                        procesando = true;
                        $wire.confirmar(rol, password).then(() => {
                            password = '';
                            procesando = false;
                        });
                    "
                    x-bind:class="(password.length === 0 || procesando) ? 'opacity-50 cursor-not-allowed' : ''"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    aria-label="Confirmar cambio de rol"
                >
                    <span x-show="!procesando">Confirmar</span>
                    <span x-show="procesando" class="inline-flex items-center gap-2">
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
