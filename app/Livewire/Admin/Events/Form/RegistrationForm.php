<?php

namespace App\Livewire\Admin\Events\Form;

use App\Rules\Events\IsAtLeastDaysBefore;
use App\Rules\Events\IsBeforeOrEqualToDate;
use Illuminate\Support\Carbon;
use Livewire\Form;

/**
 * Capacidad de asistentes y ventana de inscripción.
 *
 * MAPEO CON LA BD (sin columna nueva para "ilimitado"):
 *  'unlimitedCapacity' es un campo virtual, solo de interfaz — no existe en
 *  la tabla 'events'. Reutiliza la convención ya establecida en
 *  AdminEventItemDto: capacity_total = 0 significa "ilimitado". Al cargar
 *  el formulario, unlimitedCapacity = (capacityTotal === 0); al guardar, si
 *  está activo, EventForm fuerza capacityTotal = 0 antes de persistir.
 *
 * VENTANA DE INSCRIPCIÓN — TRES ESCENARIOS (ver sincronizarConEvento()):
 *  A. 'registrationEnabled' = true y 'registrationNoEndDate' = false: el
 *     admin captura ambas fechas a mano. Reglas cruzadas con ScheduleForm:
 *     - registrationStartAt debe abrir, como mínimo, DIAS_MINIMOS_APERTURA
 *       día(s) antes del inicio del evento (IsAtLeastDaysBefore).
 *     - registrationEndAt debe ser posterior a registrationStartAt (regla
 *       nativa 'after': ambos campos viven en ESTE mismo Form, sí se puede
 *       resolver por nombre) y no puede superar el inicio del evento,
 *       aunque sí puede ser igual (IsBeforeOrEqualToDate).
 *  B. 'registrationEnabled' = true y 'registrationNoEndDate' = true: el
 *     cierre de inscripción se fija siempre al inicio del evento — el
 *     campo ni siquiera se le pide al admin (ver
 *     _capacity-registration.blade.php, x-show="!noEndDate").
 *  C. 'registrationEnabled' = false: la ventana completa la calcula el
 *     sistema (inicio = fecha de publicación, fin = inicio del evento) —
 *     "siempre abierta hasta que el evento comience", sin captura manual.
 *
 *  Los escenarios B y C son asignaciones, no reglas de validación: por eso
 *  viven en sincronizarConEvento(), no en rulesArchivos().
 */
class RegistrationForm extends Form
{
    // Mínimo de días que debe abrir la inscripción antes del evento (Escenario A).
    private const DIAS_MINIMOS_APERTURA = 1;

    public bool $registrationEnabled = false;
    public bool $unlimitedCapacity = false;
    public int $capacityTotal = 0;
    public string $registrationStartAt = '';
    public string $registrationEndAt = '';
    public bool $registrationNoEndDate = false;

    /**
     * Contexto cronológico del evento (ScheduleForm), inyectado por
     * EventForm::sincronizarFormularios() ANTES de validar o de guardar —
     * ver sincronizarConEvento(). NO son propiedades públicas a propósito:
     * Livewire serializa/persiste solo propiedades públicas, y esto es
     * únicamente una referencia de lectura para esta pasada de validación,
     * no un dato propio de este Form (no debe viajar en $this->all() ni en
     * el snapshot del cliente).
     */
    private ?string $eventoInicioAt = null;
    private ?string $eventoPublicadoAt = null;

    /**
     * Sincroniza este Form con el calendario del evento (ScheduleForm) y
     * resuelve los Escenarios B/C de inmediato:
     *  - Escenario C: inscripción deshabilitada → fechas calculadas por el
     *    sistema (nunca capturadas a mano).
     *  - Escenario B: "sin fecha de fin" → el cierre siempre coincide con
     *    el inicio del evento, sin importar lo que haya antes en el campo.
     * El Escenario A (captura manual) no necesita asignación aquí — solo
     * las reglas cruzadas de rulesArchivos() lo validan.
     *
     * Debe llamarse ANTES de rulesArchivos()/rulesPublicacion() — ver
     * EventForm::sincronizarFormularios().
     */
    public function sincronizarConEvento(string $eventoInicioAt, string $eventoPublicadoAt): void
    {
        $this->eventoInicioAt    = $eventoInicioAt;
        $this->eventoPublicadoAt = $eventoPublicadoAt;

        // 'startAt' es datetime-local; los campos de inscripción son
        // <input type="date"> — sin normalizar a solo fecha, el navegador
        // no podría pintar el valor asignado en el selector nativo.
        $inicioEventoSoloFecha = blank($eventoInicioAt)
            ? ''
            : Carbon::parse($eventoInicioAt)->format('Y-m-d');

        if (! $this->registrationEnabled) {
            $this->registrationStartAt = $eventoPublicadoAt;
            $this->registrationEndAt   = $inicioEventoSoloFecha;

            return;
        }

        if ($this->registrationNoEndDate) {
            $this->registrationEndAt = $inicioEventoSoloFecha;
        }
    }

