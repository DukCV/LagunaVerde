<?php

namespace App\Livewire\Admin\Events;

use App\Livewire\Admin\Events\Form\CollaboratorsForm;
use App\Livewire\Admin\Events\Form\GeneralInfoForm;
use App\Livewire\Admin\Events\Form\LocationForm;
use App\Livewire\Admin\Events\Form\RegistrationForm;
use App\Livewire\Admin\Events\Form\ScheduleForm;
use App\Services\Admin\AdminEventsFormService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Componente Livewire: Formulario de Creación/Edición de Eventos.
 *
 * ARQUITECTURA — UN SOLO COMPONENTE + FORM OBJECTS:
 *  El estado se organiza en 4 Livewire\Form: GeneralInfoForm, ScheduleForm,
 *  LocationForm y RegistrationForm (app/Livewire/Admin/Events/Form/). Esto
 *  da modularidad real sin pagar el costo de componentes Livewire anidados
 *  (un round-trip de red por sección, validación cruzada difícil de
 *  mantener sincronizada). Las subidas de archivo permanecen en este
 *  componente principal — Livewire\Form no está diseñado para alojar
 *  propiedades de WithFileUploads.
 *
 * MODO DE OPERACIÓN:
 *  - 'crear': montado con eventId=null → formulario vacío.
 *  - 'editar': montado con eventId=int → pre-rellenado con datos de la BD.
 *
 * VALIDACIÓN POR ESTADO (mismo patrón que NewsForm):
 *  - 'draft': cada Form valida solo su rulesArchivos() (nivel permisivo) —
 *    permite guardar avances incompletos.
 *  - 'published': cada Form valida su rulesPublicacion() (nivel estricto).
 *    Si falla, NO se muestran errores en línea: se agregan todos los
 *    mensajes en $camposFaltantes y se abre el modal interactivo
 *    (mostrarModalCamposFaltantes), igual que NewsForm.
 *
 * MEDIA DEL SLIDER — LISTA UNIFICADA:
 *  $mediaItems combina medios existentes (source='existing') y archivos
 *  recién subidos (source='new', referenciados por su índice en
 *  $newSliderUploads) en una sola lista ordenable. El reordenamiento por
 *  arrastrar y soltar (Alpine.js, ver _media-slider.blade.php) solo
 *  reescribe el campo 'order' en memoria vía reordenarMedios() — la
 *  persistencia real ocurre una sola vez, al guardar.
 *
 * SEGURIDAD:
 *  - autorizarAdmin() en mount() y en cada acción sensible (defensa en profundidad).
 *  - Rate limiting por IP + usuario en las acciones de guardado Y en cada
 *    lote de subidas a la galería (updatedNewSliderUploads) — evita que una
 *    sesión comprometida sature el disco/CPU del hosting compartido.
 *  - #[Locked] en eventId, modo, mediaToDelete e indicesRechazadosSlider →
 *    el cliente no puede mutar el ID objetivo, el modo de operación, forzar
 *    borrados arbitrarios, ni reinyectar un archivo ya rechazado.
 *  - reordenarMedios() rechaza cualquier conjunto de claves que no coincida
 *    exactamente con los medios conocidos del servidor.
 *  - Validación estricta de tipos MIME reales (mimetypes:) tanto al subir
 *    (archivoEsValido(), de inmediato) como al guardar (reglasArchivos()) —
 *    misma regla en ambos puntos, vía reglasUnArchivoSlider().
 *  - El contenido HTML del editor se sanitiza en AdminEventsFormService::guardar().
 *  - Ubicación ('location'): texto libre sin geocodificación ni mapa interactivo
 *    en el formulario — sin llamadas salientes a servicios de terceros.
 *
 * GALERÍA MULTIMEDIA — VISTA PREVIA DE ARCHIVOS NUEVOS:
 *  TemporaryUploadedFile::temporaryUrl() lanza una excepción para cualquier
 *  archivo que Livewire no considere "previsualizable" (depende de la
 *  extensión detectada vía config('livewire.temporary_file_upload.preview_mimes'),
 *  y NO incluye, por ejemplo, webm/ogg). Como esto ocurre dentro de un hook
 *  del ciclo de vida, una excepción ahí rompe la petición Livewire completa
 *  — por eso ANTES, subir un video webm/ogg (formato permitido por esta
 *  misma galería) dejaba sin renderizar TODO el lote, imágenes incluidas.
 *  Por eso updatedNewSliderUploads() ya NUNCA llama a temporaryUrl() para
 *  videos: se les asigna 'url' => null y la vista (_media-slider.blade.php)
 *  ya sabía mostrar un ícono de marcador en vez de miniatura para 'video',
 *  el mismo tratamiento que ya recibían los videos existentes guardados.
 */
class EventForm extends Component
{
    use WithFileUploads;

    // ── Identidad del formulario ────────────────────────────────────────
    #[Locked]
    public string $modo = 'crear';

    #[Locked]
    public ?int $eventId = null;

    #[Locked]
    public string $estadoActual = 'draft';

    // ── Form objects (estado + validación por sección) ───────────────────
    public GeneralInfoForm $generalInfo;
    public ScheduleForm $schedule;
    public LocationForm $location;
    public RegistrationForm $registration;
    public CollaboratorsForm $collaboratorsFilter;

    // ── Imagen de portada ───────────────────────────────────────────────
    public $coverImage = null;          // TemporaryUploadedFile o null
    public ?string $coverUrl = null;    // Portada actual (modo edición)
    public bool $removeCover = false;

