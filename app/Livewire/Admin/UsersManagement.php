<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminUsersService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: Gestión de Usuarios del Panel de Administración.
 *
 * RESPONSABILIDADES (principio de responsabilidad única):
 *  - Gestionar el estado reactivo de búsqueda, categoría y orden.
 *  - Delegar toda obtención y transformación de datos al AdminUsersService.
 *  - Decidir SI están abiertos los modales de "activar/inhabilitar" y
 *    "Administrar rol" — la lógica de cada uno vive donde corresponde:
 *    activar/inhabilitar aquí mismo; "Administrar rol" en el sub-componente
 *    independiente UserRoleManager (ver confirmarGestionRol()).
 *
 * ALCANCE DE ESTA ITERACIÓN:
 *  Por requisito explícito de negocio, los administradores NO pueden crear
 *  usuarios ni eliminarlos desde este panel — "Borrar definitivamente" sigue
 *  siendo un marcador visual inactivo a propósito (ver
 *  users-management.blade.php). "Inhabilitar"/"Activar" y "Administrar rol"
 *  SÍ son funcionales y exigen la contraseña del administrador autenticado.
 *
 * SEGURIDAD:
 *  - Rate limiting en la búsqueda, en "registros por página", en la apertura
 *    de cada modal y, de forma especialmente estricta, en toda verificación
 *    de contraseña (mismo orden de magnitud que un intento de inicio de sesión).
 *  - Filtro de categoría, orden y "registros por página" validados contra
 *    listas blancas — el de "registros por página" además tiene un límite
 *    duro independiente en el repositorio (defensa en profundidad).
 *  - #[Locked] en todas las propiedades de ambos modales → el cliente no
 *    puede mutar el ID objetivo, el estado mostrado ni forzar su apertura.
 *  - Un administrador no puede inhabilitarse ni cambiar su propio rol
 *    (evita autobloqueo).
 *  - La contraseña enviada nunca se asigna a una propiedad pública ni se
 *    registra en logs — vive solo como variable local dentro del método
 *    que la verifica.
 *  - Toda salida usa {{ }} en Blade → escape XSS automático garantizado.
 *  - La foto de perfil se sirve únicamente vía User::profilePhotoUrl()
 *    (Storage::disk('public')->url()), nunca a partir de una ruta enviada
 *    por el cliente.
 */
class UsersManagement extends Component
{
    use WithPagination;

    // ── Propiedades reactivas de filtros ─────────────────────────────────
    public string $busqueda  = '';
    public string $categoria = 'todos';
    public string $orden     = 'recientes';

    // ── Registros por página: reactivo, ver updatedPerPage() para la
    //    validación contra la lista blanca de AdminUsersService ──────────
    public int $perPage = 25;

    // ── Estado del modal de confirmación de activar/inhabilitar ──────────
    // #[Locked]: el cliente NUNCA puede escribir en estas propiedades;
    // solo el servidor las modifica vía confirmarToggleEstado().
    #[Locked]
    public ?int   $usuarioIdParaToggle     = null;
    #[Locked]
    public bool   $mostrarModalToggle      = false;
    #[Locked]
    public string $usuarioNombreParaToggle = '';
    // Estado ANTES de ejecutar la acción: true = está activo (se va a
    // inhabilitar), false = está inactivo (se va a activar).
    #[Locked]
    public bool   $usuarioActivoParaToggle = false;
    // Mensaje de error de contraseña — el cliente puede leerlo pero nunca
    // escribirlo; solo ejecutarToggleEstado() lo asigna tras un Hash::check fallido.
    #[Locked]
    public string $errorPassword           = '';

    // ── Estado del modal "Administrar rol" ───────────────────────────────
    // El flujo de cambio de rol en sí vive en el sub-componente
    // UserRoleManager (ver confirmarGestionRol()); aquí solo se controla
    // SI el overlay está visible y PARA QUÉ usuario.
    #[Locked]
    public ?int $usuarioIdParaRol = null;
    #[Locked]
    public bool $mostrarGestorRol = false;

