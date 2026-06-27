<?php

namespace App\Livewire\Admin;

use App\Services\Admin\NewsFormService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Componente Livewire: Formulario de Creación/Edición de Noticias.
 *
 * MODO DE OPERACIÓN:
 *  - 'crear': montado con newsId=null → formulario vacío.
 *  - 'editar': montado con newsId=int → pre-rellenado con datos de la BD.
 *  El modo y el ID son propiedades #[Locked]; el cliente no puede alterarlos.
 *
 * DRY: la lógica de guardado está en ejecutarGuardado(), que usan tanto
 *  publicar() como guardarBorrador(). La única diferencia es el $estado final.
 *
 * VALIDACIÓN POR ESTADO:
 *  - 'published': exige todos los campos obligatorios (reglasPublicacion()).
 *    Si falta alguno, NO se muestran errores en línea: validarParaPublicar()
 *    captura la ValidationException, vuelca $validator->errors() en
 *    $camposFaltantes y abre el modal interactivo (mostrarModalCamposFaltantes)
 *    con un botón "Cerrar". abrirModal('publicar') ya valida antes de mostrar
 *    la confirmación; ejecutarGuardado() repite la validación como defensa en
 *    profundidad (el servidor es la fuente de verdad final).
 *  - 'draft': bypass de los campos obligatorios de publicación —solo se
 *    valida lo relacionado con seguridad y formato (reglasArchivos())— para
 *    permitir guardar borradores incompletos de forma flexible.
 *  - 'scheduled': misma validación completa que 'published' (reglasPublicacion()).
 *    Se asigna automáticamente cuando la fecha de publicación elegida es futura
 *    y el campo no está bloqueado; un comando programado (news:publish-scheduled)
 *    transiciona estos artículos a 'published' al llegar su fecha/hora.
 *
 * SEGURIDAD:
 *  - autorizarAdmin() en mount() y en cada acción sensible (defensa en profundidad).
 *  - Rate limiting por IP + por usuario en las acciones de guardado.
 *  - #[Locked] en noticiaId, modo, mediosExistentes, documentosExistentes,
 *    mediosAEliminar y documentosAEliminar → el cliente no manipula el estado
 *    del servidor para inyectar IDs ajenos ni forzar borrados arbitrarios.
 *  - Validación estricta de tipos MIME reales (mimetypes:) en todas las subidas.
 *  - El contenido HTML del editor se sanitiza en NewsFormService::guardar().
 *
 * UPLOADS:
 *  Usa WithFileUploads de Livewire 4.
 *  Archivos temporales en storage/app/livewire-tmp/ hasta el guardado final.
 *  quitarMedioNuevo() y quitarDocumentoNuevo() eliminan ítems del array
 *  antes del guardado para que no se persistan.
 */
class NewsForm extends Component
{
    use WithFileUploads;

    // ── Identidad del formulario ────────────────────────────────────────
    #[Locked]
    public string $modo = 'crear';

    #[Locked]
    public ?int $noticiaId = null;

    // ── Campos del formulario ───────────────────────────────────────────
    public string $titulo          = '';
    public string $resumen         = '';
    public string $contenido       = '';   // Gestionado por el editor Trix vía wire:model
    public string $categoriaId     = '';
    public string $fechaPublicacion = '';

    // ── Imagen de portada ───────────────────────────────────────────────
    public $imagenPortada     = null;      // TemporaryUploadedFile o null
    public ?string $portadaActualUrl = null;
    public bool $eliminarPortada     = false;

    // ── Medios del slider ───────────────────────────────────────────────
    public array $mediosNuevos = [];       // array de TemporaryUploadedFile

    #[Locked]
    public array $mediosExistentes  = [];  // [{id, url, tipo, nombre}] — solo servidor

    #[Locked]
    public array $mediosAEliminar   = [];  // IDs de Media a borrar al guardar

    // ── Documentos descargables ─────────────────────────────────────────
    public array $documentosNuevos = [];   // array de TemporaryUploadedFile

    #[Locked]
    public array $documentosExistentes  = []; // [{id, nombre, url}] — solo servidor

