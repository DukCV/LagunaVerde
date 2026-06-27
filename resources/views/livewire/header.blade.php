{{--
    ══════════════════════════════════════════════════════════════════════════
    VISTA: Cabecera de navegación principal
    Componente: App\Livewire\Header

    Variables disponibles (inyectadas por render()):
      $user  — App\Models\User|null (null si no autenticado)

    Variables del componente:
      $isMenuOpen   — bool: menú hamburguesa móvil
      $dropdownOpen — bool: dropdown del perfil de usuario
      $navLinks     — array: enlaces de navegación
      $currentPage  — string: página activa
    ══════════════════════════════════════════════════════════════════════════
--}}
<header
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300
    {{ $isMenuOpen ? 'bg-white shadow-md py-3' : 'bg-white/95 backdrop-blur-sm py-4' }}"
>
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between">

            {{-- ── Logo ─────────────────────────────────────────────────────── --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                    <img src="{{ asset('img/LOGO_1.png') }}" alt="Logo Consejo Ciudadano" class="w-12 h-12 object-contain">
                </div>
                <div>
                    <div class="text-gray-900 font-semibold text-sm leading-tight">Consejo ciudadano</div>
                    <div class="text-xs text-gray-500 leading-tight">Cuidado, Conservación y Protección de la Laguna</div>
                </div>
            </div>

            {{-- ── Navegación de escritorio ────────────────────────────────── --}}
            <nav class="hidden lg:flex items-center gap-8" role="navigation" aria-label="Menú principal">
                @foreach($navLinks as $link)
                    <button
                        wire:click="navigate('{{ $link['page'] }}'{{ isset($link['hash']) ? ", '{$link['hash']}'" : '' }})"
                        class="text-gray-700 hover:text-blue-600 transition-colors text-sm font-medium
                               {{ $currentPage === $link['page'] ? 'text-blue-600 font-semibold' : '' }}"
                        aria-current="{{ $currentPage === $link['page'] ? 'page' : 'false' }}"
                    >
                        {{ $link['label'] }}
                    </button>
                @endforeach
            </nav>

            {{-- ── Zona CTA / Perfil (escritorio) ────────────────────────── --}}
            <div class="hidden lg:flex items-center gap-3">

                @auth
                    {{-- ────────────────────────────────────────────────────────
                         USUARIO AUTENTICADO: Avatar + nombre + dropdown
                         ──────────────────────────────────────────────────── --}}
                    <div class="user-dropdown-wrapper" wire:click.outside="closeDropdown">

                        {{-- Botón que activa el dropdown --}}
                        <button
                            wire:click="toggleDropdown"
                            id="user-menu-button"
                            class="user-menu-trigger"
                            aria-haspopup="true"
                            aria-expanded="{{ $dropdownOpen ? 'true' : 'false' }}"
                            aria-controls="user-dropdown-menu"
                        >
                            {{-- Avatar: foto o iniciales como fallback --}}
                            @if($user?->profilePhotoUrl())
                                {{-- Si el usuario tiene foto de perfil --}}
                                <img
                                    src="{{ $user->profilePhotoUrl() }}"
                                    alt="Foto de perfil de {{ $user->name }}"
                                    class="user-avatar-photo"
                                    loading="lazy"
                                >
                            @else
                                {{-- Fallback: badge circular con iniciales --}}
                                <span class="user-avatar-initials" aria-hidden="true">
                                    {{ $user ? $user->getInitials() : '?' }}
                                </span>
                            @endif

                            {{-- Nombre del usuario (truncado en pantallas medianas) --}}
                            <span class="user-name-label">
                                {{ $user ? Str::limit($user->name, 20) : '' }}
                            </span>

                            {{-- Chevron indicador del dropdown --}}
                            <svg
                                class="user-chevron {{ $dropdownOpen ? 'rotate-180' : '' }}"
                                xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>

                        {{-- ── Dropdown del perfil ────────────────────────── --}}
                        @if($dropdownOpen)
                            <div
                                id="user-dropdown-menu"
                                class="user-dropdown-menu dropdown-scale-in"
                                role="menu"
                                aria-orientation="vertical"
                                aria-labelledby="user-menu-button"
                            >
                                {{-- Información del usuario (no interactivo) --}}
                                <div class="dropdown-user-info">
                                    <p class="dropdown-user-name">{{ $user?->name }}</p>
                                    <p class="dropdown-user-email">{{ $user?->email }}</p>
                                </div>

                                <div class="dropdown-divider" role="separator"></div>

                                {{-- Mi Perfil --}}
                                <a
                                    href="#"
                                    class="dropdown-item"
                                    role="menuitem"
                                    wire:navigate
                                    @click="$wire.closeDropdown()"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/>
                                    </svg>
                                    Mi Perfil
                                </a>

                                {{-- Panel de Control — SOLO visible para Administradores --}}
                                @if($user?->isAdministrator())
                                    <a
                                        href="{{ route('admin.dashboard') }}"
                                        class="dropdown-item dropdown-item--admin"
                                        role="menuitem"
                                        @click="$wire.closeDropdown()"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                             stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                        </svg>
                                        Panel de Control
                                        {{-- Badge visual que indica acceso exclusivo --}}
                                        <span class="dropdown-admin-badge">Admin</span>
                                    </a>
                                @endif

                                <div class="dropdown-divider" role="separator"></div>

                                {{-- Cerrar sesión --}}
                                <button
                                    wire:click="logout"
                                    class="dropdown-item dropdown-item--danger"
                                    role="menuitem"
                                    type="button"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" aria-hidden="true">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16 17 21 12 16 7"/>
                                        <line x1="21" x2="9" y1="12" y2="12"/>
                                    </svg>
                                    Cerrar Sesión
                                </button>
                            </div>
                        @endif
                    </div>{{-- /.user-dropdown-wrapper --}}

                @else
                    {{-- ────────────────────────────────────────────────────────
                         USUARIO NO AUTENTICADO: Botón de login
                         ──────────────────────────────────────────────────── --}}
                    <button
                        wire:click="login"
                        id="header-login-btn"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg
                               text-gray-700 text-sm hover:bg-gray-50 hover:border-blue-400
                               transition-all duration-200 font-medium"
                    >
                        🔐 Iniciar Sesión
                    </button>

                    {{-- Botón de registro — junto al de login, oculto si hay sesión activa --}}
                    <button
                        wire:click="registrar"
                        id="header-register-btn"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 rounded-lg
                               text-white text-sm hover:bg-blue-700 shadow-sm
                               transition-all duration-200 font-medium"
                    >
                        📝 Registrarse
                    </button>
                @endauth

                {{-- Botón de donación desactivado temporalmente --}}
                {{--
                <button
                    wire:click="donate"
                    id="header-donate-btn"
                    class="flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700
                           text-white text-sm rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800
                           hover:shadow-xl transition-all duration-200 font-medium"
                >
                    ❤️ DONAR
                </button>
                --}}
            </div>{{-- /.hidden.lg:flex --}}

            {{-- ── Toggle menú hamburguesa (móvil) — visible solo en pantallas < lg ── --}}
            <button
                wire:click="toggleMenu"
                type="button"
                class="inline-flex items-center justify-center lg:hidden
                       w-10 h-10 rounded-lg text-gray-800
                       border border-gray-200 bg-gray-50
                       hover:bg-gray-100 hover:border-gray-300
                       active:scale-95 transition-all duration-150
                       focus-visible:outline-none focus-visible:ring-2
                       focus-visible:ring-blue-500 focus-visible:ring-offset-1"
                aria-expanded="{{ $isMenuOpen ? 'true' : 'false' }}"
                aria-label="{{ $isMenuOpen ? 'Cerrar menú' : 'Abrir menú' }}"
                aria-controls="mobile-nav"
            >
                @if($isMenuOpen)
                    {{-- Ícono X para cerrar --}}
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                @else
                    {{-- Ícono hamburguesa --}}
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/>
                        <line x1="4" x2="20" y1="18" y2="18"/>
                    </svg>
                @endif
            </button>
        </div>

        {{-- ── Menú móvil expandible ───────────────────────────────────────── --}}
        @if($isMenuOpen)
            <nav id="mobile-nav" class="lg:hidden mt-4 py-4 border-t border-gray-100" role="navigation" aria-label="Menú móvil">
                <div class="flex flex-col gap-1">
                    @foreach($navLinks as $link)
                        <button
                            wire:click="navigate('{{ $link['page'] }}'{{ isset($link['hash']) ? ", '{$link['hash']}'" : '' }})"
                            class="px-3 py-2.5 hover:bg-blue-50 hover:text-blue-600 rounded-lg text-left
                                   text-sm font-medium text-gray-700 transition-colors
                                   {{ $currentPage === $link['page'] ? 'bg-blue-50 text-blue-600' : '' }}"
                        >
                            {{ $link['label'] }}
                        </button>
                    @endforeach

                    <div class="border-t border-gray-100 my-2"></div>

                    @auth
                        {{-- ── Usuario autenticado en móvil ─────────────────── --}}

                        {{-- Información del usuario --}}
                        <div class="px-3 py-2 flex items-center gap-3">
                            @if($user?->profilePhotoUrl())
                                <img src="{{ $user->profilePhotoUrl() }}" alt="Foto de perfil"
                                     class="w-8 h-8 rounded-full object-cover">
                            @else
                                <span class="user-avatar-initials user-avatar-initials--sm">
                                    {{ $user ? $user->getInitials() : '?' }}
                                </span>
                            @endif
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $user?->name }}</p>
                                <p class="text-xs text-gray-500">{{ $user?->email }}</p>
                            </div>
                        </div>

                        {{-- Opciones del perfil --}}
                        <a href="#" class="px-3 py-2.5 hover:bg-gray-50 rounded-lg text-left text-sm text-gray-700
                                          flex items-center gap-2 transition-colors">
                            👤 Mi Perfil
                        </a>

                        {{-- Panel de Control solo para administradores --}}
                        @if($user?->isAdministrator())
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-2.5 hover:bg-blue-50 rounded-lg text-left text-sm
                                              text-blue-700 flex items-center gap-2 transition-colors font-medium">
                                ⚙️ Panel de Control
                            </a>
                        @endif

                        {{-- Cerrar sesión --}}
                        <button
                            wire:click="logout"
                            class="px-3 py-2.5 hover:bg-red-50 rounded-lg text-left text-sm text-red-600
                                   flex items-center gap-2 transition-colors w-full font-medium"
                            type="button"
                        >
                            🚪 Cerrar Sesión
                        </button>

                    @else
                        {{-- ── Usuario NO autenticado en móvil ─────────────── --}}
                        <button
                            wire:click="login"
                            class="px-3 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50
                                   rounded-lg flex items-center gap-2 transition-colors"
                        >
                            🔐 Iniciar Sesión
                        </button>

                        {{-- Botón de registro — debajo del de login en móvil --}}
                        <button
                            wire:click="registrar"
                            class="px-3 py-2.5 text-left text-sm text-white bg-blue-600 hover:bg-blue-700
                                   rounded-lg flex items-center gap-2 transition-colors font-medium"
                        >
                            📝 Registrarse
                        </button>
                    @endauth

                    {{-- Botón de donación desactivado temporalmente --}}
                    {{--
                    <button
                        wire:click="donate"
                        class="px-3 py-3 bg-linear-to-r from-blue-600 to-blue-700 text-white
                               rounded-lg text-sm font-medium hover:from-blue-700 hover:to-blue-800
                               transition-all mt-1"
                    >
                        ❤️ DONAR
                    </button>
                    --}}
                </div>
            </nav>
        @endif
    </div>
</header>

