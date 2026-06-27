<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminNewsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: Gestión de Noticias del Panel de Administración.
 *
 * RESPONSABILIDADES (principio de responsabilidad única):
 *  - Gestionar el estado reactivo de búsqueda, filtros y paginación.
 *  - Delegar toda obtención y transformación de datos al AdminNewsService.
 *  - Orquestar el flujo del modal de confirmación para el toggle de estado.
 *
 * PAGINACIÓN CON FILTROS DINÁMICOS:
 *  Cada hook updatingX() llama a resetPage() de forma obligatoria.
 *  Previene el bug donde filtros nuevos dejan la tabla en blanco si
 *  el usuario estaba en una página mayor a las páginas disponibles.
 *
 * SEGURIDAD:
 *  - Rate limiting en búsqueda (DoS ligero), apertura de modal y en toggle (brute force/DoS).
 *  - #[Locked] en noticiaIdParaToggle, mostrarModalToggle y noticiaEstaDeshabilitada
 *    → el cliente no puede mutar ninguna propiedad del flujo del modal.
 *  - El estado actual del toggle se lee desde la BD en el servicio, nunca del cliente.
 *  - Verificación de autorización en cada acción sensible (defensa en profundidad).
 *  - Toda salida usa {{ }} en Blade → escape XSS automático garantizado.
 */
class NewsManagement extends Component
{
    use WithPagination;

    // ── Vista activa: 'lista' muestra el grid, 'formulario' el NewsForm ────
    public string $vista = 'lista';

    // El ID de la noticia a editar es #[Locked] — el cliente no puede inyectar
    // un ID distinto al que el servidor determinó en editarNoticia().
    #[Locked]
    public ?int $noticiaIdEdicion = null;

    // ── Propiedades reactivas de filtros ─────────────────────────────────
    public string $busqueda  = '';
    public string $estado    = 'todas';
    public string $categoria = 'todas';
    public string $orden     = 'recientes';

    // ── Estado del modal de confirmación de toggle ───────────────────────
    // #[Locked]: el cliente NUNCA puede escribir en estas propiedades;
    // solo el servidor las modifica vía confirmarToggleEstado().
    // Protección contra payloads adulterados que fuercen la apertura del modal.
    #[Locked]
    public ?int  $noticiaIdParaToggle      = null;
    #[Locked]
    public bool  $mostrarModalToggle       = false;
    #[Locked]
    public bool  $noticiaEstaDeshabilitada = false;

    // ── Estado del modal de confirmación de eliminación permanente ───────
    // #[Locked]: igual que en el toggle — el cliente nunca escribe aquí;
    // solo el servidor puebla estas propiedades vía confirmarEliminacion(),
    // y el título mostrado se lee desde la BD, nunca del payload del cliente.
    #[Locked]
    public ?int   $noticiaIdParaEliminar     = null;
    #[Locked]
    public bool   $mostrarModalEliminar      = false;
    #[Locked]
    public string $noticiaTituloParaEliminar = '';

    // ── Constantes de configuración ──────────────────────────────────────
    private const PER_PAGE                   = 12;
    private const RATE_LIMIT_MAX             = 30;  // Búsqueda: max actualizaciones/ventana
    private const RATE_LIMIT_DECAY           = 60;  // Búsqueda: ventana en segundos
    private const MODAL_RATE_LIMIT_IP        = 30;  // Apertura modal: max por IP/minuto (mitiga DoS)
    private const MODAL_RATE_LIMIT_USR       = 15;  // Apertura modal: max por usuario/minuto
    private const TOGGLE_RATE_LIMIT_IP       = 20;  // Toggle: max por IP/minuto (mitiga DoS)
    private const TOGGLE_RATE_LIMIT_USR      = 10;  // Toggle: max por usuario/minuto (mitiga brute force)
    private const TOGGLE_RATE_DECAY          = 60;  // Toggle: ventana en segundos

    // Eliminación: límites más estrictos que el toggle — es una acción
    // irreversible y de alto costo (cascada + borrado físico de archivos).
    private const DELETE_MODAL_RATE_LIMIT_IP  = 20;  // Apertura modal: max por IP/minuto
    private const DELETE_MODAL_RATE_LIMIT_USR = 10;  // Apertura modal: max por usuario/minuto
    private const DELETE_RATE_LIMIT_IP        = 10;  // Eliminación: max por IP/minuto (anti-DoS)
    private const DELETE_RATE_LIMIT_USR       = 5;   // Eliminación: max por usuario/minuto (anti-brute force)
    private const DELETE_RATE_DECAY           = 60;  // Eliminación: ventana en segundos

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN — resetPage() es obligatorio en cada uno
    // ════════════════════════════════════════════════════════════════════

