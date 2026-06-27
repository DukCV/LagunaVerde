<?php

namespace App\Livewire\Admin;

use App\Models\Partner;
use App\Services\Admin\AdminRoleService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Componente Livewire: "Administrar rol" de un usuario (Gestión de Usuarios).
 *
 * Sub-componente independiente (mismo patrón que EventForm/PartnerForm):
 * se monta SOLO cuando el modal está abierto y se cierra despachando
 * 'cerrar-gestor-rol' al padre (UsersManagement), que controla la visibilidad
 * del overlay. Esto mantiene UsersManagement enfocado en listado/filtros y
 * a este componente enfocado únicamente en el flujo de cambio de rol.
 *
 * FLUJO:
 *  - mount(): carga rol/puesto/permisos/perfil de colaborador ACTUALES desde
 *    la BD (nunca se confía en lo que el cliente afirme).
 *  - El radio de rol y la sección visible se manejan en Alpine.js, en el
 *    cliente, sin tocar el servidor — solo al confirmar() se envía el rol
 *    elegido como argumento explícito (no es una propiedad Livewire).
 *  - confirmar(): valida los campos según el rol, verifica la contraseña del
 *    ADMINISTRADOR AUTENTICADO (nunca la del usuario objetivo) y, si todo es
 *    correcto, delega el guardado atómico a AdminRoleService.
 *
 * SEGURIDAD:
 *  - autorizarAdmin() en mount() y en confirmar() (defensa en profundidad).
 *  - Un administrador no puede gestionar su propio rol (evita autobloqueo).
 *  - Una cuenta INHABILITADA no puede tener su rol administrado — se
 *    verifica en mount() (con los datos ya cargados, sin consulta extra) Y
 *    de nuevo en confirmar() con una lectura fresca de la BD, por si la
 *    cuenta se inhabilitó DESPUÉS de abrir el modal pero ANTES de confirmar.
 *  - Rate limiting estricto (IP + usuario) en la verificación de contraseña,
 *    mismo criterio que UsersManagement::ejecutarToggleEstado(): el contador
 *    solo se incrementa en intentos fallidos y se limpia en éxito.
 *  - $password vive solo como variable local de confirmar() — nunca se
 *    asigna a una propiedad pública ni se registra en logs.
 *  - Validación estricta de tipo MIME real (mimes:) en la subida del logo.
 *  - Enlaces (sitio web y redes sociales) restringidos a esquema http(s).
 *  - 'permisos' siempre se filtra de nuevo contra la lista blanca dentro de
 *    AdminRoleService antes de persistir — defensa en profundidad.
 */
class UserRoleManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $userId;

    #[Locked]
    public string $usuarioNombre = '';

    /**
     * Rol con el que se precargó el modal — usado únicamente para sembrar el
     * x-data de Alpine en la vista. Livewire no lo vuelve a leer: el rol
     * elegido se recibe como argumento explícito en confirmar().
     */
    #[Locked]
    public string $rolInicial = 'usuario';

    // ── Administrador ─────────────────────────────────────────────────────
    public string $puesto   = '';
    public array  $permisos = [];

    // ── Colaborador ────────────────────────────────────────────────────────
    public string $colabNombre       = '';
    public string $colabTipo         = '';
    public string $colabQuienesSon   = '';
    public string $colabComoApoyan   = '';
    public string $colabSitioWeb     = '';
    public string $colabRedInstagram = '';
    public string $colabRedFacebook  = '';
    public string $colabRedTwitter   = '';
    public string $colabRedLinkedin  = '';
    public string $colabRedYoutube   = '';

    public $colabLogo = null;                  // TemporaryUploadedFile o null
    public ?string $colabLogoActualUrl = null;
    public bool    $colabEliminarLogo = false;

    // ── Error de verificación de contraseña ─────────────────────────────────
    #[Locked]
    public string $errorPassword = '';

    // ── Rate limiting de la verificación de contraseña ───────────────────
    private const RL_PASSWORD_IP_MAX  = 10;
    private const RL_PASSWORD_USR_MAX = 5;
    private const RL_PASSWORD_DECAY   = 60;

    // ════════════════════════════════════════════════════════════════════
    //  CICLO DE VIDA
    // ════════════════════════════════════════════════════════════════════

    public function mount(int $userId, AdminRoleService $service): void
    {
        $this->autorizarAdmin();

        if ($userId === auth()->id()) {
            abort(403, 'No puedes administrar tu propio rol.');
        }

        $this->userId = $userId;

        $datos = $service->getRoleManagementData($userId);

        // Defensa en profundidad: el padre (UsersManagement::confirmarGestionRol)
        // ya rechazó esto antes de montar este componente, pero se vuelve a
        // verificar aquí por si el sub-componente llegara a montarse por
        // cualquier otra vía — nunca se confía en una sola capa de validación.
        if (! $datos['activo']) {
            abort(403, 'No puedes administrar el rol de una cuenta inhabilitada.');
        }

        $this->usuarioNombre = $datos['nombre'];
        $this->rolInicial    = $datos['rolActual'];
        $this->puesto        = $datos['puesto'];
        $this->permisos      = $datos['permisos'];

        if ($datos['colaborador'] !== null) {
            $perfil = $datos['colaborador'];

            $this->colabNombre       = $perfil['nombre'];
            $this->colabTipo         = $perfil['tipo'];
            $this->colabSitioWeb     = $perfil['sitioWeb'];
            $this->colabRedInstagram = $perfil['redInstagram'];
            $this->colabRedFacebook  = $perfil['redFacebook'];
            $this->colabRedTwitter   = $perfil['redTwitter'];
            $this->colabRedLinkedin  = $perfil['redLinkedin'];
            $this->colabRedYoutube   = $perfil['redYoutube'];
            $this->colabQuienesSon   = $perfil['quienesSon'];
            $this->colabComoApoyan   = $perfil['comoApoyan'];
            $this->colabLogoActualUrl = $perfil['logoUrl'];
        } else {
            // Sin perfil de socio todavía: el nombre del usuario es solo una
            // sugerencia editable (requisito: pre-rellenado pero modificable).
            $this->colabNombre = $datos['nombre'];
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES
    // ════════════════════════════════════════════════════════════════════

    /** Alterna el flag de eliminación del logotipo actual del colaborador. */
    public function toggleEliminarLogo(): void
    {
        $this->colabEliminarLogo = ! $this->colabEliminarLogo;

        if ($this->colabEliminarLogo) {
            $this->colabLogo = null;
        }
    }

    /** Cierra el modal sin guardar nada. */
    public function cancelar(): void
    {
        $this->dispatch('cerrar-gestor-rol');
    }

    /**
     * Valida los campos según el rol elegido, verifica la contraseña del
     * administrador autenticado y, si todo es correcto, persiste los
     * cambios a través de AdminRoleService.
     *
     * @param string $rol      'usuario' | 'colaborador' | 'administrador' — recibido
     *                         como argumento explícito (ver $wire.confirmar() en la vista)
     * @param string $password Contraseña del administrador autenticado — nunca
     *                         se asigna a una propiedad pública de este componente
     */
    public function confirmar(string $rol, string $password, AdminRoleService $service): void
    {
        $this->autorizarAdmin();

        // Defensa en profundidad: el usuario pudo haber sido inhabilitado por
        // otro administrador en el tiempo transcurrido desde que se abrió
        // este modal (mount()) hasta que se confirma — se vuelve a verificar
        // con una lectura fresca de la BD antes de ejecutar cualquier cambio.
        try {
            $activo = $service->getUserStatus($this->userId);
        } catch (\Throwable) {
            $this->dispatch('cerrar-gestor-rol');
            return;
        }

        if (! $activo) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No puedes administrar el rol de una cuenta inhabilitada.');
            $this->dispatch('cerrar-gestor-rol');
            return;
        }

        if (! in_array($rol, ['usuario', 'colaborador', 'administrador'], true)) {
            return;
        }

        // Validación de campos según el rol — ANTES del chequeo de
        // contraseña, para que el administrador corrija datos sin tener
        // que volver a escribir su contraseña innecesariamente.
        $reglas = match ($rol) {
            'administrador' => ['puesto' => 'required|string|max:100'],
            'colaborador'   => $this->reglasColaborador(),
            default         => [],
        };

        if ($reglas !== []) {
            $this->validate($reglas, $this->mensajes());
        }

        $claveIp = 'admin-role-password-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::RL_PASSWORD_IP_MAX)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            return;
        }

        $claveUsuario = 'admin-role-password-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUsuario, self::RL_PASSWORD_USR_MAX)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de intentos alcanzado. Espera un minuto.');
            return;
        }

        if ($password === '' || ! Hash::check($password, auth()->user()->password)) {
            RateLimiter::hit($claveIp,      self::RL_PASSWORD_DECAY);
            RateLimiter::hit($claveUsuario, self::RL_PASSWORD_DECAY);
            $this->errorPassword = 'Contraseña incorrecta.';
            return;
        }

        RateLimiter::clear($claveIp);
        RateLimiter::clear($claveUsuario);

        $datosColaborador = $rol === 'colaborador' ? [
            'nombre'       => $this->colabNombre,
            'tipo'         => $this->colabTipo,
            'activo'       => true,
            'sitioWeb'     => $this->colabSitioWeb,
            'redInstagram' => $this->colabRedInstagram,
            'redFacebook'  => $this->colabRedFacebook,
            'redTwitter'   => $this->colabRedTwitter,
            'redLinkedin'  => $this->colabRedLinkedin,
            'redYoutube'   => $this->colabRedYoutube,
            'quienesSon'   => $this->colabQuienesSon,
            'comoApoyan'   => $this->colabComoApoyan,
        ] : null;

        try {
            $service->guardarCambiosDeRol(
                userId:                  $this->userId,
                rolClave:                $rol,
                puesto:                  $this->puesto,
                permisos:                $this->permisos,
                datosColaborador:        $datosColaborador,
                logoColaborador:         $this->colabLogo,
                eliminarLogoColaborador: $this->colabEliminarLogo,
            );

            $this->dispatch('notificacion', tipo: 'exito', mensaje: "El rol de \"{$this->usuarioNombre}\" fue actualizado.");
            $this->dispatch('cerrar-gestor-rol', refrescar: true);

        } catch (\Throwable $e) {
            Log::error('Error al guardar cambios de rol', [
                'usuario_objetivo' => $this->userId,
                'usuario_admin'    => auth()->id(),
                'ip'               => request()->ip(),
                'excepcion'        => get_class($e),
                'archivo'          => $e->getFile() . ':' . $e->getLine(),
                'error'            => $e->getMessage(),
            ]);

            $this->dispatch('notificacion', tipo: 'error', mensaje: 'No se pudo actualizar el rol. Inténtalo de nuevo.');
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminRoleService $service): \Illuminate\View\View
    {
        return view('livewire.admin.user-role-manager', [
            'rolOpciones'      => $service->getRoleOptions(),
            'catalogoPermisos' => $service->getPermissionCatalog(),
            'tiposSocio'       => Partner::TYPES,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Reglas de validación del perfil de colaborador (mismas que PartnerForm). */
    private function reglasColaborador(): array
    {
        $reglaEnlace = ['nullable', 'string', 'url', 'max:255', $this->reglaEsquemaSeguro()];

        return [
            'colabNombre'       => 'required|string|max:150',
            'colabTipo'         => ['required', Rule::in(Partner::TYPES)],
            'colabQuienesSon'   => 'required|string|max:600',
            'colabComoApoyan'   => 'required|string|max:600',
            'colabSitioWeb'     => $reglaEnlace,
            'colabRedInstagram' => $reglaEnlace,
            'colabRedFacebook'  => $reglaEnlace,
            'colabRedTwitter'   => $reglaEnlace,
            'colabRedLinkedin'  => $reglaEnlace,
            'colabRedYoutube'   => $reglaEnlace,
            'colabLogo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    /** Mensajes en español para los campos obligatorios. */
    private function mensajes(): array
    {
        return [
            'puesto.required'          => 'Falta indicar el puesto del administrador.',
            'colabNombre.required'     => 'Falta el nombre del colaborador.',
            'colabTipo.required'       => 'Falta seleccionar una categoría.',
            'colabTipo.in'             => 'La categoría seleccionada no es válida.',
            'colabQuienesSon.required' => 'Falta describir quién es el colaborador.',
            'colabComoApoyan.required' => 'Falta describir cómo apoya el colaborador.',
            '*.url'                    => 'El enlace ingresado no tiene un formato válido.',
            'colabLogo.image'          => 'El logotipo debe ser una imagen.',
            'colabLogo.mimes'          => 'El logotipo debe ser JPG, PNG o WEBP.',
            'colabLogo.max'            => 'El logotipo no puede superar los 2 MB.',
        ];
    }

    /**
     * Regla en línea reutilizable: bloquea cualquier esquema distinto de
     * http(s) — la regla 'url' nativa acepta "javascript:..." como válido.
     */
    private function reglaEsquemaSeguro(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fail): void {
            if ($valor !== '' && ! preg_match('#^https?://#i', $valor)) {
                $fail('El enlace debe comenzar con http:// o https://.');
            }
        };
    }

    /** Verifica que el usuario autenticado es administrador. */
    private function autorizarAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }
    }
}