    public function rulesArchivos(): array
    {
        $reglas = [
            'capacityTotal' => $this->unlimitedCapacity
                ? 'nullable|integer|min:0'
                : 'required|integer|min:1',
        ];

        // Escenario A únicamente: en B/C, sincronizarConEvento() ya dejó
        // ambas fechas resueltas y mutuamente consistentes de antemano
        // (ver docblock de la clase) — pero se validan igual aquí, ya que
        // 'required'/'after'/IsBeforeOrEqualToDate siguen cumpliéndose por
        // construcción y mantienen una sola fuente de verdad para "¿la
        // ventana de inscripción es válida?", sin una rama aparte por
        // escenario.
        if ($this->registrationEnabled) {
            $reglas['registrationStartAt'] = [
                'required',
                'date',
                new IsAtLeastDaysBefore(
                    $this->eventoInicioAt,
                    self::DIAS_MINIMOS_APERTURA,
                    'El inicio de inscripción debe ser, al menos, ' . self::DIAS_MINIMOS_APERTURA
                        . ' día(s) antes del inicio del evento.',
                ),
            ];

            $reglas['registrationEndAt'] = [
                'required',
                'date',
                'after:registrationStartAt',
                new IsBeforeOrEqualToDate(
                    $this->eventoInicioAt,
                    'El cierre de inscripción no puede ser posterior al inicio del evento.',
                ),
            ];
        }

        return $reglas;
    }

    public function rulesPublicacion(): array
    {
        return $this->rulesArchivos();
    }

    /**
     * Auto-corrección reactiva: un aforo negativo no tiene sentido de
     * negocio (no es solo "inválido para publicar", es un valor que nunca
     * debería poder existir ni mientras se edita el borrador), así que se
     * reinicia a 0 de inmediato en vez de esperar al error de validación
     * de rulesArchivos()/rulesPublicacion() al guardar.
     *
     * Livewire invoca updated{Propiedad}() directamente sobre ESTE Form
     * object (no sobre EventForm) cuando cambia 'registration.capacityTotal'
     * — ver SupportFormObjects::update() del paquete. Para que el hook se
     * dispare sin esperar a otra acción (Publicar/Guardar), el input usa
     * wire:model.live.debounce.500ms (ver _capacity-registration.blade.php).
     *
     * GUARDA isset(): un <input type="number"> reporta "" (no null ni un
     * número) mientras el contenido no es un número válido — ej. un "-"
     * suelto a medio escribir "-50", o el campo vacío tras borrar todo
     * antes de que venza el debounce. Livewire convierte eso en null, y
     * FormObjectSynth::set() NO puede asignar null a esta propiedad 'int'
     * no nullable: en vez de eso hace unset($target->capacityTotal), que
     * la deja "sin inicializar" otra vez. Leer $this->capacityTotal
     * directamente en ese momento relanza exactamente ese error fatal de
     * PHP 8.2; isset() es la única forma de comprobarlo sin que el simple
     * acceso ya falle.
     */
    public function updatedCapacityTotal(): void
    {
        if (! isset($this->capacityTotal) || $this->capacityTotal < 0) {
            $this->capacityTotal = 0;
        }
    }

    /**
     * Mensajes en español para el modal de campos faltantes al publicar
     * (ver EventForm::validarParaPublicar()) — mismo motivo que
     * GeneralInfoForm::messages(): no existe lang/es/validation.php, así
     * que sin esto Livewire usa los mensajes en inglés por defecto.
     */
    public function messages(): array
    {
        return [
            'capacityTotal.required'     => 'Falta indicar el aforo del evento.',
            'capacityTotal.integer'      => 'El aforo debe ser un número entero.',
            'capacityTotal.min'          => 'El aforo debe ser de al menos 1 (o activa "Aforo ilimitado").',
            'registrationStartAt.required' => 'Falta la fecha de inicio de inscripción.',
            'registrationStartAt.date'   => 'La fecha de inicio de inscripción no es válida.',
            'registrationEndAt.date'     => 'La fecha de cierre de inscripción no es válida.',
            'registrationEndAt.after'    => 'La fecha de cierre de inscripción debe ser posterior a la de inicio.',
            'registrationEndAt.required' => 'Falta la fecha de cierre de inscripción (o activa "Sin fecha de cierre").',
        ];
    }
}
