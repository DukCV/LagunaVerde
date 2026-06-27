<?php

namespace App\Rules\Events;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Exige que la fecha validada sea anterior O IGUAL a $fechaLimite (no es
 * "before" estricto: la igualdad pasa).
 *
 * Mismo motivo que IsAtLeastDaysBefore: $fechaLimite vive en otro Form
 * object (ej. el inicio del evento, en ScheduleForm), así que no puede
 * expresarse con before_or_equal:campo nativo de Laravel — ese helper solo
 * resuelve campos dentro del mismo conjunto de datos validado.
 *
 * Reutilizada para dos reglas de negocio distintas con el mismo criterio
 * de comparación (solo cambia el mensaje): 'registrationEndAt' no puede
 * superar el inicio del evento, y (potencialmente) otras fechas límite
 * futuras del mismo tipo — de ahí el mensaje como parámetro en vez de
 * hardcodearlo, evitando duplicar esta clase por cada caso de uso.
 */
final class IsBeforeOrEqualToDate implements ValidationRule
{
    public function __construct(
        private readonly ?string $fechaLimite,
        private readonly string $mensaje,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($this->fechaLimite) || blank($value)) {
            return;
        }

        if (Carbon::parse($value)->startOfDay()->gt(Carbon::parse($this->fechaLimite)->startOfDay())) {
            $fail($this->mensaje);
        }
    }
}