    /**
     * Limita la búsqueda a 100 caracteres y aplica rate limiting por sesión.
     * Previene abuso de la búsqueda como vector de scraping o DoS ligero.
     * Debounce aplicado en la vista con wire:model.live.debounce.400ms.
     */
    public function updatingBusqueda(string $value): void
    {
        $this->busqueda = mb_substr($value, 0, 100);

        // Clave única por sesión — previene colisiones entre usuarios distintos
        $clave = 'admin-news-search:' . request()->session()->getId();

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

    // ════════════════════════════════════════════════════════════════════
    //  NAVEGACIÓN LISTA ↔ FORMULARIO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el formulario en modo creación.
     * noticiaIdEdicion = null indica al NewsForm que debe crear un registro nuevo.
     */
    public function crearNoticia(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        $this->noticiaIdEdicion = null;
        $this->vista            = 'formulario';
    }

    /**
     * Abre el formulario en modo edición pre-cargado con la noticia indicada.
     *
     * SEGURIDAD:
     *  - ID validado como entero positivo antes de cambiar de vista.
     *  - #[Locked] en noticiaIdEdicion garantiza que el cliente no muta el valor
     *    entre la asignación aquí y la lectura en el NewsForm.
     */
    public function editarNoticia(int $id): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        if ($id <= 0) {
            return;
        }

        $this->noticiaIdEdicion = $id;
        $this->vista            = 'formulario';
    }