    // ── Constantes de configuración ──────────────────────────────────────
    private const RATE_LIMIT_MAX   = 30;  // Búsqueda: max actualizaciones/ventana
    private const RATE_LIMIT_DECAY = 60;  // Ventana en segundos (compartida)

    // "Registros por página" cambia con mucha menos frecuencia que la
    // búsqueda durante el uso normal — límite más estricto que el de búsqueda.
    private const PERPAGE_RATE_LIMIT_MAX = 15;

    // Apertura del modal de activar/inhabilitar: límites por IP/usuario
    // para mitigar DoS/scraping (mismo patrón que EventsManagement).
    private const TOGGLE_MODAL_RATE_LIMIT_IP  = 30;
    private const TOGGLE_MODAL_RATE_LIMIT_USR = 15;
    private const TOGGLE_RATE_DECAY           = 60;

    // Verificación de contraseña: límite estricto, del mismo orden de
    // magnitud que un intento de inicio de sesión — frena fuerza bruta.
    private const PASSWORD_RATE_LIMIT_IP  = 10;
    private const PASSWORD_RATE_LIMIT_USR = 5;
    private const PASSWORD_RATE_DECAY     = 60;

    // Apertura del modal "Administrar rol": mismos límites que el de
    // activar/inhabilitar — la verificación de contraseña del cambio de rol
    // en sí vive en UserRoleManager, con su propio rate limiting dedicado.
    private const ROL_MODAL_RATE_LIMIT_IP  = 30;
    private const ROL_MODAL_RATE_LIMIT_USR = 15;

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN — resetPage() es obligatorio en cada uno
    // ════════════════════════════════════════════════════════════════════

