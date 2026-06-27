<?php

namespace App\Livewire\Admin\Partners;

use App\Models\Partner;
use App\Services\Admin\AdminPartnersService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Componente Livewire: Formulario de Creación/Edición de Socios Colaboradores.
 *
 * MODO DE OPERACIÓN:
 *  - 'crear': montado con socioId=null → formulario vacío.
 *  - 'editar': montado con socioId=int → pre-rellenado con datos de la BD.
 *  El modo y el ID son propiedades #[Locked]; el cliente no puede alterarlos.
 *
 * A diferencia de NewsForm, no existe un estado de "borrador": el socio
 * siempre se valida por completo al guardar — la única bandera de
 * visibilidad pública es 'activo', independiente de la validez de los datos.
 *
 * VÍNCULO CON USUARIO ("Pertenece a un usuario"):
 *  - El admin puede buscar y elegir UN usuario para vincular este socio a su
 *    cuenta. Al guardar, ese usuario pasa a tener el rol 'Colaborador'.
 *  - Si el socio YA tenía otro usuario vinculado y se reemplaza (o se quita
 *    el vínculo sin reemplazo), el usuario anterior vuelve a 'Usuario Normal'.
 *  - Toda esta sincronización ocurre dentro de la MISMA transacción que el
 *    guardado del socio — ver AdminPartnersService::guardar()/
 *    sincronizarUsuarioVinculado().
 *
 * SEGURIDAD:
 *  - autorizarAdmin() en mount() y en cada acción sensible (defensa en profundidad).
 *  - Rate limiting por IP + por usuario en la acción de guardado, y por
 *    sesión en la búsqueda de usuarios a vincular.
 *  - #[Locked] en socioId, modo y en el ID del usuario vinculado → el
 *    cliente no puede inyectar un ID ajeno ni forzar una selección sin
 *    pasar por seleccionarUsuario()/quitarUsuarioVinculado().
 *  - Validación estricta de tipo MIME real (mimes:) en la subida del logotipo.
 *  - Enlaces (sitio web y redes sociales) restringidos a esquema http(s) — bloquea
 *    payloads tipo "javascript:" que la regla 'url' nativa no rechaza por sí sola.
 *  - WithFileUploads: archivo temporal en storage/app/livewire-tmp/ hasta el guardado final.
 */
class PartnerForm extends Component
{
    use WithFileUploads;

    // ── Identidad del formulario ────────────────────────────────────────
    #[Locked]
    public string $modo = 'crear';

    #[Locked]
    public ?int $socioId = null;

    // ── Campos del formulario ───────────────────────────────────────────
    public string $nombre     = '';
    public string $tipo       = '';
    public bool   $activo     = true;
    public string $quienesSon = '';
    public string $comoApoyan = '';

    public string $sitioWeb     = '';
    public string $redInstagram = '';
    public string $redFacebook  = '';
    public string $redTwitter   = '';
    public string $redLinkedin  = '';
    public string $redYoutube   = '';

    // ── Logotipo ─────────────────────────────────────────────────────────
    public $logo = null;               // TemporaryUploadedFile o null
    public ?string $logoActualUrl = null;
    public bool $eliminarLogo = false;

    // ── Vínculo con usuario ("Pertenece a un usuario") ───────────────────
    public bool   $vincularUsuario = false;
    public string $busquedaUsuario = '';

    // Selección EN CURSO — solo se modifica vía seleccionarUsuario()/
    // quitarUsuarioVinculado(), nunca por binding directo de un input.
    #[Locked]
    public ?int $usuarioVinculadoId = null;

    // Vínculo ORIGINAL con el que se abrió el formulario (null en 'crear',
    // o si el socio no tenía usuario vinculado) — referencia inmutable para
    // que el guardado sepa a quién revertir el rol si el vínculo cambia.
    #[Locked]
    public ?int $usuarioVinculadoOriginalId = null;

    // ── Estado del UI ───────────────────────────────────────────────────
    public ?string $modalConfirmacion = null; // 'guardar' | 'cancelar'

    // ── Constantes de rate limiting ─────────────────────────────────────
    private const RL_GUARDAR_IP_MAX   = 20;
    private const RL_GUARDAR_USER_MAX = 10;
    private const RL_DECAY_SEGUNDOS   = 60;

    // Búsqueda de usuarios a vincular: mismo orden de magnitud que el resto
    // de buscadores administrativos (ver UsersManagement::updatingBusqueda()).
    private const RL_BUSQUEDA_USUARIO_MAX   = 30;
    private const RL_BUSQUEDA_USUARIO_DECAY = 60;

    // ════════════════════════════════════════════════════════════════════
    //  CICLO DE VIDA
    // ════════════════════════════════════════════════════════════════════

