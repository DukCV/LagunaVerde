<?php

namespace App\Livewire\Admin\Partners;

use App\Services\Admin\AdminPartnersService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: Gestión de Socios Colaboradores del Panel de Administración.
 *
 * RESPONSABILIDADES (principio de responsabilidad única):
 *  - Gestionar el estado reactivo de búsqueda, filtros y paginación.
 *  - Delegar toda obtención y transformación de datos al AdminPartnersService.
 *  - Orquestar el flujo de los modales de confirmación de toggle y eliminación.
 *
 * Mismo patrón arquitectónico y de seguridad que App\Livewire\Admin\NewsManagement
 * (ver esa clase para el razonamiento extendido de cada decisión).
 *
 * SEGURIDAD:
 *  - Rate limiting en búsqueda, apertura de modal y en toggle/eliminación.
 *  - #[Locked] en todas las propiedades de los modales → el cliente no puede
 *    mutarlas; solo el servidor las puebla tras leer el estado real desde la BD.
 *  - Verificación de autorización en cada acción sensible (defensa en profundidad).
 *  - Toda salida usa {{ }} en Blade → escape XSS automático garantizado.
 */
class PartnersManagement extends Component
{
    use WithPagination;

    // ── Vista activa: 'lista' muestra el grid, 'formulario' el PartnerForm ──
    public string $vista = 'lista';

    #[Locked]
    public ?int $socioIdEdicion = null;

    // ── Propiedades reactivas de filtros ─────────────────────────────────
    public string $busqueda = '';
    public string $tipo     = 'todos';
    public string $estado   = 'todos';
    public string $orden    = 'recientes';

    // ── Estado del modal de confirmación de toggle de visibilidad ───────
    #[Locked]
    public ?int $socioIdParaToggle = null;
    #[Locked]
    public bool $mostrarModalToggle = false;
    #[Locked]
    public bool $socioEstaActivo = false;

    // ── Estado del modal de confirmación de eliminación permanente ──────
    #[Locked]
    public ?int   $socioIdParaEliminar     = null;
    #[Locked]
    public bool   $mostrarModalEliminar    = false;
    #[Locked]
    public string $socioNombreParaEliminar = '';

    // ── Constantes de configuración ──────────────────────────────────────
    private const PER_PAGE             = 12;
    private const RATE_LIMIT_MAX       = 30;  // Búsqueda: max actualizaciones/ventana
    private const RATE_LIMIT_DECAY     = 60;
    private const MODAL_RATE_LIMIT_IP  = 30;
    private const MODAL_RATE_LIMIT_USR = 15;
    private const TOGGLE_RATE_LIMIT_IP  = 20;
    private const TOGGLE_RATE_LIMIT_USR = 10;
    private const TOGGLE_RATE_DECAY     = 60;

    private const DELETE_MODAL_RATE_LIMIT_IP  = 20;
    private const DELETE_MODAL_RATE_LIMIT_USR = 10;
    private const DELETE_RATE_LIMIT_IP        = 10;
    private const DELETE_RATE_LIMIT_USR       = 5;
    private const DELETE_RATE_DECAY           = 60;

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN — resetPage() es obligatorio en cada uno
    // ════════════════════════════════════════════════════════════════════

    /** Limita la búsqueda a 100 caracteres y aplica rate limiting por sesión. */
    public function updatingBusqueda(string $value): void
    {
        $this->busqueda = mb_substr($value, 0, 100);

        $clave = 'admin-partners-search:' . request()->session()->getId();

        if (RateLimiter::tooManyAttempts($clave, self::RATE_LIMIT_MAX)) {
            $this->busqueda = '';
            return;
        }

        RateLimiter::hit($clave, self::RATE_LIMIT_DECAY);
        $this->resetPage();
    }

    public function updatingTipo(): void
    {
        $this->resetPage();
    }

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    public function updatingOrden(): void
    {
        $this->resetPage();
    }

    // ════════════════════════════════════════════════════════════════════
    //  NAVEGACIÓN LISTA ↔ FORMULARIO
    // ════════════════════════════════════════════════════════════════════

    public function crearSocio(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        $this->socioIdEdicion = null;
        $this->vista          = 'formulario';
    }

    public function editarSocio(int $id): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        if ($id <= 0) {
            return;
        }