    #[Locked]
    public array $documentosAEliminar   = []; // IDs de Media a borrar al guardar

    // ── Estado derivado del historial de publicación ────────────────────
    // #[Locked]: el cliente no puede desbloquear la fecha alterando este valor.
    #[Locked]
    public bool $fechaPublicacionBloqueada = false;

    // #[Locked]: el cliente no puede falsificar el estado actual del artículo.
    #[Locked]
    public string $estadoActual = 'draft';

    // ── Estado del UI ───────────────────────────────────────────────────
    public ?string $modalConfirmacion = null; // 'publicar' | 'borrador' | 'cancelar' | 'programar' | 'publicar_ahora'

    // Texto formateado (en español) de la fecha y hora en que se publicará
    // automáticamente la noticia si se confirma el modal "programar".
    // Solo se usa para mostrarlo en la vista; el valor real se recalcula
    // en el servidor al guardar (nunca se confía en este texto formateado).
    public ?string $fechaHoraProgramada = null;

    // Modal de validación de publicación: lista los campos obligatorios
    // que faltan por completar. Se activa en lugar de los errores en línea
    // para que el usuario vea de un vistazo todo lo que falta.
    public bool $mostrarModalCamposFaltantes = false;

    /** @var array<int, string> Mensajes legibles de los campos obligatorios faltantes */
    public array $camposFaltantes = [];

    // Aviso de auto-corrección: se activa cuando el usuario intenta elegir una
    // fecha de publicación pasada y el campo se ajusta a la fecha actual.
    // Es solo un indicador de UI (no se persiste ni se valida en el guardado).
    public bool $avisoFechaPasada = false;

    // ── Constantes de rate limiting ─────────────────────────────────────
    private const RL_GUARDAR_IP_MAX   = 20;
    private const RL_GUARDAR_USER_MAX = 10;
    private const RL_DECAY_SEGUNDOS   = 60;

    // ════════════════════════════════════════════════════════════════════
    //  CICLO DE VIDA
    // ════════════════════════════════════════════════════════════════════

    /**
     * mount() recibe el ID opcional de la noticia a editar.
     * La inyección del servicio ocurre aquí para que Livewire gestione el ciclo de vida.
     */
    public function mount(?int $newsId = null, NewsFormService $service): void
    {
        $this->autorizarAdmin();

        $this->fechaPublicacion = now()->format('Y-m-d');

        if ($newsId !== null) {
            $this->modo       = 'editar';
            $this->noticiaId  = $newsId;
            $this->cargarDatosEdicion($newsId, $service);
        }
    }

