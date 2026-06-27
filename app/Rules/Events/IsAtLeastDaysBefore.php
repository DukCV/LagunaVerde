<?php

namespace App\Rules\Events;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Exige que la fecha validada sea, como mínimo, $diasMinimos días
 * calendario anterior a $fechaReferencia.
 *
 * POR QUÉ UNA REGLA APARTE (no una cadena nativa de Laravel):
 *  $fechaReferencia vive en un Livewire\Form distinto del que se está
 *  validando (ej. el inicio del evento vive en ScheduleForm, mientras esta
 *  regla se usa para validar 'registrationStartAt' en RegistrationForm).
 *  Las reglas de cadena before:/after: solo resuelven nombres de campo
 *  dentro del MISMO conjunto de datos que se está validando — una
 *  comparación cruzada entre dos Form objects necesita el VALOR real, no
 *  una referencia por nombre, así que se pasa explícitamente al construir
 *  la regla (ver RegistrationForm::sincronizarConEvento()).
 *
 * SEGURIDAD: $fechaReferencia y $value se parsean con Carbon — ninguna
 * entrada llega a una consulta SQL, por lo que no hay superficie de
 * inyección aquí (la comparación es puramente en memoria).
 */
final class IsAtLeastDaysBefore implements ValidationRule
{
    public function __construct(
        private readonly ?string $fechaReferencia,
        private readonly int $diasMinimos,
        private readonly string $mensaje,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Sin fecha de referencia o sin valor: sus propias reglas
        // 'required'/'date' ya señalan el problema por separado.
        if (blank($this->fechaReferencia) || blank($value)) {
            return;
        }

        $limite = Carbon::parse($this->fechaReferencia)->startOfDay()->subDays($this->diasMinimos);
        $fecha  = Carbon::parse($value)->startOfDay();

        if ($fecha->gt($limite)) {
            $fail($this->mensaje);
        }
    }
}