        $this->socioIdEdicion = $id;
        $this->vista          = 'formulario';
    }

    /**
     * Escucha el evento despachado por PartnerForm al cancelar o guardar.
     * Vuelve a la lista y opcionalmente resetea la paginación.
     */
    #[On('cerrar-formulario-socio')]
    public function volverALista(bool $refrescar = false): void
    {
        $this->vista          = 'lista';
        $this->socioIdEdicion = null;

        if ($refrescar) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'tipo', 'estado', 'orden']);
        $this->resetPage();
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN DE TOGGLE DE VISIBILIDAD
    // ════════════════════════════════════════════════════════════════════

    public function confirmarToggleEstado(int $id, AdminPartnersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        if ($id <= 0) {
            return;
        }

        $claveIpModal = 'admin-partners-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        $claveUsuarioModal = 'admin-partners-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::TOGGLE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::TOGGLE_RATE_DECAY);

        try {
            $activo = $service->getPartnerStatus($id);
        } catch (\Throwable) {
            return;
        }

        $this->socioIdParaToggle = $id;
        $this->socioEstaActivo   = $activo;
        $this->mostrarModalToggle = true;
    }

    public function ejecutarToggleEstado(AdminPartnersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModal();
            abort(403, 'Acceso no autorizado.');
        }

        if ($this->socioIdParaToggle === null) {
            $this->cancelarModal();
            return;
        }

        $claveIp = 'admin-partners-toggle-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::TOGGLE_RATE_LIMIT_IP)) {
            $this->cancelarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        $claveUsuario = 'admin-partners-toggle-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::TOGGLE_RATE_LIMIT_USR)) {
            $this->cancelarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de acciones alcanzado. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        RateLimiter::hit($claveIp,      self::TOGGLE_RATE_DECAY);
        RateLimiter::hit($claveUsuario, self::TOGGLE_RATE_DECAY);

        $accion = $this->socioEstaActivo ? 'desactivado' : 'activado';

        $exitoso = false;
        try {
            $service->toggleStatus($this->socioIdParaToggle);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            Log::error('Error al cambiar visibilidad de socio', [
                'id'               => $this->socioIdParaToggle,
                'accion_intentada' => $accion,
                'usuario_id'       => auth()->id(),
                'ip'               => request()->ip(),
                'excepcion'        => get_class($excepcion),
                'archivo'          => $excepcion->getFile() . ':' . $excepcion->getLine(),
                'error'            => $excepcion->getMessage(),
            ]);
        }

        $this->cancelarModal();

        if ($exitoso) {
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "Socio {$accion} correctamente.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo actualizar el estado. Inténtalo de nuevo.');
        }
    }

    public function cancelarModal(): void
    {
        $this->socioIdParaToggle = null;
        $this->mostrarModalToggle = false;
        $this->socioEstaActivo    = false;
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN DE ELIMINACIÓN PERMANENTE
    // ════════════════════════════════════════════════════════════════════

    public function confirmarEliminacion(int $id, AdminPartnersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        if ($id <= 0) {
            return;
        }

        $claveIpModal = 'admin-partners-delete-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::DELETE_MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        $claveUsuarioModal = 'admin-partners-delete-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::DELETE_MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::DELETE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::DELETE_RATE_DECAY);

        try {
            $nombre = $service->getPartnerName($id);
        } catch (\Throwable) {
            return;
        }

        $this->socioIdParaEliminar     = $id;
        $this->socioNombreParaEliminar = $nombre;
        $this->mostrarModalEliminar    = true;
    }

    public function ejecutarEliminacion(AdminPartnersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModalEliminar();
            abort(403, 'Acceso no autorizado.');
        }

        if ($this->socioIdParaEliminar === null) {
            $this->cancelarModalEliminar();
            return;
        }

        $claveIp = 'admin-partners-delete-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::DELETE_RATE_LIMIT_IP)) {
            $this->cancelarModalEliminar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        $claveUsuario = 'admin-partners-delete-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::DELETE_RATE_LIMIT_USR)) {
            $this->cancelarModalEliminar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de acciones alcanzado. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        RateLimiter::hit($claveIp,      self::DELETE_RATE_DECAY);
        RateLimiter::hit($claveUsuario, self::DELETE_RATE_DECAY);

        $idAEliminar     = $this->socioIdParaEliminar;
        $nombreAEliminar = $this->socioNombreParaEliminar;

        $exitoso = false;
        try {
            $service->eliminarSocio($idAEliminar);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            Log::error('Error al eliminar socio de forma permanente', [
                'id'         => $idAEliminar,
                'usuario_id' => auth()->id(),
                'ip'         => request()->ip(),
                'excepcion'  => get_class($excepcion),
                'archivo'    => $excepcion->getFile() . ':' . $excepcion->getLine(),
                'error'      => $excepcion->getMessage(),
            ]);
        }

        $this->cancelarModalEliminar();

        if ($exitoso) {
            $this->resetPage();
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "El socio \"{$nombreAEliminar}\" fue eliminado permanentemente.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo eliminar el socio. Inténtalo de nuevo.');
        }
    }

    public function cancelarModalEliminar(): void
    {
        $this->socioIdParaEliminar     = null;
        $this->mostrarModalEliminar    = false;
        $this->socioNombreParaEliminar = '';
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminPartnersService $service)
    {
        try {
            $socios       = $service->getPaginatedPartners(
                $this->busqueda,
                $this->tipo,
                $this->estado,
                $this->orden,
                self::PER_PAGE
            );
            $totalActivos = $service->getActiveCount();
            $tipos        = $service->getTypeOptions();
            $estados      = $service->getStatusOptions();

        } catch (\Throwable) {
            $socios       = $this->emptyPaginator();
            $totalActivos = 0;
            $tipos        = ['todos' => 'Todos los tipos'];
            $estados      = ['todos' => 'Todos los estados'];
        }

        return view('livewire.admin.partners.partners-management', compact(
            'socios',
            'totalActivos',
            'tipos',
            'estados',
        ));
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: self::PER_PAGE,
            currentPage: 1,
            options: ['pageName' => 'page'],
        );
    }
}