    /**
     * Limita la búsqueda a 100 caracteres y aplica rate limiting por sesión.
     * Debounce aplicado en la vista con wire:model.live.debounce.500ms.
     */
    public function updatingBusqueda(string $value): void
    {
        $this->busqueda = mb_substr($value, 0, 100);

        $clave = 'admin-users-search:' . request()->session()->getId();

        if (RateLimiter::tooManyAttempts($clave, self::RATE_LIMIT_MAX)) {
            $this->busqueda = '';
            return;
        }

        RateLimiter::hit($clave, self::RATE_LIMIT_DECAY);
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
     * Valida "registros por página" contra la lista blanca de
     * AdminUsersService, aplica rate limiting y resetea la paginación.
     *
     * NOTA TÉCNICA: se usa el hook "updated" (no "updating"). Livewire aplica
     * el valor entrante a la propiedad DESPUÉS de ejecutar "updating{Prop}"
     * usando el valor original de la petición — cualquier corrección hecha
     * ahí se sobrescribe de inmediato. El hook "updated" corre después de
     * esa asignación, así que una corrección aquí sí persiste.
     */
    public function updatedPerPage($value, AdminUsersService $service): void
    {
        $clave = 'admin-users-perpage:' . request()->session()->getId();

        if (RateLimiter::tooManyAttempts($clave, self::PERPAGE_RATE_LIMIT_MAX)) {
            $this->perPage = $service->getDefaultPerPage();
            $this->resetPage();
            return;
        }

        RateLimiter::hit($clave, self::RATE_LIMIT_DECAY);

        // Whitelist: solo se aceptan los valores expuestos en el selector.
        // (int) normaliza el valor recibido sin arriesgar un TypeError.
        if (! in_array((int) $value, $service->getPerPageOptions(), true)) {
            $this->perPage = $service->getDefaultPerPage();
        }

        $this->resetPage();
    }

    /**
     * Limpia todos los filtros y vuelve a la primera página.
     * Usa reset() nativo de Livewire para restaurar valores iniciales de forma atómica.
     * "registros por página" es una preferencia de visualización, no un
     * filtro de contenido, por lo que se conserva tras limpiar filtros.
     */
    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'categoria', 'orden']);
        $this->resetPage();
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN: ACTIVAR / INHABILITAR (exige contraseña)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal de confirmación cargando el ID, nombre y estado ACTUAL
     * del usuario objetivo, leídos siempre desde la BD — nunca se confía en
     * el estado que el cliente pudiera enviar. Ese estado decide si el
     * modal muestra "Inhabilitar" o "Activar".
     */
    public function confirmarToggleEstado(int $id, AdminUsersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        if ($id <= 0) {
            return;
        }

        // Un administrador no puede inhabilitar su propia cuenta — evita un autobloqueo.
        if ($id === auth()->id()) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No puedes cambiar el estado de tu propia cuenta.');
            return;
        }

        $claveIpModal = 'admin-users-toggle-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::TOGGLE_MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        $claveUsuarioModal = 'admin-users-toggle-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::TOGGLE_MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::TOGGLE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::TOGGLE_RATE_DECAY);

        try {
            $activo = $service->getUserStatus($id);
            $nombre = $service->getUserName($id);
        } catch (\Throwable) {
            return;
        }

        $this->usuarioIdParaToggle     = $id;
        $this->usuarioNombreParaToggle = $nombre;
        $this->usuarioActivoParaToggle = $activo;
        $this->errorPassword           = '';
        $this->mostrarModalToggle      = true;
    }

    /**
     * Verifica la contraseña del ADMINISTRADOR AUTENTICADO (nunca la del
     * usuario objetivo) y, solo si es correcta, alterna el estado activo/
     * inactivo del usuario objetivo.
     *
     * SEGURIDAD:
     *  - Rate limiting estricto por IP y por usuario, separado del límite de
     *    apertura del modal — mismo orden de magnitud que un intento de login.
     *  - El contador solo se incrementa en intentos FALLIDOS y se limpia en
     *    éxito (mismo criterio que el throttle de login de Laravel Fortify),
     *    así un administrador que acierta a la primera nunca es penalizado.
     *  - $password vive únicamente en esta variable local: nunca se asigna
     *    a una propiedad pública (no viaja de vuelta al cliente) ni se
     *    incluye en el log de errores.
     */
    public function ejecutarToggleEstado(string $password, AdminUsersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            $this->cancelarModalToggle();
            abort(403, 'Acceso no autorizado.');
        }

        if ($this->usuarioIdParaToggle === null) {
            $this->cancelarModalToggle();
            return;
        }

        $claveIp = 'admin-users-password-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::PASSWORD_RATE_LIMIT_IP)) {
            $this->cancelarModalToggle();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        $claveUsuario = 'admin-users-password-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::PASSWORD_RATE_LIMIT_USR)) {
            $this->cancelarModalToggle();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de intentos alcanzado. Espera un minuto.');
            return;
        }

        // Contraseña vacía o incorrecta: se cuenta como intento fallido y se
        // mantiene el modal abierto para que el administrador pueda reintentar.
        if ($password === '' || ! Hash::check($password, auth()->user()->password)) {
            RateLimiter::hit($claveIp,      self::PASSWORD_RATE_DECAY);
            RateLimiter::hit($claveUsuario, self::PASSWORD_RATE_DECAY);
            $this->errorPassword = 'Contraseña incorrecta.';
            return;
        }

        RateLimiter::clear($claveIp);
        RateLimiter::clear($claveUsuario);

        $idATogglear     = $this->usuarioIdParaToggle;
        $nombreATogglear = $this->usuarioNombreParaToggle;
        $accion          = $this->usuarioActivoParaToggle ? 'inhabilitado' : 'activado';

        $exitoso = false;
        try {
            $service->toggleUserStatus($idATogglear);
            $exitoso = true;
        } catch (\Throwable $excepcion) {
            Log::error('Error al cambiar el estado de un usuario', [
                'id'         => $idATogglear,
                'usuario_id' => auth()->id(),
                'ip'         => request()->ip(),
                'excepcion'  => get_class($excepcion),
                'archivo'    => $excepcion->getFile() . ':' . $excepcion->getLine(),
                'error'      => $excepcion->getMessage(),
            ]);
        }

        $this->cancelarModalToggle();

        if ($exitoso) {
            $this->dispatch('notificacion', tipo: 'exito', mensaje: "El usuario \"{$nombreATogglear}\" fue {$accion}.");
        } else {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo actualizar el estado. Inténtalo de nuevo.');
        }
    }

    /** Cierra el modal de cambio de estado y limpia por completo el estado pendiente. */
    public function cancelarModalToggle(): void
    {
        $this->usuarioIdParaToggle     = null;
        $this->mostrarModalToggle      = false;
        $this->usuarioNombreParaToggle = '';
        $this->usuarioActivoParaToggle = false;
        $this->errorPassword           = '';
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL "ADMINISTRAR ROL"
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal "Administrar rol" (UserRoleManager) para el usuario
     * indicado. Este componente padre solo decide SI se monta el
     * sub-componente; toda la lógica de rol/permisos/colaborador vive en
     * UserRoleManager (igual patrón que EventsManagement → EventForm).
     *
     * RESTRICCIÓN: una cuenta inhabilitada no puede tener su rol
     * administrado — se rechaza ANTES de montar UserRoleManager, leyendo el
     * estado actual desde la BD (nunca se confía en lo que el cliente afirme).
     */
    public function confirmarGestionRol(int $id, AdminUsersService $service): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }

        if ($id <= 0) {
            return;
        }

        // Un administrador no puede gestionar su propio rol — evita un autobloqueo.
        if ($id === auth()->id()) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No puedes administrar tu propio rol.');
            return;
        }

        $claveIpModal = 'admin-users-rol-modal-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIpModal, self::ROL_MODAL_RATE_LIMIT_IP)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
            return;
        }

        $claveUsuarioModal = 'admin-users-rol-modal-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuarioModal, self::ROL_MODAL_RATE_LIMIT_USR)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de solicitudes alcanzado. Espera un momento.');
            return;
        }

        RateLimiter::hit($claveIpModal,      self::TOGGLE_RATE_DECAY);
        RateLimiter::hit($claveUsuarioModal, self::TOGGLE_RATE_DECAY);

        try {
            $activo = $service->getUserStatus($id);
        } catch (\Throwable) {
            return;
        }

        if (! $activo) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No puedes administrar el rol de una cuenta inhabilitada.');
            return;
        }

        $this->usuarioIdParaRol = $id;
        $this->mostrarGestorRol = true;
    }

    /**
     * Escucha el evento despachado por UserRoleManager al cancelar o guardar.
     * Cierra el overlay y, si hubo cambios guardados, refresca la paginación
     * (el rol/categoría del usuario pudo haber cambiado de pestaña).
     */
    #[On('cerrar-gestor-rol')]
    public function cerrarGestorRol(bool $refrescar = false): void
    {
        $this->usuarioIdParaRol = null;
        $this->mostrarGestorRol = false;

        if ($refrescar) {
            $this->resetPage();
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminUsersService $service)
    {
        try {
            $usuarios       = $service->getPaginatedUsers(
                $this->busqueda,
                $this->categoria,
                $this->orden,
                $this->perPage
            );
            $totales        = $service->getCountsByCategory();
            $categorias     = $service->getCategoryOptions();
            $ordenes        = $service->getSortOptions();
            $perPageOptions = $service->getPerPageOptions();

        } catch (\Throwable) {
            // Error de BD u otro fallo inesperado: estado vacío seguro.
            $usuarios       = $this->emptyPaginator();
            $totales        = ['todos' => 0, 'normal' => 0, 'colaborador' => 0, 'administrador' => 0, 'inactivos' => 0];
            $categorias     = ['todos' => 'Todos'];
            $ordenes        = ['recientes' => 'Más recientes primero'];
            $perPageOptions = [10, 25, 50, 100];
        }

        return view('livewire.admin.users-management', compact(
            'usuarios',
            'totales',
            'categorias',
            'ordenes',
            'perPageOptions',
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
            perPage: $this->perPage,
            currentPage: 1,
            options: ['pageName' => 'page'],
        );
    }
}
