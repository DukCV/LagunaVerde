{{--
    Vista: Gestión de Usuarios del Panel de Administración
    Componente: App\Livewire\Admin\UsersManagement

    RESTRICCIÓN DE NEGOCIO:
     - Los administradores NO pueden crear usuarios desde este panel —
       a propósito, esta vista NO incluye ningún botón "Nuevo usuario".
     - "Borrar definitivamente" sigue siendo un marcador visual inactivo
       (disabled + opacity-50 + cursor-not-allowed): aún no existe lógica
       de mutación para ella.
     - "Inhabilitar"/"Activar" y "Administrar rol" SÍ son funcionales: ambos
       exigen la contraseña del administrador autenticado en su respectivo
       modal de confirmación. "Administrar rol" vive en un sub-componente
       independiente (UserRoleManager) — ver el include condicionado por
       $mostrarGestorRol al final de esta vista.

    SEGURIDAD:
     - Toda variable de usuario se renderiza con {{ }} → escape XSS automático de Blade.
     - La foto de perfil proviene únicamente de User::profilePhotoUrl(), nunca
       de una ruta enviada por el cliente.
     - La contraseña de cada modal viaja solo como argumento de un único
       método Livewire ($wire.ejecutarToggleEstado / $wire.confirmar) —
       nunca queda atada a una propiedad pública ni se refleja en el HTML.

    RESPONSIVE:
     - Grid de tarjetas (mismo patrón que Gestión de Eventos): 1 columna en
       móvil, hasta 4 en pantallas grandes — sin tablas ni scroll horizontal.

    ACCESIBILIDAD:
     - Roles ARIA en formulario de filtro (search, combobox).
     - aria-label / aria-disabled en todos los botones de acción.
--}}
<div class="space-y-6">

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO DE SECCIÓN                                              --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 mb-1">Gestión de Usuarios</h1>
            <div class="flex items-center gap-3 mt-2 flex-wrap">
                <span class="text-3xl font-semibold text-blue-600">{{ number_format($totales['todos'] ?? 0) }}</span>
                <span class="text-gray-500 text-sm">usuarios registrados</span>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 border border-gray-300">
                        {{ $totales['normal'] ?? 0 }} normales
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-purple-50 text-purple-700 border border-purple-200">
                        {{ $totales['colaborador'] ?? 0 }} colaboradores
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-700 border border-blue-200">
                        {{ $totales['administrador'] ?? 0 }} administradores
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-red-50 text-red-700 border border-red-200">
                        {{ $totales['inactivos'] ?? 0 }} inactivos
                    </span>
                </div>
            </div>
        </div>
        {{-- A propósito: ningún botón "Nuevo usuario" — la creación está deshabilitada para este panel --}}
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- BARRA DE FILTROS                                                    --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">

        <div class="flex items-center gap-2 mb-3">
            <x-admin-icon name="funnel" class="w-4 h-4 text-gray-400" />
            <span class="text-sm text-gray-600">Filtrar usuarios</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

            {{-- Búsqueda por texto: debounce 500ms para reducir requests y mitigar DoS ligero --}}
            <div class="sm:col-span-2">
                <label for="busqueda-usuarios" class="sr-only">Buscar usuarios</label>
                <div class="relative">
                    <x-admin-icon
                        name="magnifying-glass"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    />
                    <input
                        id="busqueda-usuarios"
                        type="search"
                        wire:model.live.debounce.500ms="busqueda"
                        placeholder="Buscar por nombre, correo o teléfono..."
                        maxlength="100"
                        autocomplete="off"
                        class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        aria-label="Buscar usuarios"
                        role="searchbox"
                    >
                </div>
            </div>

            {{-- Orden --}}
            <div>
                <label for="orden-usuarios" class="sr-only">Ordenar por</label>
                <div class="relative">
                    <select
                        id="orden-usuarios"
                        wire:model.live="orden"
                        class="w-full appearance-none px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Ordenar usuarios"
                    >
                        @foreach($ordenes as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>
        </div>

        {{-- Tabs de categoría --}}
        @php
            $tabsCategoria = [
                ['id' => 'todos',         'label' => 'Todos',           'count' => $totales['todos'] ?? 0],
                ['id' => 'normal',        'label' => 'Normales',        'count' => $totales['normal'] ?? 0],
                ['id' => 'colaborador',   'label' => 'Colaboradores',   'count' => $totales['colaborador'] ?? 0],
                ['id' => 'administrador', 'label' => 'Administradores', 'count' => $totales['administrador'] ?? 0],
                ['id' => 'inactivos',     'label' => 'Inactivos',       'count' => $totales['inactivos'] ?? 0],
            ];
        @endphp
        <div class="flex items-center gap-2 mt-4 flex-wrap">
            @foreach($tabsCategoria as $tab)
                <button
                    type="button"
                    wire:click="$set('categoria', '{{ $tab['id'] }}')"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors {{ $categoria === $tab['id'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                    aria-pressed="{{ $categoria === $tab['id'] ? 'true' : 'false' }}"
                >
                    {{ $tab['label'] }}
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs {{ $categoria === $tab['id'] ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                        {{ $tab['count'] }}
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Indicador de filtros activos --}}
        @if($busqueda !== '' || $categoria !== 'todos')
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 pt-4 border-t border-gray-100">
                <x-admin-icon name="funnel" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <span>
                    Mostrando <strong class="text-gray-900">{{ $usuarios->total() }}</strong>
                    {{ $usuarios->total() === 1 ? 'usuario' : 'usuarios' }} con filtros activos
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
    {{-- LISTADO DE USUARIOS                                                 --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($usuarios->isNotEmpty())

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- CONTROL: resumen de resultados + "registros por página"        --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-gray-500">
                Mostrando <strong class="text-gray-900">{{ $usuarios->firstItem() }}</strong>
                a <strong class="text-gray-900">{{ $usuarios->lastItem() }}</strong>
                de <strong class="text-gray-900">{{ $usuarios->total() }}</strong> usuarios
            </p>

            <div class="flex items-center gap-2">
                <label for="por-pagina-usuarios" class="text-sm text-gray-500 whitespace-nowrap">Registros por página</label>
                <div class="relative">
                    <select
                        id="por-pagina-usuarios"
                        wire:model.live="perPage"
                        class="appearance-none px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-8"
                        aria-label="Registros por página"
                    >
                        @foreach($perPageOptions as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    <x-admin-icon
                        name="chevron-down"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    />
                </div>
            </div>
        </div>

        {{-- GRID DE TARJETAS DE USUARIOS — mismo patrón que Gestión de Eventos --}}
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" wire:loading.class="opacity-60">
            @foreach($usuarios as $usuario)
                <article
                    wire:key="usuario-tarjeta-{{ $usuario->id }}"
                    class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col"
                    aria-label="Usuario: {{ $usuario->name }}"
                >
                    {{-- Banner superior con el badge de estado --}}
                    <div class="relative h-16 bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 flex-shrink-0">
                        <div class="absolute top-2.5 right-2.5">
                            @include('livewire.admin.partials.user-status-badge', ['active' => $usuario->active, 'statusLabel' => $usuario->statusLabel])
                        </div>
                    </div>

                    {{--
                        relative + z-10: el banner de arriba también es "relative"
                        (para anclar su badge absolute) — sin esto, el banner se
                        pintaría encima del avatar pese a venir antes en el DOM,
                        porque los elementos posicionados siempre se pintan
                        después que el contenido normal del mismo contenedor.
                    --}}
                    <div class="relative z-10 px-4 pb-4 flex flex-col flex-1">

                        {{-- Avatar superpuesto al banner, centrado --}}
                        <div class="flex justify-center -mt-8 mb-3">
                            @if($usuario->avatarUrl)
                                <img
                                    src="{{ $usuario->avatarUrl }}"
                                    alt="Foto de {{ $usuario->name }}"
                                    loading="lazy"
                                    decoding="async"
                                    class="w-16 h-16 rounded-full object-cover ring-4 ring-white"
                                >
                            @else
                                <div class="w-16 h-16 rounded-full bg-blue-600 ring-4 ring-white flex items-center justify-center">
                                    <span class="text-white text-lg font-semibold">{{ $usuario->initials }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Nombre, correo y rol, centrados --}}
                        <div class="text-center mb-3">
                            <h3 class="text-sm font-semibold text-gray-900 truncate" title="{{ $usuario->name }}">
                                {{ $usuario->name }}
                            </h3>
                            <p class="text-xs text-gray-500 truncate" title="{{ $usuario->email }}">
                                {{ $usuario->email }}
                            </p>
                            <div class="flex justify-center mt-2">
                                @include('livewire.admin.partials.user-role-badge', ['roleKey' => $usuario->roleKey, 'roleLabel' => $usuario->roleLabel])
                            </div>
                        </div>

                        {{-- Teléfono y fecha de registro --}}
                        <div class="bg-gray-50 rounded-lg p-3 space-y-2 mb-3">
                            <div class="flex items-center gap-2 text-xs">
                                <x-admin-icon name="phone" class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" />
                                <span class="text-gray-700 truncate">{{ $usuario->phoneLabel }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <x-admin-icon name="calendar-days" class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
                                <span class="text-gray-700">Registrado: {{ $usuario->createdAtLabel }}</span>
                            </div>
                        </div>

                        {{-- Botones de acción — empujados al fondo de la tarjeta --}}
                        <div class="grid grid-cols-3 gap-1.5 mt-auto">
                            @include('livewire.admin.partials.user-row-actions', ['usuario' => $usuario])
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Paginación --}}
        @if($usuarios->hasPages())
            <div class="flex justify-center pt-2">
                {{ $usuarios->links() }}
            </div>
        @endif

    @else

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- ESTADO VACÍO                                                     --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl p-12 shadow-sm border border-gray-200 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <x-admin-icon name="users" class="w-8 h-8 text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-2">No se encontraron usuarios</h3>
            <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                @if($busqueda !== '' || $categoria !== 'todos')
                    Intenta ajustar los filtros o realiza una búsqueda diferente.
                @else
                    Aún no hay usuarios registrados.
                @endif
            </p>
            @if($busqueda !== '' || $categoria !== 'todos')
                <button
                    wire:click="limpiarFiltros"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-admin-icon name="arrow-path" class="w-4 h-4" />
                    Limpiar filtros
                </button>
            @endif
        </div>

    @endif

    {{--
        Toast de notificación: escucha el evento 'notificacion' despachado desde el
        componente Livewire. Alpine.js lo muestra 3.5 s y luego lo oculta automáticamente.
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
    {{-- MODAL DE CONFIRMACIÓN: ACTIVAR / INHABILITAR (exige contraseña)    --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarModalToggle)
        <div
            x-data="{ password: '', procesando: false }"
            wire:key="modal-toggle-estado"
            wire:click.self="cancelarModalToggle"
            wire:keydown.escape.window="cancelarModalToggle"
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
                <div class="h-1.5 w-full {{ $usuarioActivoParaToggle ? 'bg-amber-500' : 'bg-emerald-500' }}"></div>

                <div class="p-6 sm:p-7">
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center {{ $usuarioActivoParaToggle ? 'bg-amber-100' : 'bg-emerald-100' }}">
                            <x-admin-icon
                                name="{{ $usuarioActivoParaToggle ? 'no-symbol' : 'check-circle' }}"
                                class="w-7 h-7 {{ $usuarioActivoParaToggle ? 'text-amber-600' : 'text-emerald-600' }}"
                            />
                        </div>
                    </div>

                    <h2 id="modal-titulo-toggle" class="text-lg font-semibold text-gray-900 text-center mb-2 wrap-break-word">
                        {{ $usuarioActivoParaToggle ? '¿Inhabilitar' : '¿Activar' }} a {{ $usuarioNombreParaToggle }}?
                    </h2>

                    <p id="modal-desc-toggle" class="text-sm text-gray-500 text-center leading-relaxed mb-5">
                        @if($usuarioActivoParaToggle)
                            Esta cuenta dejará de poder iniciar sesión hasta que la vuelvas a activar.
                        @else
                            Esta cuenta podrá iniciar sesión normalmente de nuevo.
                        @endif
                        Por seguridad, confirma con <strong class="text-gray-700">tu propia contraseña</strong>.
                    </p>

                    {{-- Campo de contraseña del administrador autenticado (NUNCA la del usuario objetivo) --}}
                    <div class="mb-2">
                        <label for="password-confirmacion-toggle" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tu contraseña
                        </label>

                        @if($errorPassword !== '')
                            <p class="text-sm text-red-600 mb-1.5" role="alert">{{ $errorPassword }}</p>
                        @endif

                        <input
                            type="password"
                            id="password-confirmacion-toggle"
                            x-model="password"
                            autocomplete="current-password"
                            autofocus
                            placeholder="Ingresa tu contraseña actual"
                            class="w-full px-3 py-2.5 text-sm border rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-transparent transition-all {{ $errorPassword !== '' ? 'border-red-300 focus:ring-red-400' : 'border-gray-300 focus:ring-blue-500' }}"
                            aria-label="Tu contraseña"
                            aria-invalid="{{ $errorPassword !== '' ? 'true' : 'false' }}"
                        >
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row mt-6">
                        <button
                            type="button"
                            wire:click="cancelarModalToggle"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                            aria-label="Cancelar y cerrar el modal"
                        >
                            Cancelar
                        </button>

                        {{--
                            Confirmar: deshabilitado hasta escribir algo en la contraseña
                            (x-bind:disabled) y mientras la petición está en curso
                            (procesando) — ambas condiciones evaluadas en el cliente con
                            Alpine, sin depender de wire:loading para este botón.
                        --}}
                        <button
                            type="button"
                            x-bind:disabled="password.length === 0 || procesando"
                            x-on:click="
                                procesando = true;
                                $wire.ejecutarToggleEstado(password).then(() => {
                                    password = '';
                                    procesando = false;
                                });
                            "
                            :class="(password.length === 0 || procesando) ? 'opacity-50 cursor-not-allowed' : ''"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $usuarioActivoParaToggle
                                ? 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-400'
                                : 'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 focus:ring-emerald-500' }}"
                            aria-label="Confirmar"
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
    @endif

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL "ADMINISTRAR ROL" — sub-componente independiente             --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($mostrarGestorRol)
        <livewire:admin.user-role-manager
            :user-id="$usuarioIdParaRol"
            :key="'gestor-rol-' . $usuarioIdParaRol"
        />
    @endif

</div>
