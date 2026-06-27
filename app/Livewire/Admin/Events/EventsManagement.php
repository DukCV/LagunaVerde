<?php

namespace App\Livewire\Admin\Events;

use App\Services\Admin\AdminEventsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: Gestión de Eventos del Panel de Administración.
 *
 * RESPONSABILIDADES (principio de responsabilidad única):
 *  - Gestionar el estado reactivo de búsqueda, filtros y paginación.
 *  - Delegar toda obtención y transformación de datos al AdminEventsService.
 *  - Orquestar el flujo de los modales de confirmación (cancelar / eliminar).
 *
 * ALCANCE DE ESTA ITERACIÓN:
 *  - El formulario de creación/edición (AdminEventsForm) AÚN NO existe.
 *    Los botones "Nuevo evento", "Editar" y "Ver" se renderizan como
 *    marcadores visuales inactivos (sin wire:click, sin acción) — ver
 *    events-management.blade.php. Este componente no expone ningún método
 *    para esas acciones a propósito: no hay nada que ejecutar todavía.
 *
 * PAGINACIÓN CON FILTROS DINÁMICOS:
 *  Cada hook updatingX() llama a resetPage() de forma obligatoria.
 *
 * SEGURIDAD:
 *  - Rate limiting en búsqueda, apertura de modales y en las acciones de
 *    cancelar/eliminar (mitiga brute force y DoS ligero).
 *  - #[Locked] en las propiedades de los modales → el cliente no puede
 *    mutar el ID objetivo ni forzar la apertura de un modal.
 *  - El estado/título mostrado en cada modal se lee siempre desde la BD,
 *    nunca del cliente.
 *  - Toda salida usa {{ }} en Blade → escape XSS automático garantizado.
 */
class EventsManagement extends Component
{
    use WithPagination;

    // ── Vista activa: 'lista' muestra el grid, 'formulario' el EventForm ──
    public string $vista = 'lista';

    // El ID del evento a editar es #[Locked] — el cliente no puede inyectar
    // un ID distinto al que el servidor determinó en editarEvento().
    #[Locked]
    public ?int $eventoIdEdicion = null;

    // ── Propiedades reactivas de filtros ─────────────────────────────────
    public string $busqueda  = '';
    public string $estado    = 'todos';
    public string $categoria = 'todas';
    public string $orden     = 'proximos';

    // ── Estado del modal de confirmación de cancelación ──────────────────
    // #[Locked]: el cliente NUNCA puede escribir en estas propiedades;
    // solo el servidor las modifica vía confirmarCancelacion().
    #[Locked]
    public ?int   $eventoIdParaCancelar   = null;
    #[Locked]
    public bool   $mostrarModalCancelar   = false;
    #[Locked]
    public string $eventoTituloParaCancelar = '';

    // ── Estado del modal de confirmación de eliminación permanente ───────
    #[Locked]
    public ?int   $eventoIdParaEliminar     = null;
    #[Locked]
    public bool   $mostrarModalEliminar      = false;
    #[Locked]
    public string $eventoTituloParaEliminar = '';

    // ── Constantes de configuración ──────────────────────────────────────
    private const PER_PAGE         = 12;
    private const RATE_LIMIT_MAX   = 30;  // Búsqueda: max actualizaciones/ventana
    private const RATE_LIMIT_DECAY = 60;  // Búsqueda: ventana en segundos

    // Apertura de modal: límites por IP/usuario para mitigar DoS/scraping
    private const MODAL_RATE_LIMIT_IP  = 30;
    private const MODAL_RATE_LIMIT_USR = 15;

    // Cancelar: reversible solo desde el formulario futuro — límites moderados
    private const CANCEL_RATE_LIMIT_IP  = 20;
    private const CANCEL_RATE_LIMIT_USR = 10;
    private const CANCEL_RATE_DECAY     = 60;

    // Eliminar: acción irreversible — límites más estrictos que cancelar
    private const DELETE_MODAL_RATE_LIMIT_IP  = 20;
    private const DELETE_MODAL_RATE_LIMIT_USR = 10;
    private const DELETE_RATE_LIMIT_IP        = 10;
    private const DELETE_RATE_LIMIT_USR       = 5;
    private const DELETE_RATE_DECAY           = 60;

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN — resetPage() es obligatorio en cada uno
    // ════════════════════════════════════════════════════════════════════

