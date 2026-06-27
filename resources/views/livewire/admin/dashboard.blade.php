{{-- Contenedor raíz: h-screen + overflow-hidden bloquean el scroll del body --}}
<div class="h-screen overflow-hidden bg-gray-50 flex">

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- SIDEBAR / MENÚ LATERAL                                         --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Sidebar: flex-shrink-0 + h-screen lo mantiene fijo sin desplazarse --}}
    <aside
        class="{{ $sidebarAbierto ? 'w-64' : 'w-0 lg:w-20' }} h-screen bg-white border-r border-gray-200 transition-all duration-300 flex-shrink-0 overflow-hidden flex flex-col"
        aria-label="Menú de navegación del administrador"
    >
        <div class="flex flex-col flex-1 min-h-0">

            {{-- Logotipo --}}
            <div class="p-6 border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-sm font-bold">LV</span>
                    </div>
                    @if($sidebarAbierto)
                        <div class="overflow-hidden">
                            <p class="text-sm font-semibold text-gray-900 truncate">Admin Panel</p>
                            <p class="text-xs text-gray-500 truncate">Laguna Verde</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Navegación principal --}}
            <nav class="flex-1 p-4 overflow-y-auto">
                <ul class="space-y-1">
                    @php
                        $items = [
                            ['id' => 'inicio',     'label' => 'Inicio',     'icon' => 'home'],
                            ['id' => 'noticias',   'label' => 'Noticias',   'icon' => 'newspaper'],
                            ['id' => 'eventos',    'label' => 'Eventos',    'icon' => 'calendar-days'],
                            ['id' => 'multimedia', 'label' => 'Multimedia', 'icon' => 'photo'],
                            ['id' => 'socios',     'label' => 'Socios',     'icon' => 'user-group'],
                            ['id' => 'usuarios',   'label' => 'Usuarios',   'icon' => 'users'],
                            ['id' => 'mensajes',   'label' => 'Mensajes',   'icon' => 'chat-bubble-left-right'],
                            ['id' => 'perfil',     'label' => 'Mi perfil',  'icon' => 'user'],
                        ];
                    @endphp

                    @foreach($items as $item)
                        <li>
                            <button
                                wire:click="cambiarSeccion('{{ $item['id'] }}')"
                                class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ $seccionActiva === $item['id'] ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-50' }}"
                                aria-label="{{ $item['label'] }}"
                                title="{{ $item['label'] }}"
                                aria-current="{{ $seccionActiva === $item['id'] ? 'page' : 'false' }}"
                            >
                                {{-- Ícono SVG inline: hereda color del botón padre (currentColor) --}}
                                <x-admin-icon :name="$item['icon']" class="w-5 h-5 flex-shrink-0" />
                                @if($sidebarAbierto)
                                    <span class="truncate">{{ $item['label'] }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Botón Cerrar Sesión --}}
            <div class="p-4 border-t border-gray-200 flex-shrink-0">
                <button
                    onclick="fetch('/logout',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}}).then(()=>window.location.href='/')"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors"
                    aria-label="Cerrar sesión"
                    title="Cerrar sesión"
                >
                    <x-admin-icon name="arrow-right-start-on-rectangle" class="w-5 h-5 flex-shrink-0" />
                    @if($sidebarAbierto)
                        <span>Cerrar sesión</span>
                    @endif
                </button>
            </div>

        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- CONTENIDO PRINCIPAL                                            --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Columna derecha: h-screen + overflow-hidden limitan el overflow al main --}}
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

        {{-- CABECERA / HEADER --}}
        {{-- Header: flex-shrink-0 lo mantiene siempre visible, sin scroll --}}
        <header class="bg-white border-b border-gray-200 z-40 flex-shrink-0">
            <div class="px-6 py-4 flex items-center justify-between gap-4">

                {{-- Botón toggle sidebar --}}
                <button
                    wire:click="toggleSidebar"
                    class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    aria-label="{{ $sidebarAbierto ? 'Colapsar menú' : 'Expandir menú' }}"
                >
                    @if($sidebarAbierto)
                        <x-admin-icon name="x-mark" class="w-5 h-5 text-gray-700" />
                    @else
                        <x-admin-icon name="bars-3" class="w-5 h-5 text-gray-700" />
                    @endif
                </button>

                <div class="flex-1"></div>

                {{-- Acciones del encabezado (notificaciones, mensajes, perfil) --}}
                <div class="flex items-center gap-4">

                    {{-- Notificaciones --}}
                    <button class="relative p-2 hover:bg-gray-100 rounded-lg transition-colors" aria-label="Notificaciones">
                        <x-admin-icon name="bell" class="w-5 h-5 text-gray-700" />
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>

                    {{-- Mensajes --}}
                    <button
                        wire:click="cambiarSeccion('mensajes')"
                        class="relative p-2 hover:bg-gray-100 rounded-lg transition-colors"
                        aria-label="Mensajes"
                    >
                        <x-admin-icon name="envelope" class="w-5 h-5 text-gray-700" />
                        <span class="absolute top-1 right-1 w-2 h-2 bg-blue-500 rounded-full"></span>
                    </button>

                    <div class="w-px h-8 bg-gray-200"></div>

                    {{-- Perfil de usuario con dropdown (Alpine.js: no necesita round-trip) --}}
                    <div
                        x-data="{ open: false }"
                        @click.outside="open = false"
                        class="relative"
                    >
                        <button
                            @click="open = !open"
                            class="flex items-center gap-3 p-2 hover:bg-gray-100 rounded-lg transition-colors"
                            :aria-expanded="open"
                            aria-haspopup="true"
                        >
                            {{-- Foto de perfil o iniciales --}}
                            @if($usuario->profilePhotoUrl())
                                <img
                                    src="{{ $usuario->profilePhotoUrl() }}"
                                    alt="Foto de {{ $usuario->name }}"
                                    class="w-10 h-10 rounded-full object-cover"
                                >
                            @else
                                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0">
                                    <span class="text-white text-sm font-semibold">{{ $usuario->getInitials() }}</span>
                                </div>
                            @endif

                            <div class="text-left hidden sm:block">
                                <p class="text-sm font-medium text-gray-900">{{ $usuario->name }}</p>
                                <p class="text-xs text-gray-500">Administrador</p>
                            </div>

                            <x-admin-icon
                                name="chevron-down"
                                class="w-4 h-4 text-gray-500 transition-transform"
                                ::class="{ 'rotate-180': open }"
                            />
                        </button>

                        {{-- Dropdown del usuario --}}
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 top-full mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50"
                            x-cloak
                        >
                            <button
                                @click="open = false; $wire.cambiarSeccion('perfil')"
                                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                            >
                                <x-admin-icon name="user" class="w-4 h-4" />
                                <span>Mi perfil</span>
                            </button>
                            <button
                                @click="open = false"
                                onclick="window.location.href='/'"
                                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                            >
                                <x-admin-icon name="arrow-top-right-on-square" class="w-4 h-4" />
                                <span>Ver sitio público</span>
                            </button>
                            <div class="border-t border-gray-200 my-2"></div>
                            <button
                                @click="open = false"
                                onclick="fetch('/logout',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}}).then(()=>window.location.href='/')"
                                class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2"
                            >
                                <x-admin-icon name="arrow-right-start-on-rectangle" class="w-4 h-4" />
                                <span>Cerrar sesión</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        {{-- ÁREA DE CONTENIDO PRINCIPAL --}}
        {{-- Main: único punto de scroll vertical — overflow-x-hidden previene desbordamiento
             horizontal de widgets en móvil; el padding reduce en pantallas pequeñas. --}}
        <main class="flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6 admin-main-scroll">

            {{-- ── Sección: Inicio (Dashboard principal) ── --}}
            @if($seccionActiva === 'inicio')
                <div class="max-w-7xl mx-auto space-y-6">

                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 mb-1">Dashboard</h1>
                        <p class="text-gray-500">Bienvenido de nuevo, {{ $usuario->name }}</p>
                    </div>

                    {{-- Tarjetas de estadísticas con actualización automática --}}
                    <livewire:admin.widgets.stats-overview />

                    {{-- Gráfico de tráfico con filtros de rango --}}
                    <livewire:admin.widgets.traffic-chart />

                    {{-- Widgets de noticias y eventos en dos columnas --}}
                    <div class="grid lg:grid-cols-2 gap-6">
                        <livewire:admin.widgets.news-widget />
                        <livewire:admin.widgets.events-widget />
                    </div>

                    {{-- Socios (2/3 ancho) y Mensajes (1/3 ancho) --}}
                    <div class="grid lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2">
                            <livewire:admin.widgets.partners-widget />
                        </div>
                        <livewire:admin.widgets.messages-widget />
                    </div>

                </div>

            {{-- ── Sección: Gestión de Noticias ── --}}
            @elseif($seccionActiva === 'noticias')
                <div class="max-w-7xl mx-auto">
                    <livewire:admin.news-management />
                </div>

            {{-- ── Sección: Gestión de Eventos ── --}}
            @elseif($seccionActiva === 'eventos')
                <div class="max-w-7xl mx-auto">
                    <livewire:admin.events.events-management />
                </div>

            {{-- ── Sección: Gestión de Socios Colaboradores ── --}}
            @elseif($seccionActiva === 'socios')
                <div class="max-w-7xl mx-auto">
                    <livewire:admin.partners.partners-management />
                </div>

            {{-- ── Sección: Gestión de Usuarios ── --}}
            @elseif($seccionActiva === 'usuarios')
                <div class="max-w-7xl mx-auto">
                    <livewire:admin.users-management />
                </div>

            {{-- ── Sección: Mi Perfil ── --}}
            @elseif($seccionActiva === 'perfil')
                <div class="max-w-7xl mx-auto">
                    <livewire:admin.my-profile />
                </div>

            @else
                {{-- ── Secciones en desarrollo ── --}}
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-xl p-12 shadow-sm border border-gray-200 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            @php
                                $iconoSeccion = match($seccionActiva) {
                                    'multimedia' => 'photo',
                                    'mensajes'   => 'chat-bubble-left-right',
                                    default      => 'squares-2x2',
                                };
                                $labelSeccion = match($seccionActiva) {
                                    'multimedia' => 'Multimedia',
                                    'mensajes'   => 'Mensajes',
                                    default      => ucfirst($seccionActiva),
                                };
                            @endphp
                            {{-- Ícono representativo de la sección activa --}}
                            <x-admin-icon :name="$iconoSeccion" class="w-8 h-8 text-gray-400" />
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Sección: {{ $labelSeccion }}</h2>
                        <p class="text-gray-500">Esta sección está en desarrollo. Los componentes se implementarán próximamente.</p>
                    </div>
                </div>
            @endif

        </main>
    </div>

</div>