    public function mount(?int $socioId = null, AdminPartnersService $service): void
    {
        $this->autorizarAdmin();

        if ($socioId !== null) {
            $this->modo    = 'editar';
            $this->socioId = $socioId;
            $this->cargarDatosEdicion($socioId, $service);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    /** Alterna el flag de eliminación del logotipo actual. */
    public function toggleEliminarLogo(): void
    {
        $this->autorizarAdmin();
        $this->eliminarLogo = ! $this->eliminarLogo;

        if ($this->eliminarLogo) {
            $this->logo = null;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  VÍNCULO CON USUARIO ("Pertenece a un usuario")
    // ════════════════════════════════════════════════════════════════════

    /**
     * Limita la búsqueda a 100 caracteres (vía el repositorio) y aplica
     * rate limiting por sesión — mismo patrón que UsersManagement::updatingBusqueda().
     */
    public function updatingBusquedaUsuario(): void
    {
        $clave = 'admin-partners-user-search:' . request()->session()->getId();

        if (RateLimiter::tooManyAttempts($clave, self::RL_BUSQUEDA_USUARIO_MAX)) {
            $this->busquedaUsuario = '';
            return;
        }

        RateLimiter::hit($clave, self::RL_BUSQUEDA_USUARIO_DECAY);
    }

    /**
     * Al desactivar el interruptor "Pertenece a un usuario", se limpia
     * cualquier selección y término de búsqueda pendiente — el formulario
     * vuelve a un estado "sin vínculo" coherente con lo que se ve en pantalla.
     */
    public function updatedVincularUsuario(bool $valor): void
    {
        if (! $valor) {
            $this->usuarioVinculadoId = null;
            $this->busquedaUsuario    = '';
        }
    }

    /**
     * Selecciona un usuario de los resultados de búsqueda. El ID se
     * revalida en guardar()/AdminPartnersService — esto solo refleja la
     * elección en el formulario, sin tocar la BD todavía.
     */
    public function seleccionarUsuario(int $userId): void
    {
        $this->autorizarAdmin();

        if ($userId <= 0) {
            return;
        }

        $this->usuarioVinculadoId = $userId;
        $this->busquedaUsuario    = '';
    }

    /** Quita la selección actual — vuelve a mostrar el buscador. */
    public function quitarUsuarioVinculado(): void
    {
        $this->usuarioVinculadoId = null;
    }

    /** Abre el modal de confirmación para la acción indicada. */
    public function abrirModal(string $accion): void
    {
        $this->autorizarAdmin();

        if (! in_array($accion, ['guardar', 'cancelar'], strict: true)) {
            return;
        }

        // $this->validate() lanza ValidationException si falla; Livewire la
        // captura automáticamente, puebla $errors y detiene la ejecución
        // aquí mismo — el modal nunca llega a abrirse con datos inválidos.
        if ($accion === 'guardar') {
            $this->validate($this->reglas(), $this->mensajes());
        }

        $this->modalConfirmacion = $accion;
    }

    /** Cierra el modal de confirmación sin ejecutar ninguna acción. */
    public function cerrarModal(): void
    {
        $this->modalConfirmacion = null;
    }

    /** Descarta el formulario sin guardar y vuelve a la lista. */
    public function cancelar(): void
    {
        $this->autorizarAdmin();
        $this->cerrarModal();
        $this->dispatch('cerrar-formulario-socio');
    }

    /** Guarda el socio (creación o edición) tras la confirmación del modal. */
    public function guardar(AdminPartnersService $service): void
    {
        $this->autorizarAdmin();

        $claveIp = 'admin-partners-save-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::RL_GUARDAR_IP_MAX)) {
            $this->cerrarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento.');
            return;
        }

        $claveUser = 'admin-partners-save-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUser, self::RL_GUARDAR_USER_MAX)) {
            $this->cerrarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de guardados alcanzado. Espera un minuto.');
            return;
        }

        RateLimiter::hit($claveIp,   self::RL_DECAY_SEGUNDOS);
        RateLimiter::hit($claveUser, self::RL_DECAY_SEGUNDOS);

        $this->cerrarModal();

        // Defensa en profundidad: el botón ya validó antes de abrir el modal
        // (ver abrirModal()), pero el servidor es la fuente de verdad final.
        $this->validate($this->reglas(), $this->mensajes());

        try {
            $service->guardar(
                socioId:       $this->socioId,
                datos: [
                    'nombre'       => $this->nombre,
                    'tipo'         => $this->tipo,
                    'activo'       => $this->activo,
                    'sitioWeb'     => $this->sitioWeb,
                    'redInstagram' => $this->redInstagram,
                    'redFacebook'  => $this->redFacebook,
                    'redTwitter'   => $this->redTwitter,
                    'redLinkedin'  => $this->redLinkedin,
                    'redYoutube'   => $this->redYoutube,
                    'quienesSon'   => $this->quienesSon,
                    'comoApoyan'   => $this->comoApoyan,
                ],
                logo:          $this->logo,
                eliminarLogo:  $this->eliminarLogo,
                usuarioVinculadoAnteriorId: $this->usuarioVinculadoOriginalId,
                usuarioVinculadoNuevoId:    $this->vincularUsuario ? $this->usuarioVinculadoId : null,
            );

            $mensaje = $this->modo === 'editar'
                ? 'Socio actualizado correctamente.'
                : 'Socio creado correctamente.';

            $this->dispatch('notificacion', tipo: 'exito', mensaje: $mensaje);
            $this->dispatch('cerrar-formulario-socio', refrescar: true);

        } catch (\Throwable $e) {
            Log::error('Error al guardar socio en PartnerForm', [
                'modo'       => $this->modo,
                'socio_id'   => $this->socioId,
                'usuario_id' => auth()->id(),
                'ip'         => request()->ip(),
                'excepcion'  => get_class($e),
                'archivo'    => $e->getFile() . ':' . $e->getLine(),
                'error'      => $e->getMessage(),
            ]);

            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Error al guardar el socio. Inténtalo de nuevo.');
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminPartnersService $service): \Illuminate\View\View
    {
        // Solo se consulta la BD para lo que realmente se va a mostrar:
        // si el interruptor está apagado, ninguna de las dos consultas corre.
        $resultadosUsuarios  = [];
        $usuarioVinculadoInfo = null;

        if ($this->vincularUsuario) {
            if ($this->usuarioVinculadoId !== null) {
                $usuarioVinculadoInfo = $service->obtenerUsuarioParaVincular($this->usuarioVinculadoId);
            } else {
                $resultadosUsuarios = $service->buscarUsuariosParaVincular($this->busquedaUsuario, $this->socioId);
            }
        }

        return view('livewire.admin.partners.partner-form', [
            'tipos'                => Partner::TYPES,
            'resultadosUsuarios'   => $resultadosUsuarios,
            'usuarioVinculadoInfo' => $usuarioVinculadoInfo,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Reglas de validación completas del formulario de socios. */
    private function reglas(): array
    {
        $reglaEnlace = ['nullable', 'string', 'url', 'max:255', $this->reglaEsquemaSeguro()];

        return [
            'nombre'       => 'required|string|max:150',
            'tipo'         => ['required', Rule::in(Partner::TYPES)],
            'quienesSon'   => 'required|string|max:600',
            'comoApoyan'   => 'required|string|max:600',
            'sitioWeb'     => $reglaEnlace,
            'redInstagram' => $reglaEnlace,
            'redFacebook'  => $reglaEnlace,
            'redTwitter'   => $reglaEnlace,
            'redLinkedin'  => $reglaEnlace,
            'redYoutube'   => $reglaEnlace,
            'logo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'activo'       => 'boolean',
        ];
    }

    /** Mensajes en español para los campos obligatorios. */
    private function mensajes(): array
    {
        return [
            'nombre.required'     => 'Falta el nombre del socio.',
            'tipo.required'       => 'Falta seleccionar un tipo de organización.',
            'tipo.in'             => 'El tipo seleccionado no es válido.',
            'quienesSon.required' => 'Falta describir quién es el socio.',
            'comoApoyan.required' => 'Falta describir cómo apoya el socio.',
            '*.url'               => 'El enlace ingresado no tiene un formato válido.',
            'logo.image'          => 'El logotipo debe ser una imagen.',
            'logo.mimes'          => 'El logotipo debe ser JPG, PNG o WEBP.',
            'logo.max'            => 'El logotipo no puede superar los 2 MB.',
        ];
    }

    /**
     * Regla en línea reutilizable: bloquea cualquier esquema distinto de
     * http(s). La regla 'url' nativa de Laravel acepta "javascript:..." como
     * válido (FILTER_VALIDATE_URL no exige una autoridad "//"), por lo que
     * esta verificación adicional es imprescindible para evitar XSS vía href.
     */
    private function reglaEsquemaSeguro(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fail): void {
            if ($valor !== '' && ! preg_match('#^https?://#i', $valor)) {
                $fail('El enlace debe comenzar con http:// o https://.');
            }
        };
    }

    /** Pre-rellena todas las propiedades del componente con los datos del socio. */
    private function cargarDatosEdicion(int $id, AdminPartnersService $service): void
    {
        $datos = $service->obtenerParaEdicion($id);

        $this->nombre       = $datos['nombre'];
        $this->tipo         = $datos['tipo'];
        $this->activo       = $datos['activo'];
        $this->sitioWeb     = $datos['sitioWeb'];
        $this->redInstagram = $datos['redInstagram'];
        $this->redFacebook  = $datos['redFacebook'];
        $this->redTwitter   = $datos['redTwitter'];
        $this->redLinkedin  = $datos['redLinkedin'];
        $this->redYoutube   = $datos['redYoutube'];
        $this->quienesSon   = $datos['quienesSon'];
        $this->comoApoyan   = $datos['comoApoyan'];
        $this->logoActualUrl = $datos['logoUrl'];

        // Vínculo con usuario: el ID original se congela aquí (#[Locked],
        // nunca se vuelve a tocar) para que guardar() sepa a quién revertir
        // el rol si el vínculo cambia o se quita.
        $this->usuarioVinculadoId         = $datos['userId'];
        $this->usuarioVinculadoOriginalId = $datos['userId'];
        $this->vincularUsuario            = $datos['userId'] !== null;
    }

    /** Verifica que el usuario autenticado es administrador. */
    private function autorizarAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }
    }
}