    // ── Slider multimedia: lista unificada existentes + nuevos ───────────
    public array $newSliderUploads = []; // array de TemporaryUploadedFile

    public array $mediaItems = []; // [{key,source,id,tmpIndex,url,tipo,nombre,order}]

    #[Locked]
    public array $mediaToDelete = []; // IDs de Media (slider) a borrar al guardar

    /**
     * Índices de $newSliderUploads ya rechazados por archivoEsValido().
     * Evita re-validar (y re-notificar el mismo rechazo) en cada re-render
     * del hook updatedNewSliderUploads(), sin necesidad de mutar el array
     * de subidas a mitad de iteración.
     */
    #[Locked]
    public array $indicesRechazadosSlider = [];

    // ── Colaboradores invitados: lista unificada de BD + externos ────────
    /**
     * Cada ítem:
     * [
     *   'key'                    => 'partner-7' | 'custom-3' | 'custom-new-1',
     *   'source'                 => 'partner' | 'custom',
     *   'partnerId'              => int|null,
     *   'name'                   => string,
     *   'logoUrl'                => string|null,   // vista previa (BD, ruta ya guardada, o temporaryUrl())
     *   'customLogoExistingPath' => string|null,    // ruta ya persistida en BD (solo 'custom', modo edición)
     *   'tmpLogoIndex'           => int|null,        // índice en $customLogoUploads (archivo nuevo aún no guardado)
     *   'participationDetails'   => string,
     *   'order'                  => int,
     * ]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $selectedCollaborators = [];

    /**
     * Logotipos nuevos de colaboradores externos, aún sin guardar — mismo
     * patrón que $newSliderUploads: el archivo permanece en memoria hasta
     * el guardado final (construirPlanDeColaboradores() resuelve cada
     * 'tmpLogoIndex' contra este array), nunca se escribe a disco antes de
     * confirmar la transacción completa del evento.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $customLogoUploads = [];

    // ── Mini-formulario "Agregar colaborador externo" ────────────────────
    public string $customCollaboratorName = '';

    public $customCollaboratorLogo = null; // TemporaryUploadedFile|null

    /**
     * Contador interno para generar claves únicas de colaboradores
     * personalizados nuevos ('custom-new-{n}') — #[Locked] porque es un
     * simple correlativo de servidor, no un dato que el cliente deba mutar.
     */
    #[Locked]
    public int $contadorColaboradorPersonalizado = 0;

    // ── Modal de confirmación ─────────────────────────────────────────────
    public ?string $modalConfirmacion = null; // 'publicar' | 'borrador' | 'cancelar'

    // Modal de campos faltantes al intentar publicar (mismo patrón que NewsForm)
    public bool $mostrarModalCamposFaltantes = false;

    /** @var array<int, string> */
    public array $camposFaltantes = [];

    /**
     * Qué campos de fecha del evento (de $this->schedule) fallaron en el
     * último intento de publicar — ['startAt'] | ['endAt'] | ['startAt',
     * 'endAt'] | []. Alimenta exclusivamente corregirFechasEvento(): sin
     * registrar esto en validarParaPublicar(), no habría forma de saber
     * cuál limpiar al cerrar el modal, porque resetErrorBag() ya borra el
     * error bag del Form en ese mismo método.
     *
     * @var array<int, string>
     */
    public array $camposFechaInvalidos = [];

    /**
     * Igual que $camposFechaInvalidos, pero para los campos de la ventana
     * de inscripción ($this->registration): ['registrationStartAt'] |
     * ['registrationEndAt'] | ambos | []. Alimenta exclusivamente
     * corregirFechasInscripcion().
     *
     * @var array<int, string>
     */
    public array $camposInscripcionInvalidos = [];

    // ── Constantes de rate limiting ───────────────────────────────────────
    private const RL_GUARDAR_IP_MAX   = 20;
    private const RL_GUARDAR_USER_MAX = 10;
    private const RL_DECAY_SEGUNDOS   = 60;

    // Límite de lotes de subida a la galería (no de bytes): cada llamada al
    // hook updatedNewSliderUploads() cuenta como un intento, sin importar
    // cuántos archivos traiga el lote — protege contra scripts que disparen
    // peticiones repetidas al endpoint de Livewire para agotar CPU/disco.
    private const RL_SUBIDA_IP_MAX   = 40;
    private const RL_SUBIDA_USER_MAX = 25;

    // Límite de acciones de colaboradores (agregar de BD o personalizado),
    // por IP y por usuario — mismo propósito que RL_SUBIDA_* para la galería.
    private const RL_COLAB_IP_MAX   = 40;
    private const RL_COLAB_USER_MAX = 25;

    // Tope de colaboradores por evento — mitiga abuso/spam y mantiene la
    // lista manejable en la interfaz (no es una limitación técnica de BD).
    private const MAX_COLABORADORES = 30;

    // ── Reglas de archivo de la galería — única fuente de verdad ──────────
    // Reutilizadas tanto al subir (archivoEsValido(), de inmediato) como al
    // guardar (reglasArchivos()) — ver reglasUnArchivoSlider().
    private const SLIDER_MAX_KB = 51200; // 50 MB
    private const SLIDER_MIMES  = 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg';

    // ════════════════════════════════════════════════════════════════════
    //  CICLO DE VIDA
    // ════════════════════════════════════════════════════════════════════

