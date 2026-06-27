<?php

namespace App\Livewire\Admin\Events\Form;

use Illuminate\Support\Carbon;
use Livewire\Form;

/**
 * Fechas del evento: inicio, fin y fecha de publicación.
 *
 * A diferencia de GeneralInfoForm/LocationForm, las fechas son estructurales
 * (no "contenido"): se exigen igual en borrador y en publicación — un evento
 * sin fechas no tiene sentido ni siquiera como borrador. Por eso
 * rulesPublicacion() no añade nada sobre rulesArchivos().
 *
 * 'publishedAt': a diferencia de News, no existe un estado 'scheduled' ni
 * lógica de publicación automática futura — pero sigue siendo obligatoria y
 * no puede quedar en el pasado (after_or_equal:today), igual que startAt/
 * endAt. Al editar un evento publicado hace tiempo, esta fecha puede llegar
 * "vencida" desde la BD; EventForm::corregirFechaPublicacion() la reinicia a
 * hoy automáticamente al cerrar el modal de campos faltantes, así que un
 * "publishedAt" del pasado nunca deja al admin atascado sin poder publicar.
 *
 * CADENA DE DEPENDENCIA CRONOLÓGICA (publishedAt → startAt → endAt):
 *  - startAt no puede ser anterior a publishedAt (no tiene sentido que el
 *    evento empiece antes de anunciarse). Expresado con el propio motor de
 *    Laravel (after_or_equal:publishedAt) — sin closure: ambos campos viven
 *    en el mismo Form, así que el validador resuelve 'publishedAt' como
 *    referencia a otro campo, no como literal.
 *  - endAt SÍ necesita una closure (validateEndAt()) porque la regla no es
 *    una simple comparación: además de no ser anterior a startAt, exige un
 *    mínimo de 1 hora de diferencia cuando ambos caen el mismo día
 *    calendario, pero NO cuando endAt cae en un día posterior (evento
 *    nocturno/de varios días, ej. inicia 20:00 del día 1 y termina 02:00
 *    del día 2 — la hora de fin es numéricamente menor, pero válida).
 */
class ScheduleForm extends Form
{
    // Mínimo de diferencia exigido SOLO cuando inicio y fin caen el mismo día.
    private const MINUTOS_MINIMOS_MISMO_DIA = 60;

    public string $startAt = '';
    public string $endAt = '';
    public string $publishedAt = '';

    public function rulesArchivos(): array
    {
        return [
            'startAt'     => ['required', 'date', 'after_or_equal:publishedAt'],
            'endAt'       => ['required', 'date', $this->validateEndAt()],
            'publishedAt' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function rulesPublicacion(): array
    {
        return $this->rulesArchivos();
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
            'startAt.required'        => 'Falta la fecha y hora de inicio del evento.',
            'startAt.date'            => 'La fecha de inicio no es válida.',
            'startAt.after_or_equal'  => 'El evento no puede iniciar antes de su fecha de publicación.',
            'endAt.required'          => 'Falta la fecha y hora de fin del evento.',
            'endAt.date'              => 'La fecha de fin no es válida.',
            'publishedAt.required'       => 'Falta la fecha de publicación del evento.',
            'publishedAt.date'           => 'La fecha de publicación no es válida.',
            'publishedAt.after_or_equal' => 'La fecha de publicación no puede ser anterior a hoy.',
        ];
    }

    /**
     * Closure de validación de 'endAt' contra 'startAt'.
     *
     * REGLA DE NEGOCIO (no expresable con reglas de cadena de Laravel):
     *  1. endAt nunca puede ser anterior a startAt.
     *  2. Si ambos caen el MISMO día calendario, endAt debe tener al menos
     *     MINUTOS_MINIMOS_MISMO_DIA de diferencia respecto a startAt.
     *  3. Si endAt cae en un día POSTERIOR, la regla 2 no aplica — permite
     *     eventos nocturnos donde la hora de fin es numéricamente menor que
     *     la de inicio (ej. 20:00 → 02:00 del día siguiente).
     *
     * Devuelve un Closure (no un método validateXxx con la firma de
     * Laravel) para poder capturar $this y leer 'startAt' sin duplicar el
     * acceso al estado del Form en un método estático aparte.
     */
    private function validateEndAt(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fail): void {
            if ($this->startAt === '') {
                return; // 'startAt' ya falla su propia regla 'required' — no hay nada que comparar aquí.
            }

            $inicio = Carbon::parse($this->startAt);
            $fin    = Carbon::parse($valor);

            if ($fin->lt($inicio)) {
                $fail('La fecha de fin no puede ser anterior a la fecha de inicio.');

                return;
            }

            if ($fin->isSameDay($inicio) && $fin->lt($inicio->copy()->addMinutes(self::MINUTOS_MINIMOS_MISMO_DIA))) {
                $fail('Si el evento termina el mismo día, debe haber al menos 1 hora de diferencia entre el inicio y el fin.');
            }
        };
    }
}
