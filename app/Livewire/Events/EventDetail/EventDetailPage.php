<?php

namespace App\Livewire\Events\EventDetail;

use App\DTOs\Events\EventDetail\EventDetailDto;
use App\Services\Events\EventDetail\EventDetailService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: página de detalle de un evento.
 *
 * RESPONSABILIDADES:
 *  - Recibir el UUID desde el parámetro de ruta (vía la vista blade).
 *  - Cargar el evento via EventDetailService en mount().
 *  - Pasar el DTO a la vista y a los sub-componentes.
 *  - Redirigir con 404 si el evento no existe o no está publicado.
 *
 * SEGURIDAD:
 *  - #[Locked] en $uuid y $event → el cliente no puede alterar el estado.
 *  - UUID validado en el servicio antes de cualquier query.
 *  - abort(404) silencioso: sin distinción entre "no existe" y "no publicado".
 *  - El ID entero NUNCA se serializa en el estado de Livewire.
 *  - try/catch en mount() → errores internos no llegan a la vista.
 */
class EventDetailPage extends Component
{
    /** UUID recibido del parámetro de ruta — bloqueado contra tampering. */
    #[Locked]
    public string $uuid = '';

    /** DTO inmutable con todos los datos del evento. */
    #[Locked]
    public EventDetailDto $event;

    // ════════════════════════════════════════════════════════════════════
    //  LIFECYCLE
    // ════════════════════════════════════════════════════════════════════

    public function mount(string $uuid, EventDetailService $service): void
    {
        $this->uuid = $uuid;

        try {
            $dto = $service->getDetail($uuid);
        } catch (\Throwable) {
            // Error de BD u otro fallo interno → 404 sin revelar detalles
            abort(404);
        }

        // 404 silencioso: mismo resultado para UUID inválido,
        // evento inexistente o no publicado → previene enumeración
        abort_unless($dto !== null, 404);

        $this->event = $dto;
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.events.event-detail.event-detail-page');
    }
}