    public function mount(?int $eventId, AdminEventsFormService $service): void
    {
        $this->autorizarAdmin();

        // Valor por defecto razonable, igual que NewsForm
        $this->schedule->publishedAt = now()->format('Y-m-d');

        if ($eventId !== null) {
            $this->modo    = 'editar';
            $this->eventId = $eventId;
            $this->cargarDatosEdicion($eventId, $service);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    public function publicar(AdminEventsFormService $service): void
    {
        $this->ejecutarGuardado('published', $service);
    }

    public function guardarBorrador(AdminEventsFormService $service): void
    {
        $this->ejecutarGuardado('draft', $service);
    }

    /** Descarta el formulario sin guardar y vuelve a la lista. */
    public function cancelar(): void
    {
        $this->autorizarAdmin();
        $this->cerrarModal();
        $this->dispatch('cerrar-formulario-evento');
    }

    // ════════════════════════════════════════════════════════════════════
    //  GESTIÓN DE PORTADA
    // ════════════════════════════════════════════════════════════════════

    public function toggleEliminarPortada(): void
    {
        $this->autorizarAdmin();
        $this->removeCover = ! $this->removeCover;

        if ($this->removeCover) {
            $this->coverImage = null;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  GESTIÓN DEL SLIDER MULTIMEDIA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Hook de Livewire: se ejecuta cuando cambia el array de archivos subidos.
     * Agrega al final de $mediaItems solo los archivos que aún no tienen
     * una entrada correspondiente (evita duplicar al re-disparar el hook).
     *
     * Por cada archivo nuevo, en orden:
     *  1. Límite de tasa del lote completo (no se procesa nada si se excede).
     *  2. Validación inmediata de MIME real + tamaño (archivoEsValido()) —
     *     un archivo inválido se descarta aquí, nunca llega a $mediaItems
     *     ni puede colarse en construirPlanDeMedia() al guardar.
     *  3. Para imágenes: temporaryUrl() genera la miniatura de vista previa.
     *     Para videos: 'url' queda en null a propósito — ver el bloque
     *     "GALERÍA MULTIMEDIA" en el docblock de la clase.
     */
    public function updatedNewSliderUploads(): void
    {
        $this->autorizarAdmin();

        if (! $this->dentroDeLimiteDeSubidas()) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas subidas en poco tiempo. Espera un momento e inténtalo de nuevo.');

            return;
        }

        $indicesConocidos = collect($this->mediaItems)
            ->where('source', 'new')
            ->pluck('tmpIndex')
            ->all();

        foreach ($this->newSliderUploads as $indice => $archivo) {
            if (in_array($indice, $indicesConocidos, true)
                || in_array($indice, $this->indicesRechazadosSlider, true)
            ) {
                continue;
            }

            if (! $this->archivoEsValido($archivo)) {
                $this->indicesRechazadosSlider[] = $indice;
                $this->dispatch(
                    'notificacion',
                    tipo: 'error',
                    mensaje: "«{$archivo->getClientOriginalName()}» no tiene un formato o tamaño permitido."
                );

                continue;
            }

            $esVideo = str_starts_with($archivo->getMimeType(), 'video/');

            $this->mediaItems[] = [
                'key'      => 'new-' . $indice,
                'source'   => 'new',
                'id'       => null,
                'tmpIndex' => $indice,
                // Solo las imágenes obtienen miniatura real de Livewire —
                // ver el bloque "GALERÍA MULTIMEDIA" en el docblock de la clase.
                'url'      => $esVideo ? null : $archivo->temporaryUrl(),
                'tipo'     => $esVideo ? 'video' : 'imagen',
                'nombre'   => $archivo->getClientOriginalName(),
                'order'    => count($this->mediaItems),
            ];
        }

        $this->dispatch('media-items-updated', items: $this->mediaItems);
    }

    /**
     * Marca un medio existente del slider para borrar al guardar.
     * Verifica que el ID pertenece a esta lista antes de aceptarlo.
     */
    public function marcarMedioParaEliminar(int $id): void
    {
        $this->autorizarAdmin();

        $existe = collect($this->mediaItems)
            ->contains(fn ($item) => $item['source'] === 'existing' && $item['id'] === $id);

        if (! $existe) {
            return;
        }

        $this->mediaToDelete[] = $id;
        $this->mediaItems      = array_values(array_filter(
            $this->mediaItems,
            fn ($item) => ! ($item['source'] === 'existing' && $item['id'] === $id)
        ));

        $this->dispatch('media-items-updated', items: $this->mediaItems);
    }

    /**
     * Quita un archivo recién subido (aún no guardado) del slider.
     *
     * array_splice() desplaza los índices posteriores en $newSliderUploads,
     * así que los 'tmpIndex' de los demás ítems 'new' se recalculan aquí
     * mismo para que sigan apuntando al archivo correcto.
     */
    public function quitarMedioNuevo(int $indice): void
    {
        $this->autorizarAdmin();

        if (! array_key_exists($indice, $this->newSliderUploads)) {
            return;
        }

        array_splice($this->newSliderUploads, $indice, 1);

        $this->mediaItems = array_values(array_filter(
            $this->mediaItems,
            fn ($item) => ! ($item['source'] === 'new' && $item['tmpIndex'] === $indice)
        ));

        foreach ($this->mediaItems as $i => $item) {
            if ($item['source'] === 'new' && $item['tmpIndex'] > $indice) {
                $this->mediaItems[$i]['tmpIndex']--;
            }
        }

        $this->dispatch('media-items-updated', items: $this->mediaItems);
    }

    /**
     * Persiste el orden final del slider tras arrastrar y soltar.
     *
     * Solo reescribe 'order' en memoria — ninguna escritura a BD ocurre
     * aquí; el orden final se persiste una sola vez al guardar el evento.
     *
     * SEGURIDAD: rechaza el reordenamiento si el conjunto de claves recibido
     * no coincide EXACTAMENTE con los medios conocidos del servidor.
     */
    public function reordenarMedios(array $orderedKeys): void
    {
        $this->autorizarAdmin();

        $clavesConocidas = collect($this->mediaItems)->pluck('key')->all();

        if (count($orderedKeys) !== count($clavesConocidas)
            || array_diff($clavesConocidas, $orderedKeys) !== []
            || array_diff($orderedKeys, $clavesConocidas) !== []
        ) {
            return;
        }

        $porClave = collect($this->mediaItems)->keyBy('key');

        $this->mediaItems = collect($orderedKeys)
            ->map(function ($clave, $posicion) use ($porClave) {
                $item          = $porClave[$clave];
                $item['order'] = $posicion;

                return $item;
            })
            ->values()
            ->toArray();
    }

    // ════════════════════════════════════════════════════════════════════
    //  GESTIÓN DE COLABORADORES INVITADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Añade un socio existente (tabla 'partners') a la lista de
     * colaboradores del evento. El ID llega de un wire:click sobre la
     * grilla de resultados, así que se revalida contra la BD (existencia +
     * activo) vía el servicio antes de aceptarlo — nunca se confía en los
     * datos ya renderizados en el cliente.
     */
    public function agregarColaborador(int $partnerId, AdminEventsFormService $service): void
    {
        $this->autorizarAdmin();

        if (! $this->dentroDeLimiteDeColaboradores()) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento.');

            return;
        }

        if (count($this->selectedCollaborators) >= self::MAX_COLABORADORES) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Se alcanzó el máximo de ' . self::MAX_COLABORADORES . ' colaboradores por evento.');

            return;
        }

        $yaAgregado = collect($this->selectedCollaborators)
            ->contains(fn (array $item) => $item['source'] === 'partner' && $item['partnerId'] === $partnerId);

        if ($yaAgregado) {
            return;
        }

        $colaborador = $service->buscarColaboradorParaAgregar($partnerId);

        if ($colaborador === null) {
            return;
        }

        $this->selectedCollaborators[] = [
            'key'                    => 'partner-' . $colaborador->id,
            'source'                 => 'partner',
            'partnerId'              => $colaborador->id,
            'name'                   => $colaborador->name,
            'logoUrl'                => $colaborador->logoUrl,
            'customLogoExistingPath' => null,
            'tmpLogoIndex'           => null,
            'participationDetails'   => '',
            'order'                  => count($this->selectedCollaborators),
        ];
    }

    /**
     * Añade un colaborador externo: no existe en 'partners' y nunca se
     * escribe ahí — solo vive en $selectedCollaborators hasta el guardado,
     * momento en el que construirPlanDeColaboradores() lo traduce a una
     * fila de 'event_partner' con is_custom = true.
     */
    public function agregarColaboradorPersonalizado(): void
    {
        $this->autorizarAdmin();

        if (! $this->dentroDeLimiteDeColaboradores()) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento.');

            return;
        }

        if (count($this->selectedCollaborators) >= self::MAX_COLABORADORES) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Se alcanzó el máximo de ' . self::MAX_COLABORADORES . ' colaboradores por evento.');

            return;
        }

