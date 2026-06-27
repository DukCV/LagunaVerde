<?php
// ════════════════════════════════════════════════════════════════════════
//  EventCard.php
//  Ruta: app/Livewire/Events/EventCard.php
// ════════════════════════════════════════════════════════════════════════

namespace App\Livewire\Events;

use App\DTOs\Events\EventSummaryDto;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: tarjeta de resumen de un evento en el listado.
 *
 * RESPONSABILIDADES:
 *  - Renderizar los datos de un EventSummaryDto.
 *  - Sin acceso a BD ni lógica de negocio.
 *
 * SEGURIDAD:
 *  - #[Locked] en $event y $horizontal → sin tampering del cliente.
 *  - El DTO llega serializado como array — EventCard lo rehidrata.
 *  - La vista usa {{ }} en toda salida → XSS imposible.
 */
class EventCard extends Component
{
    /**
     * Array plano del EventSummaryDto serializado.
     * Se recibe como array para compatibilidad con la serialización
     * de Livewire al pasar propiedades entre componentes padre/hijo.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $event = [];

    /** Modo horizontal para mobile — sin tampering del cliente. */
    #[Locked]
    public bool $horizontal = false;

    public function render()
    {
        return view('livewire.events.event-card');
    }
}