    /**
     * Hook reactivo de Livewire — DEBE permanecer ligero (sin consultas a BD,
     * colas ni lógica pesada): solo una comparación de fechas con Carbon.
     *
     * Disparador: Alpine.js (sincronizarFecha() en news-form.blade.php) escucha
     * el evento "change" del input de fecha y empuja el valor con
     * $wire.set('fechaPublicacion', valor). Ese evento cubre tanto la selección
     * en el selector nativo del navegador como la escritura manual seguida de
     * blur, por lo que este hook se ejecuta UNA SOLA VEZ por interacción
     * (nunca en cada pulsación de tecla), garantizando sincronización
     * instantánea sin penalizar el rendimiento de la UI.
     *
     * REGLA DE NEGOCIO: no se permiten fechas de publicación pasadas. Si el
     * usuario selecciona una fecha anterior a hoy, el valor se sobrescribe de
     * inmediato con la fecha actual y se activa un aviso accesible; Alpine.js
     * lee ese valor corregido vía $wire.fechaPublicacion (tras resolverse la
     * promesa de $wire.set()) y lo refleja al instante en el input. Las fechas
     * futuras se permiten sin restricción: habilitan la programación de la
     * publicación (ver abrirModal() y esFechaFutura()).
     *
     * SEGURIDAD: esta auto-corrección es solo asistencia de UI; el servidor
     * vuelve a validar 'fechaPublicacion' como 'required|date' en
     * ejecutarGuardado(), por lo que un valor manipulado en el cliente no
     * puede colarse sin pasar por la validación final.
     */
    public function updatedFechaPublicacion(string $valor): void
    {
        // Si el campo está bloqueado (artículo ya publicado antes), el cliente
        // no debería poder modificarlo; ignoramos cualquier intento de cambio.
        if ($this->fechaPublicacionBloqueada) {
            return;
        }

        $fechaSeleccionada = $this->parsearFecha($valor);

        if ($fechaSeleccionada === null) {
            // Formato inválido: la regla 'date' del validate() lo capturará al guardar.
            $this->avisoFechaPasada = false;
            return;
        }

        if ($fechaSeleccionada->lt(now()->startOfDay())) {
            $this->fechaPublicacion  = now()->format('Y-m-d');
            $this->avisoFechaPasada  = true;
        } else {
            $this->avisoFechaPasada = false;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    /** Publica la noticia (status = 'published') tras confirmar en el modal. */
    public function publicar(NewsFormService $service): void
    {
        $this->ejecutarGuardado('published', $service);
    }

    /** Guarda la noticia como borrador (status = 'draft') tras confirmar. */
    public function guardarBorrador(NewsFormService $service): void
    {
        $this->ejecutarGuardado('draft', $service);
    }

    /**
     * Programa la publicación de la noticia para una fecha futura
     * (status = 'scheduled') tras confirmar en el modal "programar".
     */
    public function programar(NewsFormService $service): void
    {
        $this->ejecutarGuardado('scheduled', $service);
    }

    /** Descarta el formulario sin guardar y vuelve a la lista. */
    public function cancelar(): void
    {
        $this->autorizarAdmin();
        $this->cerrarModal();
        $this->dispatch('cerrar-formulario-noticia');
    }

    // ════════════════════════════════════════════════════════════════════
    //  GESTIÓN DE PORTADA
    // ════════════════════════════════════════════════════════════════════

    /** Alterna el flag de eliminación de la portada actual. */
    public function toggleEliminarPortada(): void
    {
        $this->autorizarAdmin();
        $this->eliminarPortada = ! $this->eliminarPortada;
        // Al marcar para eliminar también limpiar cualquier nueva subida
        if ($this->eliminarPortada) {
            $this->imagenPortada = null;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  GESTIÓN DE MEDIOS EXISTENTES (MODO EDICIÓN)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Marca un medio existente del slider para borrar al guardar.
     * Verifica que el ID pertenece a esta noticia antes de aceptarlo.
     */
    public function marcarMedioParaEliminar(int $id): void
    {
        $this->autorizarAdmin();

        // Validar que el ID pertenece a los medios cargados de esta noticia
        if (! collect($this->mediosExistentes)->contains('id', $id)) {
            return;
        }

        $this->mediosAEliminar[]  = $id;
        $this->mediosExistentes   = array_values(
            array_filter($this->mediosExistentes, fn ($m) => $m['id'] !== $id)
        );
    }

    /**
     * Marca un documento existente para borrar al guardar.
     * Verifica que el ID pertenece a esta noticia antes de aceptarlo.
     */
    public function marcarDocumentoParaEliminar(int $id): void
    {
        $this->autorizarAdmin();

        if (! collect($this->documentosExistentes)->contains('id', $id)) {
            return;
        }

        $this->documentosAEliminar[]  = $id;
        $this->documentosExistentes   = array_values(
            array_filter($this->documentosExistentes, fn ($d) => $d['id'] !== $id)
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  GESTIÓN DE NUEVAS SUBIDAS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Quita un archivo recién subido del array de nuevos medios del slider.
     * El archivo temporal de Livewire permanece hasta que expire; solo lo
     * descartamos del estado del componente para no persistirlo.
     */
    public function quitarMedioNuevo(int $indice): void
    {
        $this->autorizarAdmin();

        if (array_key_exists($indice, $this->mediosNuevos)) {
            array_splice($this->mediosNuevos, $indice, 1);
        }
    }

    /**
     * Quita un documento recién subido del array de nuevos documentos.
     */
    public function quitarDocumentoNuevo(int $indice): void
    {
        $this->autorizarAdmin();

        if (array_key_exists($indice, $this->documentosNuevos)) {
            array_splice($this->documentosNuevos, $indice, 1);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE CONFIRMACIÓN
    // ════════════════════════════════════════════════════════════════════

    /**
     * Abre el modal de confirmación para la acción indicada.
     *
     * REGLA DE NEGOCIO: antes de mostrar la confirmación de "Publicar", se
     * valida que estén completos los campos obligatorios. Si falta alguno,
     * se muestra el modal de campos faltantes en su lugar — el usuario sabe
     * de inmediato qué corregir, en vez de confirmar una acción que fallará.
     *
     * PROGRAMACIÓN: si la fecha de publicación elegida es futura (y el campo
     * no está bloqueado por una publicación previa), "Publicar" no ejecuta la
     * publicación inmediata: se muestra el modal "programar", que indica al
     * usuario la fecha y hora exactas en que el artículo se publicará
     * automáticamente (o reprogramará, si ya estaba programado). La
     * confirmación de ese modal invoca programar().
     *
     * PUBLICAR AHORA: al editar un borrador (estadoActual = 'draft', fecha
     * editable) cuya fecha de publicación es HOY, "Guardar borrador" no
     * guarda en silencio: se muestra el modal "publicar_ahora", que ofrece
     * publicar el artículo de inmediato como alternativa a seguir
     * guardándolo como borrador. Ambas opciones llaman a métodos existentes
     * (publicar() / guardarBorrador()), sin duplicar lógica de guardado.
     */
    public function abrirModal(string $accion): void
    {
        $this->autorizarAdmin();

        if (! in_array($accion, ['publicar', 'borrador', 'cancelar'], strict: true)) {
            return;
        }

        if ($accion === 'publicar') {
            if (! $this->validarParaPublicar()) {
                return;
            }

            if (! $this->fechaPublicacionBloqueada && $this->esFechaFutura()) {
                $this->fechaHoraProgramada = $this->calcularFechaHoraProgramada()
                    ->translatedFormat('d \d\e F \d\e Y, H:i');
                $this->modalConfirmacion = 'programar';

                return;
            }
        }

        if ($accion === 'borrador'
            && $this->modo === 'editar'
            && $this->estadoActual === 'draft'
            && ! $this->fechaPublicacionBloqueada
            && $this->esFechaHoy()
        ) {
            $this->modalConfirmacion = 'publicar_ahora';

            return;
        }

        $this->modalConfirmacion = $accion;
    }

    /** Cierra el modal de confirmación sin ejecutar ninguna acción. */
    public function cerrarModal(): void
    {
        $this->modalConfirmacion = null;
    }

    /** Cierra el modal de campos faltantes y limpia su estado. */
    public function cerrarModalCamposFaltantes(): void
    {
        $this->mostrarModalCamposFaltantes = false;
        $this->camposFaltantes = [];
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(NewsFormService $service): \Illuminate\View\View
    {
        $categorias = $service->obtenerCategorias();

        return view('livewire.admin.news-form', compact('categorias'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Núcleo del guardado: usado por publicar() y guardarBorrador().
     * Contiene validación, rate limiting, sanitización y delegación al servicio.
     */
    private function ejecutarGuardado(string $estado, NewsFormService $service): void
    {
        $this->autorizarAdmin();

        // Rate limiting por IP: mitiga DoS de escritura masiva
        $claveIp = 'admin-news-save-ip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($claveIp, self::RL_GUARDAR_IP_MAX)) {
            $this->cerrarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiadas solicitudes. Espera un momento.');
            return;
        }

        // Rate limiting por usuario: mitiga brute force desde cuenta comprometida
        $claveUser = 'admin-news-save-user:' . auth()->id();
        if (RateLimiter::tooManyAttempts($claveUser, self::RL_GUARDAR_USER_MAX)) {
            $this->cerrarModal();
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Límite de guardados alcanzado. Espera un minuto.');
            return;
        }

        RateLimiter::hit($claveIp,   self::RL_DECAY_SEGUNDOS);
        RateLimiter::hit($claveUser, self::RL_DECAY_SEGUNDOS);

        $this->cerrarModal();

        // ── Validación según el estado de destino ─────────────────────
        // 'published'/'scheduled': se exigen todos los campos obligatorios;
        //   si falta alguno, validarParaPublicar() abre el modal de campos
        //   faltantes y detenemos el guardado aquí (defensa en profundidad:
        //   el botón ya valida antes de abrir la confirmación, pero el
        //   servidor es la fuente de verdad final).
        // 'draft': se omiten los campos obligatorios de publicación —solo
        //   se valida lo relacionado con seguridad (archivos subidos,
        //   longitudes máximas, formato de fecha)— para permitir el
        //   guardado flexible de borradores incompletos.
        if (in_array($estado, ['published', 'scheduled'], strict: true)) {
            if (! $this->validarParaPublicar()) {
                return;
            }
        } else {
            $this->validate($this->reglasArchivos());
        }

        // 'scheduled': la fecha de publicación se combina con la hora actual
        // para registrar el momento exacto programado (ver calcularFechaHoraProgramada()).
        $fechaPublicacion = $estado === 'scheduled'
            ? $this->calcularFechaHoraProgramada()->format('Y-m-d H:i:s')
            : $this->fechaPublicacion;

        try {
            $service->guardar(
                noticiaId:           $this->noticiaId,
                datos: [
                    'titulo'           => $this->titulo,
                    'resumen'          => $this->resumen,
                    'contenido'        => $this->contenido,
                    'categoriaId'      => $this->categoriaId,
                    'estado'           => $estado,
                    'fechaPublicacion' => $fechaPublicacion,
                    'autorId'          => auth()->id(),
                    'autorNombre'      => auth()->user()->name,
                ],
                portada:             $this->imagenPortada,
                eliminarPortada:     $this->eliminarPortada,
                mediosNuevos:        $this->mediosNuevos,
                mediosAEliminar:     $this->mediosAEliminar,
                documentosNuevos:    $this->documentosNuevos,
                documentosAEliminar: $this->documentosAEliminar,
                // El servidor es la fuente de verdad; el cliente no puede desbloquear
                fechaBloqueada:      $this->fechaPublicacionBloqueada,
            );

            $mensaje = match ($estado) {
                'published' => $this->modo === 'editar'
                    ? 'Noticia actualizada correctamente.'
                    : 'Noticia publicada correctamente.',
                'scheduled' => 'Noticia programada correctamente.',
                default     => 'Borrador guardado correctamente.',
            };

            $this->dispatch('notificacion', tipo: 'exito', mensaje: $mensaje);
            // Señal al componente padre (NewsManagement) para volver a la lista
            $this->dispatch('cerrar-formulario-noticia', refrescar: true);

        } catch (\Throwable $e) {
            Log::error('Error al guardar noticia en AdminNewsForm', [
                'modo'       => $this->modo,
                'noticia_id' => $this->noticiaId,
                'estado'     => $estado,
                'usuario_id' => auth()->id(),
                'ip'         => request()->ip(),
                'excepcion'  => get_class($e),
                'archivo'    => $e->getFile() . ':' . $e->getLine(),
                'error'      => $e->getMessage(),
            ]);

            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Error al guardar la noticia. Inténtalo de nuevo.');
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  VALIDACIÓN DE PUBLICACIÓN — MODAL DE CAMPOS FALTANTES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Valida los campos obligatorios para publicar.
     *
     * Si la validación falla, NO se muestran los errores en línea habituales:
     * en su lugar, se captura la ValidationException, se extraen los mensajes
     * del error bag ($validator->errors()) y se asignan a $camposFaltantes
     * para alimentar el modal interactivo (mantiene la UI reactiva y rápida,
     * sin depender de redirecciones ni de recargas de página).
     *
     * @return bool true si la validación pasó (se puede continuar), false si
     *              se abrió el modal de campos faltantes y debe detenerse el flujo.
     */
    private function validarParaPublicar(): bool
    {
        try {
            $this->validate($this->reglasPublicacion(), $this->mensajesValidacionPublicacion());
        } catch (ValidationException $e) {
            // No se muestran errores en línea: se limpia el bag y se listan
            // los campos faltantes en el modal interactivo.
            $this->resetErrorBag();

            $this->camposFaltantes             = array_values($e->validator->errors()->all());
            $this->mostrarModalCamposFaltantes = true;

            return false;
        }

        return true;
    }

    /**
     * Reglas de validación comunes a ambos estados (publicar y borrador).
     *
     * Cubren exclusivamente seguridad y formato —tipos MIME reales, límites
     * de tamaño y longitud— nunca la obligatoriedad de campos de contenido.
     * Esto es lo que permite el guardado flexible de borradores: el archivo
     * subido siempre se valida, pero el título, la categoría, etc. pueden
     * quedar vacíos.
     */
    private function reglasArchivos(): array
    {
        $reglas = [
            'titulo'             => 'nullable|string|max:220',
            'resumen'            => 'nullable|string|max:800',
            'imagenPortada'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'mediosNuevos.*'     => [
                'nullable', 'file', 'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg',
            ],
            'documentosNuevos.*' => [
                'nullable', 'file', 'max:10240',
                'mimetypes:application/pdf,application/msword,'
                    . 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                    . 'application/vnd.ms-excel,'
                    . 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                    . 'application/vnd.ms-powerpoint,'
                    . 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        ];

        // categoriaId: cadena vacía = "sin categoría todavía", permitido en
        // borrador (la columna admite NULL — ver migración make_category_id_nullable).
        // Si el admin SÍ seleccionó una, se valida su formato y existencia
        // real (defensa contra IDs manipulados, igual que en publicación).
        if ($this->categoriaId !== '') {
            $reglas['categoriaId'] = 'integer|exists:categories,id';
        }

        // El formato se valida igualmente cuando el campo es editable, aunque
        // no sea obligatorio para guardar un borrador.
        if (! $this->fechaPublicacionBloqueada) {
            $reglas['fechaPublicacion'] = 'nullable|date';
        }

        return $reglas;
    }

    /**
     * Reglas completas para publicar: parte de reglasArchivos() (DRY) y
     * sobrescribe los campos que pasan a ser obligatorios para publicación.
     */
    private function reglasPublicacion(): array
    {
        $reglas = $this->reglasArchivos();

        $reglas['titulo']      = 'required|string|max:220';
        $reglas['categoriaId'] = 'required|integer|exists:categories,id';

        // Regla en línea: el editor Trix genera HTML, por lo que strip_tags
        // extrae el texto visible real para medir su longitud mínima.
        $reglas['contenido'] = ['required', function (string $atributo, mixed $valor, \Closure $fail): void {
            if (mb_strlen(trim(strip_tags((string) $valor))) < 10) {
                $fail('El cuerpo de la noticia es muy corto (mínimo 10 caracteres visibles).');
            }
        }];

        if (! $this->fechaPublicacionBloqueada) {
            $reglas['fechaPublicacion'] = 'required|date';
        }

        return $reglas;
    }

    /**
     * Mensajes en español para los campos obligatorios de publicación.
     * Se listan literalmente en el modal de campos faltantes, por lo que
     * deben ser claros y autoexplicativos por sí mismos.
     */
    private function mensajesValidacionPublicacion(): array
    {
        return [
            'titulo.required'           => 'Falta el título de la noticia.',
            'titulo.max'                => 'El título no puede superar los 220 caracteres.',
            'categoriaId.required'      => 'Falta seleccionar una categoría.',
            'categoriaId.exists'        => 'La categoría seleccionada no es válida.',
            'contenido.required'        => 'Falta el cuerpo de la noticia.',
            'fechaPublicacion.required' => 'Falta la fecha de publicación.',
            'fechaPublicacion.date'     => 'La fecha de publicación no es válida.',
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  PROGRAMACIÓN DE PUBLICACIONES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Punto único de parseo de $fechaPublicacion (o el valor indicado) como
     * Carbon al inicio del día. DRY: lo reutilizan esFechaFutura(),
     * esFechaHoy(), esFechaPasada() y updatedFechaPublicacion(). Devuelve
     * null ante un formato inválido en lugar de lanzar una excepción.
     */
    private function parsearFecha(?string $fecha = null): ?\Illuminate\Support\Carbon
    {
        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $fecha ?? $this->fechaPublicacion)
                ->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Indica si $fechaPublicacion es una fecha futura (posterior a hoy).
     *
     * Usado por abrirModal() para decidir si "Publicar" debe programar (o
     * reprogramar) la noticia (status = 'scheduled') en lugar de publicarla
     * de inmediato.
     */
    private function esFechaFutura(): bool
    {
        return $this->parsearFecha()?->gt(now()->startOfDay()) ?? false;
    }

    /**
     * Indica si $fechaPublicacion es exactamente la fecha de hoy.
     *
     * Usado por abrirModal() para detectar, al editar un borrador con la
     * fecha desbloqueada, si "Guardar borrador" debe interceptarse con el
     * modal "publicar_ahora" en lugar de guardar en silencio.
     */
    private function esFechaHoy(): bool
    {
        return $this->parsearFecha()?->equalTo(now()->startOfDay()) ?? false;
    }

    /**
     * Indica si $fechaPublicacion es una fecha anterior a hoy.
     *
     * Usado por cargarDatosEdicion() para detectar borradores cuya fecha de
     * publicación quedó en el pasado (p.ej. estuvo programada y se revirtió
     * a borrador antes de cumplirse) y reiniciarla automáticamente a hoy.
     */
    private function esFechaPasada(): bool
    {
        return $this->parsearFecha()?->lt(now()->startOfDay()) ?? false;
    }

    /**
     * Calcula el instante exacto en que se publicará automáticamente la
     * noticia programada: la fecha futura elegida combinada con la hora
     * actual (el selector de fecha es solo de día, no de hora).
     */
    private function calcularFechaHoraProgramada(): \Illuminate\Support\Carbon
    {
        // esFechaFutura() ya garantizó un formato válido antes de llegar aquí;
        // el fallback a "ahora" es solo defensivo (nunca debería usarse).
        return ($this->parsearFecha() ?? now())->setTimeFrom(now());
    }

    /**
     * Pre-rellena todas las propiedades del componente con los datos de la noticia.
     * Llamado únicamente en mount() cuando modo = 'editar'.
     */
    private function cargarDatosEdicion(int $id, NewsFormService $service): void
    {
        $datos = $service->obtenerParaEdicion($id);

        $this->titulo              = $datos['titulo'];
        $this->resumen             = $datos['resumen'];
        $this->contenido           = $datos['contenido'];
        $this->categoriaId         = (string) $datos['categoriaId'];
        $this->fechaPublicacion    = $datos['fechaPublicacion'];
        $this->portadaActualUrl    = $datos['portadaUrl'];
        $this->mediosExistentes    = $datos['mediosSlider'];
        $this->documentosExistentes = $datos['documentos'];
        $this->estadoActual         = $datos['estadoActual'];

        // Bloquear la fecha si el artículo fue publicado alguna vez.
        // #[Locked] garantiza que el cliente no puede revertir este valor.
        //
        // EXCEPCIÓN EXPLÍCITA 'scheduled': first_published_at solo se asigna
        // al pasar a 'published' (ver NewsFormService::guardar()), así que un
        // artículo "programado" nunca lo tiene y esta condición ya sería
        // false por sí sola. Se deja explícita para que la regla de negocio
        // —la fecha de un artículo programado SIEMPRE debe quedar editable—
        // sea visible en el código y no dependa de un invariante implícito.
        $this->fechaPublicacionBloqueada = $datos['firstPublishedAt'] !== null
            && $this->estadoActual !== 'scheduled';

        // REGLA DE NEGOCIO (borradores con fecha programada vencida): si el
        // artículo es un borrador con fecha de publicación editable y esa
        // fecha ya quedó en el pasado (p.ej. estuvo "programada" para una
        // fecha y se revirtió a borrador antes de cumplirse), se reinicia
        // automáticamente a hoy al cargar el formulario, reutilizando el
        // mismo aviso visual que la auto-corrección en vivo de
        // updatedFechaPublicacion().
        if ($this->estadoActual === 'draft'
            && ! $this->fechaPublicacionBloqueada
            && $this->esFechaPasada()
        ) {
            $this->fechaPublicacion = now()->format('Y-m-d');
            $this->avisoFechaPasada = true;
        }
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