        $this->validate([
            'customCollaboratorName' => 'required|string|max:150',
            'customCollaboratorLogo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'customCollaboratorName.required' => 'Escribe el nombre del colaborador.',
            'customCollaboratorName.max'       => 'El nombre no puede superar los 150 caracteres.',
            'customCollaboratorLogo.image'     => 'El logotipo debe ser una imagen.',
            'customCollaboratorLogo.mimes'     => 'El logotipo debe ser JPG, PNG o WEBP.',
            'customCollaboratorLogo.max'       => 'El logotipo no puede superar los 2 MB.',
        ]);

        $tmpIndex = null;
        $logoUrl  = null;

        if ($this->customCollaboratorLogo !== null) {
            $this->customLogoUploads[] = $this->customCollaboratorLogo;
            $tmpIndex = array_key_last($this->customLogoUploads);
            $logoUrl  = $this->customCollaboratorLogo->isPreviewable()
                ? $this->customCollaboratorLogo->temporaryUrl()
                : null;
        }

        $this->contadorColaboradorPersonalizado++;

        $this->selectedCollaborators[] = [
            'key'                    => 'custom-new-' . $this->contadorColaboradorPersonalizado,
            'source'                 => 'custom',
            'partnerId'              => null,
            'name'                   => trim($this->customCollaboratorName),
            'logoUrl'                => $logoUrl,
            'customLogoExistingPath' => null,
            'tmpLogoIndex'           => $tmpIndex,
            'participationDetails'   => '',
            'order'                  => count($this->selectedCollaborators),
        ];