    /**
     * Limita la búsqueda a 100 caracteres y aplica rate limiting por sesión.
     * Debounce aplicado en la vista con wire:model.live.debounce.400ms.
     */
    public function updatingBusqueda(string $value): void
    {
        $this->busqueda = mb_substr($value, 0, 100);

        $clave = 'admin-events-search:' . request()->session()->getId();

        if (RateLimiter::tooManyAttempts($clave, self::RATE_LIMIT_MAX)) {
            $this->busqueda = '';
            return;
        }

        RateLimiter::hit($clave, self::RATE_LIMIT_DECAY);
        $this->resetPage();
    }

    /** Cada cambio de estado resetea la paginación para evitar páginas vacías */
    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    /** Cada cambio de categoría resetea la paginación para evitar páginas vacías */
    public function updatingCategoria(): void
    {
        $this->resetPage();
    }

    /** Cada cambio de orden resetea la paginación para evitar páginas vacías */
    public function updatingOrden(): void
    {
        $this->resetPage();
    }

    /**
     * Limpia todos los filtros y vuelve a la primera página.
     * Usa reset() nativo de Livewire para restaurar valores iniciales de forma atómica.
     */
    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'estado', 'categoria', 'orden']);
        $this->resetPage();
    }

    // ════════════════════════════════════════════════════════════════════
    //  NAVEGACIÓN LISTA ↔ FORMULARIO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el formulario en modo creación.
     * eventoIdEdicion = null indica al EventForm que debe crear un registro nuevo.
     */
    public function crearEvento(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        $this->eventoIdEdicion = null;
        $this->vista           = 'formulario';
    }

    /**
     * Abre el formulario en modo edición pre-cargado con el evento indicado.
     *
     * SEGURIDAD:
     *  - ID validado como entero positivo antes de cambiar de vista.
     *  - #[Locked] en eventoIdEdicion garantiza que el cliente no muta el
     *    valor entre la asignación aquí y la lectura en el EventForm.
     */
    public function editarEvento(int $id): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        if ($id <= 0) {
            return;
        }

        $this->eventoIdEdicion = $id;
        $this->vista           = 'formulario';
    }

    /**
     * Escucha el evento 'cerrar-formulario-evento' despachado por EventForm.
     * Vuelve a la lista y opcionalmente resetea la paginación para reflejar
     * el nuevo o modificado registro.
     */
    #[On('cerrar-formulario-evento')]
    public function volverALista(bool $refrescar = false): void
    {
        $this->vista           = 'lista';
        $this->eventoIdEdicion = null;

        if ($refrescar) {
            $this->resetPage();
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN DE CANCELACIÓN
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal de confirmación cargando el ID y el nombre (leído desde
     * la BD) del evento objetivo. Si el evento ya está cancelado o finalizado,
     * la solicitud se rechaza en silencio — no existe transición válida.
     *
     * SEGURIDAD: mismo esquema de defensa en profundidad que NewsManagement.
     */
    public function confirmarCancelacion(int $id, AdminEventsService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        if ($id <= 0) {
            return;
        }

        $claveIpModal = 'admin-events-cancel-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        $claveUsuarioModal = 'admin-events-cancel-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::CANCEL_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::CANCEL_RATE_DECAY);

        try {
            $estadoActual = $service->getEventStatus($id);
        } catch (\Throwable) {
            return;
        }

        // Solo draft/published pueden cancelarse — transición de un solo sentido
        if (! in_array($estadoActual, ['draft', 'published'], strict: true)) {
            return;
        }

        try {
            $titulo = $service->getEventTitle($id);
        } catch (\Throwable) {
            return;
        }

        $this->eventoIdParaCancelar     = $id;
        $this->eventoTituloParaCancelar = $titulo;
        $this->mostrarModalCancelar     = true;
    }

    /**
     * Ejecuta la cancelación tras la confirmación explícita del usuario.
     * SEGURIDAD (defensa en profundidad — igual patrón que NewsManagement).
     */
    public function ejecutarCancelacion(AdminEventsService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModalCancelar();
            abort(403, 'Acceso no autorizado.');
        }

        if ($this->eventoIdParaCancelar === null) {
            $this->cancelarModalCancelar();
            return;
        }

        $claveIp = 'admin-events-cancel-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::CANCEL_RATE_LIMIT_IP)) {
            $this->cancelarModalCancelar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        $claveUsuario = 'admin-events-cancel-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::CANCEL_RATE_LIMIT_USR)) {
            $this->cancelarModalCancelar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de acciones alcanzado. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        RateLimiter::hit($claveIp,      self::CANCEL_RATE_DECAY);
        RateLimiter::hit($claveUsuario, self::CANCEL_RATE_DECAY);

        $idACancelar     = $this->eventoIdParaCancelar;
        $tituloACancelar = $this->eventoTituloParaCancelar;

        $exitoso = false;
        try {
            $service->cancelEvent($idACancelar);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            Log::error('Error al cancelar evento', [
                'id'         => $idACancelar,
                'usuario_id' => auth()->id(),
                'ip'         => request()->ip(),
                'excepcion'  => get_class($excepcion),
                'archivo'    => $excepcion->getFile() . ':' . $excepcion->getLine(),
                'error'      => $excepcion->getMessage(),
            ]);
        }

        $this->cancelarModalCancelar();

        if ($exitoso) {
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "El evento \"{$tituloACancelar}\" fue cancelado.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo cancelar el evento. Inténtalo de nuevo.');
        }
    }

    /** Cierra el modal de cancelación y limpia por completo el estado pendiente. */
    public function cancelarModalCancelar(): void
    {
        $this->eventoIdParaCancelar     = null;
        $this->mostrarModalCancelar     = false;
        $this->eventoTituloParaCancelar = '';
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN DE ELIMINACIÓN PERMANENTE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal de confirmación de eliminación cargando el ID y el
     * nombre (leído desde la BD) del evento objetivo.
     */
    public function confirmarEliminacion(int $id, AdminEventsService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        if ($id <= 0) {
            return;
        }

        $claveIpModal = 'admin-events-delete-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::DELETE_MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        $claveUsuarioModal = 'admin-events-delete-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::DELETE_MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::DELETE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::DELETE_RATE_DECAY);

        try {
            $titulo = $service->getEventTitle($id);
        } catch (\Throwable) {
            return;
        }

        $this->eventoIdParaEliminar     = $id;
        $this->eventoTituloParaEliminar = $titulo;
        $this->mostrarModalEliminar     = true;
    }

    /**
     * Ejecuta la eliminación permanente tras la confirmación explícita del usuario.
     */
    public function ejecutarEliminacion(AdminEventsService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModalEliminar();
            abort(403, 'Acceso no autorizado.');
        }

        if ($this->eventoIdParaEliminar === null) {
            $this->cancelarModalEliminar();
            return;
        }

        $claveIp = 'admin-events-delete-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::DELETE_RATE_LIMIT_IP)) {
            $this->cancelarModalEliminar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        $claveUsuario = 'admin-events-delete-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::DELETE_RATE_LIMIT_USR)) {
            $this->cancelarModalEliminar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de acciones alcanzado. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        RateLimiter::hit($claveIp,      self::DELETE_RATE_DECAY);
        RateLimiter::hit($claveUsuario, self::DELETE_RATE_DECAY);

        $idAEliminar     = $this->eventoIdParaEliminar;
        $tituloAEliminar = $this->eventoTituloParaEliminar;

        $exitoso = false;
        try {
            $service->deleteEvent($idAEliminar);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            Log::error('Error al eliminar evento de forma permanente', [
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
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "El evento \"{$tituloAEliminar}\" fue eliminado permanentemente.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo eliminar el evento. Inténtalo de nuevo.');
        }
    }

    /** Cierra el modal de eliminación y limpia por completo el estado pendiente. */
    public function cancelarModalEliminar(): void
    {
        $this->eventoIdParaEliminar     = null;
        $this->mostrarModalEliminar     = false;
        $this->eventoTituloParaEliminar = '';
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminEventsService $service)
    {
        try {
            $eventos = $service->getPaginatedEvents(
                $this->busqueda,
                $this->estado,
                $this->categoria,
                $this->orden,
                self::PER_PAGE
            );
            $totales    = $service->getCountsByStatus();
            $categorias = $service->getCategoryOptions();
            $estados    = $service->getStatusOptions();
            $ordenes    = $service->getSortOptions();

        } catch (\Throwable) {
            // Error de BD u otro fallo inesperado: estado vacío seguro.
            $eventos    = $this->emptyPaginator();
            $totales    = ['draft' => 0, 'published' => 0, 'cancelled' => 0, 'closed' => 0];
            $categorias = ['todas' => 'Todas las categorías'];
            $estados    = ['todos' => 'Todos los estados'];
            $ordenes    = ['proximos' => 'Más próximos a celebrarse'];
        }

        return view('livewire.admin.events.events-management', compact(
            'eventos',
            'totales',
            'categorias',
            'estados',
            'ordenes',
        ));
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginador vacío para el estado de error — evita condicionales en Blade
     * y mantiene la firma de tipo consistente en la vista.
     */
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