    /**
     * Abre la noticia publicada en el sitio público en una nueva pestaña.
     * El UUID es validado con regex antes de redirigir para evitar open redirect.
     */
    public function verNoticia(string $uuid): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403);
        }

        // Validar formato UUID v4 antes de usarlo en una URL
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            return;
        }

        $this->dispatch('abrir-noticia-publica', url: url('/noticias/' . $uuid));
    }

    /**
     * Escucha el evento 'cerrar-formulario-noticia' despachado por NewsForm.
     * Vuelve a la lista y opcionalmente resetea la paginación para reflejar
     * el nuevo o modificado registro.
     *
     * #[On] es la forma de Livewire 4 para registrar un listener de evento.
     */
    #[On('cerrar-formulario-noticia')]
    public function volverALista(bool $refrescar = false): void
    {
        $this->vista            = 'lista';
        $this->noticiaIdEdicion = null;

        // Resetear paginación para que el artículo creado/editado aparezca
        if ($refrescar) {
            $this->resetPage();
        }
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
    //  MODAL DE CONFIRMACIÓN DE TOGGLE DE ESTADO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal de confirmación cargando el ID de la noticia objetivo.
     *
     * FIX (Bug #1 — Causa Raíz):
     *  El parámetro $status fue eliminado completamente de esta firma.
     *  Anteriormente, pasarlo como string desde wire:click causaba corrupción
     *  silenciosa del argumento al mezclar comillas simples dentro de un atributo
     *  HTML delimitado con comillas dobles. El parser de Livewire lo descartaba,
     *  dejando $noticiaIdParaToggle en null y el modal abierto sin ID objetivo.
     *
     *  Ahora el estado actual se lee directamente desde la BD en el servicio.
     *  Esto elimina la ambigüedad del parser Y endurece la seguridad: el servidor
     *  NUNCA confía en el estado enviado por el cliente.
     *
     * SEGURIDAD:
     *  - Verificación de autorización antes de cualquier operación.
     *  - Rate limiting por IP + usuario en la apertura del modal (anti-DoS).
     *  - ID validado como entero positivo antes de consultar la BD.
     *  - Estado actual leído desde BD — nunca del cliente.
     */
    public function confirmarToggleEstado(int $id, AdminNewsService $service): void
    {
        // Verificación de autorización en profundidad (defensa en capas)
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        // Rechazar IDs inválidos — cero o negativos no corresponden a registros reales
        if ($id <= 0) {
            return;
        }

        // Rate limiting por IP: mitiga apertura masiva del modal como vector DoS
        $claveIpModal = 'admin-news-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        // Rate limiting por usuario: mitiga abuso desde cuenta comprometida
        $claveUsuarioModal = 'admin-news-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,    self::TOGGLE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::TOGGLE_RATE_DECAY);

        try {
            // FIX CENTRAL: el estado se lee desde la BD — nunca del cliente
            $estadoActual = $service->getNewsStatus($id);
        } catch (\Throwable) {
            // El ID no existe en la BD — rechazar silenciosamente
            return;
        }

        // Poblar el estado del modal desde datos de servidor verificados
        $this->noticiaIdParaToggle      = $id;
        $this->noticiaEstaDeshabilitada = $estadoActual === 'disabled';
        $this->mostrarModalToggle       = true;
    }

    /**
     * Ejecuta el toggle de estado tras la confirmación explícita del usuario.
     *
     * SEGURIDAD (defensa en profundidad):
     *  1. Verificación de autorización antes de cualquier operación de BD.
     *  2. Rate limiting por IP → mitiga DoS y múltiples cuentas comprometidas.
     *  3. Rate limiting por usuario autenticado → mitiga brute force.
     *  4. #[Locked] en noticiaIdParaToggle → el cliente no puede inyectar un ID distinto.
     *  5. El nuevo estado se determina en el servicio desde el estado ACTUAL en BD,
     *     nunca a partir de datos enviados por el cliente en este método.
     *  6. Errores internos se registran en log sin filtrar detalles al cliente.
     *
     * INYECCIÓN DE DEPENDENCIA:
     *  El servicio se inyecta como parámetro del método (no via app()) para que
     *  Livewire gestione el ciclo de vida y cualquier fallo sea rastreable.
     */
    public function ejecutarToggleEstado(AdminNewsService $service): void
    {
        // Verificación de autorización antes de cualquier operación
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModal();
            abort(403, 'Acceso no autorizado.');
        }

        // Estado inválido — puede ocurrir si el modal se manipuló manualmente
        if ($this->noticiaIdParaToggle === null) {
            $this->cancelarModal();
            return;
        }

        // Rate limiting por IP: mitiga DoS desde múltiples cuentas o conexiones
        $claveIp = 'admin-news-toggle-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::TOGGLE_RATE_LIMIT_IP)) {
            $this->cancelarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        // Rate limiting por usuario: mitiga brute force desde cuenta comprometida
        $claveUsuario = 'admin-news-toggle-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::TOGGLE_RATE_LIMIT_USR)) {
            $this->cancelarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de acciones alcanzado. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        // Registrar intentos ANTES de ejecutar la operación para no saltarse el límite
        RateLimiter::hit($claveIp,      self::TOGGLE_RATE_DECAY);
        RateLimiter::hit($claveUsuario, self::TOGGLE_RATE_DECAY);

        // Capturar la acción ANTES de que cancelarModal() limpie noticiaEstaDeshabilitada
        $accion = $this->noticiaEstaDeshabilitada ? 'republicada' : 'deshabilitada';

        $exitoso = false;
        try {
            // El servicio carga el estado actual desde BD y determina el nuevo estado
            $service->toggleNewsStatus($this->noticiaIdParaToggle);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            // Registrar el error completo internamente — el cliente solo ve el mensaje genérico.
            // Contexto enriquecido: clase, archivo y línea facilitan diagnóstico sin abrir Telescope.
            Log::error('Error al cambiar estado de noticia', [
                'id'               => $this->noticiaIdParaToggle,
                'accion_intentada' => $accion,
                'usuario_id'       => auth()->id(),
                'ip'               => request()->ip(),
                'excepcion'        => get_class($excepcion),
                'archivo'          => $excepcion->getFile() . ':' . $excepcion->getLine(),
                'error'            => $excepcion->getMessage(),
            ]);
        }

        $this->cancelarModal();

        // Notificar al usuario el resultado real de la operación
        if ($exitoso) {
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "Noticia {$accion} correctamente.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo actualizar el estado. Inténtalo de nuevo.');
        }
    }

    /**
     * Cierra el modal y limpia por completo el estado de la operación pendiente.
     * Se llama tanto al cancelar como al terminar de confirmar la acción.
     */
    public function cancelarModal(): void
    {
        $this->noticiaIdParaToggle      = null;
        $this->mostrarModalToggle       = false;
        $this->noticiaEstaDeshabilitada = false;
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN DE ELIMINACIÓN PERMANENTE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal de confirmación de eliminación cargando el ID y el
     * título (leído desde la BD) de la noticia objetivo.
     *
     * SEGURIDAD (mismo esquema de defensa en profundidad que confirmarToggleEstado):
     *  - Verificación de autorización antes de cualquier operación.
     *  - Rate limiting por IP + usuario en la apertura del modal (anti-DoS).
     *  - ID validado como entero positivo antes de consultar la BD.
     *  - El título mostrado en el modal se lee SIEMPRE desde la BD —
     *    nunca se confía en texto enviado por el cliente, lo que además
     *    garantiza que {{ }} en Blade escape un valor genuino y seguro.
     */
    public function confirmarEliminacion(int $id, AdminNewsService $service): void
    {
        // Verificación de autorización en profundidad (defensa en capas)
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        // Rechazar IDs inválidos — cero o negativos no corresponden a registros reales
        if ($id <= 0) {
            return;
        }

        // Rate limiting por IP: mitiga apertura masiva del modal como vector DoS
        $claveIpModal = 'admin-news-delete-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::DELETE_MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        // Rate limiting por usuario: mitiga abuso desde cuenta comprometida
        $claveUsuarioModal = 'admin-news-delete-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::DELETE_MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::DELETE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::DELETE_RATE_DECAY);

        try {
            // El título se lee desde la BD — nunca del cliente — para que el
            // texto del modal sea siempre genuino y seguro de interpolar.
            $titulo = $service->getNewsTitle($id);
        } catch (\Throwable) {
            // El ID no existe en la BD — rechazar silenciosamente
            return;
        }

        $this->noticiaIdParaEliminar     = $id;
        $this->noticiaTituloParaEliminar = $titulo;
        $this->mostrarModalEliminar      = true;
    }

    /**
     * Ejecuta la eliminación permanente tras la confirmación explícita del usuario.
     *
     * SEGURIDAD (defensa en profundidad — igual patrón que ejecutarToggleEstado,
     * con límites más estrictos por tratarse de una acción irreversible):
     *  1. Verificación de autorización antes de cualquier operación de BD.
     *  2. Rate limiting por IP → mitiga DoS y múltiples cuentas comprometidas.
     *  3. Rate limiting por usuario autenticado → mitiga brute force.
     *  4. #[Locked] en noticiaIdParaEliminar → el cliente no puede inyectar un ID distinto.
     *  5. Toda la cascada de borrado ocurre en AdminNewsService::eliminarNoticia,
     *     envuelta en DB::transaction para garantizar atomicidad y rollback.
     *  6. Errores internos se registran en log sin filtrar detalles al cliente.
     */
    public function ejecutarEliminacion(AdminNewsService $service): void
    {
        // Verificación de autorización antes de cualquier operación
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModalEliminar();
            abort(403, 'Acceso no autorizado.');
        }

        // Estado inválido — puede ocurrir si el modal se manipuló manualmente
        if ($this->noticiaIdParaEliminar === null) {
            $this->cancelarModalEliminar();
            return;
        }

        // Rate limiting por IP: mitiga DoS desde múltiples cuentas o conexiones
        $claveIp = 'admin-news-delete-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::DELETE_RATE_LIMIT_IP)) {
            $this->cancelarModalEliminar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        // Rate limiting por usuario: mitiga brute force desde cuenta comprometida
        $claveUsuario = 'admin-news-delete-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::DELETE_RATE_LIMIT_USR)) {
            $this->cancelarModalEliminar();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de acciones alcanzado. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        // Registrar intentos ANTES de ejecutar la operación para no saltarse el límite
        RateLimiter::hit($claveIp,      self::DELETE_RATE_DECAY);
        RateLimiter::hit($claveUsuario, self::DELETE_RATE_DECAY);

        // Capturar valores ANTES de que cancelarModalEliminar() limpie el estado
        $idAEliminar     = $this->noticiaIdParaEliminar;
        $tituloAEliminar = $this->noticiaTituloParaEliminar;

        $exitoso = false;
        try {
            // Toda la cascada (comentarios, media, archivos físicos, registro)
            // ocurre aquí dentro de una única transacción atómica.
            $service->eliminarNoticia($idAEliminar);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            // Registrar el error completo internamente — el cliente solo ve el mensaje genérico.
            Log::error('Error al eliminar noticia de forma permanente', [
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
            // La noticia desapareció del listado: resetear paginación evita
            // dejar al admin viendo una página vacía si era el último ítem.
            $this->resetPage();
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "La noticia \"{$tituloAEliminar}\" fue eliminada permanentemente.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo eliminar la noticia. Inténtalo de nuevo.');
        }
    }

    /**
     * Cierra el modal de eliminación y limpia por completo el estado pendiente.
     * Se llama tanto al cancelar como al terminar de confirmar la acción.
     */
    public function cancelarModalEliminar(): void
    {
        $this->noticiaIdParaEliminar     = null;
        $this->mostrarModalEliminar      = false;
        $this->noticiaTituloParaEliminar = '';
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminNewsService $service)
    {
        try {
            $noticias        = $service->getPaginatedNews(
                $this->busqueda,
                $this->estado,
                $this->categoria,
                $this->orden,
                self::PER_PAGE
            );
            $totalPublicadas = $service->getPublishedCount();
            $categorias      = $service->getCategoryOptions();
            $estados         = $service->getStatusOptions();

        } catch (\Throwable) {
            // Error de BD u otro fallo inesperado: estado vacío seguro.
            // Los detalles del error no llegan a la vista del usuario.
            $noticias        = $this->emptyPaginator();
            $totalPublicadas = 0;
            $categorias      = ['todas' => 'Todas las categorías'];
            $estados         = ['todas' => 'Todos los estados'];
        }

        return view('livewire.admin.news-management', compact(
            'noticias',
            'totalPublicadas',
            'categorias',
            'estados',
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
