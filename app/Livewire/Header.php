<?php

// ══════════════════════════════════════════════════════════════════════════════
//  COMPONENTE LIVEWIRE: Cabecera de navegación principal
//
//  Responsabilidades:
//    - Navegación entre páginas (rutas nombradas o estado interno)
//    - Control del menú móvil hamburguesa
//    - Apertura del modal de login (via evento a LoginModal)
//    - Estado autenticado: avatar del usuario + dropdown del perfil
//    - Cierre de sesión seguro (invalidación de sesión + regeneración de token)
//
//  Optimización N+1:
//    - Los roles se cargan con eager loading en render() para evitar
//      consultas adicionales cuando la vista accede a $user->isAdministrator()
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Header extends Component
{
    // ── Estado del menú de navegación ────────────────────────────────────────

    /** Controla la apertura/cierre del menú hamburguesa en móvil */
    public bool $isMenuOpen = false;

    /** Página activa actual (para resaltado visual en nav) */
    public string $currentPage = 'home';

    /** Controla la apertura/cierre del dropdown del perfil de usuario */
    public bool $dropdownOpen = false;

    // ── Configuración de navegación ──────────────────────────────────────────

    /** Definición de los enlaces de navegación principal */
    public array $navLinks = [
        ['label' => 'Inicio',        'page' => 'home',      'hash' => 'inicio'],
        ['label' => 'Quiénes Somos', 'page' => 'about'],
        ['label' => 'Noticias',      'page' => 'news'],
        ['label' => 'Colaboradores', 'page' => 'collaborators'],
        ['label' => 'Eventos',       'page' => 'events'],
        // Galería multimedia centralizada (gestiona filtros con #[Url] en su componente)
        ['label' => 'Multimedia',    'page' => 'multimedia'],
        ['label' => 'Contacto',      'page' => 'contact'],
    ];

    /** Páginas que tienen ruta nombrada en routes/web.php */
    private array $routedPages = ['home', 'about', 'news', 'collaborators', 'events', 'multimedia', 'contact'];

    // ── Acciones de navegación ───────────────────────────────────────────────

    /** Abre o cierra el menú hamburguesa en dispositivos móviles */
    public function toggleMenu(): void
    {
        $this->isMenuOpen = ! $this->isMenuOpen;
    }

    /**
     * Navega a la página indicada.
     * Si tiene ruta nombrada en web.php, usa redirect() con Livewire navigate.
     * De lo contrario, actualiza el estado interno para páginas sin ruta real.
     */
    public function navigate(string $page, ?string $hash = null): mixed
    {
        // Cierra el menú móvil al navegar
        $this->isMenuOpen   = false;
        $this->dropdownOpen = false;

        // Páginas con ruta real: redirección de Livewire SPA
        if (in_array($page, $this->routedPages, true)) {
            return $this->redirect(route($page), navigate: true);
        }

        // Páginas sin ruta real: actualiza estado interno
        $this->currentPage = $page;
        $this->dispatch('scroll-top');

        if ($hash) {
            $this->dispatch('scroll-to', id: $hash);
        }

        return null;
    }

    // ── Acciones de autenticación ────────────────────────────────────────────

    /**
     * Emite el evento para abrir el modal de login.
     * LoginModal escucha 'abrir-modal-login' con el atributo #[On].
     */
    public function login(): void
    {
        // Cierra el menú móvil para evitar superposición con el modal
        $this->isMenuOpen = false;

        // Notifica al componente LoginModal que debe mostrarse
        $this->dispatch('abrir-modal-login');
    }

    /**
     * Emite el evento para abrir el modal de registro.
     * RegisterModal escucha 'abrir-modal-registro' con el atributo #[On].
     */
    public function registrar(): void
    {
        $this->isMenuOpen = false;
        $this->dispatch('abrir-modal-registro');
    }

    /**
     * Cierra la sesión del usuario de forma segura.
     *
     * Pasos de seguridad:
     *  1. Auth::logout() — invalida la sesión autenticada
     *  2. session()->invalidate() — destruye todos los datos de sesión
     *  3. session()->regenerateToken() — renueva el token CSRF
     *  4. Redirige al home para evitar que la URL del dashboard quede en historial
     */
    public function logout(): void
    {
        // Cierra la sesión autenticada en el guard por defecto
        Auth::logout();

        // Destruye la sesión completa (elimina todos los datos almacenados)
        session()->invalidate();

        // Regenera el token CSRF para invalidar formularios de la sesión anterior
        session()->regenerateToken();

        // Cierra el dropdown antes de redirigir
        $this->dropdownOpen = false;

        // Redirige al home con Livewire SPA navigation
        $this->redirect(route('home'), navigate: true);
    }

    // ── Acciones del dropdown de perfil ──────────────────────────────────────

    /** Alterna la visibilidad del dropdown del menú de perfil */
    public function toggleDropdown(): void
    {
        $this->dropdownOpen = ! $this->dropdownOpen;
    }

    /** Cierra el dropdown del perfil (usado por click.away en la vista) */
    public function closeDropdown(): void
    {
        $this->dropdownOpen = false;
    }

    // ── Otros ────────────────────────────────────────────────────────────────

    /** Redirige a la sección de donaciones */
    public function donate(): mixed
    {
        return $this->navigate('donations');
    }

    // ── Renderizado ──────────────────────────────────────────────────────────

    /**
     * Renderiza el componente inyectando el usuario autenticado en la vista.
     *
     * Eager loading de roles (evita N+1):
     *   Al llamar $user->load('roles'), los roles se cargan en una sola consulta.
     *   La vista puede llamar $user->isAdministrator() sin generar más queries,
     *   porque User::hasRole() detecta la relación ya cargada.
     */
    public function render(): View
    {
        // Obtiene el usuario autenticado (null si no hay sesión activa)
        $user = Auth::user();

        // Precarga los roles si hay usuario autenticado (prevención de N+1)
        if ($user !== null) {
            $user->load('roles');
        }

        return view('livewire.header', compact('user'));
    }
}