        $this->reset(['customCollaboratorName', 'customCollaboratorLogo']);
    }

    /** Quita un colaborador (de BD o externo) de la lista, por su clave única. */
    public function quitarColaborador(string $key): void
    {
        $this->autorizarAdmin();

        $this->selectedCollaborators = array_values(array_filter(
            $this->selectedCollaborators,
            fn (array $item) => $item['key'] !== $key
        ));

        foreach ($this->selectedCollaborators as $indice => $item) {
            $this->selectedCollaborators[$indice]['order'] = $indice;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODALES
    // ════════════════════════════════════════════════════════════════════

    public function abrirModal(string $accion): void
    {
        $this->autorizarAdmin();

        if (! in_array($accion, ['publicar', 'borrador', 'cancelar'], true)) {
            return;
        }

        if ($accion === 'publicar' && ! $this->validarParaPublicar()) {
            return;
        }

        $this->modalConfirmacion = $accion;
    }

    public function cerrarModal(): void
    {
        $this->modalConfirmacion = null;
    }

    public function cerrarModalCamposFaltantes(): void
    {
        $this->mostrarModalCamposFaltantes = false;
        $this->camposFaltantes             = [];
    }

    /**
     * Auto-corrección de la fecha de publicación al cerrar el modal de
     * campos faltantes.
     *
     * POR QUÉ: ScheduleForm exige 'publishedAt' >= hoy (after_or_equal:today).
     * Al editar un evento publicado hace tiempo, ese campo llega "vencido"
     * desde la BD (la fecha original ya quedó en el pasado) y bloquea la
     * publicación con un motivo que el admin no puede corregir manualmente
     * con un solo clic. En vez de dejarlo atascado, reiniciar la fecha a
     * hoy aquí garantiza que el siguiente intento de publicar no vuelva a
     * fallar por el mismo motivo.
     *
     * Se invoca SIEMPRE junto con cerrarModalCamposFaltantes() — nunca de
     * forma aislada — para que el dato solo cambie cuando el admin ya
     * reconoció el aviso (ver _action-panel.blade.php: botón "Cerrar",
     * clic en el fondo y tecla Escape encadenan ambos métodos en una sola
     * petición Livewire, sin round-trips adicionales).
     */
    public function corregirFechaPublicacion(): void
    {
        $this->autorizarAdmin();
        $this->schedule->publishedAt = now()->format('Y-m-d');
    }

    /**
     * Limpieza DIRIGIDA de inicio/fin al cerrar el modal de campos
     * faltantes — nunca a ciegas.
     *
     * Usa $camposFechaInvalidos (registrado en validarParaPublicar() en el
     * mismo intento de publicar que abrió el modal) para limpiar SOLO el o
     * los campos que realmente fallaron:
     *  - Solo 'startAt' inválido  → se limpia únicamente 'startAt'.
     *  - Solo 'endAt' inválido    → se limpia únicamente 'endAt'.
     *  - Ambos inválidos          → se limpian los dos.
     *  - Ninguno (el modal se abrió por otro campo, ej. portada o nombre)
     *    → no se toca ninguna fecha del evento.
     *
     * Se invoca SIEMPRE junto con cerrarModalCamposFaltantes() (ver
     * _action-panel.blade.php) — mismo motivo que corregirFechaPublicacion().
     */
    public function corregirFechasEvento(): void
    {
        $this->autorizarAdmin();

        if (in_array('startAt', $this->camposFechaInvalidos, true)) {
            $this->schedule->startAt = '';
        }

        if (in_array('endAt', $this->camposFechaInvalidos, true)) {
            $this->schedule->endAt = '';
        }

        $this->camposFechaInvalidos = [];
    }

    /**
     * Limpieza DIRIGIDA de la ventana de inscripción al cerrar el modal de
     * campos faltantes — mismo patrón que corregirFechasEvento(), pero
     * sobre $this->registration en vez de $this->schedule.
     *
     * Solo aplica de verdad cuando 'registrationEnabled' está activo
     * (Escenario A): en los Escenarios B/C, RegistrationForm::
     * sincronizarConEvento() ya calculó ambas fechas automáticamente, así
     * que nunca deberían aparecer en $camposInscripcionInvalidos — limpiar
     * "a ciegas" aquí jamás borra una fecha calculada por el sistema.
     *
     * Se invoca SIEMPRE junto con cerrarModalCamposFaltantes() (ver
     * _action-panel.blade.php) — mismo motivo que corregirFechaPublicacion().
     */
    public function corregirFechasInscripcion(): void
    {
        $this->autorizarAdmin();

        if (in_array('registrationStartAt', $this->camposInscripcionInvalidos, true)) {
            $this->registration->registrationStartAt = '';
        }

        if (in_array('registrationEndAt', $this->camposInscripcionInvalidos, true)) {
            $this->registration->registrationEndAt = '';
        }

        $this->camposInscripcionInvalidos = [];
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(AdminEventsFormService $service)
    {
        $categorias = $service->obtenerCategorias();

        // Solo se consulta la BD cuando la sección está abierta — evita una
        // consulta en cada re-render del formulario mientras está colapsada.
        $colaboradoresDisponibles = $this->collaboratorsFilter->withCollaborators
            ? $service->buscarColaboradoresDisponibles(
                search: $this->collaboratorsFilter->search,
                type: $this->collaboratorsFilter->type,
                excludeIds: $this->idsPartnersSeleccionados(),
            )
            : [];

        $tiposColaborador = $service->obtenerTiposColaborador();

        return view('livewire.admin.events.event-form', compact(
            'categorias',
            'colaboradoresDisponibles',
            'tiposColaborador',
        ));
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Núcleo del guardado: usado por publicar() y guardarBorrador().
     */
    private function ejecutarGuardado(string $estado, AdminEventsFormService $service): void
    {
        $this->autorizarAdmin();

        $claveIp = 'admin-events-save-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::RL_GUARDAR_IP_MAX)) {
            $this->cerrarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento.');

            return;
        }

        $claveUser = 'admin-events-save-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUser, self::RL_GUARDAR_USER_MAX)) {
            $this->cerrarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de guardados alcanzado. Espera un minuto.');

            return;
        }

        RateLimiter::hit($claveIp,   self::RL_DECAY_SEGUNDOS);
        RateLimiter::hit($claveUser, self::RL_DECAY_SEGUNDOS);

        $this->cerrarModal();

        // Reglas de archivo siempre se validan, en ambos niveles (draft/publish)
        $this->validate($this->reglasArchivos());

        // Los colaboradores no tienen un nivel "borrador"/"publicado"
        // propio (igual que la ventana de inscripción en RegistrationForm):
        // solo se validan cuando la sección está activa. Si está
        // desactivada, los datos que pudieran quedar en memoria de un
        // toggle anterior simplemente no se persisten (ver
        // construirPlanDeColaboradores()).
        if ($this->collaboratorsFilter->withCollaborators) {
            $this->validate($this->reglasColaboradores());
        }

        if ($estado === 'published') {
            // validarParaPublicar() ya llama a sincronizarFormularios() internamente.
            if (! $this->validarParaPublicar()) {
                return;
            }
        } else {
            $this->sincronizarFormularios();

            $this->generalInfo->validate($this->generalInfo->rulesArchivos());
            $this->schedule->validate($this->schedule->rulesArchivos());
            $this->location->validate($this->location->rulesArchivos());
            $this->registration->validate($this->registration->rulesArchivos());
        }

        [$mediaPlan, $mediaIdsAEliminar] = $this->construirPlanDeMedia();

        try {
            $service->guardar(
                eventId: $this->eventId,
                datos: [
                    'estado'       => $estado,
                    'generalInfo'  => $this->generalInfo->all(),
                    'schedule'     => $this->schedule->all(),
                    'location'     => $this->location->all(),
                    'registration' => $this->registration->all(),
                ],
                cover: $this->coverImage,
                removeCover: $this->removeCover,
                mediaPlan: $mediaPlan,
                mediaIdsAEliminar: $mediaIdsAEliminar,
                collaboratorsPlan: $this->construirPlanDeColaboradores(),
            );

            $mensaje = $estado === 'published'
                ? ($this->modo === 'editar' ? 'Evento actualizado correctamente.' : 'Evento publicado correctamente.')
                : 'Borrador guardado correctamente.';

            $this->dispatch('notificacion', tipo: 'exito', mensaje: $mensaje);
            $this->dispatch('cerrar-formulario-evento', refrescar: true);

        } catch (\Throwable $e) {
            Log::error('Error al guardar evento en EventForm', [
                'modo'       => $this->modo,
                'event_id'   => $this->eventId,
                'estado'     => $estado,
                'usuario_id' => auth()->id(),
                'ip'         => request()->ip(),
                'excepcion'  => get_class($e),
                'archivo'    => $e->getFile() . ':' . $e->getLine(),
                'error'      => $e->getMessage(),
            ]);

            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Error al guardar el evento. Inténtalo de nuevo.');
        }
    }

    /**
     * Propaga el calendario del evento ($this->schedule) hacia
     * $this->registration ANTES de construir o validar sus reglas — único
     * punto de entrada para esta sincronización entre Form objects
     * hermanos, que de otro modo no tienen forma de verse entre sí (cada
     * Livewire\Form solo conoce a su propio componente padre, no a sus
     * hermanos). Ver RegistrationForm::sincronizarConEvento() para el
     * detalle de los tres escenarios que resuelve.
     */
    private function sincronizarFormularios(): void
    {
        $this->registration->sincronizarConEvento($this->schedule->startAt, $this->schedule->publishedAt);
    }

    /**
     * Valida los 4 Form objects con sus reglas de publicación. Si alguno
     * falla, NO se muestran errores en línea: se agregan todos los mensajes
     * en $camposFaltantes y se abre el modal interactivo (mismo patrón que
     * NewsForm::validarParaPublicar()).
     *
     * Cada Form expone su propio messages() con texto en español — sin
     * pasarlo aquí, Livewire recae en los mensajes en inglés por defecto de
     * Laravel (no existe lang/es/validation.php en el proyecto), que es
     * justo lo que mostraba este modal antes de este método.
     *
     * LA PORTADA NO VIVE EN NINGÚN Form OBJECT: 'coverImage'/'coverUrl'/
     * 'removeCover' son propiedades de este componente (WithFileUploads no
     * puede alojarse en un Livewire\Form — ver docblock de la clase), así
     * que su obligatoriedad al publicar se comprueba aquí con tienePortada()
     * en vez de una regla de validación más, manteniendo un solo punto de
     * verdad para "¿falta algo para publicar?".
     *
     * REGISTRO DE QUÉ CAMPO DE FECHA FALLÓ: justo antes de resetErrorBag()
     * (que borra el error bag del Form $this->schedule), se anota en
     * $camposFechaInvalidos cuál de 'startAt'/'endAt' causó el rechazo —
     * es la única forma de que corregirFechasEvento() sepa, más tarde, cuál
     * de los dos limpiar al cerrar el modal sin tocar el que sí era válido.
     *
     * CLAVES CON PREFIJO: Livewire\Form::validate() reescribe las claves del
     * error bag con el prefijo "nombrePropiedad." (ver Form::prefixErrorBag())
     * ANTES de relanzar la ValidationException — por eso el error real no
     * queda en 'startAt', sino en 'schedule.startAt'. Comprobar
     * has('startAt') sin el prefijo nunca encuentra nada (el mensaje SÍ se
     * mostraba en el modal porque ->all() solo lee los valores, no las
     * claves) y dejaba $camposFechaInvalidos siempre vacío.
     *
     * sincronizarFormularios() se llama PRIMERO: las reglas cruzadas de
     * RegistrationForm (inicio/fin de inscripción contra el calendario del
     * evento) necesitan el 'startAt'/'publishedAt' YA actualizados de
     * $this->schedule antes de construir sus reglas.
     */
    private function validarParaPublicar(): bool
    {
        $this->sincronizarFormularios();

        $errores = [];
        $this->camposFechaInvalidos        = [];
        $this->camposInscripcionInvalidos  = [];

        foreach ([$this->generalInfo, $this->schedule, $this->location, $this->registration] as $form) {
            try {
                $form->validate($form->rulesPublicacion(), $form->messages());
            } catch (ValidationException $e) {
                $prefijo = $form->getPropertyName() . '.';

                if ($form === $this->schedule) {
                    foreach (['startAt', 'endAt'] as $campo) {
                        if ($e->validator->errors()->has($prefijo . $campo)) {
                            $this->camposFechaInvalidos[] = $campo;
                        }
                    }
                }

                if ($form === $this->registration) {
                    foreach (['registrationStartAt', 'registrationEndAt'] as $campo) {
                        if ($e->validator->errors()->has($prefijo . $campo)) {
                            $this->camposInscripcionInvalidos[] = $campo;
                        }
                    }
                }

                $form->resetErrorBag();
                $errores = array_merge($errores, $e->validator->errors()->all());
            }
        }

        if (! $this->tienePortada()) {
            $errores[] = 'Falta la imagen de portada del evento.';
        }

        if (! empty($errores)) {
            $this->resetErrorBag();
            $this->camposFaltantes             = array_values($errores);
            $this->mostrarModalCamposFaltantes = true;

            return false;
        }

        return true;
    }

    /**
     * Indica si el evento tendrá portada tras guardar: una nueva imagen
     * recién seleccionada, o la portada ya existente sin marcar para
     * eliminar (toggleEliminarPortada()). Evita exigir una nueva subida al
     * editar un evento que ya tiene portada y el admin no la tocó.
     */
    private function tienePortada(): bool
    {
        return $this->coverImage !== null || ($this->coverUrl !== null && ! $this->removeCover);
    }

    /**
     * Reglas de validación de los archivos subidos — seguridad y formato,
     * idénticas en ambos niveles (no son "contenido" que pueda omitirse).
     */
    private function reglasArchivos(): array
    {
        return [
            'coverImage'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'newSliderUploads.*'   => array_merge(['nullable'], $this->reglasUnArchivoSlider()),
            // Revalidación al guardar (defensa en profundidad) de los logos
            // de colaboradores externos — mismas reglas que al subirlos en
            // agregarColaboradorPersonalizado(), igual que el slider valida
            // dos veces vía reglasUnArchivoSlider().
            'customLogoUploads.*'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    /**
     * Reglas para UN archivo de la galería — única fuente de verdad
     * reutilizada por archivoEsValido() (al subir) y reglasArchivos()
     * (al guardar). mimetypes: inspecciona el contenido real del archivo
     * (no solo la extensión), evitando que un script renombrado a .jpg
     * pase como imagen válida.
     *
     * @return array<int, string>
     */
    private function reglasUnArchivoSlider(): array
    {
        return ['file', 'max:' . self::SLIDER_MAX_KB, 'mimetypes:' . self::SLIDER_MIMES];
    }

    /**
     * Valida un archivo de la galería en el momento de la subida, con las
     * mismas reglas que se aplicarán de nuevo al guardar (defensa en
     * profundidad: un archivo rechazado aquí ni siquiera llega a ocupar un
     * puesto en $mediaItems).
     */
    private function archivoEsValido(TemporaryUploadedFile $archivo): bool
    {
        return Validator::make(
            ['archivo' => $archivo],
            ['archivo' => $this->reglasUnArchivoSlider()],
        )->passes();
    }

    /**
     * Límite de tasa de lotes de subida a la galería, por IP y por usuario.
     * Se cuenta por invocación del hook (un lote = un intento), no por
     * archivo individual — protege el disco/CPU del hosting compartido sin
     * penalizar a un admin legítimo que arrastra varios archivos a la vez.
     */
    private function dentroDeLimiteDeSubidas(): bool
    {
        $claveIp   = 'admin-events-upload-ip:' . request()->ip();
        $claveUser = 'admin-events-upload-user:' . auth()->id();

        if (RateLimiter::tooManyAttempts($claveIp, self::RL_SUBIDA_IP_MAX)
            || RateLimiter::tooManyAttempts($claveUser, self::RL_SUBIDA_USER_MAX)
        ) {
            return false;
        }

        RateLimiter::hit($claveIp,   self::RL_DECAY_SEGUNDOS);
        RateLimiter::hit($claveUser, self::RL_DECAY_SEGUNDOS);

        return true;
    }

    /**
     * Construye, a partir de $mediaItems (ya en su orden final tras un
     * posible arrastre), el plan ordenado que espera el servicio y la lista
     * de IDs a eliminar.
     *
     * @return array{0: array, 1: int[]}
     */
    private function construirPlanDeMedia(): array
    {
        $mediaPlan = collect($this->mediaItems)
            ->sortBy('order')
            ->values()
            ->map(function (array $item) {
                if ($item['source'] === 'existing') {
                    return ['tipo' => 'existente', 'id' => $item['id']];
                }

                $archivo = $this->newSliderUploads[$item['tmpIndex']] ?? null;

                return $archivo !== null ? ['tipo' => 'nuevo', 'archivo' => $archivo] : null;
            })
            ->filter()
            ->values()
            ->all();

        return [$mediaPlan, $this->mediaToDelete];
    }

    /**
     * Reglas de validación de la lista de colaboradores — solo se aplican
     * cuando "Con colaboradores" está activo (ver ejecutarGuardado()).
     * 'name' cubre ambas fuentes ('partner' ya trae el nombre resuelto del
     * servicio en agregarColaborador(); 'custom' ya fue validado al
     * agregarse en agregarColaboradorPersonalizado(), pero se revalida aquí
     * por defensa en profundidad).
     */
    private function reglasColaboradores(): array
    {
        return [
            'selectedCollaborators'                       => 'array|max:' . self::MAX_COLABORADORES,
            'selectedCollaborators.*.name'                 => 'required|string|max:150',
            'selectedCollaborators.*.participationDetails' => 'nullable|string|max:300',
        ];
    }

    /**
     * Traduce $selectedCollaborators (+ $customLogoUploads para los
     * archivos nuevos aún no guardados) al plan que espera
     * AdminEventsFormService::guardar(). Vacío si "Con colaboradores" está
     * desactivado: cualquier dato que quedara en memoria de un toggle
     * anterior no se persiste — mismo criterio que fuerza capacityTotal = 0
     * cuando 'unlimitedCapacity' está activo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function construirPlanDeColaboradores(): array
    {
        if (! $this->collaboratorsFilter->withCollaborators) {
            return [];
        }

        return collect($this->selectedCollaborators)
            ->sortBy('order')
            ->values()
            ->map(function (array $item) {
                if ($item['source'] === 'partner') {
                    return [
                        'source'               => 'partner',
                        'partnerId'            => $item['partnerId'],
                        'customName'           => null,
                        'logo'                 => null,
                        'participationDetails' => trim((string) $item['participationDetails']),
                    ];
                }

                $logo = null;
                if ($item['tmpLogoIndex'] !== null && array_key_exists($item['tmpLogoIndex'], $this->customLogoUploads)) {
                    $logo = ['tipo' => 'nuevo', 'archivo' => $this->customLogoUploads[$item['tmpLogoIndex']]];
                } elseif ($item['customLogoExistingPath'] !== null) {
                    $logo = ['tipo' => 'existente', 'path' => $item['customLogoExistingPath']];
                }

                return [
                    'source'               => 'custom',
                    'partnerId'            => null,
                    'customName'           => trim((string) $item['name']),
                    'logo'                 => $logo,
                    'participationDetails' => trim((string) $item['participationDetails']),
                ];
            })
            ->values()
            ->all();
    }

    /** IDs de socios ya añadidos a la lista — excluidos de la grilla de búsqueda. */
    private function idsPartnersSeleccionados(): array
    {
        return collect($this->selectedCollaborators)
            ->where('source', 'partner')
            ->pluck('partnerId')
            ->all();
    }

    /**
     * Límite de tasa para agregar colaboradores (de BD o personalizados),
     * por IP y por usuario — mismo patrón que dentroDeLimiteDeSubidas()
     * para la galería multimedia.
     */
    private function dentroDeLimiteDeColaboradores(): bool
    {
        $claveIp   = 'admin-events-collab-ip:' . request()->ip();
        $claveUser = 'admin-events-collab-user:' . auth()->id();

        if (RateLimiter::tooManyAttempts($claveIp, self::RL_COLAB_IP_MAX)
            || RateLimiter::tooManyAttempts($claveUser, self::RL_COLAB_USER_MAX)
        ) {
            return false;
        }

        RateLimiter::hit($claveIp,   self::RL_DECAY_SEGUNDOS);
        RateLimiter::hit($claveUser, self::RL_DECAY_SEGUNDOS);

        return true;
    }

    /**
     * Pre-rellena todos los Form objects con los datos del evento.
     * Llamado únicamente en mount() cuando modo = 'editar'.
     */
    private function cargarDatosEdicion(int $id, AdminEventsFormService $service): void
    {
        $datos = $service->obtenerParaEdicion($id);

        $this->generalInfo->fill($datos['generalInfo']);
        $this->schedule->fill($datos['schedule']);
        $this->location->fill($datos['location']);
        $this->registration->fill($datos['registration']);

        $this->coverUrl      = $datos['coverUrl'];
        $this->mediaItems    = $datos['mediaItems'];
        $this->estadoActual  = $datos['status'];

        $this->collaboratorsFilter->withCollaborators = $datos['collaborators']['withCollaborators'];
        $this->selectedCollaborators                  = $datos['collaborators']['items'];
    }

    /**
     * Verifica que el usuario autenticado es administrador.
     * Se llama en mount() y en cada método público para defensa en profundidad.
     */
    private function autorizarAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado.');
        }
    }

}
